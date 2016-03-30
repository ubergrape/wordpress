<?php

function grape_base64_encode_image ($filename=string,$filetype=string) {
    if ($filename &&  file_exists($filename)) {
        $imgbinary = fread(fopen($filename, "r"), filesize($filename));
        return base64_encode($imgbinary);
    }
}

class GrapePost {
    public $wp_post;
    public $wp_id = 0;
    public $id = 0;
    public $wp_type;
    public $slug = "";
    public $url = "";
    public $status = "";
    public $pub_date = 0;
    public $title = "";
    public $title_plain = "";
    public $content = "";
    public $description = "";
    public $language = "en";
    public $tags = array();
    public $options = array();

    function __construct($wp_post_id = NULL) {
        $this->options = grape_get_options();
        if (NULL != $wp_post_id) {
            $this->import_wp_object($wp_post_id);
        }
    }

    function __toString() {
        return $this->title." (".$this->url.")";
    }

    function import_wp_object($wp_post_id) {
        $wp_post = get_post($wp_post_id);

        $the_content = $wp_post->post_content;
        $the_content = apply_filters('the_content', $the_content);
        $the_content = str_replace(']]>', ']]&gt;', $the_content);

        $this->wp_post      = $wp_post;
        $this->wp_id        = $wp_post_id;
        $this->grape_href   = get_post_meta($wp_post_id, '_grape_href', true);
        $this->grape_indexed= get_post_meta($wp_post_id, '_grape_indexed', true);
        $this->wp_type      = $wp_post->post_type;
        $this->slug         = $wp_post->post_name;
        $this->url          = get_permalink($wp_post_id);
        $this->post_status  = $wp_post->post_status;
        $this->pub_date     = get_post_time('U', true, $wp_post);
        $this->title        = html_entity_decode(strip_tags(get_the_title($wp_post_id)));
        $this->content      = $the_content;
        //$this->tags           = $this->import_tags($wp_post_id);

        /* description */
        $content = $this->content;
        if ( '' != $wp_post->post_excerpt) {
            grape_debug('post has manual excerpt');
            $this->description = $wp_post->post_excerpt;
        } else if ( preg_match('/<!--more(.*?)?-->/', $content, $matches) ) {
            grape_debug('post has teaser (more tag)');
            $content = explode($matches[0], $content, 2);
            $this->description = $content[0];
            $this->content = $content[1];
        }
        $this->description = strip_tags($this->description);

        /* find an image */
        $this->image_url    = $this->find_a_post_image_url();
    }

    function serialize() {
        $post_format = get_post_format($this->wp_id);
        $post_format = $post_format ? (' (' . $post_format . ')') : '';
        $post_type = get_post_type_object(get_post_type($this->wp_id))->labels->singular_name;

        $serialized = array(
            'name' => $this->title,
            'url' => $this->url,
            'description' => $this->description,
            'meta' => array(
                array(
                    'label' => 'Author',
                    'value' => get_the_author_meta('display_name', $this->wp_post->post_author)
                ),
                array(
                    'label' => 'Created',
                    'value' => $this->wp_post->post_date
                ),
                array(
                    'label' => 'Type',
                    'value' => $post_type . $post_format
                ),
            ),
        );

        if ($this->image_url) {
            $serialized['preview'] = array(
                'image' => array(
                    'url' => $this->image_url
                )
            );
        }

        return $serialized;
    }

    function import_tags($wp_post_id) {
        $tags = array();

        $options = grape_get_options();

        $tag = $this->options['tag'];

        if (1 == $tag || 3 == $tag) {
            $post_categories = wp_get_post_categories($wp_post_id);
            foreach($post_categories as $c){
                $cat = get_category($c);
                $tags[] = $cat->name;
            }
        }
        if (2 == $tag || 3 == $tag) {
            $post_tags = wp_get_post_categories($wp_post_id);
            foreach($post_tags as $t){
                $tag = get_category($t);
                $tags[] = $tags->name;
            }
        }

        return $tags;
    }

    function get_eid() {
        return $this->wp_id;
    }

    function get_grape_href() {
        return $this->grape_href;
    }

    function get_connections() {
        return $this->options['syncable_post_types'][$this->wp_type];
    }

    function set_grape_href($grape_href) {
        $this->grape_href = $grape_href;
        update_post_meta($this->wp_id, '_grape_href', $grape_href);
    }

    function set_grape_indexed($grape_indexed) {
        $this->grape_indexed = $grape_indexed;
        update_post_meta($this->wp_id, '_grape_indexed', $grape_indexed);
    }

    /* finds a post image:
     * 1) post-thumbnail
     * 2) first image in post
     *    if the post starts with an image, the image is removed from the content
     *
     * attention: modifies $this->content
     */
    function find_a_post_image_url() {
        $image_url = false;

        // Check for post image
        if(current_theme_supports('post-thumbnails') && null!=get_post_thumbnail_id($this->wp_id)){
            $image_url = wp_get_attachment_url(get_post_thumbnail_id($this->wp_id));
            grape_debug('post image found');
        }

        // if we have no post image check for images inside content
        if(!$image_url) {

            // strip html except img
            $stripped_content = trim(str_replace('&nbsp;','',strip_tags($this->wp_post->post_content,'<img>')));

            // find image urls
            $output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $stripped_content, $matches, PREG_SET_ORDER);

            // do we have at least one image in post?
            if(count($matches) > 0 && 2==count($matches[0])) {

                // first image
                $found_image_url = $matches[0][1];
                $found_image_tag = $matches[0][0];

                grape_debug("image tag found in post: $found_image_url");

                // wordpress resizes images and gives them names like image-150x150.jpg
                $image_url = preg_replace('/\-[0-9]+x[0-9]+/', '', $found_image_url);
                grape_debug("original image: $image_url");

                if($image_url) {
                    // does the post start with the image? then remove image
                    if(0==strpos($stripped_content, $found_image_tag)) {
                        grape_debug('post starts with image, removing image from content');
                        $this->content = str_replace($found_image_tag, '', $this->content);
                    }
                } else {
                    grape_debug('image not found mediathek, ignoring');
                }
            }
        }

        if(!$image_url) {
            grape_debug('no usable image found in post');
        }

        return $image_url;
    }

    function should_be_synced() {
        // Don't sync if the post has the wrong post type
        // or it's private
        // also publish posts with a publish date in the future

        if (
            (!array_key_exists($this->wp_type, $this->options['syncable_post_types'])) ||
            (count($this->options['syncable_post_types'][$this->wp_type]) === 0) ||
            ('private' == $this->post_status) ||
            ('publish' != $this->post_status && 'future' != $this->post_status)
        ) {
            return false;
        }

        return true;
    }

    function should_be_deleted_because_private(){
        // If ...
        // - It's changed to private, and we've chosen not to sync private entries
        // - It now isn't published or private (trash, pending, draft, etc.)
        // - It was synced but now it's set to not sync

        if (
            ('private' == $this->post_status) ||
            ('publish' != $this->post_status && 'private' != $this->post_status) //||
            // 0 == get_post_meta($this->wp_id, 'grape_sync', true)
        ) {
            return true;
        }

        return false;
    }

    function was_synced() {
        return ("" != $this->grape_href && null != $this->grape_href);

    }

    function smartTruncate($string, $limit, $break=" ", $pad="...") {
        // Original PHP code by Chirp Internet: www.chirp.com.au
        // return with no change if string is shorter than $limit
        if(strlen($string) <= $limit) return $string;
        $string = substr($string, 0, $limit);
        if(false !== ($breakpoint = strrpos($string, $break))) {
            $string = substr($string, 0, $breakpoint);
            $string = $string.'.';
        }
        return $string . $pad;
    }
}

?>
