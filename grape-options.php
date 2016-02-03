<?php

function grape_get_options() {
    // set defaults
    $defaults = array(
            'api_url'       => '',
            'api_token'     => '',
            'api_success'   => false,
            'post_types'    => array( 'post' => 1, 'page' => 1),
    );

    $options = get_option('grape');
    if (!is_array($options)) $options = array();

    // still need to get the defaults for the new settings, so we'll merge again
    return array_merge( $defaults, $options );
}

// Validation/sanitization. Add errors to $msg[].
function grape_validate_options($input) {
    $options = get_option('grape');

    $msg = array();
    $msgtype = 'error';

    // don't lose fields
    $input['api_success'] = $options['api_success'];

    // API token
    // only test the connection if api token or url change or previous attempt fails
    if (isset($input['api_token']) && isset($input['api_url']) &&
        ($input['api_token'] != $options['api_token'] || $input['api_url'] != $options['api_url'] || false == $options['api_success'])) {
        $input['api_success'] = false;

        $api_token = $input['api_token'];
        $api_url = $input['api_url'];

        $api = new GRAPE_API($api_token, $api_url);
        $result = $api->test_connection();
        if ($result === true) {
            $input['api_success'] = true;
            $msg[] .= __('Sucessfully connected to Grape!', 'grape');
            $msgtype = 'updated';
        } else {
            $msg[] .= __('Could not connect to Grape: ', 'grape') . $result;
        }
    }

    // Send custom updated message
    if( isset($input['api_token']) || isset($input['api_url']) || isset($input['post_types'])) {
        $msg = implode('<br />', $msg);

        if (empty($msg)) {
            $msg = __('Settings saved.', 'grape');
            $msgtype = 'updated';
        }

        add_settings_error( 'grape', 'grape', $msg, $msgtype );
    }

    return $input;
}

// ---- Options Page -----


function grape_add_menu() {
    $pg = add_submenu_page(
        'options-general.php',
        __('Grape','grape'),
        __('Grape','grape'),
        'manage_options',
        'grape',
        'grape_display_options'
    );
    // add_action("admin_head-$pg", 'grape_settings_css');
    // register setting
    add_action('admin_init', 'grape_register_settings');
    // register JS
    add_action ('admin_enqueue_scripts', 'grape_enqueue_scripts');
}

/* Admin options page style */
function grape_settings_css() { ?>
    <style type="text/css">
        <?php include('css/settings.css');?>
    </style>
<?php
}

/* Register settings */
function grape_register_settings() {
    register_setting( 'grape', 'grape', 'grape_validate_options');
}

/* Register JS */
function grape_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('grape-settings-scripts', grape_plugin_dir_url() . 'js/settings.js', array(), '1.0.0', true );
    wp_enqueue_style('grape-settings-style', grape_plugin_dir_url() . 'css/settings.css');

}

// Add link to options page from plugin list
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'grape_plugin_actions');
function grape_plugin_actions($links) {
    $new_links = array();
    $new_links[] = '<a href="admin.php?page=grape">' . __('Settings', 'grape') . '</a>';
    return array_merge($new_links, $links);
}


add_action( 'wp_ajax_grape_full_sync', 'grape_full_sync' );

function grape_full_sync() {
    global $wpdb;

    // we need an array of post types that looks like array("post", "page")
    $options = grape_get_options();
    $post_types = array();
    foreach ($options['post_types'] as $post_type => $value) {
        if (1 == $value) {
            $post_types[] = $post_type;
        }
    }

    // get all the posts from the DB. query takes multiple post types
    $args = array(
        'post_type' => $post_types
    );
    $posts = get_posts($args);

    $post_count = count($posts);

    // $post_id is not the real post id! it's just the index in the list
    $post_id = 0;
    if (isset($_POST['postId'])) {
        $post_id = intval($_POST['postId']);
    }
    $next_post_id = $post_id + 1;

    $current_post = $posts[$post_id];

    grape_debug('Full Sync: post id ' . $current_post->ID);
    Grape_Controller::post($current_post->ID, true);

    $response = array(
        'postsTotal' => $post_count,
        'currentPostId' => $post_id,
        'currentPostTitle' => $current_post->title,

    );

    if ($next_post_id < $post_count) {
        $response['nextPostId'] = $next_post_id;
    }

    echo json_encode($response);

    wp_die(); // this is required to terminate immediately and return a proper response
}

// Display the options page
function grape_display_options() {
?>

<? include_once 'options-head.php';  ?>

<div class="wrap grape">
    <h1><?php _e('Grape Settings', 'grape'); ?></h1>
    <form method="post" id="grape" action="options.php">
        <?php
        settings_fields('grape');
        // settings_errors('grape');
        $options = grape_get_options();
        ?>

        <fieldset class="options">
            <h2 class="title"><?php _e('API Credentials', 'grape'); ?></h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="grape[api_url]"><?php _e('API URL', 'grape'); ?></label>
                        </th>
                        <td>
                            <input name="grape[api_url]" type="text" value="<?php echo $options['api_url']; ?>" class="regular-text">
                            <?php if ($options['api_success'] === true): ?>
                                <span class="dashicons dashicons-yes"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="grape[api_token]"><?php _e('API Token', 'grape'); ?></label>
                        </th>
                        <td>
                            <input name="grape[api_token]" type="text" value="<?php echo $options['api_token']; ?>" class="regular-text">
                            <?php if ($options['api_success'] === true): ?>
                                <span class="dashicons dashicons-yes"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </fieldset>

        <fieldset class="options">
            <h2 class="title"><?php _e('Index Options', 'grape'); ?></h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="grape[post_types]"><?php _e('Post types to index', 'grape'); ?></label>
                        </th>
                        <td>
                            <?php
                                $args = array(
                                   'public'   => true,
                                );
                                $post_types = get_post_types($args, 'objects');
                                if (!is_array($options['post_types'])) $options['post_types'] = (array)$options['post_types'];
                            ?>
                            <?php foreach ( $post_types as $post_type ): ?>
                                <label>
                                    <input name="grape[post_types][<?php echo $post_type->name ?>]" type="checkbox" value="1"
                                    <?php checked(array_key_exists($post_type->name, $options['post_types']) && $options['post_types'][$post_type->name] == 1, true); ?>/>
                                    <?php echo $post_type->labels->name ?>
                                    <?php if (!post_type_supports($post_type->name, 'title')): ?>
                                        <span class="grape-warning"><?php _e('Warning: These posts have no title'); ?></span>
                                    <?php endif; ?>
                                </label>
                                <br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </fieldset>

        <p class="submit">
            <input type="submit" name="grape[update_grape_options]" value="<?php esc_attr_e('Save Changes'); ?>" class="button button-primary" />
        </p>
    </form>

    <hr>

    <h2>Tools</h2>

    <input type="button" name="grape[full_sync]" id="grape-full-sync" value="<?php esc_attr_e('Sync all posts with Grape'); ?>" class="button button-secondary" />


    <div class='grape-progress-container hidden'>
        <span class="grape-loading"><img src="<?php echo grape_plugin_dir_url() ?>img/loading.gif"></span>
        <div class='grape-progress-wrap grape-progress' data-progress-percent='0' data-speed='500' style=''>
            <div class='grape-progress-bar grape-progress'></div>
            <div class='grape-progress-text'></div>
        </div>
    </div>
    <span class='grape-progress-done hidden'>
        <?php _e('done'); ?> <span class="dashicons dashicons-yes"></span>
    </span>
    <p class="description"><?php _e('Please press "Save Changes" before running this if you changed any setting.'); ?></p>
</div>

<?php
}

?>
