<?php

function grape_get_options() {
    // set defaults
    $defaults = array(
        'syncables' => array(
            'post_type' => array(),
            'taxonomy'  => array()
        )
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

    // register setting
    add_action('admin_init', 'grape_register_settings');
    // register JS
    add_action ('admin_enqueue_scripts', 'grape_enqueue_scripts');
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
    if(GRAPE_DEBUG) {
        wp_enqueue_style('grape-settings-debug-style', grape_plugin_dir_url() . 'css/settings-debug.css');
    }
}

// Add link to options page from plugin list
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'grape_plugin_actions');
function grape_plugin_actions($links) {
    $new_links = array();
    $new_links[] = '<a href="admin.php?page=grape">' . __('Settings', 'grape') . '</a>';
    return array_merge($new_links, $links);
}


add_action( 'wp_ajax_grape_add_syncable', 'grape_add_syncable' );
function grape_add_syncable() {
    global $wpdb;

    $response = array();
    $response['status'] = 'error';

    if (!isset($_POST['syncable_type']) || !isset($_POST['type']) || !isset($_POST['api_token']) || !isset($_POST['api_url'])) {
        $response['error'] = 'required parameter missing';
        echo json_encode($response);
        wp_die();
    }

    $options = grape_get_options();
    $syncable_type = $_POST['syncable_type'];
    $type = $_POST['type'];
    $api_token = $_POST['api_token'];
    $api_url = $_POST['api_url'];

    if ($syncable_type == 'post_type') {
        $item = array(
            'type' => $type,
            'api_token' => $api_token,
            'api_url' => $api_url,
            'custom_title_field' => $_POST['custom_title_field'],
        );
    } else if (syncable_type == 'taxonomy') {
        $item = array(
            'type' => $type,
            'api_token' => $api_token,
            'api_url' => $api_url,
            'custom_url' => $_POST['custom_url'],
        );
    }


    // only test the connection if api token or url change or previous attempt fails
    $api = new GRAPE_API($api_token, $api_url);
    $api_success = $api->test_connection();
    if ($api_success !== true) {
        $response['error'] = 'Problem connecting to API: ' . $api_success;
        echo json_encode($response);
        wp_die();
    }

    // this is probably the first
    if (!array_key_exists($type, $options['syncables'][$syncable_type])) {
        $options['syncables'][$syncable_type][$type] = array();
    }

    $options['syncables'][$syncable_type][$type][] = $item;

    update_option('grape', $options);

    $response['status'] = 'success';

    echo json_encode($response);

    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_grape_delete_syncable', 'grape_delete_syncable' );
function grape_delete_syncable() {
    global $wpdb;

    $response = array();
    $response['status'] = 'error';

    if (!isset($_POST['syncable_type']) && !isset($_POST['id'])) {
        $response['error'] = 'required parameter missing';
        echo json_encode($response);
        wp_die();
    }

    $syncable_type = $_POST['syncable_type'];
    $type = $_POST['type'];
    $id = $_POST['id'];

    $options = grape_get_options();


    if (!array_key_exists($type, $options['syncables'][$syncable_type])) {
        $response['error'] = "type $type not found";
        echo json_encode($response);
        wp_die();
    }
    if (!array_key_exists($id, $options['syncables'][$syncable_type][$type])) {
        $response['error'] = "id $id not found";
        echo json_encode($response);
        wp_die();
    }

    $connection = $options['syncables'][$syncable_type][$type][$id];

    $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
    $result = $api->delete_everything();
    if (!($result===true)) {
        $response['error'] = $result;
        echo json_encode($response);
        wp_die();
    }

    unset($options['syncables'][$syncable_type][$type][$id]);

    update_option('grape', $options);

    $response['status'] = 'success';

    echo json_encode($response);

    wp_die(); // this is required to terminate immediately and return a proper response
}


add_action( 'wp_ajax_grape_full_sync_start', 'grape_full_sync_start' );
function grape_full_sync_start() {
    global $wpdb;

    $result = true;

    // TODO: implement syncing of taxonomies

    $options = grape_get_options();
    foreach ($options['syncables']['post_type'] as $post_type => $connections) {
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

    // TODO: implement syncing of taxonomies

    // we need an array of post types that looks like array("post", "page")
    $options = grape_get_options();
    $post_types = array_unique(array_keys($options['syncables']['post_type']));

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
        <h2 class="title"><?php _e('Index Options', 'grape'); ?></h2>
        <input type="button" id="button-add" class="button" value="<?php _e('Add', 'grape'); ?>" />
        <div id="placeholder-add-post-type"></div>
        <div id="placeholder-syncables"></div>
    </form>

    <script id="template1" type="text/x-handlebars-template">
        <ul class="syncable-post-types">
            {{#each post_type}}
                {{#each this}}
                    <li>
                        <span class="post-type">{{this.post_type_label}}</span><br>
                        <span class="api-url">{{this.api_url}}</span><br>
                        <span class="custom-title-field">{{this.custom_title_field}}</span><br>
                        <a href="#" class="delete-syncable" data-syncable-type="post_type" data-type="{{this.type}}" data-id="{{this.index}}">Delete</a>
                    </li>
                {{/each}}
            {{/each}}
        </ul>
        <ul class="syncable-taxonomies">
            {{#each taxonomy}}
                {{#each this}}
                    <li>
                        <span class="taxonomy">{{this.type}}</span><br>
                        <span class="api-url">{{this.api_url}}</span><br>
                        <span class="custom-url">{{this.custom_url}}</span><br>
                        <a href="#" class="delete-syncable" data-syncable-type="taxonomy" data-type="{{this.type}}" data-id="{{this.index}}">Delete</a>
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
            $syncable_post_types = $options['syncables']['post_type'];
            foreach($syncable_post_types as $type => $connections) {
                foreach($connections as $index => $connection) {
                    $syncable_post_types[$type][$index]['index'] = $index;
                    $syncable_post_types[$type][$index]['post_type_label'] = $post_types[$type]->label;
                }
            }
            $syncable_taxonomies = $options['syncables']['taxonomy'];
            foreach($syncable_taxonomies as $type => $connections) {
                foreach($connections as $index => $connection) {
                    $syncable_taxonomies[$type][$index]['index'] = $index;
                }
            }
        ?>
        var syncables = {
            'post_type': <?php echo json_encode($syncable_post_types); ?>,
            'taxonomy': <?php echo json_encode($syncable_taxonomies); ?>
        };
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

    <?php if(GRAPE_DEBUG): ?>
        <hr>
        <h2>Options (raw)</h2>
        <pre>
            <?php print_r($options); ?>
        </pre>
    <?php endif; ?>

</div>

<div style="display:none" id="div-add-post-type" class="div-add-post-type">
    <h2><?php _e('Add', 'grape'); ?></h2>


    <form class="syncable_type">
        <label for="syncable_type">
            <?php _e('Type (required)', 'grape'); ?>
        </label>
        <select name="syncable_type">
                <option value="post_type">
                    <?php _e('Post type', 'grape'); ?>
                </option>
                <option value="taxonomy">
                    <?php _e('Taxonomy', 'grape'); ?>
                </option>
        </select>
    </form>

    <form class="post_type">
        <input name="syncable_type" type="hidden" value="post_type">
        <?php
            $args = array(
               'public'   => true,
            );
            $post_types = get_post_types($args, 'objects');
        ?>
        <label for="type">
            <?php _e('Post type (required)', 'grape'); ?>
        </label>
        <select name="type">
            <?php foreach ( $post_types as $post_type ): ?>
                <option value="<?php echo $post_type->name ?>">
                    <?php echo $post_type->labels->name ?>
                    <?php if (!post_type_supports($post_type->name, 'title')): ?>
                        <span class="grape-warning"><?php _e('Warning: These posts have no title'); ?></span>
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input name="action" type="hidden" value="grape_add_syncable">
        <label for="api_url"><?php _e('API URL (required)', 'grape'); ?></label>
        <input name="api_url" type="text" class="regular-text" required="required">

        <label for="api_token"><?php _e('API Token (required)', 'grape'); ?></label>
        <input name="api_token" type="text" class="regular-text" required="required">

        <label for="custom_title_field">Custom title field name (leave empty for default)</label>
        <input name="custom_title_field" type="text">

        <br>

        <input type="submit" name="add" value="<?php _e('Add', 'grape'); ?>" class="button button-primary button-add" />
    </form>

    <form class="taxonomy">
        <input name="syncable_type" type="hidden" value="taxonomy">
        <?php
            $args = array();
            $taxonomies = get_taxonomies($args, 'objects');
        ?>
        <label for="type">
            <?php _e('Taxonomy type (required)', 'grape'); ?>
        </label>
        <select name="type">
            <?php foreach ( $taxonomies as $taxonomy ): ?>
                <option value="<?php echo $taxonomy->name ?>">
                    <?php echo $taxonomy->labels->name ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input name="action" type="hidden" value="grape_add_syncable">
        <label for="api_url"><?php _e('API URL (required)', 'grape'); ?></label>
        <input name="api_url" type="text" class="regular-text" required="required">

        <label for="api_token"><?php _e('API Token (required)', 'grape'); ?></label>
        <input name="api_token" type="text" class="regular-text" required="required">

        <label for="custom_url"><?php _e('Custom URL (required)', 'grape'); ?></label>
        <span class="descripton">Example: <tt><?php echo get_site_url(); ?>/my/taxonomy/$title</tt>. <tt>$title</tt> will be replaced by the taxonomy title</span>
        <input name="custom_url" type="text" required="required">

        <br>

        <input type="submit" name="add" value="<?php _e('Add', 'grape'); ?>" class="button button-primary button-add" />
    </form>
</div>

<?php
}

?>
