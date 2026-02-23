<?php
/**
 * Language Filter Widget for HubSpot Events
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class HSEC_Language_Filter_Widget extends Widget_Base {

    public function get_name() {
        return 'hsec-language-filter';
    }

    public function get_title() {
        return __('Events Language Filter', 'hsec-elementor-filters');
    }

    public function get_icon() {
        return 'eicon-globe';
    }

    public function get_categories() {
        return ['hsec-filters'];
    }

    public function get_keywords() {
        return ['filter', 'language', 'hubspot', 'events', 'flag'];
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'display_type',
            [
                'label' => __('Display Type', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SELECT,
                'default' => 'buttons',
                'options' => [
                    'buttons' => __('Buttons', 'hsec-elementor-filters'),
                    'dropdown' => __('Dropdown', 'hsec-elementor-filters'),
                    'flags' => __('Flags Only', 'hsec-elementor-filters'),
                ],
            ]
        );

        $this->add_control(
            'show_flags',
            [
                'label' => __('Show Flags', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_label',
            [
                'label' => __('Show Language Code', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'display_type!' => 'flags',
                ],
            ]
        );

        $this->add_control(
            'show_full_name',
            [
                'label' => __('Show Full Name', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => [
                    'display_type' => 'dropdown',
                ],
            ]
        );

        $this->add_control(
            'show_all_option',
            [
                'label' => __('Show "All" Option', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'all_label',
            [
                'label' => __('All Label', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('All Languages', 'hsec-elementor-filters'),
                'condition' => [
                    'show_all_option' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'dropdown_placeholder',
            [
                'label' => __('Dropdown Placeholder', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Select Language', 'hsec-elementor-filters'),
                'condition' => [
                    'display_type' => 'dropdown',
                ],
            ]
        );

        $this->add_control(
            'default_language',
            [
                'label' => __('Default Language', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SELECT,
                'default' => 'pl',
                'options' => [
                    '' => __('All (no filter)', 'hsec-elementor-filters'),
                    'pl' => __('Polish (PL)', 'hsec-elementor-filters'),
                    'en' => __('English (EN)', 'hsec-elementor-filters'),
                    'hu' => __('Hungarian (HU)', 'hsec-elementor-filters'),
                    'cs' => __('Czech (CS)', 'hsec-elementor-filters'),
                    'sk' => __('Slovak (SK)', 'hsec-elementor-filters'),
                    'de' => __('German (DE)', 'hsec-elementor-filters'),
                    'ro' => __('Romanian (RO)', 'hsec-elementor-filters'),
                ],
                'description' => __('Language selected by default when page loads', 'hsec-elementor-filters'),
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();

        // Style Section - Buttons
        $this->start_controls_section(
            'section_style_buttons',
            [
                'label' => __('Buttons Style', 'hsec-elementor-filters'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'display_type' => ['buttons', 'flags'],
                ],
            ]
        );

        $this->add_responsive_control(
            'buttons_gap',
            [
                'label' => __('Gap', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 50],
                ],
                'default' => ['size' => 10, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-filter-buttons' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hsec-lang-btn',
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab('button_normal', ['label' => __('Normal', 'hsec-elementor-filters')]);

        $this->add_control(
            'button_color',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_bg',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('button_active', ['label' => __('Active', 'hsec-elementor-filters')]);

        $this->add_control(
            'button_color_active',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-btn.active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_bg_active',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-btn.active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .hsec-lang-btn',
            ]
        );

        $this->end_controls_section();

        // Style Section - Dropdown
        $this->start_controls_section(
            'section_style_dropdown',
            [
                'label' => __('Dropdown Style', 'hsec-elementor-filters'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'display_type' => 'dropdown',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'dropdown_typography',
                'selector' => '{{WRAPPER}} .hsec-lang-select',
            ]
        );

        $this->add_control(
            'dropdown_color',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'dropdown_bg',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'dropdown_padding',
            [
                'label' => __('Padding', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'dropdown_width',
            [
                'label' => __('Width', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 100, 'max' => 500],
                    '%' => ['min' => 10, 'max' => 100],
                ],
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-select' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'dropdown_border',
                'selector' => '{{WRAPPER}} .hsec-lang-select',
            ]
        );

        $this->add_responsive_control(
            'dropdown_border_radius',
            [
                'label' => __('Border Radius', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-lang-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Safe editor mode detection
        $is_editor = false;
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance !== null) {
            $editor = \Elementor\Plugin::$instance->editor;
            if ($editor !== null && method_exists($editor, 'is_edit_mode')) {
                $is_editor = $editor->is_edit_mode();
            }
        }

        $languages = HSEC_Elementor_Filters::get_available_languages();

        // In editor, show preview with sample languages if none exist
        if (empty($languages) && $is_editor) {
            $languages = ['pl', 'en', 'hu', 'cs', 'de'];
        }

        // Get current language from URL or use default
        $default_lang = $settings['default_language'] ?? 'pl';

        if (isset($_GET[HSEC_Elementor_Filters::QUERY_VAR_LANGUAGE])) {
            $current = sanitize_text_field($_GET[HSEC_Elementor_Filters::QUERY_VAR_LANGUAGE]);
        } else {
            $current = $default_lang;
        }

        // In editor, show default language as active for preview
        if ($is_editor && !isset($_GET[HSEC_Elementor_Filters::QUERY_VAR_LANGUAGE])) {
            $current = $default_lang;
        }

        if (empty($languages)) {
            echo '<div class="hsec-filter-empty">' . __('No languages found in events.', 'hsec-elementor-filters') . '</div>';
            return;
        }

        $this->add_render_attribute('wrapper', 'class', 'hsec-lang-filter');
        $this->add_render_attribute('wrapper', 'data-filter-type', 'language');
        $this->add_render_attribute('wrapper', 'data-default-lang', $default_lang);
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <?php if ($settings['display_type'] === 'dropdown'): ?>
                <?php $this->render_dropdown($settings, $languages, $current); ?>
            <?php else: ?>
                <?php $this->render_buttons($settings, $languages, $current); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_buttons($settings, $languages, $current) {
        $show_flags = $settings['show_flags'] === 'yes';
        $show_label = $settings['show_label'] === 'yes' && $settings['display_type'] !== 'flags';
        ?>
        <div class="hsec-lang-filter-buttons">
            <?php if ($settings['show_all_option'] === 'yes'): ?>
                <button type="button"
                        class="hsec-lang-btn <?php echo empty($current) || $current === 'all' ? 'active' : ''; ?>"
                        data-lang="all">
                    <?php if ($show_flags): ?>
                        <span class="hsec-flag">🌐</span>
                    <?php endif; ?>
                    <?php if ($show_label): ?>
                        <span class="hsec-lang-label"><?php echo esc_html($settings['all_label']); ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <?php foreach ($languages as $lang_code):
                $lang_data = HSEC_Elementor_Filters::get_language_data($lang_code);
                $is_active = $current === $lang_code;
                ?>
                <button type="button"
                        class="hsec-lang-btn <?php echo $is_active ? 'active' : ''; ?>"
                        data-lang="<?php echo esc_attr($lang_code); ?>"
                        title="<?php echo esc_attr($lang_data['full']); ?>">
                    <?php if ($show_flags): ?>
                        <span class="hsec-flag"><?php echo $lang_data['flag']; ?></span>
                    <?php endif; ?>
                    <?php if ($show_label): ?>
                        <span class="hsec-lang-label"><?php echo esc_html($lang_data['name']); ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_dropdown($settings, $languages, $current) {
        $show_flags = $settings['show_flags'] === 'yes';
        $show_full = $settings['show_full_name'] === 'yes';
        ?>
        <select class="hsec-lang-select" data-filter="language">
            <option value=""><?php echo esc_html($settings['dropdown_placeholder']); ?></option>

            <?php if ($settings['show_all_option'] === 'yes'): ?>
                <option value="all" <?php selected($current, 'all'); ?>>
                    <?php if ($show_flags): ?>🌐 <?php endif; ?>
                    <?php echo esc_html($settings['all_label']); ?>
                </option>
            <?php endif; ?>

            <?php foreach ($languages as $lang_code):
                $lang_data = HSEC_Elementor_Filters::get_language_data($lang_code);
                $label = $show_full ? $lang_data['full'] : $lang_data['name'];
                ?>
                <option value="<?php echo esc_attr($lang_code); ?>" <?php selected($current, $lang_code); ?>>
                    <?php if ($show_flags): echo $lang_data['flag'] . ' '; endif; ?>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
