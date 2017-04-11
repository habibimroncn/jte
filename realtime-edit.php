<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/*
Plugin Name: Jetty Realtime Edit
Plugin URI: http://eresources.com
Description: Realtime Editing
Version: 0.1.2
Author: Jetty Team
Author URI: http://eresources.com
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// core utils
// Lib
require __DIR__ . '/lib/diff-match/Utils.php';
require __DIR__ . '/lib/diff-match/Diff.php';
require __DIR__ . '/lib/diff-match/DiffMatchPatch.php';
require __DIR__ . '/lib/diff-match/DiffToolkit.php';
require __DIR__ . '/lib/diff-match/Match.php';
require __DIR__ . '/lib/diff-match/Patch.php';
require __DIR__ . '/lib/diff-match/PatchObject.php';

// Pusher
require __DIR__ . '/lib/Pusher.php';

// Our
require __DIR__ . '/editing.php';
require __DIR__ . '/editing-ajax.php';

/**
 * Disable WP Locks
 */
function jte_disable_wp_post_lock_window() {
    if (in_array(get_current_screen()->post_type, array('post', 'page'))) {
        add_filter('wp_check_post_lock_window', '__return_false');
    }
}
add_action('wp_check_post_lock_window', 'jte_disable_wp_post_lock_window');

/**
 *
 */
function jte_disable_wp_post_lock_dialog($b, $p) {
    if (in_array($p->post_type, array('post', 'page'))) {
        return false;
    }
    return $b;
}
add_filter('show_post_locked_dialog', 'jte_disable_wp_post_lock_dialog', 10, 2);

function jte_prevent_check_edit_lock($retval, $object_id, $meta_key, $single) {
    if ( '_edit_lock' == $meta_key) {
        $p = get_post($object_id);
        if (in_array($p->post_type, array('post', 'page'))) {
            $retval = $single ? '' : array('');
        }
    }

    return $retval;
}
add_action('get_post_metadata', 'jte_prevent_check_edit_lock' , 10, 4 );

function jte_admin_enqueue_scripts()
{
    global $pagenow, $typenow, $post;
    if ( $typenow == 'post' && isset($_GET['post']) && ! empty($_GET['post'])) {
        $typenow = $post->post_type;
    } elseif (empty($typenow) && ! empty($_GET['post'])) {
        $post = get_post($_GET['post']);
        $typenow = $post->post_type;
    }
    if ($pagenow == 'post-new.php' || $pagenow == 'post.php' ) {
        if (in_array($typenow, array('post', 'page'))) {
            // disable autosave
            wp_dequeue_script('autosave');
            // register and enqueue it
            if (!wp_script_is('pusher', 'registered')) {
                wp_register_script('pusher', '//js.pusher.com/2.2/pusher.min.js', array('jquery'), '2.2' , true );
                wp_enqueue_script('pusher');
            }
        }
    }
}
add_action('admin_enqueue_scripts', 'jte_admin_enqueue_scripts');

add_action( 'after_wp_tiny_mce', 'jte_admin_print_enqueue_scripts' );
function jte_admin_print_enqueue_scripts() {
    global $pagenow, $typenow, $post;
    if ( $typenow == 'post' && isset($_GET['post']) && ! empty($_GET['post'])) {
        $typenow = $post->post_type;
    } elseif (empty($typenow) && ! empty($_GET['post'])) {
        $post = get_post($_GET['post']);
        $typenow = $post->post_type;
    }
    if ($pagenow != 'post-new.php' && $pagenow != 'post.php') return;
    if (!in_array($typenow, array('post', 'page'))) return;
    $url = plugin_dir_url(__FILE__);
    $script = '';
    $script .= '<script type="text/javascript">';
    $script .= "\nvar JTEC = " . wp_json_encode(array(
        'current_uid' => get_current_user_id()
    )) . ';';
    $script .= "</script>\n";
    echo $script;
    printf('<script type="text/javascript" src="%s"></script>',  $url . 'assets/js/editor.js');
}

register_activation_hook(__FILE__, 'jte_activate_realtime_edit');
function jte_activate_realtime_edit() {
    $data = get_plugin_data(__FILE__);
    flush_rewrite_rules();
    if ($data['Version'] == '0.1.2') {
        do_action( 'jte_activate_realtime_edit_012');
    }
}

function jte_activate_realtime_edit_012() {
    $edits = get_posts(array('post_type' => 'editing', 'post_status' => 'inherit'));
    if (count($edits) > 0) {
        foreach ($edits as $k => $edit) {
            wp_delete_post($edit->ID, true);
        }
    }
}

add_action('jte_activate_realtime_edit_012', 'jte_activate_realtime_edit_012', 20);

// routing for authenticated
function jte_query_vars_pusher($vars) {
    $vars[] = 'jte-pusher';
    $vars[] = 'jte-pusher-version';
    $vars[] = 'jte-pusher-route';
    return $vars;
}
add_action('query_vars', 'jte_query_vars_pusher', 0);

function jte_api_add_endpoint() {
    add_rewrite_rule( '^jte-pusher/v([1-3]{1})/?$', 'index.php?jte-pusher-version=$matches[1]&jte-pusher-route=/', 'top' );
    add_rewrite_rule( '^jte-pusher/v([1-3]{1})(.*)?', 'index.php?jte-pusher-version=$matches[1]&jte-pusher-route=$matches[2]', 'top');

    add_rewrite_endpoint('jte-pusher', EP_ALL);
}
add_action('init', 'jte_api_add_endpoint', 0);

function jte_api_parse_endpoint() {
    global $wp;
    if (! empty( $_GET['jte-pusher-version'] ) ) {
        $wp->query_vars['jte-pusher-version'] = $_GET['jte-pusher-version'];
    }

    if (! empty( $_GET['jte-pusher-route'] ) ) {
        $wp->query_vars['jte-pusher-route'] = $_GET['jte-pusher-route'];
    }

    // Our route
    if ( ! empty( $wp->query_vars['jte-pusher-version'] ) && ! empty( $wp->query_vars['jte-pusher-route'] ) ) {
        $options = array(
            'encrypted' => true
        );
        $pusher = new Pusher(
            'a93b916c848e1743e492',
            '6382dcd60b3487bafe22',
            '313687',
            $options
        );
        switch ($wp->query_vars['jte-pusher-route']) {
            case '/':
                echo json_encode(array(
                    'error' => 'no routing found',
                    'e' => $wp->query_vars['jte-pusher-route']
                ));
                break;
            case '/auth':
                if (is_user_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    global $current_user;
                    get_currentuserinfo();
                    $presence_data = array('name' => $current_user->display_name);
                    echo $pusher->presence_auth($_POST['channel_name'], $_POST['socket_id'], $current_user->ID, $presence_data);
                } else {
                    header('', true, 403);
                    echo json_encode(array(
                        'error' => 'authenticated required',
                        'method' => $_SERVER['REQUEST_METHOD'],
                        'e' => $wp->query_vars['jte-pusher-route']
                    ));
                }
                break;
            default:
                header('', true, 404);
                echo json_encode(array(
                    'error' => 'no routing found',
                    'e' => $wp->query_vars['jte-pusher-route']
                ));
                break;
        }
        exit;
    }
}
add_action('parse_request', 'jte_api_parse_endpoint', 0);