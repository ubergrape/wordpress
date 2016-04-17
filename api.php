<?php

class GRAPE_API {
    function __construct($api_token=null, $api_url=null) {
        $this->api_token = $api_token;
        $this->api_url = $api_url;
        $this->options = grape_get_options();

        /* user agent */
        $this->client = 'Wordpress/'.get_bloginfo('version').' Grape/'.GRAPE_VERSION;
    }

    private function get_headers() {
        $headers = array(
            'Authorization' => 'Token ' . $this->api_token,
            'Content-Type' => 'application/json',
            'User-Agent' => $this->client,
        );

        return $headers;
    }

    function test_connection() {
        $this->log(__FUNCTION__);

        $url = $this->api_url;
        $args = array(
            'headers' => $this->get_headers(),
        );

        $result = wp_remote_get($url, $args);

        if (is_wp_error($result)) {
            $this->log(__FUNCTION__, $result->get_error_message());
            return $result->get_error_message();
        }

        if ($result['response']['code'] === 200) {
            return true;
        }

        if ($result['response']['code'] === 403) {
            $response_decoded = json_decode($result['body'], true);
            return $response_decoded['detail'];
        }

        if (substr($result['response']['code'], 0, 1) === "5") {
            return "Server Error (". $result['response']['code'] . " " . $result['response']['message'] . ")";
        }

        $this->log(__FUNCTION__, print_r($result, true));

        return "Unexpected Error";
    }

    function delete_everything() {
        $this->log(__FUNCTION__);

        $url = $this->api_url;
        $args = array(
            'method' => 'DELETE',
            'headers' => $this->get_headers()
        );

        $this->log(__FUNCTION__, $url);
        $this->log(__FUNCTION__, print_r($args, true));

        $result = wp_remote_request($url, $args);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
            $this->log(__FUNCTION__, $error);
            return $error;
        }

        $this->log(__FUNCTION__, 'done');

        return true;
    }

    function create($post) {
        $this->log(__FUNCTION__);

        $url = $this->api_url;
        $post_data = $post->serialize();
        $post_data['eid'] = $post->get_eid();
        $args = array(
            'headers' => $this->get_headers(),
            'body' => json_encode($post_data, JSON_UNESCAPED_SLASHES)
        );

        $this->log(__FUNCTION__, $url);
        $this->log(__FUNCTION__, print_r($args, true));

        $result = wp_remote_post($url, $args);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
            $this->log(__FUNCTION__, $error);
            return $error;
        }

        $this->log(__FUNCTION__, print_r($result, true));

        if ($result['response']['code'] === 400) {
            $this->log(__FUNCTION__, "ERROR 400!" . print_r($result['response'], true));
            //$detail = json_decode($result['body'], true)->detail;
            // if (isset($detail, 'eid') && strstr($detail['eid'], 'already exists in this index') !== false) {
            //     // we already synced this, but never saved the href?
            //     // TODO handle this
            // }
            // TODO handle 400
            return;
        }

        $response_decoded = json_decode($result['body'], true);
        grape_debug(print_r($response_decoded, true));

        // grape might change its internal id but the external id (=wordpress id) is stable
        $post->set_grape_href($response_decoded['href_by_external_id']);
        $post->set_grape_indexed($response_decoded['indexed']);

        $this->log(__FUNCTION__, 'done');
    }

    function update($post) {
        $this->log(__FUNCTION__);

        $url = $post->get_grape_href();
        $post_data = $post->serialize();
        $args = array(
            'method' => 'PUT',
            'headers' => $this->get_headers(),
            'body' => json_encode($post_data, JSON_UNESCAPED_SLASHES)
        );

        $this->log(__FUNCTION__, $url);
        $this->log(__FUNCTION__, print_r($args, true));

        $result = wp_remote_request($url, $args);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
            $this->log(__FUNCTION__, $error);
            return $error;
        }

        $this->log(__FUNCTION__, $result['body']);

        $this->log(__FUNCTION__, 'done');
    }

    function delete($post) {
        $this->log(__FUNCTION__);

        $url = $post->get_grape_href();
        $args = array(
            'method' => 'DELETE',
            'headers' => $this->get_headers(),
        );

        $this->log(__FUNCTION__, $url);
        $this->log(__FUNCTION__, print_r($args, true));

        $result = wp_remote_request($url, $args);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
            $this->log(__FUNCTION__, $error);
            return $error;
        }

        $this->log(__FUNCTION__, $result['body']);

        $this->log(__FUNCTION__, 'done');
    }




    private function log($function_name, $message="start") {
        grape_debug("Grape API ($function_name): $message");
    }

}