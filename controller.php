<?php

class Grape_Controller {
    static function post($post_ID, $force) {
        // global $grape_synced;

        if (!Grape_Controller::check_nonce()) {
            return $post_ID; // nothing to do here
        }

        $post = new GrapePost($post_ID);

        if (!$post->should_be_synced()) {
            grape_debug("controller: post -> STOP (should not be synced)");
            return $post_ID;
        }

        if ($post->was_synced() && !$force) {
            grape_debug("controller: post -> edit (was synced before)");
            return Grape_Controller::edit($post_ID);
        }

        foreach ($post->get_connections() as $connection) {
            $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
            $response = $api->create($post);
        }

        return $post_ID;
    }

    static function edit($post_ID) {
        // global $grape_synced;

        if (!Grape_Controller::check_nonce()) {
            return $post_ID; // nothing to do here
        }

        $post = new GrapePost($post_ID);

        if (!$post->was_synced()) {
            grape_debug("controller: edit -> post (was never synced before)");
            return Grape_Controller::post($post_ID);
        }

        if ($post->should_be_deleted_because_private()) {
            grape_debug("controller: edit -> delete (should be deleted, private)");
            return Grape_Controller::delete($post_ID);
        }

        foreach ($post->get_connections() as $connection) {
            $api = new GRAPE_API($connection['api_token'], $connection['api_url']);
            $api->update($post);
        }

        return $post_ID;
    }

    static function delete($post_ID) {
        // global $grape_synced;

        if (!Grape_Controller::check_nonce()) {
            return $post_ID;
        }

        $post = new GrapePost($post_ID);

        if (!$post->was_synced()) {
            grape_debug("controller: delete -> STOP (was never synced before)");
            return $post_ID;
        }

        foreach ($post->get_connections() as $connection) {
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