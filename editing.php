<?php

use DiffMatchPatch\DiffMatchPatch;

function jte_register_custom_post_type() {
    register_post_type('editing', array(
        'labels' => array(
            'name' => __('Editings'),
            'singular_name' => __('Editing'),
        ),
        'public' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => false,
        'rewrite' => false,
        'query_var' => false,
        'can_export' => false,
        'delete_with_user' => true,
        'supports' => array('author'),
    ));
}
add_action('init', 'jte_register_custom_post_type');

function _jte_post_editing_fields($post = null, $autosave = false)
{
    static $fields;
    if ($fields === null) {
        $fields = array(
            'post_title' => __('Title', 'jte'),
            'post_content' => __('Content', 'jte'),
            'post_excerpt' => __('Excerpt', 'jte'),
        );

        $fields = apply_filters('_jte_post_editing_fields', $fields);
        $k = array(
            'ID', 'post_name', 'post_parent', 'post_date', 'post_date_gmt', 'post_status',
            'post_type', 'comment_count', 'post_author'
        );

        foreach ($k as $ki) {
            unset($fields[$ki]);
        }
    }
    if (! is_array($post)) return $fields;

    $ret = array();
    foreach (array_intersect(array_keys($post), array_keys($fields)) as $field) {
        $ret[$field] = $post[$field];
    }

    $ret['post_parent']   = $post['ID'];
    $ret['post_title']    = $post['post_title'];
    $ret['post_content']  = $post['post_content'];
    $ret['post_status']   = 'inherit';
    $ret['post_type']     = 'editing';
    $ret['post_name']     = $autosave ? "$post[ID]-autosave-v1" : "$post[ID]-editing-v1";
    $ret['post_date']     = isset($post['post_modified']) ? $post['post_modified'] : '';
    $ret['post_date_gmt'] = isset($post['post_modified_gmt']) ? $post['post_modified_gmt'] : '';
    return $ret;
}

function jte_save_post_editing($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if (! $post = get_post($post_id)) return;
    if ('auto-draft' == $post->post_status || !in_array($post->post_type, array('post', 'page')))
        return;

    $editing = jte_get_post_first_editing($post);

    $fields = _jte_post_editing_fields($post);
    $fields['ID'] = $editing->ID;
    $fields['post_content'] = $post->post_content;
    $fields['post_title'] = $post->post_title;
    wp_update_post($fields);
}
add_action('post_updated', 'jte_save_post_editing', 10, 1);

function jte_get_post_editing(&$post, $output = OBJECT, $filter = 'raw')
{
    if (! $editing = get_post($post, OBJECT, $filter)) return $editing;
    if ( 'editing' !== $editing->post_type ) return null;

    if ( $output == OBJECT ) {
        return $editing;
    } elseif ( $output == ARRAY_A ) {
        $_editing = get_object_vars($editing);
        return $_editing;
    } elseif ( $output == ARRAY_N ) {
        $_editing = array_values(get_object_vars($editing));
        return $_editing;
    }

    return $editing;
}

function jte_delete_post_editing($post_id)
{
    if (!$editing = jte_get_post_editing($post_id)) {
        return $editing;
    }

    $delete = wp_delete_post($editing->ID);
    if ($delete) {
        do_action('jte_delete_post_editing', $editing->ID, $editing);
    }

    return $delete;
}

function _jte_put_post_editing($post = null, $autosave = false)
{
    if (is_object($post)) {
        $post = get_object_vars($post);
    } elseif (!is_array($post) ) {
        $post = get_post($post, ARRAY_A);
    }

    if (! $post || empty($post['ID'])) {
        return new WP_Error( 'invalid_post', __('Invalid post ID.', 'jte'));
    }

    if (isset($post['post_type']) && 'editing' == $post['post_type']) {
        return new WP_Error('post_type', __('Cannot create a editing of a editing', 'jte'));
    }

    $post = _jte_post_editing_fields($post, $autosave);
    $post = wp_slash($post); //since data is from db

    $editing_id = wp_insert_post($post);
    if (is_wp_error($editing_id)) {
        return $editing_id;
    }

    if ($editing_id) {
        do_action('_jte_put_post_editing', $editing_id);
    }

    return $editing_id;
}

function jte_get_post_editings($post_id = 0, $args = null) {
    $post = get_post($post_id);
    if (! $post || empty($post->ID)) return array();

    $defaults = array('order' => 'DESC', 'orderby' => 'date ID', 'check_enabled' => true);
    $args = wp_parse_args($args, $defaults);

    $args = array_merge($args, array( 'post_parent' => $post->ID, 'post_type' => 'editing', 'post_status' => 'inherit' ) );

    if ( ! $editings = get_children( $args ) )
        return array();

    return $editings;
}

function jte_get_post_first_editing($post_id = 0, $args = null) {
    $editings = array_values(jte_get_post_editings($post_id, $args));
    if (empty($editings)) {
        $post = get_post($post_id);
        $editing = get_post(_jte_put_post_editing(get_object_vars($post)));
        jte_save_post_editing($post_id);
        $editing = get_post($editing->ID);
    } else {
        $editing = $editings[0];
    }
    return $editing;
}

function jte_sync_content_editing_pusher($changesets, $post_id) {
    $editing = jte_get_post_first_editing($post_id);
    $last_synced = get_post_meta($post_id, 'jte_post_last_synced', true);
    if ($sync_time = get_post_meta($post_id, '_jte_post_doing_sync', true )) {
        if ( time() - $sync_time >= 10 ) {
            delete_post_meta($post_id, '_jte_post_doing_sync' );
        } else {
            // We're mid-sync, so bail
            return array($editing->post_content, array());
        }
    }
    $results = jte_apply_changeset($changesets, $editing->post_content);
    update_post_meta($post_id, '_jte_post_doing_sync', time());

    $new_last_synced = strtotime($editing->post_modified_gmt);

    // $options = array(
    //     'encrypted' => true
    // );
    // $pusher = new Pusher(
    //     'a93b916c848e1743e492',
    //     '6382dcd60b3487bafe22',
    //     '313687',
    //     $options
    // );

    // $res['changesets'] = $changesets;
    // $res['post_id'] = $post_id;
    // $res['timestamp'] = time();
    // $res['user_id'] = get_current_user_id();
    // $pusher->trigger("jte-post-editing-$post_id", 'content-updated', $res);

    if (isset($new_last_synced)) {
        update_post_meta($post_id, 'jte_post_last_synced', $new_last_synced );
    }

    delete_post_meta($post_id, '_jte_post_doing_sync');

    return $results;
}

function jte_sync_title_editing_pusher($changesets, $post_id) {
    $editing = jte_get_post_first_editing($post_id);
    $results = jte_apply_changeset($changesets, $editing->post_title);

    // $options = array(
    //     'encrypted' => true
    // );
    // $pusher = new Pusher(
    //     'a93b916c848e1743e492',
    //     '6382dcd60b3487bafe22',
    //     '313687',
    //     $options
    // );
    // $res['changesets'] = $changesets;
    // $res['post_id'] = $post_id;
    // $res['timestamp'] = time();
    // $res['user_id'] = get_current_user_id();
    // $pusher->trigger("jte-post-editing-$post_id", 'title-updated', $res);

    return $results;
}

function jte_apply_changeset($changesets, $content) {
    $diff = new DiffMatchPatch();
    $patch = $diff->patch_fromText($changesets);
    $results = $diff->patch_apply($patch, $content);
    return $results;
}

function jte_create_changeset_text($text1, $text2) {
    $dmp = new DiffMatchPatch();
    $diff = $dmp->diff_main($text1, $text2, true);
    if (count($diff) > 2) {
        $dmp->diff_cleanupSemantic($diff);
    }
    $patches = $dmp->dpatch_make($text1, $text2, $diff);
    return $dmp->patch_toText($patches);
}
