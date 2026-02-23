<?php
/**
 * On-Demand Toggle Widget for HubSpot Events
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class HSEC_OnDemand_Toggle_Widget extends Widget_Base {

    public function get_name() {
        return 'hsec-ondemand-toggle';
    }

    public function get_title() {
        return __('Events On-Demand Toggle', 'hsec-elementor-filters');
    }

    public function get_icon() {
        return 'eicon-toggle';
    }

    public function get_categories() {
        return ['hsec-filters'];
    }

    public function get_keywords() {
        return ['filter', 'toggle', 'switch', 'ondemand', 'hubspot', 'events'];
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
            'label',
            [
                'label' => __('Label', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Show On-Demand', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'label_position',
            [
                'label' => __('Label Position', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SELECT,
                'default' => 'after',
                'options' => [
                    'before' => __('Before', 'hsec-elementor-filters'),
                    'after' => __('After', 'hsec-elementor-filters'),
                ],
            ]
        );

        $this->add_control(
            'default_state',
            [
                'label' => __('Default State', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => __('No Filter', 'hsec-elementor-filters'),
                    '1' => __('Show Only On-Demand', 'hsec-elementor-filters'),
                    '0' => __('Hide On-Demand', 'hsec-elementor-filters'),
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'hsec-elementor-filters'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label' => __('Gap', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 50],
                ],
                'selectors' => [
                    '{{WRAPPER}} .hsec-ondemand-toggle' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .hsec-ondemand-label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Label Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-ondemand-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'heading_switch',
            [
                'label' => __('Switch', 'hsec-elementor-filters'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'switch_width',
            [
                'label' => __('Width', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 30, 'max' => 100],
                ],
                'default' => ['size' => 50, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-switch' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'switch_height',
            [
                'label' => __('Height', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 16, 'max' => 60],
                ],
                'default' => ['size' => 26, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-switch' => 'height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .hsec-switch-slider' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .hsec-switch-slider:before' => 'width: calc({{SIZE}}{{UNIT}} - 6px); height: calc({{SIZE}}{{UNIT}} - 6px);',
                ],
            ]
        );

        $this->add_control(
            'switch_bg_off',
            [
                'label' => __('Background (Off)', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ccc',
                'selectors' => [
                    '{{WRAPPER}} .hsec-switch-slider' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'switch_bg_on',
            [
                'label' => __('Background (On)', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2196F3',
                'selectors' => [
                    '{{WRAPPER}} .hsec-switch input:checked + .hsec-switch-slider' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'switch_knob_color',
            [
                'label' => __('Knob Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'default' => '#fff',
                'selectors' => [
                    '{{WRAPPER}} .hsec-switch-slider:before' => 'background-color: {{VALUE}};',
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

        $current = isset($_GET[HSEC_Elementor_Filters::QUERY_VAR_ONDEMAND])
            ? sanitize_text_field($_GET[HSEC_Elementor_Filters::QUERY_VAR_ONDEMAND])
            : $settings['default_state'];

        // In editor, show as checked for preview
        $is_checked = $current === '1' || $current === 'yes' || $is_editor;

        $this->add_render_attribute('wrapper', 'class', 'hsec-ondemand-toggle');
        $this->add_render_attribute('wrapper', 'class', 'hsec-label-' . $settings['label_position']);
        $this->add_render_attribute('wrapper', 'data-filter-type', 'ondemand');

        if ($is_editor) {
            $this->add_render_attribute('wrapper', 'class', 'hsec-editor-preview');
        }
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <?php if ($settings['label_position'] === 'before'): ?>
                <span class="hsec-ondemand-label"><?php echo esc_html($settings['label']); ?></span>
            <?php endif; ?>

            <label class="hsec-switch">
                <input type="checkbox"
                       class="hsec-ondemand-checkbox"
                       data-filter="ondemand"
                       <?php checked($is_checked); ?>>
                <span class="hsec-switch-slider"></span>
            </label>

            <?php if ($settings['label_position'] === 'after'): ?>
                <span class="hsec-ondemand-label"><?php echo esc_html($settings['label']); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}
