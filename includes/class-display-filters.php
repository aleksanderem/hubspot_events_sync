<?php
/**
 * Display Filters Settings for HubSpot Events
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Display_Filters {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Add section to existing HubSpot Events settings page
        add_settings_section(
            'hsec_display_section',
            __('Display Filtering', 'hubspot-events-connector'),
            [$this, 'render_section_description'],
            'hsec-settings'
        );

        // Exclude Thank You pages
        register_setting('hsec_settings', 'hsec_exclude_thank_you', [
            'type' => 'boolean',
            'default' => false,
        ]);

        add_settings_field(
            'hsec_exclude_thank_you',
            __('Exclude Thank You Pages', 'hubspot-events-connector'),
            [$this, 'render_exclude_thank_you_field'],
            'hsec-settings',
            'hsec_display_section'
        );

        // Require event date
        register_setting('hsec_settings', 'hsec_require_event_date', [
            'type' => 'boolean',
            'default' => false,
        ]);

        add_settings_field(
            'hsec_require_event_date',
            __('Require Event Date', 'hubspot-events-connector'),
            [$this, 'render_require_date_field'],
            'hsec-settings',
            'hsec_display_section'
        );

        // Require event URL
        register_setting('hsec_settings', 'hsec_require_event_url', [
            'type' => 'boolean',
            'default' => false,
        ]);

        add_settings_field(
            'hsec_require_event_url',
            __('Require Event URL', 'hubspot-events-connector'),
            [$this, 'render_require_url_field'],
            'hsec-settings',
            'hsec_display_section'
        );

        // Custom exclude patterns
        register_setting('hsec_settings', 'hsec_exclude_title_patterns', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => [$this, 'sanitize_patterns'],
        ]);

        add_settings_field(
            'hsec_exclude_title_patterns',
            __('Exclude Title Patterns', 'hubspot-events-connector'),
            [$this, 'render_exclude_patterns_field'],
            'hsec-settings',
            'hsec_display_section'
        );
    }

    /**
     * Sanitize patterns and clear cache
     */
    public function sanitize_patterns($value) {
        wp_cache_delete('hsec_excluded_event_ids', 'hsec');
        return sanitize_textarea_field($value);
    }

    /**
     * Section description
     */
    public function render_section_description() {
        ?>
        <p>
            <?php _e('Configure which events should be displayed on the frontend. These filters only affect public display, not the admin area.', 'hubspot-events-connector'); ?>
        </p>
        <?php
        $stats = $this->get_filter_stats();
        if ($stats['total'] > 0) {
            ?>
            <div class="notice notice-info inline" style="margin: 10px 0;">
                <p>
                    <strong><?php _e('Current events:', 'hubspot-events-connector'); ?></strong>
                    <?php echo $stats['total']; ?> total,
                    <?php echo $stats['with_date']; ?> with date,
                    <?php echo $stats['with_url']; ?> with URL,
                    <?php echo $stats['thank_you']; ?> Thank You pages
                </p>
            </div>
            <?php
        }
    }

    /**
     * Get filter statistics
     */
    private function get_filter_stats() {
        global $wpdb;

        $total = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'hs_event' AND post_status = 'publish'
        ");

        $with_date = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'hs_event'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_hsec_start_datetime'
            AND pm.meta_value != ''
        ");

        $with_url = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'hs_event'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_hsec_event_url'
            AND pm.meta_value != ''
        ");

        $thank_you = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'hs_event'
            AND post_status = 'publish'
            AND (
                LOWER(post_title) LIKE '%thank%'
                OR LOWER(post_title) LIKE '%dziękuj%'
                OR LOWER(post_title) LIKE '%dziekuj%'
                OR LOWER(post_title) LIKE '%köszön%'
            )
        ");

        return compact('total', 'with_date', 'with_url', 'thank_you');
    }

    /**
     * Render exclude thank you field
     */
    public function render_exclude_thank_you_field() {
        $value = get_option('hsec_exclude_thank_you', false);
        ?>
        <label>
            <input type="checkbox" name="hsec_exclude_thank_you" value="1" <?php checked($value); ?>>
            <?php _e('Hide events with "Thank You", "Dziękujemy", "Köszönjük" etc. in title', 'hubspot-events-connector'); ?>
        </label>
        <p class="description">
            <?php _e('Excludes confirmation/thank you pages from public display.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render require date field
     */
    public function render_require_date_field() {
        $value = get_option('hsec_require_event_date', false);
        ?>
        <label>
            <input type="checkbox" name="hsec_require_event_date" value="1" <?php checked($value); ?>>
            <?php _e('Only show events that have a start date set', 'hubspot-events-connector'); ?>
        </label>
        <p class="description">
            <?php _e('Hides on-demand events and landing pages without specific dates.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render require URL field
     */
    public function render_require_url_field() {
        $value = get_option('hsec_require_event_url', false);
        ?>
        <label>
            <input type="checkbox" name="hsec_require_event_url" value="1" <?php checked($value); ?>>
            <?php _e('Only show events that have an event URL', 'hubspot-events-connector'); ?>
        </label>
        <p class="description">
            <?php _e('Ensures only events with registration/info links are displayed.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render exclude patterns field
     */
    public function render_exclude_patterns_field() {
        $value = get_option('hsec_exclude_title_patterns', '');
        ?>
        <textarea name="hsec_exclude_title_patterns"
                  rows="4"
                  class="large-text code"
                  placeholder="registration&#10;confirm&#10;success"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Additional words/phrases to exclude (one per line). Events with these in the title will be hidden.', 'hubspot-events-connector'); ?>
        </p>
        <?php
    }
}
