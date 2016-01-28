<?php

class GRAPE_API {
    function __construct($api_token=null, $api_url=null) {
        $this->api_token = $api_token;
        $this->api_url = $api_url;

        if (null === $api_token) {
            $options = grape_get_options();
            $this->api_token = $options['api_token'];
        }

        if (null === $api_url) {
            $options = grape_get_options();
            $this->api_url = $options['api_url'];
        }

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
            return $response_decoded->detail;
        }

        return "Unexpected Error";
    }

    function create($post) {
        $this->log(__FUNCTION__);

        $url = $this->api_url;
        $args = array(
            'headers' => $this->get_headers(),
            'body' => json_encode(array(
                'eid' => $post->wp_id,
                'name' => $post->title,
                'url' => $post->url,
                'description' => $post->description,
                 ), JSON_UNESCAPED_SLASHES)
        );

        $this->log(__FUNCTION__, print_r($args, true));

        $result = wp_remote_post($url, $args);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
           $this->log(__FUNCTION__, $error);
           return $error;
        }

        $this->log(__FUNCTION__, print_r($result, true));

        if ($result['response']['code'] === 400) {
            $detail = json_decode($result['body'], true)->detail;
            // if (isset($detail, 'eid') && strstr($detail['eid'], 'already exists in this index') !== false) {
            //     // we already synced this, but never saved the href?
            //     // TODO handle this
            // }
            // TODO handle 400
        }

        $response_decoded = json_decode($result['body'], true);
        grape_debug(print_r($response_decoded, true));

        $post->set_grape_href($response_decoded['href']);
        $post->set_grape_indexed($response_decoded['indexed']);

        return $response_decoded;

        $this->log(__FUNCTION__, 'posted');
    }

    function update($post) {
        $this->log(__FUNCTION__);

        $url = $post->grape_href;
        grape_debug($url);
        $args = array(
            'method' => 'PUT',
            'headers' => $this->get_headers(),
            'body' => json_encode(array(
                'name' => $post->title,
                'url' => $post->url,
                'description' => $post->description,
                 ), JSON_UNESCAPED_SLASHES)
        );

        $this->log(__FUNCTION__, print_r($args, true));

        $result = wp_remote_request($url, $args);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
           $this->log(__FUNCTION__, $error);
           return $error;
        }

        $this->log(__FUNCTION__, $result['body']);

        $result_decoded = json_decode($result['body'], true);

        return $result_decoded;

        $this->log(__FUNCTION__, 'posted');
    }



    private function log($function_name, $message="start") {
        grape_debug("Grape API ($function_name): $message");
    }

}