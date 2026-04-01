<?php
/**
 * Clear Filters Button Widget for HubSpot Events
 *
 * Shows a "Clear all filters" button when any filter is active.
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class HSEC_Clear_Filters_Widget extends Widget_Base {

    public function get_name() {
        return 'hsec-clear-filters';
    }

    public function get_title() {
        return __('Events Clear Filters', 'hsec-elementor-filters');
    }

    public function get_icon() {
        return 'eicon-close-circle';
    }

    public function get_categories() {
        return ['hsec-filters'];
    }

    public function get_keywords() {
        return ['clear', 'reset', 'filter', 'hubspot', 'events'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'label',
            [
                'label' => __('Button Label', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Clear Filters', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'show_icon',
            [
                'label' => __('Show Icon', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'hsec-elementor-filters'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .hsec-clear-filters',
            ]
        );

        $this->start_controls_tabs('color_tabs');

        $this->start_controls_tab('tab_normal', ['label' => __('Normal', 'hsec-elementor-filters')]);
        $this->add_control('text_color', [
            'label' => __('Color', 'hsec-elementor-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hsec-clear-filters' => 'color: {{VALUE}}; border-color: {{VALUE}};'],
        ]);
        $this->add_control('bg_color', [
            'label' => __('Background', 'hsec-elementor-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hsec-clear-filters' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_hover', ['label' => __('Hover', 'hsec-elementor-filters')]);
        $this->add_control('text_color_hover', [
            'label' => __('Color', 'hsec-elementor-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hsec-clear-filters:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('bg_color_hover', [
            'label' => __('Background', 'hsec-elementor-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hsec-clear-filters:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('padding', [
            'label' => __('Padding', 'hsec-elementor-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'separator' => 'before',
            'selectors' => [
                '{{WRAPPER}} .hsec-clear-filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('border_radius', [
            'label' => __('Border Radius', 'hsec-elementor-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .hsec-clear-filters' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $show_icon = $settings['show_icon'] === 'yes';

        // Check if any filter params are in URL
        $has_filters = !empty($_GET['hsec_lang']) || !empty($_GET['hsec_category']) ||
                       !empty($_GET['hsec_date_filter']) || !empty($_GET['hsec_ondemand']);

        $visible_class = $has_filters ? ' hsec-clear-visible' : '';

        // In editor, always show
        $is_editor = false;
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance !== null) {
            $editor = \Elementor\Plugin::$instance->editor;
            if ($editor !== null && method_exists($editor, 'is_edit_mode')) {
                $is_editor = $editor->is_edit_mode();
            }
        }
        if ($is_editor) {
            $visible_class = ' hsec-clear-visible';
        }
        ?>
        <button type="button" class="hsec-clear-filters<?php echo $visible_class; ?>">
            <?php if ($show_icon): ?>
                <span class="hsec-clear-icon">✕</span>
            <?php endif; ?>
            <span class="hsec-clear-label"><?php echo esc_html($settings['label']); ?></span>
        </button>
        <?php
    }
}
