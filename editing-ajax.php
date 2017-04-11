<?php

function jte_ajax_sync_post_content() {
    $changesets = wp_unslash($_POST['changesets']);
    $post_id = (int) $_POST['post_id'];
    $ret = jte_sync_content_editing_pusher($changesets, $post_id);

    $editing = jte_get_post_first_editing($post_id);
    $post = get_object_vars(get_post($post_id, OBJECT));
    $fields = _jte_post_editing_fields($post);

    $fields['ID'] = $editing->ID;
    $fields['post_content'] = $ret[0];
    // don't send push on this update
    wp_update_post($fields);
    wp_send_json(array(
        'status'     => 'success',
        'results'    => $ret,
        'test'        => $changesets
    ));
}
add_filter('wp_ajax_jte_ajax_sync_post_content', 'jte_ajax_sync_post_content', 10, 2);

function jte_ajax_ask_current_post_info() {
    $post_id = (int) $_GET['post_id'];
    $editing = jte_get_post_first_editing($post_id);
    wp_send_json(array(
        'content' => $editing->post_content,
        'title' => $editing->post_title,
        'post_id' => $post_id,
        'timestamp' => time()
    ));
}
add_filter('wp_ajax_jte_ajax_ask_current_post_info', 'jte_ajax_ask_current_post_info', 10, 2);

function jte_ajax_sync_post_title() {
    $changesets = wp_unslash($_POST['changesets']);
    $post_id = (int) $_POST['post_id'];
    $ret = jte_sync_title_editing_pusher($changesets, $post_id);

    $editing = jte_get_post_first_editing($post_id);
    $post = get_object_vars(get_post($post_id, OBJECT));

    $fields = _jte_post_editing_fields($post);
    $fields['ID'] = $editing->ID;
    $fields['post_title'] = $ret[0];
    wp_update_post($fields);

    wp_send_json(array(
        'status' => 'success',
        'results' => $ret
    ));
}
add_filter('wp_ajax_jte_ajax_sync_post_title', 'jte_ajax_sync_post_title', 10, 2);