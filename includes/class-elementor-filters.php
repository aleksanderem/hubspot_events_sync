<?php
/**
 * Elementor Loop Filters for HubSpot Events
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

/**
 * Main class for HubSpot Events Elementor Filters
 */
class HSEC_Elementor_Filters {

    private static $instance = null;

    const QUERY_VAR_LANGUAGE = 'hsec_lang';
    const QUERY_VAR_DATE_FILTER = 'hsec_date_filter';
    const QUERY_VAR_DATE_MONTH = 'hsec_month';
    const QUERY_VAR_ONDEMAND = 'hsec_ondemand';

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
        // Register widgets
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // Register widget category
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);

        // Enqueue scripts - frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Enqueue scripts - editor preview
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_scripts']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);

        // Hook into Elementor query args - this is the key hook!
        add_filter('elementor/query/query_args', [$this, 'filter_elementor_query_args'], 10, 2);

        // Also hook for main queries on hs_event archives
        add_action('pre_get_posts', [$this, 'modify_main_query']);

        // Register query vars for WordPress
        add_filter('query_vars', [$this, 'register_query_vars']);

        // AJAX endpoints
        add_action('wp_ajax_hsec_filter_events', [$this, 'ajax_filter_events']);
        add_action('wp_ajax_nopriv_hsec_filter_events', [$this, 'ajax_filter_events']);

        // Debug: Monitor actual queries
        add_action('pre_get_posts', [$this, 'debug_query'], 999);
    }

    /**
     * Debug hook to monitor queries
     */
    public function debug_query($query) {
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }
        if ($query->get('post_type') !== 'hs_event') {
            return;
        }

        $debug_file = WP_CONTENT_DIR . '/hsec-debug.log';
        $log = date('Y-m-d H:i:s') . " pre_get_posts (priority 999) for hs_event:\n";
        $log .= "  post__not_in: " . ($query->get('post__not_in') ? implode(', ', $query->get('post__not_in')) : 'NOT SET') . "\n";
        $log .= "  is_main_query: " . ($query->is_main_query() ? 'yes' : 'no') . "\n";
        $log .= "  posts_per_page: " . $query->get('posts_per_page') . "\n";
        $log .= "  backtrace: " . $this->get_short_backtrace() . "\n";
        file_put_contents($debug_file, $log, FILE_APPEND);
    }

    /**
     * Get shortened backtrace for debugging
     */
    private function get_short_backtrace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $parts = [];
        foreach ($trace as $i => $frame) {
            if ($i < 3) continue; // Skip internal frames
            if (isset($frame['file'])) {
                $file = basename($frame['file']);
                $line = $frame['line'] ?? '?';
                $parts[] = "$file:$line";
            }
            if (count($parts) >= 5) break;
        }
        return implode(' -> ', $parts);
    }

    /**
     * Register query variables
     */
    public function register_query_vars($vars) {
        $vars[] = self::QUERY_VAR_LANGUAGE;
        $vars[] = self::QUERY_VAR_DATE_FILTER;
        $vars[] = self::QUERY_VAR_DATE_MONTH;
        $vars[] = self::QUERY_VAR_ONDEMAND;
        return $vars;
    }

    /**
     * Register widget category
     */
    public function register_category($elements_manager) {
        $elements_manager->add_category(
            'hsec-filters',
            [
                'title' => __('HubSpot Events Filters', 'hsec-elementor-filters'),
                'icon' => 'eicon-filter',
            ]
        );
    }

    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        require_once HSEC_PLUGIN_DIR . 'includes/hsec-elementor-filters/widget-language-filter.php';
        require_once HSEC_PLUGIN_DIR . 'includes/hsec-elementor-filters/widget-date-filter.php';
        require_once HSEC_PLUGIN_DIR . 'includes/hsec-elementor-filters/widget-ondemand-toggle.php';

        $widgets_manager->register(new HSEC_Language_Filter_Widget());
        $widgets_manager->register(new HSEC_Date_Filter_Widget());
        $widgets_manager->register(new HSEC_OnDemand_Toggle_Widget());
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'hsec-elementor-filters',
            $this->get_assets_url() . 'style.css',
            [],
            '2.0.0'
        );

        wp_enqueue_script(
            'hsec-elementor-filters',
            $this->get_assets_url() . 'script.js',
            ['jquery'],
            '2.0.0',
            true
        );

        // Get the archive template ID for hs_event
        $archive_template_id = $this->get_hs_event_archive_template_id();

        wp_localize_script('hsec-elementor-filters', 'hsecFilters', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hsec_filter_nonce'),
            'archiveTemplateId' => $archive_template_id,
            'queryVars' => [
                'language' => self::QUERY_VAR_LANGUAGE,
                'dateFilter' => self::QUERY_VAR_DATE_FILTER,
                'dateMonth' => self::QUERY_VAR_DATE_MONTH,
                'ondemand' => self::QUERY_VAR_ONDEMAND,
            ],
        ]);
    }

    /**
     * Get assets URL
     */
    private function get_assets_url() {
        return HSEC_PLUGIN_URL . 'includes/hsec-elementor-filters/assets/';
    }

    /**
     * Get the Elementor archive template ID for hs_event post type
     */
    private function get_hs_event_archive_template_id() {
        global $wpdb;

        // Cache the result
        static $template_id = null;
        if ($template_id !== null) {
            return $template_id;
        }

        // Find archive template with condition for hs_event
        $template_id = $wpdb->get_var(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'elementor_library'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_elementor_conditions'
             AND pm.meta_value LIKE '%hs_event%'
             LIMIT 1"
        );

        return $template_id ? (int) $template_id : 0;
    }

    /**
     * Enqueue editor styles
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'hsec-elementor-filters-editor',
            $this->get_assets_url() . 'style.css',
            [],
            '2.0.0'
        );
    }

    /**
     * Filter Elementor query args - works for ALL Elementor queries including Loop Grid
     */
    public function filter_elementor_query_args($query_args, $widget) {
        // Only apply to hs_event post type queries
        $post_type = isset($query_args['post_type']) ? $query_args['post_type'] : '';

        if ($post_type !== 'hs_event') {
            return $query_args;
        }

        $result = $this->apply_filters_to_args($query_args);

        // Debug: log the actual returned query args
        $debug_file = WP_CONTENT_DIR . '/hsec-debug.log';
        $log = date('Y-m-d H:i:s') . " filter_elementor_query_args returning:\n";
        $log .= "  post__not_in: " . (isset($result['post__not_in']) ? implode(', ', $result['post__not_in']) : 'NOT SET') . "\n";
        file_put_contents($debug_file, $log, FILE_APPEND);

        return $result;
    }

    /**
     * Modify main query for hs_event archives
     */
    public function modify_main_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'hs_event') {
            return;
        }

        $meta_query = $query->get('meta_query') ?: [];
        $modified_args = $this->apply_filters_to_args(['meta_query' => $meta_query]);

        if (!empty($modified_args['meta_query'])) {
            $query->set('meta_query', $modified_args['meta_query']);
        }

        if (!empty($modified_args['meta_key'])) {
            $query->set('meta_key', $modified_args['meta_key']);
        }

        if (!empty($modified_args['orderby'])) {
            $query->set('orderby', $modified_args['orderby']);
        }

        if (!empty($modified_args['order'])) {
            $query->set('order', $modified_args['order']);
        }
    }

    /**
     * Apply all filters to query args array
     */
    public function apply_filters_to_args($query_args) {
        // Only show published events
        $query_args['post_status'] = 'publish';

        // Apply display filters (exclude Thank You pages, require date, etc.)
        $query_args = $this->apply_display_filters($query_args);

        $meta_query = isset($query_args['meta_query']) ? $query_args['meta_query'] : [];

        // Language filter
        $language = $this->get_filter_value(self::QUERY_VAR_LANGUAGE);
        if ($language && $language !== 'all') {
            $meta_query[] = [
                'key' => '_hsec_language',
                'value' => sanitize_text_field($language),
                'compare' => '=',
            ];
        }

        // Date filter
        $date_filter = $this->get_filter_value(self::QUERY_VAR_DATE_FILTER);
        $show_ondemand = $this->get_filter_value(self::QUERY_VAR_ONDEMAND);
        $include_ondemand = ($show_ondemand === '1' || $show_ondemand === 'yes');

        // On-demand condition (events without date)
        $ondemand_condition = [
            'relation' => 'OR',
            [
                'key' => '_hsec_start_datetime',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_hsec_start_datetime',
                'value' => '',
                'compare' => '=',
            ],
        ];

        if ($date_filter === 'past') {
            // Past events - have date and date < now
            $past_condition = [
                'relation' => 'AND',
                [
                    'key' => '_hsec_start_datetime',
                    'value' => '',
                    'compare' => '!=',
                ],
                [
                    'key' => '_hsec_start_datetime',
                    'value' => current_time('mysql'),
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
            ];

            if ($include_ondemand) {
                // Past events OR on-demand events
                $meta_query[] = [
                    'relation' => 'OR',
                    $past_condition,
                    $ondemand_condition,
                ];
            } else {
                $meta_query[] = $past_condition;
            }
        } elseif ($date_filter === 'upcoming') {
            // Upcoming events - have date and date >= now
            $upcoming_condition = [
                'relation' => 'AND',
                [
                    'key' => '_hsec_start_datetime',
                    'value' => '',
                    'compare' => '!=',
                ],
                [
                    'key' => '_hsec_start_datetime',
                    'value' => current_time('mysql'),
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ];

            if ($include_ondemand) {
                // Upcoming events OR on-demand events
                $meta_query[] = [
                    'relation' => 'OR',
                    $upcoming_condition,
                    $ondemand_condition,
                ];
            } else {
                $meta_query[] = $upcoming_condition;
            }
        } elseif ($date_filter === 'month') {
            // Specific month
            $month = $this->get_filter_value(self::QUERY_VAR_DATE_MONTH);
            if ($month && preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
                $year = $matches[1];
                $month_num = $matches[2];
                $start_date = "{$year}-{$month_num}-01 00:00:00";
                $end_date = date('Y-m-t 23:59:59', strtotime($start_date));

                $month_condition = [
                    'relation' => 'AND',
                    [
                        'key' => '_hsec_start_datetime',
                        'value' => $start_date,
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                    [
                        'key' => '_hsec_start_datetime',
                        'value' => $end_date,
                        'compare' => '<=',
                        'type' => 'DATETIME',
                    ],
                ];

                if ($include_ondemand) {
                    // Month events OR on-demand events
                    $meta_query[] = [
                        'relation' => 'OR',
                        $month_condition,
                        $ondemand_condition,
                    ];
                } else {
                    $meta_query[] = $month_condition;
                }
            }
        } else {
            // No date filter - just handle on-demand toggle
            if ($include_ondemand) {
                // Show only on-demand events
                $meta_query[] = $ondemand_condition;
            } elseif ($show_ondemand === '0' || $show_ondemand === 'no') {
                // Exclude on-demand (only events with dates)
                $meta_query[] = [
                    'key' => '_hsec_start_datetime',
                    'value' => '',
                    'compare' => '!=',
                ];
            }
        }

        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $query_args['meta_query'] = $meta_query;
        }

        // Order by date for events
        if (!isset($query_args['orderby']) || $query_args['orderby'] === 'date') {
            $query_args['meta_key'] = '_hsec_start_datetime';
            $query_args['orderby'] = 'meta_value';
            $query_args['order'] = $date_filter === 'past' ? 'DESC' : 'ASC';
        }

        return $query_args;
    }

    /**
     * Get filter value from URL or POST
     */
    private function get_filter_value($key) {
        if (isset($_GET[$key])) {
            return sanitize_text_field($_GET[$key]);
        }
        if (isset($_POST[$key])) {
            return sanitize_text_field($_POST[$key]);
        }

        $query_var = get_query_var($key, '');
        if ($query_var) {
            return $query_var;
        }

        // Use default language if no filter specified
        if ($key === self::QUERY_VAR_LANGUAGE) {
            return $this->get_default_language();
        }

        return '';
    }

    /**
     * Get default language from widget settings or option
     */
    private function get_default_language() {
        // Default to Polish
        return apply_filters('hsec_default_language', 'pl');
    }

    /**
     * Apply display filters (exclude Thank You pages, require date, etc.)
     */
    private function apply_display_filters($query_args) {
        // Skip in WP CLI
        if (defined('WP_CLI') && WP_CLI) {
            return $query_args;
        }

        // Skip in admin screens (but allow AJAX requests for frontend filtering)
        if (is_admin() && !wp_doing_ajax()) {
            return $query_args;
        }

        // Debug logging
        $debug_file = WP_CONTENT_DIR . '/hsec-debug.log';
        $log = date('Y-m-d H:i:s') . " apply_display_filters called\n";
        $log .= "  hsec_exclude_thank_you: " . var_export(get_option('hsec_exclude_thank_you', false), true) . "\n";

        $meta_query = $query_args['meta_query'] ?? [];

        // Require event date if enabled
        if (get_option('hsec_require_event_date', false)) {
            $meta_query[] = [
                'key' => '_hsec_start_datetime',
                'value' => '',
                'compare' => '!=',
            ];
        }

        // Require event URL if enabled
        if (get_option('hsec_require_event_url', false)) {
            $meta_query[] = [
                'key' => '_hsec_event_url',
                'value' => '',
                'compare' => '!=',
            ];
        }

        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        // Exclude posts by title patterns
        $excluded_ids = $this->get_excluded_event_ids();
        $log .= "  excluded_ids count: " . count($excluded_ids) . "\n";
        $log .= "  excluded_ids: " . implode(', ', $excluded_ids) . "\n";

        if (!empty($excluded_ids)) {
            $existing_exclude = $query_args['post__not_in'] ?? [];
            $query_args['post__not_in'] = array_unique(array_merge($existing_exclude, $excluded_ids));
            $log .= "  post__not_in set to: " . implode(', ', $query_args['post__not_in']) . "\n";
        }

        file_put_contents($debug_file, $log, FILE_APPEND);
        return $query_args;
    }

    /**
     * Get event IDs to exclude based on title patterns
     */
    private function get_excluded_event_ids() {
        // Check if exclusion is enabled
        if (!get_option('hsec_exclude_thank_you', false)) {
            $custom_patterns = get_option('hsec_exclude_title_patterns', '');
            if (empty($custom_patterns)) {
                return [];
            }
        }

        // Cache for performance
        $cache_key = 'hsec_excluded_event_ids';
        $excluded = wp_cache_get($cache_key, 'hsec');
        if ($excluded !== false) {
            return $excluded;
        }

        global $wpdb;

        $patterns = [];

        // Thank you patterns (multilingual)
        if (get_option('hsec_exclude_thank_you', false)) {
            $patterns = [
                // English
                'thank you',
                'thanks for',
                'you have registered',
                'registered for webinar',
                // Polish
                'dziękujemy',
                'dziekujemy',
                'za rejestrację',
                'za rejestracje',
                // Hungarian
                'köszönjük',
                'koszonjuk',
                // Romanian
                'mulțumim',
                'multumim',
                'vă mulțumim',
                // Czech
                'děkujeme',
                'dekujeme',
                'zaregistrovali',
                // Slovak
                'ďakujeme',
                'dakujeme',
            ];
        }

        // Custom patterns
        $custom = get_option('hsec_exclude_title_patterns', '');
        if (!empty($custom)) {
            $custom_patterns = array_filter(array_map('trim', explode("\n", $custom)));
            $patterns = array_merge($patterns, $custom_patterns);
        }

        if (empty($patterns)) {
            return [];
        }

        // Build SQL LIKE conditions
        $like_conditions = [];
        foreach ($patterns as $pattern) {
            $like_conditions[] = $wpdb->prepare(
                "LOWER(post_title) LIKE %s",
                '%' . $wpdb->esc_like(strtolower($pattern)) . '%'
            );
        }

        $sql = "
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'hs_event'
            AND post_status = 'publish'
            AND (" . implode(' OR ', $like_conditions) . ")
        ";

        $excluded = $wpdb->get_col($sql);
        $excluded = array_map('intval', $excluded);

        // Cache for 5 minutes
        wp_cache_set($cache_key, $excluded, 'hsec', 300);

        return $excluded;
    }

    /**
     * AJAX handler for filtering events
     */
    public function ajax_filter_events() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'hsec_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');

        if (!$post_id || !$widget_id) {
            wp_send_json_error('Missing post_id or widget_id');
        }

        // Set filter values to GET so our filter picks them up
        $_GET[self::QUERY_VAR_LANGUAGE] = sanitize_text_field($_POST[self::QUERY_VAR_LANGUAGE] ?? '');
        $_GET[self::QUERY_VAR_DATE_FILTER] = sanitize_text_field($_POST[self::QUERY_VAR_DATE_FILTER] ?? '');
        $_GET[self::QUERY_VAR_DATE_MONTH] = sanitize_text_field($_POST[self::QUERY_VAR_DATE_MONTH] ?? '');
        $_GET[self::QUERY_VAR_ONDEMAND] = sanitize_text_field($_POST[self::QUERY_VAR_ONDEMAND] ?? '');

        // Get the document
        $document = \Elementor\Plugin::$instance->documents->get($post_id);
        if (!$document) {
            wp_send_json_error('Document not found for post_id: ' . $post_id);
        }

        // Find widget data
        $elements_data = $document->get_elements_data();
        $widget_data = $this->find_widget_recursive($elements_data, $widget_id);

        // If not found, try to search in all Elementor templates used on this page
        if (!$widget_data) {
            // Get all template IDs from the page
            $template_ids = $this->get_template_ids_from_elements($elements_data);

            foreach ($template_ids as $template_id) {
                $template_doc = \Elementor\Plugin::$instance->documents->get($template_id);
                if ($template_doc) {
                    $widget_data = $this->find_widget_recursive($template_doc->get_elements_data(), $widget_id);
                    if ($widget_data) {
                        break;
                    }
                }
            }
        }

        if (!$widget_data) {
            wp_send_json_error('Widget not found. ID: ' . $widget_id . ', Post: ' . $post_id);
        }

        // Create widget instance
        $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance($widget_data);
        if (!$widget) {
            wp_send_json_error('Could not create widget');
        }

        // Render the widget
        ob_start();
        $widget->print_element();
        $full_html = ob_get_clean();

        // Extract just the loop container content
        if (preg_match('/<div[^>]*class="[^"]*elementor-loop-container[^"]*"[^>]*>(.*)<\/div>\s*$/s', $full_html, $matches)) {
            wp_send_json_success([
                'html' => trim($matches[1]),
            ]);
        }

        // If regex failed, return full widget HTML (will replace entire widget)
        wp_send_json_success([
            'html' => $full_html,
            'full_widget' => true,
        ]);
    }

    /**
     * Find widget recursively in elements data
     */
    private function find_widget_recursive($elements, $widget_id) {
        // Convert to string for comparison (Elementor uses string IDs)
        $widget_id = (string) $widget_id;

        foreach ($elements as $element) {
            if (isset($element['id']) && (string) $element['id'] === $widget_id) {
                return $element;
            }

            if (!empty($element['elements'])) {
                $found = $this->find_widget_recursive($element['elements'], $widget_id);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Get template IDs from elements (for nested templates)
     */
    private function get_template_ids_from_elements($elements) {
        $template_ids = [];

        foreach ($elements as $element) {
            // Check for template widget
            if (isset($element['widgetType']) && $element['widgetType'] === 'template') {
                if (!empty($element['settings']['template_id'])) {
                    $template_ids[] = $element['settings']['template_id'];
                }
            }

            // Check for theme builder locations
            if (isset($element['elType']) && $element['elType'] === 'widget') {
                if (!empty($element['settings']['template_id'])) {
                    $template_ids[] = $element['settings']['template_id'];
                }
            }

            // Recurse into nested elements
            if (!empty($element['elements'])) {
                $template_ids = array_merge($template_ids, $this->get_template_ids_from_elements($element['elements']));
            }
        }

        return array_unique($template_ids);
    }

    /**
     * Get available languages from events
     */
    public static function get_available_languages() {
        global $wpdb;

        $languages = $wpdb->get_col(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_hsec_language'
             AND pm.meta_value <> ''
             AND p.post_type = 'hs_event'
             AND p.post_status = 'publish'
             ORDER BY meta_value ASC"
        );

        return $languages ?: [];
    }

    /**
     * Get available months with events
     */
    public static function get_available_months() {
        global $wpdb;

        $months = $wpdb->get_col(
            "SELECT DISTINCT DATE_FORMAT(meta_value, '%Y-%m') as month
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_hsec_start_datetime'
             AND pm.meta_value <> ''
             AND p.post_type = 'hs_event'
             AND p.post_status = 'publish'
             ORDER BY month DESC"
        );

        return $months ?: [];
    }

    /**
     * Language data
     */
    public static function get_language_data($code) {
        $languages = [
            'pl' => ['flag' => '🇵🇱', 'name' => 'PL', 'full' => 'Polski'],
            'pl-pl' => ['flag' => '🇵🇱', 'name' => 'PL', 'full' => 'Polski'],
            'en' => ['flag' => '🇬🇧', 'name' => 'EN', 'full' => 'English'],
            'hu' => ['flag' => '🇭🇺', 'name' => 'HU', 'full' => 'Magyar'],
            'de' => ['flag' => '🇩🇪', 'name' => 'DE', 'full' => 'Deutsch'],
            'cs' => ['flag' => '🇨🇿', 'name' => 'CZ', 'full' => 'Čeština'],
            'sk' => ['flag' => '🇸🇰', 'name' => 'SK', 'full' => 'Slovenčina'],
            'ro' => ['flag' => '🇷🇴', 'name' => 'RO', 'full' => 'Română'],
            'lt' => ['flag' => '🇱🇹', 'name' => 'LT', 'full' => 'Lietuvių'],
            'lv' => ['flag' => '🇱🇻', 'name' => 'LV', 'full' => 'Latviešu'],
            'et' => ['flag' => '🇪🇪', 'name' => 'EE', 'full' => 'Eesti'],
            'bg' => ['flag' => '🇧🇬', 'name' => 'BG', 'full' => 'Български'],
            'hr' => ['flag' => '🇭🇷', 'name' => 'HR', 'full' => 'Hrvatski'],
            'sl' => ['flag' => '🇸🇮', 'name' => 'SI', 'full' => 'Slovenščina'],
            'sr' => ['flag' => '🇷🇸', 'name' => 'RS', 'full' => 'Српски'],
            'uk' => ['flag' => '🇺🇦', 'name' => 'UA', 'full' => 'Українська'],
            'ru' => ['flag' => '🇷🇺', 'name' => 'RU', 'full' => 'Русский'],
        ];

        $code = strtolower($code);

        return $languages[$code] ?? ['flag' => '🏳️', 'name' => strtoupper($code), 'full' => $code];
    }
}
