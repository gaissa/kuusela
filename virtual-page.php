<?php

/**
 * A class for creating virtual pages for WordPress.
 *
 * Based on a class "cooked up" by Ohad Raz.
 * https://coderwall.com/p/fwea7g/create-wordpress-virtual-page-on-the-fly
 *
 * @package GoogleCalendarEventWorker
 * @author  Janne Kahkonen <jannekahkonen@gmail.com>
 * @author  Ohad Raz
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 *
 */
class GCEventWorkerVirtualPage
{
    /**
     * The constructor.
     *
     * @param array $args
     *
     */
    function __construct($args)
    {
        add_filter('the_posts', array($this,'virtual_page'));

        $this->args = $args;
        $this->slug = $args['slug'];
    }

    function remove_link()
    {
        return false;
    }

    /**
     * TODO
     *
     * @param TODO
     *
     */
    function my_the_content_filter($content)
    {
        //echo $content . '[MAP HERE]';
        echo $content;

        add_filter( 'previous_post_link', array($this,'remove_link' ));
        add_filter( 'next_post_link', array($this,'remove_link' ));

        wp_enqueue_script('search-form',
                          plugin_dir_url( __FILE__ ) . 'js/temp.js',
                          array('jquery'));

        //echo $content . get_post_type( get_the_ID() ) . get_the_ID();
    }

    /**
     * A function that catches the request and returns the page as if it was retrieved from the database
     *
     * @param  array $posts
     *
     * @return array
     *
     */
    function virtual_page($posts)
    {
        global $wp, $wp_query;

        $page_slug = $this->slug;

        // Check if user is requesting our virtual page.
        if (count($posts) == 0 && (strtolower($wp->request) == $page_slug))
        {
            // Create a virtual post.
            $post = new stdClass;

            $post->post_name = $page_slug;
            $post->guid = get_bloginfo('wpurl' . '/' . $page_slug);

            // Just needs to be a number (negatives are fine).
            $post->ID = time();

            $post->post_status = 'static';
            $post->comment_status = 'closed';
            $post->ping_status = 'closed';
            $post->comment_count = 0;
            $post->post_type = "page";

            $post = (object) array_merge((array) $post, (array) $this->args);

            //$posts = NULL;
            $posts[] = $post;

            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->is_archive = false;
            $wp_query->is_category = false;

            unset($wp_query->query['error']);

            $wp_query->query_vars['error']= "";

            $wp_query->is_404 = false;

            add_filter('the_content', array($this, 'my_the_content_filter'));
        }


        return $posts;
    }

} //end class

/* End of File */