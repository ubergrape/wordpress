<?php

function grape_get_options() {
    // set defaults
    $defaults = array(
            'syncable_post_types'    => array( ),
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
    wp_enqueue_script('grape-settings-script', grape_plugin_dir_url() . 'js/settings.js', array(), '1.0.0', true );
    wp_enqueue_script('handlebars', grape_plugin_dir_url() . 'js/handlebars.js', array(), '4.0.5', true );
    wp_enqueue_style('grape-settings-style', grape_plugin_dir_url() . 'css/settings.css');

}

// Add link to options page from plugin list
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'grape_plugin_actions');
function grape_plugin_actions($links) {
    $new_links = array();
    $new_links[] = '<a href="admin.php?page=grape">' . __('Settings', 'grape') . '</a>';
    return array_merge($new_links, $links);
}


add_action( 'wp_ajax_grape_add_post_type', 'grape_add_post_type' );
function grape_add_post_type() {
    global $wpdb;

    $response = array();
    $response['status'] = 'error';

    if (!isset($_POST['post_type']) || !isset($_POST['api_token']) || !isset($_POST['api_url'])) {
        $response['error'] = 'required parameter missing';
        echo json_encode($response);
        wp_die();
    }

    $options = grape_get_options();
    $post_type = $_POST['post_type'];
    $api_token = $_POST['api_token'];
    $api_url = $_POST['api_url'];
    $custom_title_field = $_POST['custom_title_field'];

    // only test the connection if api token or url change or previous attempt fails
    $api = new GRAPE_API($api_token, $api_url);
    $api_success = $api->test_connection();
    if ($api_success !== true) {
        $response['error'] = 'Problem connecting to API: ' . $api_success;
        echo json_encode($response);
        wp_die();
    }

    // this is probably the first
    if (!array_key_exists($post_type, $options['syncable_post_types'])) {
        $options['syncable_post_types'][$post_type] = array();
    }

    $options['syncable_post_types'][$post_type][] = array(
        'post_type' => $post_type,
        'api_token' => $api_token,
        'api_url' => $api_url,
        'custom_title_field' => $custom_title_field,
    );

    update_option('grape', $options);

    $response['status'] = 'success';

    echo json_encode($response);

    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_grape_delete_post_type', 'grape_delete_post_type' );
function grape_delete_post_type() {
    global $wpdb;

    $response = array();
    $response['status'] = 'error';

    if (!isset($_POST['id'])) {
        $response['error'] = 'required parameter missing';
        echo json_encode($response);
        wp_die();
    }

    $post_type = $_POST['post_type'];
    $id = $_POST['id'];

    $options = grape_get_options();

    if (!array_key_exists($post_type, $options['syncable_post_types'])) {
        $response['error'] = "post type $post_type not found";
        echo json_encode($response);
        wp_die();
    }

    if (!array_key_exists($id, $options['syncable_post_types'][$post_type])) {
        $response['error'] = "id $id not found";
        echo json_encode($response);
        wp_die();
    }

    $connection = $options['syncable_post_types'][$post_type][$id];

    $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
    $result = $api->delete_everything();
    if (!($result===true)) {
        $response['error'] = $result;
        echo json_encode($response);
        wp_die();
    }

    unset($options['syncable_post_types'][$post_type][$id]);

    update_option('grape', $options);

    $response['status'] = 'success';

    echo json_encode($response);

    wp_die(); // this is required to terminate immediately and return a proper response
}


add_action( 'wp_ajax_grape_full_sync_start', 'grape_full_sync_start' );
function grape_full_sync_start() {
    global $wpdb;

    $result = true;

    $options = grape_get_options();
    foreach ($options['syncable_post_types'] as $post_type => $connections) {
        foreach ($connections as $connection) {
            $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
            $result = $result && $api->delete_everything();
        }
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_grape_full_sync', 'grape_full_sync' );
function grape_full_sync() {
    global $wpdb;

    // we need an array of post types that looks like array("post", "page")
    $options = grape_get_options();
    $post_types = array_unique(array_keys($options['syncable_post_types']));

    // get all the posts from the DB. query takes multiple post types
    $args = array(
        'post_type' => $post_types,
        'posts_per_page' => -1
    );
    $posts = get_posts($args);

    // TODO: if someone adds two post types twice, we have a problem
    $post_count = count($posts);

    // $post_id is not the real post id! it's just the index in the list
    $post_id = 0;
    if (isset($_POST['postId'])) {
        $post_id = intval($_POST['postId']);
    }
    $next_post_id = $post_id + 1;

    $current_post = $posts[$post_id];

    grape_debug('Full Sync: post id ' . $current_post->ID);
    GrapePostController::post($current_post->ID, true);

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
    <?php
        settings_fields('grape');
        // settings_errors('grape');
        $options = grape_get_options();
    ?>

    <h1><?php _e('Grape Settings', 'grape'); ?></h1>
    <form method="post" id="grape" action="options.php">

        <fieldset class="options">
            <h2 class="title"><?php _e('Index Options', 'grape'); ?></h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Post types to index', 'grape'); ?></label>
                        </th>
                        <td>
                            <input type="button" id="button-add-post-type" class="button" value="Add post type" />
                            <div id="placeholder-add-post-type"></div>
                            <ul id="placeholder-syncable-post-types">
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </fieldset>
    </form>

    <script id="template1" type="text/x-handlebars-template">
        <ul class="syncable-post-types">
            {{#each post_types}}

                        {{#each this}}
                            <li>
                                <span class="post-type">{{this.post_type_label}}</span><br>
                                <span class="api-url">{{this.api_url}}</span><br>
                                <span class="custom-title-field">{{this.custom_title_field}}</span><br>
                                <a href="#" class="delete-post-type" data-post-type="{{this.post_type}}" data-id="{{@index}}">Delete</a>
                            </li>
                        {{/each}}

            {{/each}}
        </ul>
    </script>

    <script type="text/javascript">
        <?php
            $args = array(
               'public'   => true,
            );
            $post_types = get_post_types($args, 'objects');
            $syncable_post_types = $options['syncable_post_types'];
            foreach($syncable_post_types as $post_type => $connections) {
                foreach($connections as $key => $connection) {
                    $syncable_post_types[$post_type][$key]['post_type_label'] = $post_types[$post_type]->label;
                }
            }
        ?>
        var syncable_post_types = {'post_types': <?php echo json_encode($syncable_post_types); ?>};
    </script>

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
</div>

<div style="display:none" id="div-add-post-type" class="div-add-post-type">
    <h2>Add post type</h2>
    <form>
    <?php
        $args = array(
           'public'   => true,
        );
        $post_types = get_post_types($args, 'objects');
        if (!is_array($options['syncable_post_types'])) $options['syncable_post_types'] = (array)$options['syncable_post_types'];
    ?>
    <label for="post_type">
        <?php _e('Post type (required)', 'grape'); ?>
    </label>
    <select name="post_type">
        <?php foreach ( $post_types as $post_type ): ?>
            <option value="<?php echo $post_type->name ?>">
                <?php echo $post_type->labels->name ?>
                <?php if (!post_type_supports($post_type->name, 'title')): ?>
                    <span class="grape-warning"><?php _e('Warning: These posts have no title'); ?></span>
                <?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>


    <input name="action" type="hidden" value="grape_add_post_type">
    <label for="api_url"><?php _e('API URL (required)', 'grape'); ?></label>
    <input name="api_url" type="text" class="regular-text" required="required">

    <label for="api_token"><?php _e('API Token (required)', 'grape'); ?></label>
    <input name="api_token" type="text" class="regular-text" required="required">

    <label for="custom_title_field">Custom title field name (leave empty for default)</label>
    <input name="custom_title_field" type="text">

    <br>

    <input type="submit" name="add" value="<?php esc_attr_e('Add'); ?>" class="button button-primary button-add" />
    </form>
</div>

<?php
}

?>
