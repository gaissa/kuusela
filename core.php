<?php

/*
Plugin Name: Google Calendar Event Worker
Plugin URI:
Description:
Version: 0.1
Date: 2014-12-12
Author: Janne Kähkönen
Author URI:
*/

/**
 * TODO.
 *
 */
set_time_limit(0);

/**
 * TODO.
 *
 */
date_default_timezone_set('Europe/Helsinki');

/**
 * Prevent direct file access.
 *
 */
if (!defined('ABSPATH'))
{
    exit;
}

/**
 * The init point of the app.
 *
 * Load the needed classes and translations. Also set the query vars.
 *
 * @package GoogleCalendarEventWorker
 * @author  Janne Kahkonen <jannekahkonen@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 *
 */
class GCEventWorkerClientCore
{
    /**
     * @TODO - Rename "widget-name" to the name your your widget
     *
     * Unique identifier for your widget.
     *
     * The variable name is used as the text domain when internationalizing strings
     * of text. Its value should match the Text Domain file header in the main
     * widget file.
     *
     * @var string
     *
     */
    protected $widget_slug = 'GoogleCalendarEventWorker';

    /**
     * The API KEY to be used.
     *
     * TODO
     *
     * @var string
     *
     */
    private $api_key;

    /**
     * The array of calendar ID keys.
     *
     * TODO
     *
     * @var array
     *
     */
    private $id;

    /**
     * Show only upcoming events or all.
     *
     * TODO
     *
     * @var string
     *
     */
    private $future_events;

    /**
     * The constructor.
     *
     * TODO
     *
     */
    function __construct()
    {
        require_once('admin/plugin-options-page.php');

        $this->api_key = get_option('gcew_api_key')['api-key'];
        $this->future_events = get_option('gcew_api_key')['future-events'];
        $this->id = get_option('gcew_api_key')['calendar-id'][0];

        if( get_option( 'gcew_api_key' ) )
        {
            //do nothing here
        }
        else
        {
            //die();
        }

        if (!isset($this->api_key) && !isset($this->id))
        {
            //die();
        }

        //if (isset($this->id))
        //{

            //var_dump($this->id);
            //die();

            // Register CRON stuff
            add_filter('cron_schedules', array(&$this, 'add_custom_cron_schedule')) ;

            register_deactivation_hook(__FILE__, array(&$this, 'gcew_deactivation'));
            register_activation_hook(__FILE__, array(&$this, 'gcew_activation'));

            add_action('gcew_get_events_schedule_hook', array(&$this, 'scheduled_function'));

            if (isset($this->api_key) && isset($this->id))
            {
                // Register AJAX stuff
                add_action('wp_ajax_nopriv_ajax-example', array(&$this, 'ajax_call'));
                add_action('wp_ajax_ajax-example', array(&$this, 'ajax_call'));

                // INIT stuff
                add_action('init', array(&$this, 'main_init'));
            }

            // Enqueue the CSS stylesheets
            add_action('admin_enqueue_scripts', array(&$this, 'add_admin_styles_and_scripts'));

        //}
        //else
        //{
             //do nothing here
        //}

    }

    /**
     * TODO
     *
     * @param  TODO
     *
     */
    function add_custom_cron_schedule($schedules)
    {
        $schedules['gcew_get_events_schedule'] = array(
            'interval' => 1,
            'display'  => __('Custom Interval'),
        );

        return $schedules;
    }

    /**
     * On deactivation, remove all functions from the scheduled action hook.
     *
     */
    function gcew_deactivation()
    {
        wp_clear_scheduled_hook('gcew_get_events_schedule_hook');
        delete_option('gcew_events_list');
        // ADD MORE!!!!
    }

    /**
     * On activation, set a time, frequency and name of an action hook to be scheduled.
     *
     */
    function gcew_activation()
    {
        wp_schedule_event(time(), 'gcew_get_events_schedule', 'gcew_get_events_schedule_hook');
    }

    /**
     * On the scheduled action hook, run the function.
     *
     */
    function scheduled_function()
    {
        $this->get_data();
    }

    /**
     * Add stylesheet to the admin page.
     *
     */
    function add_admin_styles_and_scripts($hook)
    {
        //var_dump($hook);
        //die();

        if ($hook == 'toplevel_page_gcew-options')
        {
            wp_enqueue_style('options-style', plugins_url('css/options-style.css', __FILE__));

            wp_enqueue_script('listjs',
                              plugin_dir_url( __FILE__ ) . 'js/lib/listjs/list.js',
                              array('jquery'));

            wp_enqueue_script('listjspagination',
                              plugin_dir_url( __FILE__ ) . 'js/lib/listj/list.pagination.js',
                              array('jquery'));

            wp_enqueue_script('alertifyjs',
                              plugin_dir_url( __FILE__ ) . 'js/lib/alertify/alertify.js',
                              array('jquery'));

            wp_enqueue_style('alertify-core', plugins_url('css/alertify/alertify.core.css', __FILE__));

            wp_enqueue_style('alertify-default', plugins_url('css/alertify/alertify.default.css', __FILE__));
        }
    }

    /**
     * TODO
     *
     * @param  TODO $needle
     * @param  array   $haystack
     * @param  boolean $strict
     *
     * @return boolean
     *
     */
    public function in_array_r($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $item)
        {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict)))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the data and parse it.
     *
     * @return array $parsed_data
     *
     */
    private function get_data()
    {
        $parsed_data = array();
        $categories = array();

        header('Content-Type: application/json');

        $key = $this->api_key;

        // OPTIONS HERE! CHANGE TO BOOLEAN AND CHECKBOX
        if ($this->future_events == 1)
        {
            $timeMin = '&timeMin=' . gmdate('Y-m-d\TH:i:s\Z', time()-(43200));
        }
        else
        {
            $timeMin = '';
        }

        $calendars = $this->id;

        $arrs = array();

        for ($i = 0; $i < count($calendars); $i++)
        {
            $temporary = wp_remote_get('https://www.googleapis.com/calendar/v3/calendars/' .
                                       $calendars[$i] . '/events?singleEvents=true' .
                                       $timeMin .'&key=' . $key,
                                       array('timeout' => 10000,
                                             'compress' => true,
                                             'stream' => false));

            $event_data = json_decode($temporary['body'], true);

            $event_items = $event_data['items'];

            $arrs[] = $event_items;
        }

        $result = array();

        foreach ($arrs as $arr)
        {
            $result = array_merge_recursive($result, $arr);
        }

        for ($i = 0; $i < count($result); $i++)
        {
            $start_order = date('yyyymmdd', strtotime($result[$i]['start']['dateTime']));

            $start_date = date('d.m.Y', strtotime($result[$i]['start']['dateTime']));
            $start_time = date('H:i', strtotime($result[$i]['start']['dateTime']));

            $end_date = date('d.m.Y', strtotime($result[$i]['end']['dateTime']));
            $end_time = date('H:i', strtotime($result[$i]['end']['dateTime']));

            if (isset($result[$i]['description']))
            {
                $description = $result[$i]['description'];
            }
            else
            {
                $description = '';
            }

            if (isset($result[$i]['location']))
            {
                $location = $result[$i]['location'];
            }
            else
            {
                $location = '';
            }

            //if ($this->in_array_r($result[$i]['summary'], $parsed_data) === false)
            //{
                $id = $result[$i]['summary'] . '-' . $start_date . $start_time;
            //}
            //else
            //{
                //$id = $result[$i]['summary'] . "-" . $start_date . $start_time;
            //}

            if (!in_array($result[$i]['organizer']['displayName'], $categories))
            {
                array_push($categories, $result[$i]['organizer']['displayName']);
            }

            $parsed_data[$i] = array(
                            'name' => $result[$i]['summary'],
                            'id' => sanitize_title($id),
                            'description' => $description,
                            'location' => $location,
                            'subjects' => array($result[$i]['organizer']['displayName']),
                            'start_order' => $start_order . $start_time,
                            'start_at' => $start_date,
                            'start_time' => $start_time,
                            'end_at' => $end_date,
                            'end_time' => $end_time,
                            'link' => $result[$i]['htmlLink'],
                            'single_post_time' => $result[$i]['updated']
                            );
        }

        update_option('gcew_events_list', $parsed_data);
        update_option('gcew_event_categories', $categories);
    }

    /**
     * TODO
     *
     */
    private function init_ajax()
    {
        global $post;

        if (has_shortcode($post->post_content, 'events_01'))
        {
            $page = 'front';

            wp_enqueue_script('search-form',
                              plugin_dir_url( __FILE__ ) . 'js/views/search_01.js',
                              array('jquery'));

            wp_enqueue_script('ajax-example',
                              plugin_dir_url( __FILE__ ) . 'js/views/events_01.js',
                              array('jquery'));


            wp_localize_script('ajax-example',
                               'AjaxExample',
                               array('ajaxurl' => admin_url('admin-ajax.php'),
                                     //'cat' => $temp,
                                     'page' => sanitize_title($page),
                                     'root' => get_site_url(),
                                     //'url' => get_permalink(),
                                     'nonce' => wp_create_nonce('ajax-example-nonce')));
        }
        else if (has_shortcode($post->post_content, 'events_02'))
        {
            $page = 'front';

            wp_enqueue_script('search-form',
                              plugin_dir_url( __FILE__ ) . 'js/views/search_01.js',
                              array('jquery'));

            wp_enqueue_style('front-style', plugins_url('css/front-style.css', __FILE__));

            wp_enqueue_script('listjs',
                              plugin_dir_url( __FILE__ ) . 'js/lib/listjs/list.js',
                              array('jquery'));

            wp_enqueue_script('listjspagination',
                              plugin_dir_url( __FILE__ ) . 'js/lib/listjs/list.pagination.js',
                              array('jquery'));

            $loc_array = get_option('gcew_events_list');

            wp_enqueue_script('front_view',
                              plugin_dir_url( __FILE__ ) . 'js/views/events_02.js',
                              array('jquery'));

            wp_localize_script('front_view',
                               'object_name',
                               array($loc_array,
                               array('start_text' => "Alkaa",
                                     'end_text' => "Loppuu",
                                     'location_text' => "Sijainti",
                                     'root' => get_site_url())));
        }
        else
        {
            $page = null;
        }
    }

    /**
     * TODO
     *
     */
    function main_init()
    {
        require_once('virtual-page.php');
        require_once('view.php');
        //$this->view = new View();
        add_action('wp_head', array($this, 'init_head'));
    }

    /**
     * TODO
     *
     */
    function init_head()
    {
        $this->init_ajax();
    }

    /**
     * TODO
     *
     */
    function ajax_call()
    {
        if (!isset($_REQUEST['nonce']) ||
            !wp_verify_nonce($_REQUEST['nonce'], 'ajax-example-nonce' ))
        {
            die('Invalid Nonce');
        }

        $output = get_option('gcew_events_list');

        //header( "Content-Type: application/json" );

        wp_send_json(array('success' => true,
                           'data' => $output));

        wp_die();
    }

} //end class

new GCEventWorkerClientCore();

/* End of File */