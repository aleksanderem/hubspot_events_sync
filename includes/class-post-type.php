<?php
/**
 * HubSpot Events Custom Post Type
 *
 * Registers and manages the hs_event custom post type
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Post_Type {

    private static $instance = null;

    /**
     * Post type slug
     */
    const POST_TYPE = 'hs_event';

    /**
     * Meta key prefix
     */
    const META_PREFIX = '_hsec_';

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
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta_box_data'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'sortable_columns']);
    }

    /**
     * Register custom post type
     */
    public function register_post_type() {
        $labels = [
            'name'                  => __('HubSpot Events', 'hubspot-events-connector'),
            'singular_name'         => __('HubSpot Event', 'hubspot-events-connector'),
            'menu_name'             => __('HS Events', 'hubspot-events-connector'),
            'all_items'             => __('All Events', 'hubspot-events-connector'),
            'add_new'               => __('Add New', 'hubspot-events-connector'),
            'add_new_item'          => __('Add New Event', 'hubspot-events-connector'),
            'edit_item'             => __('Edit Event', 'hubspot-events-connector'),
            'new_item'              => __('New Event', 'hubspot-events-connector'),
            'view_item'             => __('View Event', 'hubspot-events-connector'),
            'search_items'          => __('Search Events', 'hubspot-events-connector'),
            'not_found'             => __('No events found', 'hubspot-events-connector'),
            'not_found_in_trash'    => __('No events found in Trash', 'hubspot-events-connector'),
            'archives'              => __('Event Archives', 'hubspot-events-connector'),
            'filter_items_list'     => __('Filter events list', 'hubspot-events-connector'),
            'items_list_navigation' => __('Events list navigation', 'hubspot-events-connector'),
            'items_list'            => __('Events list', 'hubspot-events-connector'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'hs-events', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'show_in_rest'       => true,
            'rest_base'          => 'hs-events',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for event details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'hsec_event_details',
            __('HubSpot Event Details', 'hubspot-events-connector'),
            [$this, 'render_event_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'hsec_sync_info',
            __('Sync Information', 'hubspot-events-connector'),
            [$this, 'render_sync_info_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'hsec_raw_data',
            __('Raw HubSpot Data', 'hubspot-events-connector'),
            [$this, 'render_raw_data_meta_box'],
            self::POST_TYPE,
            'normal',
            'low'
        );
    }

    /**
     * Render event details meta box
     */
    public function render_event_details_meta_box($post) {
        wp_nonce_field('hsec_save_event_details', 'hsec_event_nonce');

        $fields = $this->get_event_fields();
        ?>
        <table class="form-table hsec-meta-table">
            <tbody>
            <?php foreach ($fields as $key => $field): ?>
                <?php
                $meta_key = self::META_PREFIX . $key;
                $value = get_post_meta($post->ID, $meta_key, true);
                ?>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr($meta_key); ?>">
                            <?php echo esc_html($field['label']); ?>
                        </label>
                    </th>
                    <td>
                        <?php $this->render_field($meta_key, $field, $value); ?>
                        <?php if (!empty($field['description'])): ?>
                            <p class="description"><?php echo esc_html($field['description']); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render sync info meta box
     */
    public function render_sync_info_meta_box($post) {
        $hubspot_id = get_post_meta($post->ID, self::META_PREFIX . 'hubspot_id', true);
        $external_event_id = get_post_meta($post->ID, self::META_PREFIX . 'external_event_id', true);
        $last_synced = get_post_meta($post->ID, self::META_PREFIX . 'last_synced', true);
        ?>
        <div class="hsec-sync-info">
            <p>
                <strong><?php _e('HubSpot Object ID:', 'hubspot-events-connector'); ?></strong><br>
                <?php echo $hubspot_id ? esc_html($hubspot_id) : '<em>' . __('Not synced', 'hubspot-events-connector') . '</em>'; ?>
            </p>
            <p>
                <strong><?php _e('External Event ID:', 'hubspot-events-connector'); ?></strong><br>
                <?php echo $external_event_id ? esc_html($external_event_id) : '<em>' . __('N/A', 'hubspot-events-connector') . '</em>'; ?>
            </p>
            <p>
                <strong><?php _e('Last Synced:', 'hubspot-events-connector'); ?></strong><br>
                <?php
                if ($last_synced) {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_synced));
                } else {
                    echo '<em>' . __('Never', 'hubspot-events-connector') . '</em>';
                }
                ?>
            </p>
            <?php if ($hubspot_id): ?>
                <p>
                    <a href="https://app.hubspot.com/contacts/events/<?php echo esc_attr($hubspot_id); ?>" target="_blank" class="button button-secondary">
                        <?php _e('View in HubSpot', 'hubspot-events-connector'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render raw HubSpot data meta box
     */
    public function render_raw_data_meta_box($post) {
        $raw_data = get_post_meta($post->ID, self::META_PREFIX . 'raw_data', true);

        if (empty($raw_data)) {
            echo '<p><em>' . __('No raw data available. Re-sync this event to populate.', 'hubspot-events-connector') . '</em></p>';
            return;
        }

        // Remove layoutSections from display (too large/complex)
        $display_data = $raw_data;
        if (isset($display_data['layoutSections'])) {
            $display_data['layoutSections'] = '[' . __('Hidden - too large', 'hubspot-events-connector') . ']';
        }

        ?>
        <div class="hsec-raw-data">
            <p class="description"><?php _e('Raw data from HubSpot API. Useful for debugging and custom field mappings.', 'hubspot-events-connector'); ?></p>
            <details>
                <summary style="cursor: pointer; padding: 10px 0; font-weight: 500;">
                    <?php _e('Click to expand/collapse', 'hubspot-events-connector'); ?>
                </summary>
                <pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px; font-size: 12px; border: 1px solid #ddd; margin-top: 10px;"><?php
                    echo esc_html(json_encode($display_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                ?></pre>
            </details>
        </div>
        <?php
    }

    /**
     * Render individual field
     */
    private function render_field($name, $field, $value) {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'textarea':
                ?>
                <textarea name="<?php echo esc_attr($name); ?>"
                          id="<?php echo esc_attr($name); ?>"
                          class="large-text"
                          rows="4"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;

            case 'datetime':
                ?>
                <input type="datetime-local"
                       name="<?php echo esc_attr($name); ?>"
                       id="<?php echo esc_attr($name); ?>"
                       value="<?php echo esc_attr($value ? date('Y-m-d\TH:i', strtotime($value)) : ''); ?>"
                       class="regular-text">
                <?php
                break;

            case 'url':
                ?>
                <input type="url"
                       name="<?php echo esc_attr($name); ?>"
                       id="<?php echo esc_attr($name); ?>"
                       value="<?php echo esc_url($value); ?>"
                       class="large-text">
                <?php
                break;

            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox"
                           name="<?php echo esc_attr($name); ?>"
                           id="<?php echo esc_attr($name); ?>"
                           value="1"
                           <?php checked($value, '1'); ?>>
                    <?php echo esc_html($field['checkbox_label'] ?? ''); ?>
                </label>
                <?php
                break;

            case 'select':
                ?>
                <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>">
                    <option value=""><?php _e('Select...', 'hubspot-events-connector'); ?></option>
                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                            <?php echo esc_html($opt_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            default:
                ?>
                <input type="<?php echo esc_attr($type); ?>"
                       name="<?php echo esc_attr($name); ?>"
                       id="<?php echo esc_attr($name); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       class="regular-text">
                <?php
        }
    }

    /**
     * Get event fields configuration
     */
    public function get_event_fields() {
        return [
            'event_organizer' => [
                'label' => __('Event Organizer', 'hubspot-events-connector'),
                'type' => 'text',
                'description' => __('Name of the person or organization hosting the event', 'hubspot-events-connector'),
            ],
            'event_type' => [
                'label' => __('Event Type', 'hubspot-events-connector'),
                'type' => 'text',
                'description' => __('Type of event (e.g., webinar, conference, workshop)', 'hubspot-events-connector'),
            ],
            'event_url' => [
                'label' => __('Event URL', 'hubspot-events-connector'),
                'type' => 'url',
                'description' => __('Registration or information URL', 'hubspot-events-connector'),
            ],
            'start_datetime' => [
                'label' => __('Start Date/Time', 'hubspot-events-connector'),
                'type' => 'datetime',
            ],
            'end_datetime' => [
                'label' => __('End Date/Time', 'hubspot-events-connector'),
                'type' => 'datetime',
            ],
            'event_cancelled' => [
                'label' => __('Cancelled', 'hubspot-events-connector'),
                'type' => 'checkbox',
                'checkbox_label' => __('This event has been cancelled', 'hubspot-events-connector'),
            ],
            'registered_count' => [
                'label' => __('Registered Attendees', 'hubspot-events-connector'),
                'type' => 'number',
                'description' => __('Number of registered attendees (synced from HubSpot)', 'hubspot-events-connector'),
            ],
            'attended_count' => [
                'label' => __('Attended', 'hubspot-events-connector'),
                'type' => 'number',
                'description' => __('Number of people who attended', 'hubspot-events-connector'),
            ],
            'cancelled_count' => [
                'label' => __('Cancellations', 'hubspot-events-connector'),
                'type' => 'number',
                'description' => __('Number of cancellations', 'hubspot-events-connector'),
            ],
        ];
    }

    /**
     * Save meta box data
     */
    public function save_meta_box_data($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['hsec_event_nonce']) || !wp_verify_nonce($_POST['hsec_event_nonce'], 'hsec_save_event_details')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save fields
        $fields = $this->get_event_fields();

        foreach ($fields as $key => $field) {
            $meta_key = self::META_PREFIX . $key;

            if (isset($_POST[$meta_key])) {
                $value = $this->sanitize_field($_POST[$meta_key], $field['type'] ?? 'text');
                update_post_meta($post_id, $meta_key, $value);
            } elseif ($field['type'] === 'checkbox') {
                // Checkbox not set means unchecked
                update_post_meta($post_id, $meta_key, '0');
            }
        }
    }

    /**
     * Sanitize field value based on type
     */
    private function sanitize_field($value, $type) {
        switch ($type) {
            case 'url':
                return esc_url_raw($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'number':
                return absint($value);
            case 'checkbox':
                return $value ? '1' : '0';
            case 'datetime':
                return sanitize_text_field($value);
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Add custom columns to admin list
     */
    public function add_admin_columns($columns) {
        $enabled_columns = get_option('hsec_admin_columns', ['thumbnail', 'event_type', 'event_date', 'language', 'event_status']);

        $available_columns = [
            'thumbnail' => __('Thumbnail', 'hubspot-events-connector'),
            'hs_featured_image' => __('HS Image', 'hubspot-events-connector'),
            'event_link' => __('Link', 'hubspot-events-connector'),
            'event_type' => __('Type', 'hubspot-events-connector'),
            'event_date' => __('Event Date', 'hubspot-events-connector'),
            'event_status' => __('Status', 'hubspot-events-connector'),
            'language' => __('Language', 'hubspot-events-connector'),
            'attendees' => __('Attendees', 'hubspot-events-connector'),
            'hubspot_id' => __('HubSpot ID', 'hubspot-events-connector'),
            'last_synced' => __('Last Synced', 'hubspot-events-connector'),
        ];

        $new_columns = [];

        foreach ($columns as $key => $value) {
            // Add thumbnail before title
            if ($key === 'title' && in_array('thumbnail', $enabled_columns)) {
                $new_columns['thumbnail'] = $available_columns['thumbnail'];
            }

            $new_columns[$key] = $value;

            // Add other custom columns after title
            if ($key === 'title') {
                foreach ($enabled_columns as $col_key) {
                    if ($col_key !== 'thumbnail' && isset($available_columns[$col_key])) {
                        $new_columns[$col_key] = $available_columns[$col_key];
                    }
                }
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'thumbnail':
                echo '<div style="text-align:center;">';
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [60, 60], ['style' => 'border-radius: 4px; object-fit: cover;']);
                } else {
                    // No local featured image - show placeholder with screenshot button
                    $event_url = get_post_meta($post_id, self::META_PREFIX . 'event_url', true);
                    echo '<span style="display:inline-block;width:60px;height:60px;background:#f0f0f1;border-radius:4px;"></span>';
                    if ($event_url) {
                        echo '<br><button type="button" class="button button-small hsec-screenshot-btn" data-post-id="' . esc_attr($post_id) . '" data-url="' . esc_attr($event_url) . '" style="margin-top:4px;">';
                        echo '<span class="dashicons dashicons-camera" style="vertical-align: middle; margin-top: -2px;"></span> ';
                        echo __('Screenshot', 'hubspot-events-connector');
                        echo '</button>';
                    }
                }
                echo '</div>';
                break;

            case 'hs_featured_image':
                $raw_data = get_post_meta($post_id, self::META_PREFIX . 'raw_data', true);
                $image_url = $raw_data['featuredImage'] ?? '';
                if ($image_url) {
                    echo '<a href="' . esc_url($image_url) . '" target="_blank">';
                    echo '<img src="' . esc_url($image_url) . '" width="60" height="60" style="object-fit: cover; border-radius: 4px;" />';
                    echo '</a>';
                } else {
                    echo '<span style="display:inline-block;width:60px;height:60px;background:#f0f0f1;border-radius:4px;" title="No image"></span>';
                }
                break;

            case 'event_link':
                $event_url = get_post_meta($post_id, self::META_PREFIX . 'event_url', true);
                if ($event_url) {
                    echo '<a href="' . esc_url($event_url) . '" target="_blank" class="button button-small">';
                    echo '<span class="dashicons dashicons-external" style="vertical-align: middle; margin-top: -2px;"></span> ';
                    echo __('View', 'hubspot-events-connector');
                    echo '</a>';
                } else {
                    echo '&mdash;';
                }
                break;

            case 'event_type':
                $type = get_post_meta($post_id, self::META_PREFIX . 'event_type', true);
                echo $type ? esc_html($type) : '&mdash;';
                break;

            case 'event_date':
                $start = get_post_meta($post_id, self::META_PREFIX . 'start_datetime', true);
                if ($start) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($start)));
                } else {
                    echo '&mdash;';
                }
                break;

            case 'event_status':
                $cancelled = get_post_meta($post_id, self::META_PREFIX . 'event_cancelled', true);
                $start = get_post_meta($post_id, self::META_PREFIX . 'start_datetime', true);

                if ($cancelled === '1') {
                    echo '<span class="hsec-status hsec-status--cancelled">' . __('Cancelled', 'hubspot-events-connector') . '</span>';
                } elseif ($start && strtotime($start) < time()) {
                    echo '<span class="hsec-status hsec-status--past">' . __('Past', 'hubspot-events-connector') . '</span>';
                } else {
                    echo '<span class="hsec-status hsec-status--upcoming">' . __('Upcoming', 'hubspot-events-connector') . '</span>';
                }
                break;

            case 'language':
                $language = get_post_meta($post_id, self::META_PREFIX . 'language', true);
                if ($language) {
                    $lang_names = [
                        'pl' => 'Polski', 'en' => 'English', 'hu' => 'Magyar',
                        'cs' => 'Čeština', 'ro' => 'Română', 'sk' => 'Slovenčina',
                        'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español',
                    ];
                    echo esc_html($lang_names[$language] ?? strtoupper($language));
                } else {
                    echo '&mdash;';
                }
                break;

            case 'attendees':
                $registered = get_post_meta($post_id, self::META_PREFIX . 'registered_count', true);
                $attended = get_post_meta($post_id, self::META_PREFIX . 'attended_count', true);

                if ($registered || $attended) {
                    echo sprintf(
                        __('%d registered / %d attended', 'hubspot-events-connector'),
                        (int) $registered,
                        (int) $attended
                    );
                } else {
                    echo '&mdash;';
                }
                break;

            case 'hubspot_id':
                $hubspot_id = get_post_meta($post_id, self::META_PREFIX . 'hubspot_id', true);
                echo $hubspot_id ? '<code>' . esc_html($hubspot_id) . '</code>' : '&mdash;';
                break;

            case 'last_synced':
                $last_synced = get_post_meta($post_id, self::META_PREFIX . 'last_synced', true);
                if ($last_synced) {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_synced));
                } else {
                    echo '&mdash;';
                }
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['event_date'] = 'event_date';
        $columns['event_type'] = 'event_type';
        return $columns;
    }

    /**
     * Find event by HubSpot ID
     */
    public function find_by_hubspot_id($hubspot_id) {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'meta_query' => [
                [
                    'key' => self::META_PREFIX . 'hubspot_id',
                    'value' => $hubspot_id,
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'any',
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Find event by external event ID
     */
    public function find_by_external_id($external_id) {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'meta_query' => [
                [
                    'key' => self::META_PREFIX . 'external_event_id',
                    'value' => $external_id,
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'any',
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Create or update event from HubSpot data
     *
     * Supports both data sources:
     * - Landing Pages API (primary for webinars)
     * - Marketing Events API (legacy)
     */
    public function create_or_update_event($hubspot_data) {
        // Detect data source based on unique fields
        $is_landing_page = isset($hubspot_data['htmlTitle']) || isset($hubspot_data['featuredImage']) || isset($hubspot_data['slug']);

        if ($is_landing_page) {
            return $this->create_or_update_from_landing_page($hubspot_data);
        }

        return $this->create_or_update_from_marketing_event($hubspot_data);
    }

    /**
     * Create or update event from Landing Page data
     *
     * Landing Pages are used for webinars in HubSpot
     */
    private function create_or_update_from_landing_page($page_data) {
        // Landing Pages use 'id' directly
        $hubspot_id = $page_data['id'] ?? null;

        if (!$hubspot_id) {
            return new WP_Error('invalid_data', __('Missing HubSpot page ID', 'hubspot-events-connector'));
        }

        // Check if event already exists
        $existing_post = $this->find_by_hubspot_id($hubspot_id);

        // Extract title - prefer htmlTitle, fall back to name
        $title = $page_data['htmlTitle'] ?? $page_data['name'] ?? __('Untitled Webinar', 'hubspot-events-connector');
        // Clean up title (remove site name suffix if present)
        $title = preg_replace('/\s*\|.*$/', '', $title);
        $title = preg_replace('/\s*-\s*MWT.*$/i', '', $title);

        // Build description from available sources
        $description = '';
        if (!empty($page_data['_event_description'])) {
            $description = $page_data['_event_description'];
        } elseif (!empty($page_data['metaDescription'])) {
            $description = $page_data['metaDescription'];
        }

        $post_data = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($description),
        ];

        if ($existing_post) {
            $post_data['ID'] = $existing_post->ID;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Determine event type based on filter keyword or default
        $event_type = 'Webinar';
        if (!empty($page_data['subcategory'])) {
            $event_type = $page_data['subcategory'];
        }

        // Build meta mappings for Landing Page
        $meta_mappings = [
            'hubspot_id' => $hubspot_id,
            'data_source' => 'landing_page',
            'event_url' => $page_data['url'] ?? '',
            'event_type' => $event_type,
            'event_location' => $page_data['_event_location'] ?? '',
            'start_datetime' => $page_data['_event_datetime'] ?? $page_data['_event_date'] ?? '',
            'event_time' => $page_data['_event_time'] ?? '',
            'language' => $page_data['language'] ?? '',
            'slug' => $page_data['slug'] ?? '',
            'domain' => $page_data['domain'] ?? '',
            'state' => $page_data['state'] ?? '',
            'last_synced' => time(),
        ];

        foreach ($meta_mappings as $key => $value) {
            update_post_meta($post_id, self::META_PREFIX . $key, $value);
        }

        // Store raw HubSpot timestamps
        if (!empty($page_data['createdAt'])) {
            update_post_meta($post_id, self::META_PREFIX . 'hs_created_at', $page_data['createdAt']);
        }
        if (!empty($page_data['updatedAt'])) {
            update_post_meta($post_id, self::META_PREFIX . 'hs_updated_at', $page_data['updatedAt']);
        }
        if (!empty($page_data['publishDate'])) {
            update_post_meta($post_id, self::META_PREFIX . 'publish_date', $page_data['publishDate']);
        }

        // Store raw HubSpot data for debugging and custom field mappings
        update_post_meta($post_id, self::META_PREFIX . 'raw_data', $page_data);

        // Handle featured image from Landing Page featuredImage field
        $this->maybe_set_featured_image_from_landing_page($post_id, $page_data);

        // Apply custom field mappings
        $this->apply_custom_mappings($post_id, $page_data);

        return $post_id;
    }

    /**
     * Apply custom field mappings from settings
     */
    private function apply_custom_mappings($post_id, $hubspot_data) {
        $mappings = get_option('hsec_field_mappings', []);

        if (empty($mappings)) {
            return;
        }

        foreach ($mappings as $mapping) {
            $hubspot_field = $mapping['hubspot'] ?? '';
            $wp_field = $mapping['wordpress'] ?? '';

            if (empty($hubspot_field) || empty($wp_field)) {
                continue;
            }

            // Get value from HubSpot data
            $value = $hubspot_data[$hubspot_field] ?? '';

            if (empty($value)) {
                continue;
            }

            // Apply to WordPress field
            if (in_array($wp_field, ['post_title', 'post_content', 'post_excerpt'])) {
                // Post field - update the post
                wp_update_post([
                    'ID' => $post_id,
                    $wp_field => $wp_field === 'post_title' ? sanitize_text_field($value) : wp_kses_post($value),
                ]);
            } else {
                // Meta field - save as post meta
                update_post_meta($post_id, $wp_field, sanitize_text_field($value));
            }
        }
    }

    /**
     * Create or update event from Marketing Events API data
     */
    private function create_or_update_from_marketing_event($hubspot_data) {
        // Marketing Events API uses 'objectId' not 'id'
        $hubspot_id = $hubspot_data['objectId'] ?? $hubspot_data['id'] ?? null;

        if (!$hubspot_id) {
            return new WP_Error('invalid_data', __('Missing HubSpot event ID', 'hubspot-events-connector'));
        }

        // Check if event already exists
        $existing_post = $this->find_by_hubspot_id($hubspot_id);

        $post_data = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($hubspot_data['eventName'] ?? __('Untitled Event', 'hubspot-events-connector')),
            'post_content' => wp_kses_post($hubspot_data['eventDescription'] ?? ''),
        ];

        if ($existing_post) {
            $post_data['ID'] = $existing_post->ID;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta fields - Marketing Events API uses flat structure
        $meta_mappings = [
            'hubspot_id' => $hubspot_id,
            'data_source' => 'marketing_event',
            'external_event_id' => $hubspot_data['externalEventId'] ?? '',
            'external_account_id' => $hubspot_data['externalAccountId'] ?? '',
            'event_organizer' => $hubspot_data['eventOrganizer'] ?? '',
            'event_type' => $hubspot_data['eventType'] ?? '',
            'event_url' => $hubspot_data['eventUrl'] ?? '',
            'start_datetime' => $hubspot_data['startDateTime'] ?? '',
            'end_datetime' => $hubspot_data['endDateTime'] ?? '',
            'event_cancelled' => ($hubspot_data['eventCancelled'] ?? false) ? '1' : '0',
            'event_completed' => ($hubspot_data['eventCompleted'] ?? false) ? '1' : '0',
            'event_status' => $hubspot_data['eventStatus'] ?? '',
            'registrants' => $hubspot_data['registrants'] ?? 0,
            'attendees' => $hubspot_data['attendees'] ?? 0,
            'cancellations' => $hubspot_data['cancellations'] ?? 0,
            'no_shows' => $hubspot_data['noShows'] ?? 0,
            'last_synced' => time(),
        ];

        foreach ($meta_mappings as $key => $value) {
            update_post_meta($post_id, self::META_PREFIX . $key, $value);
        }

        // Handle custom properties (these ARE in an array)
        $custom_properties = $hubspot_data['customProperties'] ?? [];
        if (!empty($custom_properties)) {
            foreach ($custom_properties as $custom) {
                if (isset($custom['name'], $custom['value'])) {
                    $custom_key = self::META_PREFIX . 'custom_' . sanitize_key($custom['name']);
                    update_post_meta($post_id, $custom_key, sanitize_text_field($custom['value']));
                }
            }
        }

        // Store raw HubSpot timestamps for reference
        if (!empty($hubspot_data['createdAt'])) {
            update_post_meta($post_id, self::META_PREFIX . 'hs_created_at', $hubspot_data['createdAt']);
        }
        if (!empty($hubspot_data['updatedAt'])) {
            update_post_meta($post_id, self::META_PREFIX . 'hs_updated_at', $hubspot_data['updatedAt']);
        }

        // Store raw HubSpot data for debugging and custom field mappings
        update_post_meta($post_id, self::META_PREFIX . 'raw_data', $hubspot_data);

        // Handle featured image from custom property
        $this->maybe_set_featured_image($post_id, $hubspot_data);

        return $post_id;
    }

    /**
     * Set featured image from HubSpot custom property if configured
     *
     * Used for Marketing Events data source
     *
     * @param int $post_id WordPress post ID
     * @param array $hubspot_data HubSpot event data
     */
    private function maybe_set_featured_image($post_id, $hubspot_data) {
        // Check if image property is configured
        $image_property = get_option('hsec_image_property', '');

        if (empty($image_property)) {
            return;
        }

        // Try to find image URL in custom properties
        $image_url = null;
        $custom_properties = $hubspot_data['customProperties'] ?? [];

        foreach ($custom_properties as $custom) {
            $name = $custom['name'] ?? '';
            $value = $custom['value'] ?? '';

            if ($name === $image_property && !empty($value)) {
                $image_url = $value;
                break;
            }
        }

        // Also check if the property is a direct field (not custom)
        if (!$image_url && !empty($hubspot_data[$image_property])) {
            $image_url = $hubspot_data[$image_property];
        }

        if (!$image_url || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            return;
        }

        // Check if we already have this image
        $current_image_url = get_post_meta($post_id, self::META_PREFIX . 'image_source_url', true);
        if ($current_image_url === $image_url && has_post_thumbnail($post_id)) {
            return; // Same image, no need to re-download
        }

        // Download and attach the image
        $attachment_id = $this->download_and_attach_image($image_url, $post_id);

        if ($attachment_id && !is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            update_post_meta($post_id, self::META_PREFIX . 'image_source_url', $image_url);
        }
    }

    /**
     * Set featured image from Landing Page featuredImage field
     *
     * This automatically downloads images from HubSpot Landing Pages
     * without requiring any configuration.
     *
     * @param int $post_id WordPress post ID
     * @param array $page_data Landing Page data from HubSpot
     */
    private function maybe_set_featured_image_from_landing_page($post_id, $page_data) {
        // Landing Pages have featuredImage directly on the data
        $image_url = $page_data['featuredImage'] ?? '';

        // Log for debugging
        error_log('[HSEC] Featured image check for post ' . $post_id . ': ' . ($image_url ?: 'EMPTY'));

        if (empty($image_url)) {
            return;
        }

        // Validate URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return;
        }

        // Check if we already have this image
        $current_image_url = get_post_meta($post_id, self::META_PREFIX . 'image_source_url', true);
        if ($current_image_url === $image_url && has_post_thumbnail($post_id)) {
            return; // Same image, no need to re-download
        }

        // Download and attach the image
        $attachment_id = $this->download_and_attach_image($image_url, $post_id);

        if ($attachment_id && !is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            update_post_meta($post_id, self::META_PREFIX . 'image_source_url', $image_url);

            // Log success in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[HSEC] Featured image set for post %d from URL: %s',
                    $post_id,
                    $image_url
                ));
            }
        }
    }

    /**
     * Download image from URL and attach to post
     *
     * @param string $url Image URL
     * @param int $post_id Post to attach to
     * @return int|WP_Error Attachment ID or error
     */
    private function download_and_attach_image($url, $post_id) {
        // Require WordPress media handling functions
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download file to temp location
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            error_log('[HSEC] Failed to download image: ' . $tmp->get_error_message());
            return $tmp;
        }

        // Get file info
        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        // If no extension, try to determine from content type
        if (!pathinfo($file_array['name'], PATHINFO_EXTENSION)) {
            $file_array['name'] .= '.jpg'; // Default to jpg
        }

        // Handle sideload (moves file from temp to uploads and creates attachment)
        $attachment_id = media_handle_sideload($file_array, $post_id, get_the_title($post_id));

        // Clean up temp file if sideload failed
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            error_log('[HSEC] Failed to sideload image: ' . $attachment_id->get_error_message());
        }

        return $attachment_id;
    }
}
