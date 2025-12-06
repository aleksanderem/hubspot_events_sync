<?php
/**
 * HubSpot API Client
 *
 * Handles all communication with HubSpot APIs (Landing Pages & Marketing Events)
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_API_Client {

    private static $instance = null;

    /**
     * HubSpot API base URL
     */
    const API_BASE = 'https://api.hubapi.com';

    /**
     * Marketing Events API endpoint
     */
    const EVENTS_ENDPOINT = '/marketing/v3/marketing-events';

    /**
     * Landing Pages API endpoint
     */
    const LANDING_PAGES_ENDPOINT = '/cms/v3/pages/landing-pages';

    /**
     * Request timeout in seconds
     */
    const TIMEOUT = 30;

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
        // Nothing to initialize
    }

    /**
     * Get API token from settings
     */
    private function get_api_token() {
        return get_option('hsec_api_token', '');
    }

    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->get_api_token());
    }

    /**
     * Make API request to HubSpot
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array|null $body Request body
     * @param array $query_params Query parameters
     * @param string|null $override_token Optional token to use instead of saved one
     */
    private function request($endpoint, $method = 'GET', $body = null, $query_params = [], $override_token = null) {
        $token = $override_token ?? $this->get_api_token();

        if (empty($token)) {
            return new WP_Error('no_token', __('HubSpot API token not configured', 'hubspot-events-connector'));
        }

        $url = self::API_BASE . $endpoint;

        // Add query parameters
        if (!empty($query_params)) {
            $url = add_query_arg($query_params, $url);
        }

        $args = [
            'method' => $method,
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Handle errors
        if ($status_code >= 400) {
            $error_message = $data['message'] ?? __('Unknown API error', 'hubspot-events-connector');

            // Log error for debugging
            error_log(sprintf(
                '[HSEC] HubSpot API Error: %s (Status: %d, Endpoint: %s)',
                $error_message,
                $status_code,
                $endpoint
            ));

            return new WP_Error(
                'api_error',
                $error_message,
                ['status' => $status_code, 'response' => $data]
            );
        }

        return $data;
    }

    /**
     * Test API connection
     *
     * @param string|null $test_token Optional token to test (before saving)
     */
    public function test_connection($test_token = null) {
        $token = $test_token ?? $this->get_api_token();

        if (empty($token)) {
            return [
                'success' => false,
                'error' => __('API token not configured', 'hubspot-events-connector'),
            ];
        }

        // Try Landing Pages first (primary source)
        $response = $this->request(self::LANDING_PAGES_ENDPOINT, 'GET', null, ['limit' => 1], $test_token);

        if (is_wp_error($response)) {
            // Fall back to Marketing Events
            $response = $this->request(self::EVENTS_ENDPOINT, 'GET', null, ['limit' => 1], $test_token);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message(),
                ];
            }

            return [
                'success' => true,
                'source' => 'marketing_events',
                'events_count' => $response['total'] ?? count($response['results'] ?? []),
            ];
        }

        return [
            'success' => true,
            'source' => 'landing_pages',
            'events_count' => $response['total'] ?? count($response['results'] ?? []),
        ];
    }

    // =========================================================================
    // LANDING PAGES API (Primary source for webinars)
    // =========================================================================

    /**
     * Get landing pages
     *
     * @param array $params Optional query parameters
     * @return array|WP_Error
     */
    public function get_landing_pages($params = []) {
        $default_params = [
            'limit' => 100,
            // Request all needed properties including featuredImage
            'properties' => implode(',', [
                'id',
                'name',
                'slug',
                'state',
                'domain',
                'url',
                'language',
                'htmlTitle',
                'metaDescription',
                'featuredImage',
                'featuredImageAltText',
                'layoutSections',
                'createdAt',
                'updatedAt',
                'publishDate',
                'subcategory',
            ]),
        ];

        $query_params = array_merge($default_params, $params);

        return $this->request(self::LANDING_PAGES_ENDPOINT, 'GET', null, $query_params);
    }

    /**
     * Get all landing pages with pagination
     *
     * @param string|null $filter_keyword Optional keyword to filter by (e.g., 'webinar')
     * @param string|null $language Optional language filter (e.g., 'pl')
     * @return array|WP_Error
     */
    public function get_all_landing_pages($filter_keyword = null, $language = null) {
        $all_pages = [];
        $after = null;
        $page = 0;
        $max_pages = 50; // Safety limit

        do {
            $params = ['limit' => 100];

            if ($after !== null) {
                $params['after'] = $after;
            }

            $response = $this->get_landing_pages($params);

            if (is_wp_error($response)) {
                return $response;
            }

            $pages = $response['results'] ?? [];

            // Filter pages if needed
            foreach ($pages as $page_data) {
                $include = true;

                // Filter by keyword in URL, name or title
                if ($filter_keyword) {
                    $searchable = strtolower(
                        ($page_data['url'] ?? '') . ' ' .
                        ($page_data['name'] ?? '') . ' ' .
                        ($page_data['htmlTitle'] ?? '') . ' ' .
                        ($page_data['slug'] ?? '')
                    );
                    $include = strpos($searchable, strtolower($filter_keyword)) !== false;
                }

                // Filter by language
                if ($include && $language) {
                    $page_lang = $page_data['language'] ?? '';
                    // Support comma-separated list of languages
                    $allowed_languages = array_map('trim', explode(',', $language));
                    $allowed_languages = array_filter($allowed_languages); // Remove empty values

                    if (!empty($allowed_languages)) {
                        $include = in_array($page_lang, $allowed_languages, true);

                        // Debug log
                        error_log(sprintf(
                            '[HSEC] Language filter: page_lang="%s", allowed=%s, include=%s',
                            $page_lang,
                            implode(',', $allowed_languages),
                            $include ? 'yes' : 'no'
                        ));
                    }
                }

                if ($include) {
                    // Parse event data from layout
                    $page_data = $this->enrich_landing_page_data($page_data);
                    $all_pages[] = $page_data;
                }
            }

            // Check for more pages
            $paging = $response['paging'] ?? null;
            $after = $paging['next']['after'] ?? null;

            $page++;

            // Small delay to avoid rate limiting
            if ($after !== null) {
                usleep(100000); // 100ms
            }

        } while ($after !== null && $page < $max_pages);

        return [
            'results' => $all_pages,
            'total' => count($all_pages),
        ];
    }

    /**
     * Get a single landing page by ID
     *
     * @param string $page_id HubSpot page ID
     * @return array|WP_Error
     */
    public function get_landing_page($page_id) {
        $response = $this->request(self::LANDING_PAGES_ENDPOINT . '/' . $page_id);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->enrich_landing_page_data($response);
    }

    /**
     * Enrich landing page data by parsing event info from layoutSections
     *
     * @param array $page_data Raw page data from API
     * @return array Enriched page data
     */
    private function enrich_landing_page_data($page_data) {
        $layout = $page_data['layoutSections'] ?? [];
        $layout_json = json_encode($layout);

        // Extract event date and time from layout
        $event_datetime = $this->extract_event_datetime($layout_json);
        if ($event_datetime) {
            $page_data['_event_datetime'] = $event_datetime['datetime'];
            $page_data['_event_date'] = $event_datetime['date'];
            $page_data['_event_time'] = $event_datetime['time'];
            $page_data['_event_location'] = $event_datetime['location'] ?? '';
        }

        // Try to extract description from layout
        $description = $this->extract_description($layout_json);
        if ($description) {
            $page_data['_event_description'] = $description;
        }

        return $page_data;
    }

    /**
     * Extract event datetime from layout JSON
     *
     * Looks for patterns like "12/08/2025 godz. 10:00" in module headers
     *
     * @param string $layout_json JSON string of layoutSections
     * @return array|null
     */
    private function extract_event_datetime($layout_json) {
        // Pattern for date: DD/MM/YYYY or DD.MM.YYYY or DD-MM-YYYY
        // With optional time: godz. HH:MM or HH:MM
        $patterns = [
            // Polish format: 12/08/2025 godz. 10:00
            '/(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})\s*(?:godz\.\s*)?(\d{1,2}:\d{2})?/',
            // ISO format: 2025-08-12
            '/(\d{4})-(\d{2})-(\d{2})(?:T|\s)(\d{2}:\d{2})?/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $layout_json, $matches)) {
                // Determine format and build date
                if (strlen($matches[1]) === 4) {
                    // ISO format: YYYY-MM-DD
                    $year = $matches[1];
                    $month = $matches[2];
                    $day = $matches[3];
                } else {
                    // European format: DD/MM/YYYY
                    $day = $matches[1];
                    $month = $matches[2];
                    $year = $matches[3];
                }

                $time = $matches[4] ?? '00:00';
                $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);

                // Try to find location (usually in subheader)
                $location = '';
                if (preg_match('/"subheader"\s*:\s*"([^"]+)"/', $layout_json, $loc_match)) {
                    $location = $loc_match[1];
                }

                return [
                    'datetime' => $date_str . ' ' . $time,
                    'date' => $date_str,
                    'time' => $time,
                    'location' => $location,
                ];
            }
        }

        return null;
    }

    /**
     * Extract description from layout
     *
     * @param string $layout_json JSON string of layoutSections
     * @return string|null
     */
    private function extract_description($layout_json) {
        // Look for content in rich text modules
        if (preg_match('/"html"\s*:\s*"([^"]{50,500})"/', $layout_json, $match)) {
            $html = $match[1];
            // Decode JSON escapes and strip tags
            $html = json_decode('"' . $html . '"');
            return wp_strip_all_tags($html);
        }

        return null;
    }

    // =========================================================================
    // MARKETING EVENTS API (Legacy/secondary source)
    // =========================================================================

    /**
     * Get marketing events
     *
     * @param array $params Optional query parameters
     * @return array|WP_Error
     */
    public function get_events($params = []) {
        $default_params = [
            'limit' => 100,
        ];

        $query_params = array_merge($default_params, $params);

        return $this->request(self::EVENTS_ENDPOINT, 'GET', null, $query_params);
    }

    /**
     * Get all marketing events with pagination
     *
     * @return array|WP_Error
     */
    public function get_all_events() {
        $all_events = [];
        $after = null;
        $page = 0;
        $max_pages = 100; // Safety limit

        do {
            $params = ['limit' => 100];

            if ($after !== null) {
                $params['after'] = $after;
            }

            $response = $this->get_events($params);

            if (is_wp_error($response)) {
                return $response;
            }

            $events = $response['results'] ?? [];
            $all_events = array_merge($all_events, $events);

            // Check for more pages
            $paging = $response['paging'] ?? null;
            $after = $paging['next']['after'] ?? null;

            $page++;

            // Small delay to avoid rate limiting
            if ($after !== null) {
                usleep(100000); // 100ms
            }

        } while ($after !== null && $page < $max_pages);

        return [
            'results' => $all_events,
            'total' => count($all_events),
        ];
    }

    /**
     * Get events updated since a specific time
     *
     * @param int $timestamp Unix timestamp
     * @return array|WP_Error
     */
    public function get_events_since($timestamp) {
        $all_events = $this->get_all_events();

        if (is_wp_error($all_events)) {
            return $all_events;
        }

        $filtered_events = [];

        foreach ($all_events['results'] as $event) {
            $updated_at = $event['updatedAt'] ?? $event['createdAt'] ?? null;

            if ($updated_at) {
                $event_timestamp = strtotime($updated_at);

                if ($event_timestamp >= $timestamp) {
                    $filtered_events[] = $event;
                }
            } else {
                $filtered_events[] = $event;
            }
        }

        return [
            'results' => $filtered_events,
            'total' => count($filtered_events),
            'filtered_from' => $all_events['total'],
        ];
    }

    /**
     * Get single event by ID
     *
     * @param string $event_id HubSpot event object ID
     * @return array|WP_Error
     */
    public function get_event($event_id) {
        return $this->request(self::EVENTS_ENDPOINT . '/' . $event_id);
    }

    /**
     * Get event attendance data
     *
     * @param string $event_id HubSpot event object ID
     * @return array|WP_Error
     */
    public function get_event_attendance($event_id) {
        $event = $this->get_event($event_id);

        if (is_wp_error($event)) {
            return $event;
        }

        // Marketing Events API returns fields directly, not in 'properties'
        return [
            'registered' => $event['registrants'] ?? 0,
            'attended' => $event['attendees'] ?? 0,
            'cancelled' => $event['cancellations'] ?? 0,
        ];
    }

    /**
     * Get available event types from existing events
     */
    public function discover_event_types() {
        $events = $this->get_all_events();

        if (is_wp_error($events)) {
            return $events;
        }

        $types = [];

        // Marketing Events API returns fields directly, not in 'properties'
        foreach ($events['results'] as $event) {
            $type = $event['eventType'] ?? null;
            if ($type && !in_array($type, $types)) {
                $types[] = $type;
            }
        }

        sort($types);

        return $types;
    }

    /**
     * Discover custom properties used in events
     */
    public function discover_custom_properties() {
        $events = $this->get_all_events();

        if (is_wp_error($events)) {
            return $events;
        }

        $properties = [];

        // Marketing Events API returns customProperties directly on event
        foreach ($events['results'] as $event) {
            $custom_props = $event['customProperties'] ?? [];

            foreach ($custom_props as $prop) {
                $name = $prop['name'] ?? null;
                $value = $prop['value'] ?? null;

                if ($name) {
                    if (!isset($properties[$name])) {
                        $properties[$name] = [
                            'name' => $name,
                            'values' => [],
                            'count' => 0,
                        ];
                    }

                    $properties[$name]['count']++;

                    if ($value && !in_array($value, $properties[$name]['values'])) {
                        $properties[$name]['values'][] = $value;
                    }
                }
            }
        }

        return $properties;
    }

    /**
     * Check API rate limit status
     */
    public function get_rate_limit_status() {
        $token = $this->get_api_token();

        if (empty($token)) {
            return null;
        }

        $url = self::API_BASE . self::LANDING_PAGES_ENDPOINT . '?limit=1';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $headers = wp_remote_retrieve_headers($response);

        return [
            'limit' => $headers['x-hubspot-ratelimit-daily'] ?? null,
            'remaining' => $headers['x-hubspot-ratelimit-daily-remaining'] ?? null,
            'interval_limit' => $headers['x-hubspot-ratelimit-secondly'] ?? null,
            'interval_remaining' => $headers['x-hubspot-ratelimit-secondly-remaining'] ?? null,
        ];
    }

    /**
     * Validate API token
     *
     * @param string $token Token to validate
     * @return bool
     */
    public function validate_token($token) {
        if (empty($token)) {
            return false;
        }

        $result = $this->test_connection($token);

        return $result['success'];
    }
}
