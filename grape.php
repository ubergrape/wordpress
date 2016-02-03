<?php
/*
Plugin Name: Grape
Version: 0.2.3
Description:
Author: chatgrape.com, Stefan Kröner
Author URI: http://www.newsgrape.com/
*/

/* Again, the version. used in api requests in the user agent string */
define('GRAPE_VERSION', '0.2.3');

/* Enable debug logging */
define('GRAPE_DEBUG', false);

/* GRAPE_DEBUG_FILE enables logging to "debug.log" in plugin folder
 * if this is set to false, debug messages go to the webservers error log
 */
define('GRAPE_DEBUG_FILE', true);

/* Set this to the plugin dir name if you have symlink problems */
//define('GRAPE_PLUGIN_DIR','grape');


/* General Settings. Limited by the API. No need to edit this */
define('GRAPE_MAXLENGTH_TITLE', 1024);
define('GRAPE_MAXLENGTH_DESCRIPTION', 1024);



$grape_dir = dirname(__FILE__);

@require_once "$grape_dir/api.php";
@require_once "$grape_dir/controller.php";
@require_once "$grape_dir/models.php";
@require_once "$grape_dir/grape-options.php";


/* Log Grape debug messages if GRAPE_DEBUG is set.
 * Writes to debug.log in plugin directory if GRAPE_DEBUG_FILE is set
 */
function grape_debug($message) {
    if(GRAPE_DEBUG) {
        if(GRAPE_DEBUG_FILE) {
            $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'debug.log';
            if (is_writable($path)) {
                $fp = fopen($path ,"a");
                fwrite($fp, $message."\n");
                fclose($fp);
            } else {
                error_log("Debug logfile not writable!");
            }
        } else {
            error_log($message);
        }
    }
}

/* Returns the url for the plugin directory (with trailing slash)
 * Workaround for symlinked plugin directories
 * see:
 * http://core.trac.wordpress.org/ticket/16953
 * https://bugs.php.net/bug.php?id=46260
 */
function grape_plugin_dir_url() {
    if (defined('GRAPE_PLUGIN_DIR')) {
        return plugins_url(GRAPE_PLUGIN_DIR) . '/';
    }
    return plugins_url(basename(dirname(__FILE__))) . '/';
}

function grape_is_current_user_connected() {
    $options = grape_get_options();

    if (isset($options['api_success']) && true === $options['api_success']) {
        return true;
    }

    return false;
}

add_action('publish_post', array('Grape_Controller','post'));
add_action('publish_future_post', array('Grape_Controller','post'));
add_action('draft_to_private', array('Grape_Controller','post'));
add_action('new_to_private', array('Grape_Controller','post'));
add_action('pending_to_private', array('Grape_Controller','post'));
add_action('private_to_public', array('Grape_Controller','edit'));
add_action('private_to_password', array('Grape_Controller','edit'));
add_action('untrashed_post', array('Grape_Controller','edit'));
add_action('edit_post', array('Grape_Controller','edit'));
add_action('delete_post', array('Grape_Controller','delete'));

add_action('admin_menu', 'grape_add_menu'); // Add menu to admin
