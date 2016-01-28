<?php

class Grape_Controller {
    static function post($post_ID) {
        // global $grape_synced;

        if (!Grape_Controller::check_nonce() || !Grape_Controller::has_valid_api_token()) {
            return $post_ID; // nothing to do here
        }

        $post = new GRAPE_Post($post_ID);

        if (!$post->should_be_synced()) {
            grape_debug("controller: post -> STOP (should not be synced)");
            return $post_ID;
        }

        if ($post->was_synced()) {
            grape_debug("controller: post -> edit (was synced before)");
            return Grape_Controller::edit($post_ID);
        }

        $api = new GRAPE_API();
        $respone = $api->create($post);

        // $grape_synced = $post_ID;

        return $post_ID;
    }

    static function edit($post_ID) {
        // global $grape_synced;

        if (!Grape_Controller::check_nonce() || !Grape_Controller::has_valid_api_token()) {
            return $post_ID; // nothing to do here
        }

        $post = new GRAPE_Post($post_ID);

        if (!$post->was_synced()) {
            grape_debug("controller: edit -> post (was never synced before)");
            return Grape_Controller::post($post_ID);
        }

        if ($post->should_be_deleted_because_private()) {
            grape_debug("controller: edit -> delete (should be deleted, private)");
            return Grape_Controller::delete($post_ID);
        }

        $api = new GRAPE_API();
        $api->update($post);

        // $grape_synced = $post_ID;

        return $post_ID;
    }

    static function delete($post_ID) {
        // TODO: handle delete

        // global $grape_synced;

        // if ($post_ID == $grape_synced || !Grape_Controller::check_nonce() || !Grape_Controller::has_valid_api_token()) {
        //     return $post_ID;
        // }

        // $post = new GRAPE_Post($post_ID);

        // if ($post->was_never_synced()) {
        //     grape_debug("controller: delete -> STOP (was never synced before)");
        //     return $post_ID;
        // }

        // $api = new GRAPE_API();
        // $api->delete($post);
        // $grape_synced = $post_ID;

        return $post_ID;
    }




    static function has_valid_api_token() {
        if (!grape_is_current_user_connected()) {
            update_option('grape_error_notice', array("no_api_token" => "No valid API Token or URL set."));
            grape_debug("controller: NO VALID API TOKEN/URL");
            return false;
        }

        return true;
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