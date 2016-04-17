<?php

class GrapePostController {
    static function post($post_ID, $force=false) {
        // global $grape_synced;

        if (!GrapePostController::check_nonce()) {
            return $post_ID; // nothing to do here
        }

        $post = new GrapePost($post_ID);

        if (!$post->should_be_synced()) {
            grape_debug("controller: post -> STOP (should not be synced)");
            return $post_ID;
        }

        if ($post->was_synced() && !$force) {
            grape_debug("controller: post -> edit (was synced before)");
            return GrapePostController::edit($post_ID);
        }

        do_action('grape_post_create', $post);

        foreach ($post->get_connections() as $connection) {
            $post->use_custom_title_field($connection['custom_title_field']);
            $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
            $response = $api->create($post);
        }

        return $post_ID;
    }

    static function edit($post_ID) {
        // global $grape_synced;

        if (!GrapePostController::check_nonce()) {
            return $post_ID; // nothing to do here
        }

        $post = new GrapePost($post_ID);

        if (!$post->was_synced()) {
            grape_debug("controller: edit -> post (was never synced before)");
            return GrapePostController::post($post_ID);
        }

        if ($post->should_be_deleted_because_private()) {
            grape_debug("controller: edit -> delete (should be deleted, private)");
            return GrapePostController::delete($post_ID);
        }

        do_action('grape_post_update', $post);

        foreach ($post->get_connections() as $connection) {
            $post->use_custom_title_field($connection['custom_title_field']);
            $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
            $api->update($post);
        }

        return $post_ID;
    }

    static function delete($post_ID) {
        // global $grape_synced;

        if (!GrapePostController::check_nonce()) {
            return $post_ID;
        }

        $post = new GrapePost($post_ID);

        if (!$post->was_synced()) {
            grape_debug("controller: delete -> STOP (was never synced before)");
            return $post_ID;
        }

        do_action('grape_post_delete', $post);

        foreach ($post->get_connections() as $connection) {
            $post->use_custom_title_field($connection['custom_title_field']);
            $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
            $api->delete($post);
        }

        return $post_ID;
    }

    static function check_nonce() {
        /*if (!isset($_POST['grape_nonce']) || false==wp_verify_nonce($_POST['grape_nonce'], "grape_metabox")) {
            update_option('grape_error_notice', array("wrong_nonce" => "Wrong NONCE"));
            grape_debug("controller: STOP (wrong nonce)");
            return false;
        }*/
        return true;
    }
}


?>