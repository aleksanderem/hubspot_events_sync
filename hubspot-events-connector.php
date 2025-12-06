<?php
/**
 * Plugin Name: HubSpot Events Connector
 * Plugin URI: https://mwt.pl
 * Description: Synchronizes marketing events from HubSpot to WordPress as a custom post type with automatic field mapping and incremental updates.
 * Version: 1.0.0
 * Author: Alex M.
 * Author URI: https://mwt.pl
 * Text Domain: hubspot-events-connector
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('HSEC_VERSION', '1.0.0');
define('HSEC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HSEC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HSEC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// GitHub repository for updates (change to your repo)
define('HSEC_GITHUB_REPO', 'aleksanderem/hubspot_events_sync');

/**
 * Initialize plugin update checker for GitHub releases
 */
if (file_exists(HSEC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php')) {
    require_once HSEC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

    $hsec_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/' . HSEC_GITHUB_REPO . '/',
        __FILE__,
        'hubspot-events-connector'
    );

    // For private repositories - set authentication token
    // Token should be stored in wp-config.php: define('HSEC_GITHUB_TOKEN', 'your_token_here');
    if (defined('HSEC_GITHUB_TOKEN') && HSEC_GITHUB_TOKEN) {
        $hsec_update_checker->setAuthentication(HSEC_GITHUB_TOKEN);
    }

    // Use releases as the source of updates
    $hsec_update_checker->getVcsApi()->enableReleaseAssets();
}

/**
 * Main plugin class - HubSpot Events Connector
 *
 * Handles synchronization of HubSpot Marketing Events to WordPress CPT
 */
class HubSpot_Events_Connector {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - hooks into WordPress
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Load translations
        load_plugin_textdomain('hubspot-events-connector', false, dirname(HSEC_PLUGIN_BASENAME) . '/languages');

        // Include required files
        $this->load_dependencies();

        // Initialize components
        HSEC_Post_Type::instance();
        HSEC_Taxonomy_Manager::instance();
        HSEC_API_Client::instance();
        HSEC_Sync_Manager::instance();

        // Admin only components
        if (is_admin()) {
            HSEC_Admin_Settings::instance();
        }

        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers
        add_action('wp_ajax_hsec_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_hsec_sync_now', [$this, 'ajax_sync_now']);
        add_action('wp_ajax_hsec_stop_sync', [$this, 'ajax_stop_sync']);
        add_action('wp_ajax_hsec_force_stop', [$this, 'ajax_force_stop']);
        add_action('wp_ajax_hsec_get_sync_status', [$this, 'ajax_get_sync_status']);
        add_action('wp_ajax_hsec_delete_all_events', [$this, 'ajax_delete_all_events']);
        add_action('wp_ajax_hsec_take_screenshot', [$this, 'ajax_take_screenshot']);
        add_action('wp_ajax_hsec_fetch_missing_images', [$this, 'ajax_fetch_missing_images']);
    }

    /**
     * Load required class files
     */
    private function load_dependencies() {
        require_once HSEC_PLUGIN_DIR . 'includes/class-post-type.php';
        require_once HSEC_PLUGIN_DIR . 'includes/class-taxonomy-manager.php';
        require_once HSEC_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once HSEC_PLUGIN_DIR . 'includes/class-sync-manager.php';
        require_once HSEC_PLUGIN_DIR . 'includes/class-admin-settings.php';
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page or CPT edit screens
        $screen = get_current_screen();

        if ($hook === 'settings_page_hsec-settings' ||
            ($screen && $screen->post_type === 'hs_event')) {

            wp_enqueue_style(
                'hsec-admin-css',
                HSEC_PLUGIN_URL . 'assets/css/admin.css',
                [],
                HSEC_VERSION
            );

            wp_enqueue_script(
                'hsec-admin-js',
                HSEC_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                HSEC_VERSION,
                true
            );

            wp_localize_script('hsec-admin-js', 'hsecAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hsec_admin_nonce'),
                'strings' => [
                    'testing' => __('Testing connection...', 'hubspot-events-connector'),
                    'success' => __('Connection successful!', 'hubspot-events-connector'),
                    'error' => __('Connection failed', 'hubspot-events-connector'),
                    'noToken' => __('Please enter a token first', 'hubspot-events-connector'),
                    'syncing' => __('Synchronizing events...', 'hubspot-events-connector'),
                    'stopping' => __('Stopping sync...', 'hubspot-events-connector'),
                    'syncComplete' => __('Synchronization complete!', 'hubspot-events-connector'),
                    'syncError' => __('Synchronization failed', 'hubspot-events-connector'),
                    'confirmFullSync' => __('This will fetch all events from HubSpot. This may take a while if you have many events. Continue?', 'hubspot-events-connector'),
                    'confirmDeleteAll' => __('Are you sure you want to delete ALL synced events? This action cannot be undone!', 'hubspot-events-connector'),
                    'confirmDeleteAllDouble' => __('This will permanently delete all HubSpot events from WordPress. Type "DELETE" to confirm:', 'hubspot-events-connector'),
                    'deleteAborted' => __('Delete aborted.', 'hubspot-events-connector'),
                    'deleting' => __('Deleting events...', 'hubspot-events-connector'),
                    'deleteComplete' => __('Delete complete!', 'hubspot-events-connector'),
                    'deleteError' => __('Delete failed', 'hubspot-events-connector'),
                    'fetchingImages' => __('Fetching missing images...', 'hubspot-events-connector'),
                    'fetchImagesComplete' => __('Images fetched!', 'hubspot-events-connector'),
                    'fetchImagesError' => __('Error fetching images', 'hubspot-events-connector'),
                ]
            ]);
        }
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        // Use token from request if provided (for testing before save)
        $test_token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;

        $api = HSEC_API_Client::instance();
        $result = $api->test_connection($test_token);

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Connection successful! Found events in your HubSpot account.', 'hubspot-events-connector'),
                'events_count' => $result['events_count'] ?? 0
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error'] ?? __('Unknown error occurred', 'hubspot-events-connector')
            ]);
        }
    }

    /**
     * AJAX: Trigger manual sync
     */
    public function ajax_sync_now() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        $full_sync = isset($_POST['full_sync']) && $_POST['full_sync'] === 'true';

        $sync_manager = HSEC_Sync_Manager::instance();
        $result = $sync_manager->sync_events($full_sync);

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Sync complete! Created: %d, Updated: %d, Skipped: %d', 'hubspot-events-connector'),
                    $result['created'],
                    $result['updated'],
                    $result['skipped']
                ),
                'stats' => $result
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error'] ?? __('Sync failed', 'hubspot-events-connector')
            ]);
        }
    }

    /**
     * AJAX: Stop running sync
     */
    public function ajax_stop_sync() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        $sync_manager = HSEC_Sync_Manager::instance();
        $sync_manager->request_stop();

        wp_send_json_success([
            'message' => __('Stop requested. Sync will stop after current event.', 'hubspot-events-connector')
        ]);
    }

    /**
     * AJAX: Force stop - clears lock immediately
     */
    public function ajax_force_stop() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        $sync_manager = HSEC_Sync_Manager::instance();
        $sync_manager->force_stop();

        wp_send_json_success([
            'message' => __('Sync forcefully stopped. Lock cleared.', 'hubspot-events-connector')
        ]);
    }

    /**
     * AJAX: Get current sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        $last_sync = get_option('hsec_last_sync_time', null);
        $last_result = get_option('hsec_last_sync_result', []);
        $events_count = wp_count_posts('hs_event');
        $sync_manager = HSEC_Sync_Manager::instance();

        wp_send_json_success([
            'last_sync' => $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync) : __('Never', 'hubspot-events-connector'),
            'last_result' => $last_result,
            'total_events' => $events_count->publish ?? 0,
            'is_running' => $sync_manager->is_sync_running()
        ]);
    }

    /**
     * AJAX: Fetch missing images from HubSpot raw data
     */
    public function ajax_fetch_missing_images() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        // Increase time limit for this operation
        set_time_limit(600);

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Get all events without featured image
        $events = get_posts([
            'post_type' => 'hs_event',
            'numberposts' => -1,
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => '_thumbnail_id',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        $processed = 0;
        $success = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($events as $event) {
            $processed++;

            // Get raw data
            $raw_data = get_post_meta($event->ID, '_hsec_raw_data', true);
            $image_url = $raw_data['featuredImage'] ?? '';

            if (empty($image_url)) {
                $skipped++;
                continue;
            }

            // Validate URL
            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                $skipped++;
                continue;
            }

            // Download the image
            $tmp = download_url($image_url);

            if (is_wp_error($tmp)) {
                $failed++;
                $errors[] = sprintf('Post %d: %s', $event->ID, $tmp->get_error_message());
                continue;
            }

            $file_array = [
                'name' => basename(parse_url($image_url, PHP_URL_PATH)) ?: 'image.jpg',
                'tmp_name' => $tmp,
            ];

            // Ensure extension
            if (!pathinfo($file_array['name'], PATHINFO_EXTENSION)) {
                $file_array['name'] .= '.jpg';
            }

            $attachment_id = media_handle_sideload($file_array, $event->ID, get_the_title($event->ID));

            if (is_wp_error($attachment_id)) {
                @unlink($file_array['tmp_name']);
                $failed++;
                $errors[] = sprintf('Post %d: %s', $event->ID, $attachment_id->get_error_message());
                continue;
            }

            // Set as featured image
            set_post_thumbnail($event->ID, $attachment_id);
            update_post_meta($event->ID, '_hsec_image_source_url', $image_url);

            $success++;
        }

        $message = sprintf(
            __('Processed %d events. Success: %d, Failed: %d, Skipped (no image URL): %d', 'hubspot-events-connector'),
            $processed,
            $success,
            $failed,
            $skipped
        );

        if (!empty($errors) && count($errors) <= 5) {
            $message .= '<br><small>' . implode('<br>', $errors) . '</small>';
        } elseif (!empty($errors)) {
            $message .= '<br><small>' . sprintf(__('First 5 errors: %s', 'hubspot-events-connector'), implode('<br>', array_slice($errors, 0, 5))) . '</small>';
        }

        wp_send_json_success([
            'message' => $message,
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
    }

    /**
     * AJAX: Take screenshot of event page and set as featured image
     */
    public function ajax_take_screenshot() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (!$post_id || !$url) {
            wp_send_json_error(['message' => __('Missing post ID or URL', 'hubspot-events-connector')]);
        }

        // Verify post exists and is our CPT
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'hs_event') {
            wp_send_json_error(['message' => __('Invalid post', 'hubspot-events-connector')]);
        }

        // Try multiple screenshot services until one works
        $screenshot_image_url = null;
        $error_messages = [];

        // Option 1: microlink.io - free 50/day, high quality
        $microlink_url = 'https://api.microlink.io/?url=' . urlencode($url) . '&screenshot=true&meta=false&viewport.width=1280&viewport.height=800';

        $response = wp_remote_get($microlink_url, ['timeout' => 60]);

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['status']) && $data['status'] === 'success' && !empty($data['data']['screenshot']['url'])) {
                $screenshot_image_url = $data['data']['screenshot']['url'];
            } else {
                $error_messages[] = 'Microlink: ' . ($data['message'] ?? $data['status'] ?? 'no screenshot URL');
            }
        } else {
            $error_messages[] = 'Microlink: ' . $response->get_error_message();
        }

        // Option 2: urlbox via free proxy (limited but works)
        if (!$screenshot_image_url) {
            // Use page2images.com - truly free, no key needed
            $p2i_url = 'http://api.page2images.com/restfullink?p2i_url=' . urlencode($url) . '&p2i_screen=1280x800&p2i_size=1280x0&p2i_fullpage=0&p2i_wait=3';

            $response = wp_remote_get($p2i_url, ['timeout' => 60]);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (!empty($data['image_url'])) {
                    $screenshot_image_url = $data['image_url'];
                } else {
                    $error_messages[] = 'Page2Images: ' . ($data['error'] ?? 'no image URL');
                }
            } else {
                $error_messages[] = 'Page2Images: ' . $response->get_error_message();
            }
        }

        // Option 3: Use Google PageSpeed Insights screenshot (always works, free)
        if (!$screenshot_image_url) {
            $psi_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode($url) . '&category=performance&strategy=desktop';

            $response = wp_remote_get($psi_url, ['timeout' => 90]);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                // Extract screenshot from lighthouse audit
                $screenshot_data = $data['lighthouseResult']['audits']['final-screenshot']['details']['data'] ?? null;

                if ($screenshot_data && strpos($screenshot_data, 'data:image') === 0) {
                    // It's a base64 image, we need to save it directly
                    $image_data = explode(',', $screenshot_data);
                    if (count($image_data) === 2) {
                        $decoded = base64_decode($image_data[1]);
                        if ($decoded) {
                            $upload_dir = wp_upload_dir();
                            $filename = 'screenshot-' . $post_id . '-' . time() . '.jpeg';
                            $filepath = $upload_dir['path'] . '/' . $filename;

                            if (file_put_contents($filepath, $decoded)) {
                                $screenshot_image_url = $upload_dir['url'] . '/' . $filename;

                                // Create attachment
                                $attachment = [
                                    'post_mime_type' => 'image/jpeg',
                                    'post_title' => get_the_title($post_id) . ' - Screenshot',
                                    'post_content' => '',
                                    'post_status' => 'inherit'
                                ];

                                $attachment_id = wp_insert_attachment($attachment, $filepath, $post_id);

                                if (!is_wp_error($attachment_id)) {
                                    require_once ABSPATH . 'wp-admin/includes/image.php';
                                    $metadata = wp_generate_attachment_metadata($attachment_id, $filepath);
                                    wp_update_attachment_metadata($attachment_id, $metadata);
                                    set_post_thumbnail($post_id, $attachment_id);

                                    update_post_meta($post_id, '_hsec_image_source', 'screenshot');
                                    update_post_meta($post_id, '_hsec_image_source_url', $url);

                                    wp_send_json_success([
                                        'message' => __('Screenshot captured successfully (via PageSpeed)', 'hubspot-events-connector'),
                                        'url' => $screenshot_image_url,
                                        'attachment_id' => $attachment_id,
                                    ]);
                                }
                            }
                        }
                    }
                }
                $error_messages[] = 'PageSpeed: could not extract screenshot';
            } else {
                $error_messages[] = 'PageSpeed: ' . $response->get_error_message();
            }
        }

        if (!$screenshot_image_url) {
            wp_send_json_error(['message' => __('Could not generate screenshot. Errors: ', 'hubspot-events-connector') . implode('; ', $error_messages)]);
        }

        // Download and attach the screenshot as featured image
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($screenshot_image_url);

        if (is_wp_error($tmp)) {
            wp_send_json_error(['message' => __('Failed to download screenshot: ', 'hubspot-events-connector') . $tmp->get_error_message()]);
        }

        $file_array = [
            'name' => 'screenshot-' . $post_id . '-' . time() . '.jpg',
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id, get_the_title($post_id) . ' - Screenshot');

        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            wp_send_json_error(['message' => __('Failed to save screenshot: ', 'hubspot-events-connector') . $attachment_id->get_error_message()]);
        }

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        // Store source info
        update_post_meta($post_id, '_hsec_image_source', 'screenshot');
        update_post_meta($post_id, '_hsec_image_source_url', $url);

        // Get the attachment URL
        $image_url = wp_get_attachment_url($attachment_id);

        wp_send_json_success([
            'message' => __('Screenshot captured successfully', 'hubspot-events-connector'),
            'url' => $image_url,
            'attachment_id' => $attachment_id,
        ]);
    }

    /**
     * AJAX: Delete all synced events
     */
    public function ajax_delete_all_events() {
        check_ajax_referer('hsec_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'hubspot-events-connector')]);
        }

        $events = get_posts([
            'post_type' => 'hs_event',
            'numberposts' => -1,
            'fields' => 'ids',
            'post_status' => 'any',
        ]);

        if (empty($events)) {
            wp_send_json_success([
                'message' => __('No events to delete.', 'hubspot-events-connector'),
                'deleted' => 0
            ]);
        }

        $deleted = 0;
        foreach ($events as $event_id) {
            if (wp_delete_post($event_id, true)) {
                $deleted++;
            }
        }

        // Reset sync tracking data
        delete_option('hsec_last_sync_time');
        delete_option('hsec_last_sync_result');

        wp_send_json_success([
            'message' => sprintf(
                __('Successfully deleted %d events.', 'hubspot-events-connector'),
                $deleted
            ),
            'deleted' => $deleted
        ]);
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Load dependencies first
        require_once HSEC_PLUGIN_DIR . 'includes/class-post-type.php';
        require_once HSEC_PLUGIN_DIR . 'includes/class-taxonomy-manager.php';

        // Register CPT and taxonomies
        HSEC_Post_Type::instance()->register_post_type();
        HSEC_Taxonomy_Manager::instance()->register_taxonomies();

        // Set default options
        if (get_option('hsec_api_token') === false) {
            add_option('hsec_api_token', '');
        }
        if (get_option('hsec_sync_interval') === false) {
            add_option('hsec_sync_interval', 'hourly');
        }
        if (get_option('hsec_last_sync_time') === false) {
            add_option('hsec_last_sync_time', null);
        }

        // Schedule cron job
        if (!wp_next_scheduled('hsec_cron_sync')) {
            wp_schedule_event(time(), get_option('hsec_sync_interval', 'hourly'), 'hsec_cron_sync');
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set flag for admin notice
        set_transient('hsec_activation_notice', true, 60);
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('hsec_cron_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hsec_cron_sync');
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall - called from uninstall.php
     */
    public static function uninstall() {
        // Remove options
        delete_option('hsec_api_token');
        delete_option('hsec_sync_interval');
        delete_option('hsec_last_sync_time');
        delete_option('hsec_last_sync_result');
        delete_option('hsec_event_types');
        delete_option('hsec_custom_fields_map');

        // Optionally remove all events (commented out for safety)
        // $events = get_posts(['post_type' => 'hs_event', 'numberposts' => -1]);
        // foreach ($events as $event) {
        //     wp_delete_post($event->ID, true);
        // }
    }
}

// Activation/deactivation hooks
register_activation_hook(__FILE__, ['HubSpot_Events_Connector', 'activate']);
register_deactivation_hook(__FILE__, ['HubSpot_Events_Connector', 'deactivate']);

// Initialize plugin
HubSpot_Events_Connector::instance();
