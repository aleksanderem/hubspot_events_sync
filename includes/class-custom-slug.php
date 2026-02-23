<?php
/**
 * Custom URL Slug for hs_event Post Type
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Custom_Slug {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('register_post_type_args', [$this, 'modify_post_type_args'], 10, 2);
        add_action('init', [$this, 'maybe_flush_rewrites'], 999);
    }

    /**
     * Modify the hs_event post type rewrite slug
     */
    public function modify_post_type_args($args, $post_type) {
        if ($post_type === 'hs_event') {
            $custom_slug = apply_filters('hsec_custom_slug', 'wydarzenia');

            $args['rewrite'] = [
                'slug' => $custom_slug,
                'with_front' => false,
            ];
            $args['has_archive'] = $custom_slug;
        }
        return $args;
    }

    /**
     * Flush rewrite rules on plugin activation (run once)
     */
    public function maybe_flush_rewrites() {
        $flushed = get_option('hsec_rewrite_flushed_v2', false);
        if (!$flushed) {
            flush_rewrite_rules();
            update_option('hsec_rewrite_flushed_v2', true);
        }
    }
}
