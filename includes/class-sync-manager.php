<?php
/**
 * Sync Manager for HubSpot Events
 *
 * Handles synchronization logic between HubSpot and WordPress
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Sync_Manager {

    private static $instance = null;

    /**
     * Sync lock option name
     */
    const LOCK_OPTION = 'hsec_sync_lock';

    /**
     * Stop request option name
     */
    const STOP_OPTION = 'hsec_sync_stop_requested';

    /**
     * Lock timeout in seconds
     */
    const LOCK_TIMEOUT = 600; // 10 minutes

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
     * Constructor
     */
    private function __construct() {
        // Register cron hook
        add_action('hsec_cron_sync', [$this, 'cron_sync']);

        // Add custom cron intervals
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
    }

    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'hubspot-events-connector'),
        ];

        $schedules['every_30_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'hubspot-events-connector'),
        ];

        return $schedules;
    }

    /**
     * Check if sync is currently running
     */
    public function is_sync_running() {
        $lock = get_option(self::LOCK_OPTION, null);

        if ($lock === null) {
            return false;
        }

        // Check if lock is stale
        if (time() - $lock > self::LOCK_TIMEOUT) {
            $this->release_lock();
            return false;
        }

        return true;
    }

    /**
     * Acquire sync lock
     */
    private function acquire_lock() {
        if ($this->is_sync_running()) {
            return false;
        }

        update_option(self::LOCK_OPTION, time());
        return true;
    }

    /**
     * Release sync lock
     */
    private function release_lock() {
        delete_option(self::LOCK_OPTION);
        delete_option(self::STOP_OPTION);
    }

    /**
     * Request sync stop
     */
    public function request_stop() {
        update_option(self::STOP_OPTION, true);
    }

    /**
     * Check if stop was requested
     */
    public function is_stop_requested() {
        return (bool) get_option(self::STOP_OPTION, false);
    }

    /**
     * Force stop - immediately clears all sync locks
     */
    public function force_stop() {
        delete_option(self::LOCK_OPTION);
        delete_option(self::STOP_OPTION);
    }

    /**
     * Cron sync handler
     */
    public function cron_sync() {
        $this->sync_events(false);
    }

    /**
     * Main sync method
     *
     * @param bool $full_sync If true, syncs all events. If false, only syncs changes since last sync.
     * @return array Result array with success status and statistics
     */
    public function sync_events($full_sync = false) {
        // Check if API is configured
        $api = HSEC_API_Client::instance();
        if (!$api->is_configured()) {
            return [
                'success' => false,
                'error' => __('HubSpot API not configured', 'hubspot-events-connector'),
            ];
        }

        // Try to acquire lock
        if (!$this->acquire_lock()) {
            return [
                'success' => false,
                'error' => __('Sync already in progress', 'hubspot-events-connector'),
            ];
        }

        $result = [
            'success' => true,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'start_time' => time(),
        ];

        try {
            // Determine data source
            $data_source = get_option('hsec_data_source', 'landing_pages');
            $last_sync = get_option('hsec_last_sync_time', null);

            if ($data_source === 'landing_pages') {
                // Fetch from Landing Pages API
                $filter_keyword = get_option('hsec_filter_keyword', 'webinar');
                $language_filter = get_option('hsec_language_filter', '');

                $response = $api->get_all_landing_pages(
                    $filter_keyword ?: null,
                    $language_filter ?: null
                );
                $result['sync_type'] = 'full';
                $result['data_source'] = 'landing_pages';
            } else {
                // Fetch from Marketing Events API
                if ($full_sync || $last_sync === null) {
                    $response = $api->get_all_events();
                    $result['sync_type'] = 'full';
                } else {
                    $response = $api->get_events_since($last_sync);
                    $result['sync_type'] = 'incremental';
                }
                $result['data_source'] = 'marketing_events';
            }

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $events = $response['results'] ?? [];

            // Apply event status filter (for Marketing Events)
            if ($data_source === 'marketing_events') {
                $status_filter = get_option('hsec_event_status_filter', 'all');
                if ($status_filter !== 'all') {
                    $events = $this->filter_events_by_status($events, $status_filter);
                }
            }

            $result['total_fetched'] = count($events);

            // First sync - analyze and create taxonomies
            if ($full_sync || $last_sync === null) {
                $this->setup_taxonomies($events);
            }

            // Clear any previous stop request
            delete_option(self::STOP_OPTION);

            // Process each event
            foreach ($events as $event) {
                // Check if stop was requested
                if ($this->is_stop_requested()) {
                    $result['stopped'] = true;
                    $result['stop_message'] = __('Sync stopped by user request', 'hubspot-events-connector');
                    break;
                }

                $sync_result = $this->sync_single_event($event, $full_sync);

                if ($sync_result['status'] === 'created') {
                    $result['created']++;
                } elseif ($sync_result['status'] === 'updated') {
                    $result['updated']++;
                } elseif ($sync_result['status'] === 'skipped') {
                    $result['skipped']++;
                } elseif ($sync_result['status'] === 'error') {
                    $result['errors'][] = $sync_result['error'];
                }
            }

            // Update last sync time (even if stopped, to avoid re-syncing same events)
            $is_first_sync = ($last_sync === null);
            update_option('hsec_last_sync_time', time());

        } catch (Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }

        // Release lock
        $this->release_lock();

        // Calculate duration
        $result['duration'] = time() - $result['start_time'];
        $result['end_time'] = time();

        // Save result for display
        update_option('hsec_last_sync_result', $result);

        // Set transient for first sync notification
        if ($result['success'] && isset($is_first_sync) && $is_first_sync) {
            $dynamic_taxonomies = get_option('hsec_dynamic_taxonomies', []);
            set_transient('hsec_first_sync_complete', [
                'created' => $result['created'],
                'duration' => $result['duration'],
                'taxonomies_created' => count($dynamic_taxonomies),
            ], 60);
        }

        // Log result
        $this->log_sync_result($result);

        return $result;
    }

    /**
     * Sync a single event
     *
     * @param array $event HubSpot event data
     * @param bool $force_update Force update even if timestamps match (for full sync)
     * @return array Result with status and optional error
     */
    private function sync_single_event($event, $force_update = false) {
        // HubSpot Marketing Events API uses 'objectId' not 'id'
        $hubspot_id = $event['objectId'] ?? $event['id'] ?? null;

        if (!$hubspot_id) {
            return [
                'status' => 'error',
                'error' => __('Event missing HubSpot ID', 'hubspot-events-connector'),
            ];
        }

        $post_type = HSEC_Post_Type::instance();

        // Check if event already exists
        $existing = $post_type->find_by_hubspot_id($hubspot_id);

        // Check if we need to update (skip check if force_update is true)
        if ($existing && !$force_update) {
            // Compare update timestamps
            $wp_last_synced = get_post_meta($existing->ID, '_hsec_last_synced', true);
            $hs_updated = $event['updatedAt'] ?? $event['createdAt'] ?? null;

            if ($hs_updated && $wp_last_synced) {
                $hs_timestamp = strtotime($hs_updated);

                // Skip if HubSpot data hasn't changed since last sync
                if ($hs_timestamp <= $wp_last_synced) {
                    return ['status' => 'skipped'];
                }
            }
        }

        // Create or update the post
        $post_id = $post_type->create_or_update_event($event);

        if (is_wp_error($post_id)) {
            return [
                'status' => 'error',
                'error' => $post_id->get_error_message(),
            ];
        }

        // Assign taxonomy terms
        HSEC_Taxonomy_Manager::instance()->process_event_taxonomies($post_id, $event);

        return [
            'status' => $existing ? 'updated' : 'created',
            'post_id' => $post_id,
        ];
    }

    /**
     * Setup taxonomies based on event data
     */
    private function setup_taxonomies($events) {
        $taxonomy_manager = HSEC_Taxonomy_Manager::instance();

        // Auto-create recommended taxonomies
        $created = $taxonomy_manager->auto_create_suggested_taxonomies($events);

        if (!empty($created)) {
            // Flush rewrite rules to register new taxonomies
            flush_rewrite_rules();
        }

        // Collect event types - Marketing Events API returns fields directly
        $event_types = [];
        foreach ($events as $event) {
            $type = $event['eventType'] ?? null;
            if ($type && !in_array($type, $event_types)) {
                $event_types[] = $type;
            }
        }

        // Save discovered event types
        update_option('hsec_event_types', $event_types);
    }

    /**
     * Filter events by status
     *
     * @param array $events Events from HubSpot
     * @param string $filter Filter type: 'upcoming' or 'past'
     * @return array Filtered events
     */
    private function filter_events_by_status($events, $filter) {
        return array_filter($events, function($event) use ($filter) {
            $status = $event['eventStatus'] ?? '';
            $start_date = $event['startDateTime'] ?? null;

            if ($filter === 'past') {
                // PAST status or event date is in the past
                if ($status === 'PAST') {
                    return true;
                }
                if ($start_date && strtotime($start_date) < time()) {
                    return true;
                }
                return false;
            }

            if ($filter === 'upcoming') {
                // Not PAST and not completed
                if ($status === 'PAST') {
                    return false;
                }
                // Also check by date if status is not set
                if ($start_date && strtotime($start_date) < time()) {
                    return false;
                }
                return true;
            }

            return true; // 'all' - return everything
        });
    }

    /**
     * Log sync result
     */
    private function log_sync_result($result) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[HSEC] Sync completed. Type: %s, Created: %d, Updated: %d, Skipped: %d, Duration: %ds',
                $result['sync_type'] ?? 'unknown',
                $result['created'],
                $result['updated'],
                $result['skipped'],
                $result['duration']
            ));

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    error_log('[HSEC] Sync error: ' . $error);
                }
            }
        }
    }

    /**
     * Get sync statistics
     */
    public function get_sync_stats() {
        $last_sync = get_option('hsec_last_sync_time', null);
        $last_result = get_option('hsec_last_sync_result', []);

        $events_count = wp_count_posts('hs_event');

        return [
            'last_sync' => $last_sync,
            'last_sync_formatted' => $last_sync
                ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync)
                : __('Never', 'hubspot-events-connector'),
            'last_result' => $last_result,
            'total_events' => $events_count->publish ?? 0,
            'draft_events' => $events_count->draft ?? 0,
            'next_scheduled' => wp_next_scheduled('hsec_cron_sync'),
            'sync_interval' => get_option('hsec_sync_interval', 'hourly'),
            'is_running' => $this->is_sync_running(),
        ];
    }

    /**
     * Reschedule cron job
     *
     * @param string $interval New interval (hourly, twicedaily, daily, etc.)
     */
    public function reschedule_cron($interval) {
        // Clear existing schedule
        $timestamp = wp_next_scheduled('hsec_cron_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hsec_cron_sync');
        }

        // Schedule new
        if ($interval !== 'manual') {
            wp_schedule_event(time(), $interval, 'hsec_cron_sync');
        }

        update_option('hsec_sync_interval', $interval);
    }

    /**
     * Delete all synced events
     *
     * Use with caution!
     */
    public function delete_all_events() {
        $events = get_posts([
            'post_type' => 'hs_event',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
        ]);

        foreach ($events as $event_id) {
            wp_delete_post($event_id, true);
        }

        // Reset sync time
        delete_option('hsec_last_sync_time');
        delete_option('hsec_last_sync_result');

        return count($events);
    }

    /**
     * Sync a specific event by HubSpot ID
     *
     * @param string $hubspot_id HubSpot event object ID
     * @return array Result
     */
    public function sync_single_by_id($hubspot_id) {
        $api = HSEC_API_Client::instance();
        $event = $api->get_event($hubspot_id);

        if (is_wp_error($event)) {
            return [
                'success' => false,
                'error' => $event->get_error_message(),
            ];
        }

        $result = $this->sync_single_event($event);

        return [
            'success' => $result['status'] !== 'error',
            'status' => $result['status'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Get events that need attention (no HubSpot ID, failed sync, etc.)
     */
    public function get_events_needing_attention() {
        global $wpdb;

        // Events without HubSpot ID
        $orphaned = get_posts([
            'post_type' => 'hs_event',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_hsec_hubspot_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_hsec_hubspot_id',
                    'value' => '',
                ],
            ],
        ]);

        // Events not synced in over 7 days
        $stale_threshold = time() - (7 * DAY_IN_SECONDS);

        $stale = get_posts([
            'post_type' => 'hs_event',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_hsec_last_synced',
                    'value' => $stale_threshold,
                    'compare' => '<',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        return [
            'orphaned' => $orphaned,
            'stale' => $stale,
        ];
    }
}
