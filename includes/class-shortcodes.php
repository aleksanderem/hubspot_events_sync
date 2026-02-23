<?php
/**
 * Shortcodes for HubSpot Events
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

/**
 * HubSpot Events Language Flag Shortcode
 */
class HSEC_Language_Flag_Shortcode {

    private static $instance = null;

    /**
     * Language configuration - ISO 639-1 codes mapped to flag emoji and labels
     */
    private const LANGUAGES = [
        'pl' => ['flag' => '🇵🇱', 'name' => 'PL', 'full' => 'Polski'],
        'en' => ['flag' => '🇬🇧', 'name' => 'EN', 'full' => 'English'],
        'hu' => ['flag' => '🇭🇺', 'name' => 'HU', 'full' => 'Magyar'],
        'de' => ['flag' => '🇩🇪', 'name' => 'DE', 'full' => 'Deutsch'],
        'cs' => ['flag' => '🇨🇿', 'name' => 'CZ', 'full' => 'Čeština'],
        'sk' => ['flag' => '🇸🇰', 'name' => 'SK', 'full' => 'Slovenčina'],
        'ro' => ['flag' => '🇷🇴', 'name' => 'RO', 'full' => 'Română'],
        'fr' => ['flag' => '🇫🇷', 'name' => 'FR', 'full' => 'Français'],
        'es' => ['flag' => '🇪🇸', 'name' => 'ES', 'full' => 'Español'],
        'it' => ['flag' => '🇮🇹', 'name' => 'IT', 'full' => 'Italiano'],
        'nl' => ['flag' => '🇳🇱', 'name' => 'NL', 'full' => 'Nederlands'],
        'pt' => ['flag' => '🇵🇹', 'name' => 'PT', 'full' => 'Português'],
        'ru' => ['flag' => '🇷🇺', 'name' => 'RU', 'full' => 'Русский'],
        'uk' => ['flag' => '🇺🇦', 'name' => 'UA', 'full' => 'Українська'],
        'bg' => ['flag' => '🇧🇬', 'name' => 'BG', 'full' => 'Български'],
        'hr' => ['flag' => '🇭🇷', 'name' => 'HR', 'full' => 'Hrvatski'],
        'sl' => ['flag' => '🇸🇮', 'name' => 'SI', 'full' => 'Slovenščina'],
        'sr' => ['flag' => '🇷🇸', 'name' => 'RS', 'full' => 'Српски'],
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
        add_shortcode('hsec_language_flag', [$this, 'render_shortcode']);
        add_action('wp_head', [$this, 'inline_styles']);
    }

    /**
     * Get language data by code
     *
     * @param string $code Language code (ISO 639-1)
     * @return array|null Language data or null if not found
     */
    public function get_language($code) {
        $code = strtolower(trim($code));
        return self::LANGUAGES[$code] ?? null;
    }

    /**
     * Render the shortcode
     *
     * Attributes:
     * - post_id: (int) Post ID. Defaults to current post.
     * - show_name: (bool) Whether to show short language name (e.g., "PL"). Default: true
     * - show_full_name: (bool) Whether to show full language name (e.g., "Polski"). Default: false
     * - class: (string) Additional CSS class for the wrapper
     * - fallback: (string) Text to show when language is not set. Default: empty
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id'        => 0,
            'show_name'      => 'true',
            'show_full_name' => 'false',
            'class'          => '',
            'fallback'       => '',
        ], $atts, 'hsec_language_flag');

        // Get post ID
        $post_id = absint($atts['post_id']);
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return $atts['fallback'];
        }

        // Check if it's an hs_event post type
        if (get_post_type($post_id) !== 'hs_event') {
            return $atts['fallback'];
        }

        // Get language from post meta
        $language_code = get_post_meta($post_id, '_hsec_language', true);

        if (empty($language_code)) {
            return esc_html($atts['fallback']);
        }

        // Get language data
        $language = $this->get_language($language_code);

        if (!$language) {
            // Unknown language - just show the code
            return sprintf(
                '<span class="hsec-language-flag %s"><span class="hsec-lang-code">%s</span></span>',
                esc_attr($atts['class']),
                esc_html(strtoupper($language_code))
            );
        }

        // Parse boolean attributes
        $show_name = filter_var($atts['show_name'], FILTER_VALIDATE_BOOLEAN);
        $show_full_name = filter_var($atts['show_full_name'], FILTER_VALIDATE_BOOLEAN);

        // Build output
        $output = sprintf(
            '<span class="hsec-language-flag %s" title="%s">',
            esc_attr($atts['class']),
            esc_attr($language['full'])
        );

        // Always show flag
        $output .= sprintf('<span class="hsec-flag">%s</span>', $language['flag']);

        // Show name if requested
        if ($show_name && !$show_full_name) {
            $output .= sprintf('<span class="hsec-lang-name">%s</span>', esc_html($language['name']));
        }

        // Show full name if requested (overrides show_name)
        if ($show_full_name) {
            $output .= sprintf('<span class="hsec-lang-name hsec-lang-full">%s</span>', esc_html($language['full']));
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Add inline styles for the flag shortcode
     */
    public function inline_styles() {
        if (!is_singular('hs_event') && !has_shortcode(get_the_content(), 'hsec_language_flag')) {
            return;
        }
        ?>
        <style id="hsec-language-flag-styles">
            .hsec-language-flag {
                display: inline-flex;
                align-items: center;
                gap: 0.35em;
                font-size: inherit;
                line-height: 1;
            }
            .hsec-language-flag .hsec-flag {
                font-size: 1.2em;
                line-height: 1;
            }
            .hsec-language-flag .hsec-lang-name {
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .hsec-language-flag .hsec-lang-full {
                text-transform: none;
            }
            .hsec-language-flag .hsec-lang-code {
                font-weight: 500;
                text-transform: uppercase;
            }
        </style>
        <?php
    }

    /**
     * Get all supported languages
     *
     * @return array
     */
    public static function get_supported_languages() {
        return self::LANGUAGES;
    }
}


/**
 * Template function for use in themes
 *
 * @param int|null $post_id Post ID (optional, defaults to current post)
 * @param bool $show_name Whether to show the language name
 * @param bool $echo Whether to echo or return the output
 * @return string|void
 */
function hsec_language_flag($post_id = null, $show_name = true, $echo = true) {
    if (!class_exists('HSEC_Language_Flag_Shortcode')) {
        return '';
    }

    $shortcode = sprintf(
        '[hsec_language_flag post_id="%d" show_name="%s"]',
        $post_id ?: get_the_ID(),
        $show_name ? 'true' : 'false'
    );

    $output = do_shortcode($shortcode);

    if ($echo) {
        echo $output;
    }

    return $output;
}

/**
 * HubSpot Events Date Shortcode
 */
class HSEC_Event_Date_Shortcode {

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
     * Constructor
     */
    private function __construct() {
        add_shortcode('hsec_event_date', [$this, 'render_shortcode']);
        add_action('wp_head', [$this, 'inline_styles']);
    }

    /**
     * Render the shortcode
     *
     * Attributes:
     * - post_id: (int) Post ID. Defaults to current post.
     * - format: (string) PHP date format. Default: WordPress date format
     * - show_icon: (bool) Whether to show calendar icon. Default: true
     * - show_time: (bool) Whether to show time. Default: false
     * - past_label: (string) Label for past events without date. Default: "On-demand"
     * - class: (string) Additional CSS class for the wrapper
     * - icon: (string) Custom icon (HTML/emoji). Default: SVG calendar icon
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id'    => 0,
            'format'     => '',
            'show_icon'  => 'true',
            'show_time'  => 'false',
            'past_label' => '',
            'class'      => '',
            'icon'       => '',
        ], $atts, 'hsec_event_date');

        // Get post ID
        $post_id = absint($atts['post_id']);
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return '';
        }

        // Check if it's an hs_event post type
        if (get_post_type($post_id) !== 'hs_event') {
            return '';
        }

        // Parse boolean attributes
        $show_icon = filter_var($atts['show_icon'], FILTER_VALIDATE_BOOLEAN);
        $show_time = filter_var($atts['show_time'], FILTER_VALIDATE_BOOLEAN);

        // Try to get event date from multiple meta fields
        $event_date = $this->get_event_date($post_id);

        // Determine date format
        $date_format = $atts['format'] ?: get_option('date_format');
        if ($show_time) {
            $date_format .= ' ' . get_option('time_format');
        }

        // Get event language for localized labels
        $language = get_post_meta($post_id, '_hsec_language', true) ?: 'en';

        // Build output
        $css_class = 'hsec-event-date';
        if ($atts['class']) {
            $css_class .= ' ' . esc_attr($atts['class']);
        }

        // If no date found, show past/on-demand label
        if (!$event_date) {
            $past_label = $atts['past_label'] ?: $this->get_past_label($language);
            $css_class .= ' hsec-event-date--past';

            $output = sprintf('<span class="%s">', $css_class);

            if ($show_icon) {
                $output .= $this->get_icon($atts['icon'], 'past');
            }

            $output .= sprintf('<span class="hsec-date-text">%s</span>', esc_html($past_label));
            $output .= '</span>';

            return $output;
        }

        // Check if event is in the past
        $is_past = $event_date < time();
        if ($is_past) {
            $css_class .= ' hsec-event-date--past';
        } else {
            $css_class .= ' hsec-event-date--upcoming';
        }

        $output = sprintf('<span class="%s">', $css_class);

        if ($show_icon) {
            $output .= $this->get_icon($atts['icon'], $is_past ? 'past' : 'upcoming');
        }

        $output .= sprintf(
            '<span class="hsec-date-text">%s</span>',
            esc_html(date_i18n($date_format, $event_date))
        );

        $output .= '</span>';

        return $output;
    }

    /**
     * Get event date from post meta
     *
     * @param int $post_id Post ID
     * @return int|null Unix timestamp or null
     */
    private function get_event_date($post_id) {
        // Priority: start_datetime > event_time > publish_date
        $meta_keys = [
            '_hsec_start_datetime',
            '_hsec_event_time',
        ];

        foreach ($meta_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                $timestamp = strtotime($value);
                if ($timestamp) {
                    return $timestamp;
                }
            }
        }

        return null;
    }

    /**
     * Get localized "past event" label
     *
     * @param string $language Language code
     * @return string
     */
    private function get_past_label($language) {
        $labels = [
            'pl' => 'On-demand',
            'en' => 'On-demand',
            'hu' => 'On-demand',
            'de' => 'On-demand',
            'cs' => 'On-demand',
            'sk' => 'On-demand',
        ];

        return $labels[$language] ?? $labels['en'];
    }

    /**
     * Get calendar icon
     *
     * @param string $custom_icon Custom icon if provided
     * @param string $state 'past' or 'upcoming'
     * @return string HTML
     */
    private function get_icon($custom_icon, $state = 'upcoming') {
        if (!empty($custom_icon)) {
            return sprintf('<span class="hsec-date-icon">%s</span>', $custom_icon);
        }

        // SVG calendar icon
        $svg = '<svg class="hsec-date-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';

        return $svg;
    }

    /**
     * Add inline styles
     */
    public function inline_styles() {
        ?>
        <style id="hsec-event-date-styles">
            .hsec-event-date {
                display: inline-flex;
                align-items: center;
                gap: 0.4em;
                font-size: inherit;
                line-height: 1.2;
            }
            .hsec-event-date .hsec-date-icon {
                flex-shrink: 0;
                width: 1em;
                height: 1em;
            }
            .hsec-event-date .hsec-date-icon svg {
                width: 100%;
                height: 100%;
                display: block;
            }
            .hsec-event-date .hsec-date-text {
                white-space: nowrap;
            }
            .hsec-event-date--past {
                opacity: 0.85;
            }
            .hsec-event-date--past .hsec-date-text {
                font-style: italic;
            }
        </style>
        <?php
    }
}


/**
 * Template function for event date
 *
 * @param int|null $post_id Post ID (optional, defaults to current post)
 * @param bool $show_icon Whether to show the calendar icon
 * @param bool $echo Whether to echo or return the output
 * @return string|void
 */
function hsec_event_date($post_id = null, $show_icon = true, $echo = true) {
    if (!class_exists('HSEC_Event_Date_Shortcode')) {
        return '';
    }

    $shortcode = sprintf(
        '[hsec_event_date post_id="%d" show_icon="%s"]',
        $post_id ?: get_the_ID(),
        $show_icon ? 'true' : 'false'
    );

    $output = do_shortcode($shortcode);

    if ($echo) {
        echo $output;
    }

    return $output;
}

/**
 * HubSpot Events Meta Shortcode
 *
 * Retrieves any field from _hsec_raw_data
 */
class HSEC_Meta_Shortcode {

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
     * Constructor
     */
    private function __construct() {
        add_shortcode('hsec_meta', [$this, 'render_shortcode']);
    }

    /**
     * Render the shortcode
     *
     * Attributes:
     * - post_id: (int) Post ID. Defaults to current post.
     * - key: (string) Key to retrieve from raw_data (e.g., "metaDescription", "authorName")
     * - fallback: (string) Fallback text if key not found or empty
     * - strip_tags: (bool) Whether to strip HTML tags. Default: true
     * - truncate: (int) Max characters. 0 = no truncate. Default: 0
     * - class: (string) Additional CSS class for the wrapper
     * - wrapper: (string) HTML wrapper tag (span, p, div). Default: none
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id'    => 0,
            'key'        => 'metaDescription',
            'fallback'   => '',
            'strip_tags' => 'true',
            'truncate'   => 0,
            'class'      => '',
            'wrapper'    => '',
        ], $atts, 'hsec_meta');

        // Get post ID
        $post_id = absint($atts['post_id']);
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return esc_html($atts['fallback']);
        }

        // Check if it's an hs_event post type
        if (get_post_type($post_id) !== 'hs_event') {
            return esc_html($atts['fallback']);
        }

        // Get raw data
        $raw_data = get_post_meta($post_id, '_hsec_raw_data', true);

        if (empty($raw_data)) {
            return esc_html($atts['fallback']);
        }

        // Handle serialized data
        if (is_string($raw_data)) {
            $raw_data = maybe_unserialize($raw_data);
        }

        if (!is_array($raw_data)) {
            return esc_html($atts['fallback']);
        }

        // Get the requested key (supports dot notation for nested keys)
        $value = $this->get_nested_value($raw_data, $atts['key']);

        if ($value === null || $value === '') {
            return esc_html($atts['fallback']);
        }

        // Convert to string if array/object
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        // Strip tags if requested
        $strip_tags = filter_var($atts['strip_tags'], FILTER_VALIDATE_BOOLEAN);
        if ($strip_tags) {
            $value = wp_strip_all_tags($value);
        }

        // Truncate if requested
        $truncate = absint($atts['truncate']);
        if ($truncate > 0 && mb_strlen($value) > $truncate) {
            $value = mb_substr($value, 0, $truncate) . '...';
        }

        // Build output
        $output = esc_html($value);

        // Add wrapper if specified
        $allowed_wrappers = ['span', 'p', 'div'];
        $wrapper = strtolower($atts['wrapper']);

        if (in_array($wrapper, $allowed_wrappers)) {
            $class_attr = $atts['class'] ? sprintf(' class="%s"', esc_attr($atts['class'])) : '';
            $output = sprintf('<%s%s>%s</%s>', $wrapper, $class_attr, $output, $wrapper);
        } elseif ($atts['class']) {
            // If class but no wrapper, wrap in span
            $output = sprintf('<span class="%s">%s</span>', esc_attr($atts['class']), $output);
        }

        return $output;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $array Source array
     * @param string $key Key with optional dot notation (e.g., "author.name")
     * @return mixed|null Value or null if not found
     */
    private function get_nested_value($array, $key) {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }

        return $value;
    }
}


/**
 * Template function for meta data
 *
 * @param string $key Key to retrieve (e.g., "metaDescription")
 * @param int|null $post_id Post ID (optional, defaults to current post)
 * @param string $fallback Fallback text
 * @param bool $echo Whether to echo or return the output
 * @return string|void
 */
function hsec_meta($key = 'metaDescription', $post_id = null, $fallback = '', $echo = true) {
    if (!class_exists('HSEC_Meta_Shortcode')) {
        return '';
    }

    $shortcode = sprintf(
        '[hsec_meta key="%s" post_id="%d" fallback="%s"]',
        esc_attr($key),
        $post_id ?: get_the_ID(),
        esc_attr($fallback)
    );

    $output = do_shortcode($shortcode);

    if ($echo) {
        echo $output;
    }

    return $output;
}
