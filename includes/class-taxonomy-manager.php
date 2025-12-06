<?php
/**
 * Taxonomy Manager for HubSpot Events
 *
 * Dynamically creates and manages taxonomies based on HubSpot event data
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Taxonomy_Manager {

    private static $instance = null;

    /**
     * Core taxonomies that are always registered
     */
    const CORE_TAXONOMIES = [
        'hs_event_type' => [
            'singular' => 'Event Type',
            'plural' => 'Event Types',
            'slug' => 'hs-event-type',
            'hierarchical' => true,
        ],
        'hs_event_organizer' => [
            'singular' => 'Organizer',
            'plural' => 'Organizers',
            'slug' => 'hs-event-organizer',
            'hierarchical' => false,
        ],
        'hs_event_language' => [
            'singular' => 'Language',
            'plural' => 'Languages',
            'slug' => 'hs-event-language',
            'hierarchical' => false,
        ],
    ];

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
        add_action('init', [$this, 'register_taxonomies'], 5);
        add_action('init', [$this, 'register_dynamic_taxonomies'], 6);
    }

    /**
     * Register core taxonomies
     */
    public function register_taxonomies() {
        foreach (self::CORE_TAXONOMIES as $taxonomy => $config) {
            $this->register_taxonomy($taxonomy, $config);
        }
    }

    /**
     * Register dynamic taxonomies discovered from HubSpot data
     */
    public function register_dynamic_taxonomies() {
        $dynamic_taxonomies = get_option('hsec_dynamic_taxonomies', []);

        foreach ($dynamic_taxonomies as $taxonomy => $config) {
            if (!taxonomy_exists($taxonomy)) {
                $this->register_taxonomy($taxonomy, $config);
            }
        }
    }

    /**
     * Register a single taxonomy
     */
    private function register_taxonomy($taxonomy, $config) {
        $labels = [
            'name'              => __($config['plural'], 'hubspot-events-connector'),
            'singular_name'     => __($config['singular'], 'hubspot-events-connector'),
            'search_items'      => sprintf(__('Search %s', 'hubspot-events-connector'), $config['plural']),
            'all_items'         => sprintf(__('All %s', 'hubspot-events-connector'), $config['plural']),
            'parent_item'       => sprintf(__('Parent %s', 'hubspot-events-connector'), $config['singular']),
            'parent_item_colon' => sprintf(__('Parent %s:', 'hubspot-events-connector'), $config['singular']),
            'edit_item'         => sprintf(__('Edit %s', 'hubspot-events-connector'), $config['singular']),
            'update_item'       => sprintf(__('Update %s', 'hubspot-events-connector'), $config['singular']),
            'add_new_item'      => sprintf(__('Add New %s', 'hubspot-events-connector'), $config['singular']),
            'new_item_name'     => sprintf(__('New %s Name', 'hubspot-events-connector'), $config['singular']),
            'menu_name'         => __($config['plural'], 'hubspot-events-connector'),
        ];

        $args = [
            'hierarchical'      => $config['hierarchical'] ?? false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => $config['slug']],
            'show_in_rest'      => true,
        ];

        register_taxonomy($taxonomy, HSEC_Post_Type::POST_TYPE, $args);
    }

    /**
     * Analyze HubSpot events and detect potential taxonomies
     *
     * Called during sync to discover new taxonomy-worthy fields
     */
    public function analyze_events_for_taxonomies($events) {
        $potential_taxonomies = [];
        $field_values = [];

        foreach ($events as $event) {
            // Marketing Events API returns fields directly, not in 'properties'

            // Check eventType - candidate for taxonomy
            if (!empty($event['eventType'])) {
                $field_values['eventType'][] = $event['eventType'];
            }

            // Check eventOrganizer - candidate for taxonomy
            if (!empty($event['eventOrganizer'])) {
                $field_values['eventOrganizer'][] = $event['eventOrganizer'];
            }

            // Check custom properties for potential taxonomies
            $custom_properties = $event['customProperties'] ?? [];
            if (!empty($custom_properties)) {
                foreach ($custom_properties as $custom) {
                    $name = $custom['name'] ?? '';
                    $value = $custom['value'] ?? '';

                    // Skip if no name or value
                    if (empty($name) || empty($value)) {
                        continue;
                    }

                    // Look for fields that could be taxonomies
                    // (e.g., fields ending with "category", "type", "tag", etc.)
                    $taxonomy_indicators = ['category', 'type', 'tag', 'group', 'level', 'track', 'theme'];

                    foreach ($taxonomy_indicators as $indicator) {
                        if (stripos($name, $indicator) !== false) {
                            $field_values['custom_' . $name][] = $value;
                            break;
                        }
                    }
                }
            }
        }

        // Analyze collected values to determine if they're taxonomy-worthy
        foreach ($field_values as $field => $values) {
            $unique_values = array_unique($values);
            $unique_count = count($unique_values);
            $total_count = count($values);

            // Good taxonomy candidate if:
            // - More than 1 unique value (not all the same)
            // - Less than 50% unique values (shows reuse)
            // - At least 2 events share a value
            if ($unique_count > 1 && $unique_count < ($total_count * 0.5)) {
                $potential_taxonomies[$field] = [
                    'unique_values' => $unique_count,
                    'total_occurrences' => $total_count,
                    'sample_values' => array_slice($unique_values, 0, 10),
                ];
            }
        }

        return $potential_taxonomies;
    }

    /**
     * Create dynamic taxonomy from discovered field
     */
    public function create_dynamic_taxonomy($field_name, $analysis) {
        // Generate taxonomy name
        $taxonomy_name = 'hs_' . sanitize_key($field_name);

        // Ensure it doesn't exceed WordPress limit
        if (strlen($taxonomy_name) > 32) {
            $taxonomy_name = substr($taxonomy_name, 0, 32);
        }

        // Skip if taxonomy already exists
        if (taxonomy_exists($taxonomy_name)) {
            return $taxonomy_name;
        }

        // Create human-readable labels
        $singular = ucwords(str_replace(['_', '-'], ' ', $field_name));
        $plural = $singular . 's';

        // Handle common naming patterns
        if (stripos($field_name, 'category') !== false) {
            $singular = str_ireplace('category', 'Category', $singular);
            $plural = str_ireplace('categorys', 'Categories', $plural);
        }

        $config = [
            'singular' => $singular,
            'plural' => $plural,
            'slug' => 'hs-' . sanitize_title($field_name),
            'hierarchical' => stripos($field_name, 'category') !== false,
        ];

        // Save to dynamic taxonomies option
        $dynamic_taxonomies = get_option('hsec_dynamic_taxonomies', []);
        $dynamic_taxonomies[$taxonomy_name] = $config;
        update_option('hsec_dynamic_taxonomies', $dynamic_taxonomies);

        // Register immediately
        $this->register_taxonomy($taxonomy_name, $config);

        return $taxonomy_name;
    }

    /**
     * Assign taxonomy term to event
     */
    public function assign_term_to_event($post_id, $taxonomy, $term_value) {
        if (empty($term_value) || !taxonomy_exists($taxonomy)) {
            return false;
        }

        // Check if term exists, create if not
        $term = term_exists($term_value, $taxonomy);

        if (!$term) {
            $term = wp_insert_term($term_value, $taxonomy);

            if (is_wp_error($term)) {
                return false;
            }
        }

        $term_id = is_array($term) ? $term['term_id'] : $term;

        // Assign term to post
        wp_set_object_terms($post_id, (int) $term_id, $taxonomy, true);

        return true;
    }

    /**
     * Process event and assign all relevant taxonomy terms
     */
    public function process_event_taxonomies($post_id, $hubspot_data) {
        // Assign event type (for Marketing Events or Landing Pages)
        if (!empty($hubspot_data['eventType'])) {
            $this->assign_term_to_event($post_id, 'hs_event_type', $hubspot_data['eventType']);
        }

        // Assign organizer
        if (!empty($hubspot_data['eventOrganizer'])) {
            $this->assign_term_to_event($post_id, 'hs_event_organizer', $hubspot_data['eventOrganizer']);
        }

        // Assign language - convert code to readable name
        $language = $hubspot_data['language'] ?? '';
        if (!empty($language)) {
            $language_name = $this->get_language_name($language);
            $this->assign_term_to_event($post_id, 'hs_event_language', $language_name);
        }

        // Process custom properties that became dynamic taxonomies
        $custom_properties = $hubspot_data['customProperties'] ?? [];
        if (!empty($custom_properties)) {
            $dynamic_taxonomies = get_option('hsec_dynamic_taxonomies', []);

            foreach ($custom_properties as $custom) {
                $name = $custom['name'] ?? '';
                $value = $custom['value'] ?? '';

                if (empty($name) || empty($value)) {
                    continue;
                }

                // Check if this custom field has a corresponding taxonomy
                $taxonomy_name = 'hs_custom_' . sanitize_key($name);

                // Also check without 'custom_' prefix
                $alt_taxonomy_name = 'hs_' . sanitize_key($name);

                if (isset($dynamic_taxonomies[$taxonomy_name])) {
                    $this->assign_term_to_event($post_id, $taxonomy_name, $value);
                } elseif (isset($dynamic_taxonomies[$alt_taxonomy_name])) {
                    $this->assign_term_to_event($post_id, $alt_taxonomy_name, $value);
                }
            }
        }
    }

    /**
     * Convert language code to readable name
     */
    private function get_language_name($code) {
        $languages = [
            'pl' => 'Polski',
            'en' => 'English',
            'hu' => 'Magyar',
            'cs' => 'Čeština',
            'ro' => 'Română',
            'sk' => 'Slovenčina',
            'lt' => 'Lietuvių',
            'lv' => 'Latviešu',
            'et' => 'Eesti',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'it' => 'Italiano',
        ];

        return $languages[$code] ?? strtoupper($code);
    }

    /**
     * Get all registered taxonomies for hs_event
     */
    public function get_all_event_taxonomies() {
        return get_object_taxonomies(HSEC_Post_Type::POST_TYPE, 'objects');
    }

    /**
     * Get taxonomy statistics
     */
    public function get_taxonomy_stats() {
        $taxonomies = $this->get_all_event_taxonomies();
        $stats = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
            ]);

            $stats[$taxonomy->name] = [
                'label' => $taxonomy->label,
                'term_count' => is_array($terms) ? count($terms) : 0,
                'hierarchical' => $taxonomy->hierarchical,
            ];
        }

        return $stats;
    }

    /**
     * Suggest taxonomies based on analysis
     */
    public function get_taxonomy_suggestions($events) {
        $analysis = $this->analyze_events_for_taxonomies($events);
        $suggestions = [];

        foreach ($analysis as $field => $data) {
            // Skip fields that are already core taxonomies
            if ($field === 'eventType' || $field === 'eventOrganizer') {
                continue;
            }

            $suggestions[] = [
                'field' => $field,
                'taxonomy_name' => 'hs_' . sanitize_key($field),
                'unique_values' => $data['unique_values'],
                'sample_values' => $data['sample_values'],
                'recommendation' => $data['unique_values'] <= 20 ? 'recommended' : 'optional',
            ];
        }

        return $suggestions;
    }

    /**
     * Auto-create taxonomies from suggestions
     */
    public function auto_create_suggested_taxonomies($events) {
        $suggestions = $this->get_taxonomy_suggestions($events);
        $created = [];

        foreach ($suggestions as $suggestion) {
            if ($suggestion['recommendation'] === 'recommended') {
                $taxonomy_name = $this->create_dynamic_taxonomy(
                    $suggestion['field'],
                    ['unique_values' => $suggestion['unique_values']]
                );

                if ($taxonomy_name) {
                    $created[] = $taxonomy_name;
                }
            }
        }

        return $created;
    }
}
