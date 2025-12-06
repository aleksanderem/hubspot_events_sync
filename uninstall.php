<?php
/**
 * Uninstall HubSpot Events Connector
 *
 * Removes all plugin data when the plugin is deleted
 *
 * @package HubSpot_Events_Connector
 */

// If uninstall.php is not called by WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove options
delete_option('hsec_api_token');
delete_option('hsec_sync_interval');
delete_option('hsec_last_sync_time');
delete_option('hsec_last_sync_result');
delete_option('hsec_event_types');
delete_option('hsec_custom_fields_map');
delete_option('hsec_auto_taxonomies');
delete_option('hsec_dynamic_taxonomies');
delete_option('hsec_sync_lock');

// Clear scheduled cron events
$timestamp = wp_next_scheduled('hsec_cron_sync');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'hsec_cron_sync');
}

// Optional: Remove all events and their meta
// Uncomment if you want to delete all synced events when plugin is removed
/*
$events = get_posts([
    'post_type' => 'hs_event',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($events as $event_id) {
    wp_delete_post($event_id, true);
}
*/

// Optional: Remove custom taxonomies terms
// Uncomment if you want to clean up taxonomy terms
/*
$taxonomies = ['hs_event_type', 'hs_event_organizer'];
$dynamic_taxonomies = get_option('hsec_dynamic_taxonomies', []);
$taxonomies = array_merge($taxonomies, array_keys($dynamic_taxonomies));

foreach ($taxonomies as $taxonomy) {
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'fields' => 'ids',
    ]);

    if (!is_wp_error($terms)) {
        foreach ($terms as $term_id) {
            wp_delete_term($term_id, $taxonomy);
        }
    }
}
*/

// Flush rewrite rules
flush_rewrite_rules();
