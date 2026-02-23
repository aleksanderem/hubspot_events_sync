<?php
/**
 * Admin Settings Page for HubSpot Events Connector
 *
 * Provides UI for configuring the plugin
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Admin_Settings {

    private static $instance = null;

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'hsec-settings';

    /**
     * Settings group
     */
    const SETTINGS_GROUP = 'hsec_settings';

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
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Add full settings page under Settings menu for advanced configuration
        add_options_page(
            __('HubSpot Events Settings', 'hubspot-events-connector'),
            __('HubSpot Events', 'hubspot-events-connector'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );

        // Add quick link submenu under CPT menu that redirects to Starter Dashboard addon settings
        add_submenu_page(
            'edit.php?post_type=hs_event',
            __('Quick Settings', 'hubspot-events-connector'),
            __('⚙️ Quick Settings', 'hubspot-events-connector'),
            'manage_options',
            'admin.php?page=starter-dashboard&addon=hubspot-events',
            ''
        );

        // Advanced settings link
        add_submenu_page(
            'edit.php?post_type=hs_event',
            __('Advanced Settings', 'hubspot-events-connector'),
            __('⚙️ Advanced Settings', 'hubspot-events-connector'),
            'manage_options',
            'options-general.php?page=hsec-settings',
            ''
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings Section
        add_settings_section(
            'hsec_api_section',
            __('HubSpot API Configuration', 'hubspot-events-connector'),
            [$this, 'render_api_section_description'],
            self::PAGE_SLUG
        );

        // API Token
        register_setting(self::SETTINGS_GROUP, 'hsec_api_token', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_field(
            'hsec_api_token',
            __('Private App Token', 'hubspot-events-connector'),
            [$this, 'render_api_token_field'],
            self::PAGE_SLUG,
            'hsec_api_section'
        );

        // Sync Settings Section
        add_settings_section(
            'hsec_sync_section',
            __('Synchronization Settings', 'hubspot-events-connector'),
            [$this, 'render_sync_section_description'],
            self::PAGE_SLUG
        );

        // Data Source
        register_setting(self::SETTINGS_GROUP, 'hsec_data_source', [
            'type' => 'string',
            'default' => 'landing_pages',
            'sanitize_callback' => [$this, 'sanitize_data_source'],
        ]);

        add_settings_field(
            'hsec_data_source',
            __('Data Source', 'hubspot-events-connector'),
            [$this, 'render_data_source_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Filter Keyword (for Landing Pages)
        register_setting(self::SETTINGS_GROUP, 'hsec_filter_keyword', [
            'type' => 'string',
            'default' => 'webinar',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_field(
            'hsec_filter_keyword',
            __('Filter Keyword', 'hubspot-events-connector'),
            [$this, 'render_filter_keyword_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Language Filter
        register_setting(self::SETTINGS_GROUP, 'hsec_language_filter', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_field(
            'hsec_language_filter',
            __('Language Filter', 'hubspot-events-connector'),
            [$this, 'render_language_filter_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Sync Interval
        register_setting(self::SETTINGS_GROUP, 'hsec_sync_interval', [
            'type' => 'string',
            'default' => 'hourly',
            'sanitize_callback' => [$this, 'sanitize_sync_interval'],
        ]);

        add_settings_field(
            'hsec_sync_interval',
            __('Sync Interval', 'hubspot-events-connector'),
            [$this, 'render_sync_interval_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Auto-create Taxonomies
        register_setting(self::SETTINGS_GROUP, 'hsec_auto_taxonomies', [
            'type' => 'boolean',
            'default' => true,
        ]);

        add_settings_field(
            'hsec_auto_taxonomies',
            __('Auto-detect Taxonomies', 'hubspot-events-connector'),
            [$this, 'render_auto_taxonomies_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Event Status Filter
        register_setting(self::SETTINGS_GROUP, 'hsec_event_status_filter', [
            'type' => 'string',
            'default' => 'all',
            'sanitize_callback' => [$this, 'sanitize_event_status_filter'],
        ]);

        add_settings_field(
            'hsec_event_status_filter',
            __('Event Status Filter', 'hubspot-events-connector'),
            [$this, 'render_event_status_filter_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Custom Image Property
        register_setting(self::SETTINGS_GROUP, 'hsec_image_property', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_field(
            'hsec_image_property',
            __('Image Property Name', 'hubspot-events-connector'),
            [$this, 'render_image_property_field'],
            self::PAGE_SLUG,
            'hsec_sync_section'
        );

        // Field Mapping Section
        add_settings_section(
            'hsec_mapping_section',
            __('Field Mapping', 'hubspot-events-connector'),
            [$this, 'render_mapping_section_description'],
            self::PAGE_SLUG
        );

        // Field Mappings
        register_setting(self::SETTINGS_GROUP, 'hsec_field_mappings', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitize_field_mappings'],
        ]);

        add_settings_field(
            'hsec_field_mappings',
            __('Custom Field Mappings', 'hubspot-events-connector'),
            [$this, 'render_field_mappings'],
            self::PAGE_SLUG,
            'hsec_mapping_section'
        );

        // Display Settings Section
        add_settings_section(
            'hsec_display_section',
            __('Display Settings', 'hubspot-events-connector'),
            [$this, 'render_display_section_description'],
            self::PAGE_SLUG
        );

        // Admin Columns
        register_setting(self::SETTINGS_GROUP, 'hsec_admin_columns', [
            'type' => 'array',
            'default' => ['thumbnail', 'event_type', 'event_date', 'language', 'event_status'],
            'sanitize_callback' => [$this, 'sanitize_admin_columns'],
        ]);

        add_settings_field(
            'hsec_admin_columns',
            __('Admin List Columns', 'hubspot-events-connector'),
            [$this, 'render_admin_columns_field'],
            self::PAGE_SLUG,
            'hsec_display_section'
        );
    }

    /**
     * Render mapping section description
     */
    public function render_mapping_section_description() {
        ?>
        <p>
            <?php _e('Map HubSpot fields to WordPress post meta fields. Leave empty to use default mappings.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render display section description
     */
    public function render_display_section_description() {
        ?>
        <p>
            <?php _e('Configure how events are displayed in the WordPress admin.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Get available admin columns
     */
    public function get_available_columns() {
        return [
            'thumbnail' => __('Thumbnail (local)', 'hubspot-events-connector'),
            'hs_featured_image' => __('Featured Image (HubSpot)', 'hubspot-events-connector'),
            'event_link' => __('Event Link', 'hubspot-events-connector'),
            'event_type' => __('Event Type', 'hubspot-events-connector'),
            'event_date' => __('Event Date', 'hubspot-events-connector'),
            'event_status' => __('Status', 'hubspot-events-connector'),
            'language' => __('Language', 'hubspot-events-connector'),
            'attendees' => __('Attendees', 'hubspot-events-connector'),
            'hubspot_id' => __('HubSpot ID', 'hubspot-events-connector'),
            'last_synced' => __('Last Synced', 'hubspot-events-connector'),
        ];
    }

    /**
     * Render admin columns field
     */
    public function render_admin_columns_field() {
        $selected = get_option('hsec_admin_columns', ['thumbnail', 'event_type', 'event_date', 'language', 'event_status']);
        $available = $this->get_available_columns();
        ?>
        <fieldset>
            <?php foreach ($available as $key => $label): ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox"
                           name="hsec_admin_columns[]"
                           value="<?php echo esc_attr($key); ?>"
                           <?php checked(in_array($key, $selected)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php _e('Select which columns to display in the events list.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize admin columns
     */
    public function sanitize_admin_columns($value) {
        if (!is_array($value)) {
            return ['thumbnail', 'event_type', 'event_date', 'language', 'event_status'];
        }
        $available = array_keys($this->get_available_columns());
        return array_intersect($value, $available);
    }

    /**
     * Sanitize field mappings
     */
    public function sanitize_field_mappings($value) {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];
        foreach ($value as $mapping) {
            $hubspot_field = sanitize_text_field($mapping['hubspot'] ?? '');
            $wp_field = sanitize_text_field($mapping['wordpress'] ?? '');

            if (!empty($hubspot_field) && !empty($wp_field)) {
                $sanitized[] = [
                    'hubspot' => $hubspot_field,
                    'wordpress' => $wp_field,
                ];
            }
        }

        return $sanitized;
    }

    /**
     * Render field mappings UI
     */
    public function render_field_mappings() {
        $mappings = get_option('hsec_field_mappings', []);

        // Available HubSpot fields (Landing Pages)
        $hubspot_fields = [
            'htmlTitle' => __('Title (htmlTitle)', 'hubspot-events-connector'),
            'name' => __('Name', 'hubspot-events-connector'),
            'metaDescription' => __('Meta Description', 'hubspot-events-connector'),
            'url' => __('URL', 'hubspot-events-connector'),
            'featuredImage' => __('Featured Image URL', 'hubspot-events-connector'),
            'language' => __('Language', 'hubspot-events-connector'),
            'domain' => __('Domain', 'hubspot-events-connector'),
            'slug' => __('Slug', 'hubspot-events-connector'),
            'state' => __('State (draft/published)', 'hubspot-events-connector'),
            'publishDate' => __('Publish Date', 'hubspot-events-connector'),
            'createdAt' => __('Created At', 'hubspot-events-connector'),
            'updatedAt' => __('Updated At', 'hubspot-events-connector'),
            '_event_datetime' => __('Event Date/Time (parsed)', 'hubspot-events-connector'),
            '_event_date' => __('Event Date (parsed)', 'hubspot-events-connector'),
            '_event_time' => __('Event Time (parsed)', 'hubspot-events-connector'),
            '_event_location' => __('Event Location (parsed)', 'hubspot-events-connector'),
            '_event_description' => __('Event Description (parsed)', 'hubspot-events-connector'),
        ];

        // WordPress target fields
        $wp_fields = [
            'post_title' => __('Post Title', 'hubspot-events-connector'),
            'post_content' => __('Post Content', 'hubspot-events-connector'),
            'post_excerpt' => __('Post Excerpt', 'hubspot-events-connector'),
            '_hsec_event_url' => __('Event URL (meta)', 'hubspot-events-connector'),
            '_hsec_start_datetime' => __('Start Date/Time (meta)', 'hubspot-events-connector'),
            '_hsec_end_datetime' => __('End Date/Time (meta)', 'hubspot-events-connector'),
            '_hsec_event_location' => __('Event Location (meta)', 'hubspot-events-connector'),
            '_hsec_event_type' => __('Event Type (meta)', 'hubspot-events-connector'),
            '_hsec_event_organizer' => __('Event Organizer (meta)', 'hubspot-events-connector'),
            '_hsec_custom_1' => __('Custom Field 1 (meta)', 'hubspot-events-connector'),
            '_hsec_custom_2' => __('Custom Field 2 (meta)', 'hubspot-events-connector'),
            '_hsec_custom_3' => __('Custom Field 3 (meta)', 'hubspot-events-connector'),
        ];
        ?>
        <div class="hsec-field-mappings">
            <table class="widefat hsec-mappings-table">
                <thead>
                    <tr>
                        <th><?php _e('HubSpot Field', 'hubspot-events-connector'); ?></th>
                        <th><?php _e('WordPress Field', 'hubspot-events-connector'); ?></th>
                        <th><?php _e('Action', 'hubspot-events-connector'); ?></th>
                    </tr>
                </thead>
                <tbody id="hsec-mappings-body">
                    <?php if (empty($mappings)): ?>
                        <tr class="hsec-mapping-row hsec-no-mappings">
                            <td colspan="3">
                                <em><?php _e('No custom mappings. Default mappings will be used.', 'hubspot-events-connector'); ?></em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mappings as $i => $mapping): ?>
                            <tr class="hsec-mapping-row">
                                <td>
                                    <select name="hsec_field_mappings[<?php echo $i; ?>][hubspot]">
                                        <option value=""><?php _e('Select...', 'hubspot-events-connector'); ?></option>
                                        <?php foreach ($hubspot_fields as $key => $label): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($mapping['hubspot'], $key); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="hsec_field_mappings[<?php echo $i; ?>][wordpress]">
                                        <option value=""><?php _e('Select...', 'hubspot-events-connector'); ?></option>
                                        <?php foreach ($wp_fields as $key => $label): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($mapping['wordpress'], $key); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="button hsec-remove-mapping">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button hsec-add-mapping">
                    <?php _e('+ Add Mapping', 'hubspot-events-connector'); ?>
                </button>
            </p>

            <script type="text/template" id="hsec-mapping-template">
                <tr class="hsec-mapping-row">
                    <td>
                        <select name="hsec_field_mappings[{{INDEX}}][hubspot]">
                            <option value=""><?php _e('Select...', 'hubspot-events-connector'); ?></option>
                            <?php foreach ($hubspot_fields as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="hsec_field_mappings[{{INDEX}}][wordpress]">
                            <option value=""><?php _e('Select...', 'hubspot-events-connector'); ?></option>
                            <?php foreach ($wp_fields as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="button hsec-remove-mapping">&times;</button>
                    </td>
                </tr>
            </script>
        </div>
        <?php
    }

    /**
     * Render API section description
     */
    public function render_api_section_description() {
        ?>
        <p>
            <?php _e('Configure your HubSpot Private App credentials to enable event synchronization.', 'hubspot-events-connector'); ?>
        </p>
        <p>
            <a href="https://developers.hubspot.com/docs/api/private-apps" target="_blank">
                <?php _e('Learn how to create a Private App in HubSpot', 'hubspot-events-connector'); ?> &rarr;
            </a>
        </p>
        <p class="description">
            <?php _e('Required scopes: crm.objects.marketing_events.read, content (for Landing Pages)', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render sync section description
     */
    public function render_sync_section_description() {
        ?>
        <p>
            <?php _e('Configure how and when events are synchronized from HubSpot.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render data source field
     */
    public function render_data_source_field() {
        $source = get_option('hsec_data_source', 'landing_pages');
        $options = [
            'landing_pages' => __('Landing Pages (webinars)', 'hubspot-events-connector'),
            'marketing_events' => __('Marketing Events', 'hubspot-events-connector'),
        ];
        ?>
        <select name="hsec_data_source" id="hsec_data_source">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($source, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Landing Pages: Imports webinars from HubSpot landing pages (with images). Marketing Events: Uses HubSpot Marketing Events API.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render filter keyword field
     */
    public function render_filter_keyword_field() {
        $keyword = get_option('hsec_filter_keyword', 'webinar');
        ?>
        <input type="text"
               name="hsec_filter_keyword"
               id="hsec_filter_keyword"
               value="<?php echo esc_attr($keyword); ?>"
               class="regular-text"
               placeholder="webinar">
        <p class="description">
            <?php _e('Only import landing pages containing this keyword in URL, name or title. Leave empty to import all. Works only with Landing Pages source.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render language filter field
     */
    public function render_language_filter_field() {
        $language = get_option('hsec_language_filter', '');
        ?>
        <input type="text"
               name="hsec_language_filter"
               id="hsec_language_filter"
               value="<?php echo esc_attr($language); ?>"
               class="regular-text"
               placeholder="pl">
        <p class="description">
            <?php _e('Filter by language codes, comma-separated (e.g., pl,en,hu). Leave empty to import ALL languages.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize data source
     */
    public function sanitize_data_source($value) {
        $valid = ['landing_pages', 'marketing_events'];
        return in_array($value, $valid) ? $value : 'landing_pages';
    }

    /**
     * Render API token field
     */
    public function render_api_token_field() {
        $token = get_option('hsec_api_token', '');
        $is_configured = !empty($token);
        ?>
        <div class="hsec-api-token-wrapper">
            <input type="password"
                   name="hsec_api_token"
                   id="hsec_api_token"
                   value="<?php echo esc_attr($token); ?>"
                   class="regular-text"
                   placeholder="pat-na1-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                   autocomplete="new-password">

            <button type="button" class="button hsec-toggle-visibility" data-target="hsec_api_token">
                <span class="dashicons dashicons-visibility"></span>
            </button>

            <button type="button" class="button hsec-test-connection">
                <?php _e('Test Connection', 'hubspot-events-connector'); ?>
            </button>

            <span class="hsec-connection-status"></span>
        </div>
        <p class="description">
            <?php _e('Enter your HubSpot Private App access token. This token will be stored securely.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render sync interval field
     */
    public function render_sync_interval_field() {
        $interval = get_option('hsec_sync_interval', 'hourly');
        $intervals = [
            'every_15_minutes' => __('Every 15 minutes', 'hubspot-events-connector'),
            'every_30_minutes' => __('Every 30 minutes', 'hubspot-events-connector'),
            'hourly' => __('Hourly', 'hubspot-events-connector'),
            'twicedaily' => __('Twice Daily', 'hubspot-events-connector'),
            'daily' => __('Daily', 'hubspot-events-connector'),
            'manual' => __('Manual Only', 'hubspot-events-connector'),
        ];
        ?>
        <select name="hsec_sync_interval" id="hsec_sync_interval">
            <?php foreach ($intervals as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($interval, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('How often to automatically sync events from HubSpot. More frequent syncing uses more API calls.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render auto taxonomies field
     */
    public function render_auto_taxonomies_field() {
        $auto = get_option('hsec_auto_taxonomies', true);
        ?>
        <label>
            <input type="checkbox"
                   name="hsec_auto_taxonomies"
                   value="1"
                   <?php checked($auto, true); ?>>
            <?php _e('Automatically create taxonomies from event custom fields', 'hubspot-events-connector'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, the plugin will analyze HubSpot events and automatically create WordPress taxonomies for fields that look like categories (e.g., event_category, track, theme).', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render event status filter field
     */
    public function render_event_status_filter_field() {
        $filter = get_option('hsec_event_status_filter', 'all');
        $options = [
            'all' => __('All Events (past + upcoming)', 'hubspot-events-connector'),
            'upcoming' => __('Upcoming Only', 'hubspot-events-connector'),
            'past' => __('Past Only', 'hubspot-events-connector'),
        ];
        ?>
        <select name="hsec_event_status_filter" id="hsec_event_status_filter">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($filter, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Choose which events to import based on their status. Past events have eventStatus=PAST, upcoming have eventStatus=STARTED or no status.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render image property field
     */
    public function render_image_property_field() {
        $property = get_option('hsec_image_property', '');
        ?>
        <input type="text"
               name="hsec_image_property"
               id="hsec_image_property"
               value="<?php echo esc_attr($property); ?>"
               class="regular-text"
               placeholder="event_image_url">
        <p class="description">
            <?php _e('If you have a custom property in HubSpot storing image URLs, enter its name here (e.g., event_image_url). The image will be downloaded and set as featured image.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize event status filter
     */
    public function sanitize_event_status_filter($value) {
        $valid = ['all', 'upcoming', 'past'];
        return in_array($value, $valid) ? $value : 'all';
    }

    /**
     * Sanitize sync interval
     */
    public function sanitize_sync_interval($value) {
        $valid = ['every_15_minutes', 'every_30_minutes', 'hourly', 'twicedaily', 'daily', 'manual'];

        if (!in_array($value, $valid)) {
            return 'hourly';
        }

        // Reschedule cron if changed
        $old_value = get_option('hsec_sync_interval', 'hourly');
        if ($value !== $old_value) {
            HSEC_Sync_Manager::instance()->reschedule_cron($value);
        }

        return $value;
    }

    /**
     * Render main settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap hsec-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e('Quick Actions', 'hubspot-events-connector'); ?></h2>

            <div class="hsec-quick-actions">
                <button type="button" class="button button-primary hsec-sync-now">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Sync Now (Incremental)', 'hubspot-events-connector'); ?>
                </button>

                <button type="button" class="button hsec-full-sync">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Full Sync', 'hubspot-events-connector'); ?>
                </button>

                <a href="<?php echo admin_url('edit.php?post_type=hs_event'); ?>" class="button">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('View Events', 'hubspot-events-connector'); ?>
                </a>

                <button type="button" class="button hsec-delete-all-events">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Delete All Events', 'hubspot-events-connector'); ?>
                </button>

                <button type="button" class="button hsec-fetch-missing-images">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php _e('Fetch Missing Images', 'hubspot-events-connector'); ?>
                </button>
            </div>

            <div class="hsec-sync-progress" style="display: none;">
                <span class="spinner is-active"></span>
                <span class="hsec-progress-text"></span>
                <button type="button" class="button hsec-stop-sync" style="display: none; margin-left: 10px;">
                    <span class="dashicons dashicons-no"></span>
                    <?php _e('Stop Sync', 'hubspot-events-connector'); ?>
                </button>
            </div>

            <div class="hsec-sync-result" style="display: none;"></div>
        </div>
        <?php
    }

    /**
     * Render sync status page
     */
    public function render_sync_status_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $sync_manager = HSEC_Sync_Manager::instance();
        $stats = $sync_manager->get_sync_stats();
        $taxonomy_manager = HSEC_Taxonomy_Manager::instance();
        $taxonomy_stats = $taxonomy_manager->get_taxonomy_stats();
        $attention = $sync_manager->get_events_needing_attention();
        ?>
        <div class="wrap hsec-status-wrap">
            <h1><?php _e('HubSpot Events Sync Status', 'hubspot-events-connector'); ?></h1>

            <!-- Overview Cards -->
            <div class="hsec-status-cards">
                <div class="hsec-card">
                    <div class="hsec-card-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="hsec-card-content">
                        <span class="hsec-card-number"><?php echo (int) $stats['total_events']; ?></span>
                        <span class="hsec-card-label"><?php _e('Published Events', 'hubspot-events-connector'); ?></span>
                    </div>
                </div>

                <div class="hsec-card">
                    <div class="hsec-card-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="hsec-card-content">
                        <span class="hsec-card-text"><?php echo esc_html($stats['last_sync_formatted']); ?></span>
                        <span class="hsec-card-label"><?php _e('Last Sync', 'hubspot-events-connector'); ?></span>
                    </div>
                </div>

                <div class="hsec-card">
                    <div class="hsec-card-icon">
                        <span class="dashicons dashicons-<?php echo $stats['is_running'] ? 'update-alt' : 'yes-alt'; ?>"></span>
                    </div>
                    <div class="hsec-card-content">
                        <span class="hsec-card-text">
                            <?php echo $stats['is_running']
                                ? __('Running...', 'hubspot-events-connector')
                                : __('Idle', 'hubspot-events-connector'); ?>
                        </span>
                        <span class="hsec-card-label"><?php _e('Sync Status', 'hubspot-events-connector'); ?></span>
                        <?php if ($stats['is_running']): ?>
                            <button type="button" class="button button-small hsec-force-stop" style="margin-top: 5px;">
                                <?php _e('Force Stop', 'hubspot-events-connector'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hsec-card">
                    <div class="hsec-card-icon">
                        <span class="dashicons dashicons-backup"></span>
                    </div>
                    <div class="hsec-card-content">
                        <span class="hsec-card-text">
                            <?php
                            if ($stats['next_scheduled']) {
                                echo esc_html(date_i18n(get_option('time_format'), $stats['next_scheduled']));
                            } else {
                                _e('Not scheduled', 'hubspot-events-connector');
                            }
                            ?>
                        </span>
                        <span class="hsec-card-label"><?php _e('Next Sync', 'hubspot-events-connector'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Last Sync Result -->
            <?php if (!empty($stats['last_result'])): ?>
                <div class="hsec-section">
                    <h2><?php _e('Last Sync Result', 'hubspot-events-connector'); ?></h2>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Sync Type', 'hubspot-events-connector'); ?></th>
                            <td><?php echo esc_html($stats['last_result']['sync_type'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Events Fetched', 'hubspot-events-connector'); ?></th>
                            <td><?php echo (int) ($stats['last_result']['total_fetched'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Created', 'hubspot-events-connector'); ?></th>
                            <td><?php echo (int) ($stats['last_result']['created'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Updated', 'hubspot-events-connector'); ?></th>
                            <td><?php echo (int) ($stats['last_result']['updated'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Skipped (unchanged)', 'hubspot-events-connector'); ?></th>
                            <td><?php echo (int) ($stats['last_result']['skipped'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Duration', 'hubspot-events-connector'); ?></th>
                            <td><?php echo (int) ($stats['last_result']['duration'] ?? 0); ?>s</td>
                        </tr>
                        <?php if (!empty($stats['last_result']['errors'])): ?>
                            <tr>
                                <th><?php _e('Errors', 'hubspot-events-connector'); ?></th>
                                <td class="hsec-errors">
                                    <?php foreach ($stats['last_result']['errors'] as $error): ?>
                                        <div class="hsec-error"><?php echo esc_html($error); ?></div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Taxonomies -->
            <div class="hsec-section">
                <h2><?php _e('Registered Taxonomies', 'hubspot-events-connector'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Taxonomy', 'hubspot-events-connector'); ?></th>
                            <th><?php _e('Terms', 'hubspot-events-connector'); ?></th>
                            <th><?php _e('Hierarchical', 'hubspot-events-connector'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taxonomy_stats as $name => $tax): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($tax['label']); ?></strong>
                                    <br><code><?php echo esc_html($name); ?></code>
                                </td>
                                <td><?php echo (int) $tax['term_count']; ?></td>
                                <td><?php echo $tax['hierarchical'] ? __('Yes', 'hubspot-events-connector') : __('No', 'hubspot-events-connector'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Attention Needed -->
            <?php if (!empty($attention['orphaned']) || !empty($attention['stale'])): ?>
                <div class="hsec-section hsec-attention">
                    <h2><?php _e('Attention Needed', 'hubspot-events-connector'); ?></h2>

                    <?php if (!empty($attention['orphaned'])): ?>
                        <div class="notice notice-warning inline">
                            <p>
                                <strong><?php echo count($attention['orphaned']); ?></strong>
                                <?php _e('events are missing HubSpot IDs (may have been created manually).', 'hubspot-events-connector'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($attention['stale'])): ?>
                        <div class="notice notice-info inline">
                            <p>
                                <strong><?php echo count($attention['stale']); ?></strong>
                                <?php _e('events have not been synced in over 7 days.', 'hubspot-events-connector'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="hsec-section">
                <h2><?php _e('Actions', 'hubspot-events-connector'); ?></h2>
                <p>
                    <button type="button" class="button button-primary hsec-sync-now">
                        <?php _e('Run Incremental Sync', 'hubspot-events-connector'); ?>
                    </button>
                    <button type="button" class="button hsec-full-sync">
                        <?php _e('Run Full Sync', 'hubspot-events-connector'); ?>
                    </button>
                </p>
                <div class="hsec-sync-progress" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span class="hsec-progress-text"></span>
                    <button type="button" class="button hsec-stop-sync" style="display: none; margin-left: 10px;">
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('Stop Sync', 'hubspot-events-connector'); ?>
                    </button>
                </div>
                <div class="hsec-sync-result" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Activation notice
        if (get_transient('hsec_activation_notice')) {
            delete_transient('hsec_activation_notice');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('HubSpot Events Connector activated!', 'hubspot-events-connector'); ?></strong>
                    <?php _e('Please configure your API token to start syncing events.', 'hubspot-events-connector'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=' . self::PAGE_SLUG); ?>">
                        <?php _e('Go to Settings', 'hubspot-events-connector'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        // First sync complete notice
        $first_sync_data = get_transient('hsec_first_sync_complete');
        if ($first_sync_data) {
            delete_transient('hsec_first_sync_complete');
            $events_count = $first_sync_data['created'] ?? 0;
            $taxonomies_count = $first_sync_data['taxonomies_created'] ?? 0;
            $duration = $first_sync_data['duration'] ?? 0;
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('First synchronization complete!', 'hubspot-events-connector'); ?></strong><br>
                    <?php
                    printf(
                        __('Successfully imported %d events from HubSpot in %d seconds.', 'hubspot-events-connector'),
                        (int) $events_count,
                        (int) $duration
                    );
                    if ($taxonomies_count > 0) {
                        echo ' ';
                        printf(
                            __('%d taxonomies were automatically created.', 'hubspot-events-connector'),
                            (int) $taxonomies_count
                        );
                    }
                    ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=hs_event'); ?>" class="button button-primary">
                        <?php _e('View Events', 'hubspot-events-connector'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=hsec-sync-status'); ?>" class="button">
                        <?php _e('Sync Status', 'hubspot-events-connector'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        // API not configured warning (only on our pages)
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'hs_event') !== false) {
            $api = HSEC_API_Client::instance();
            if (!$api->is_configured()) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('HubSpot API not configured.', 'hubspot-events-connector'); ?></strong>
                        <?php _e('Events cannot be synced until you add your API token.', 'hubspot-events-connector'); ?>
                        <a href="<?php echo admin_url('options-general.php?page=' . self::PAGE_SLUG); ?>">
                            <?php _e('Configure now', 'hubspot-events-connector'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
}
