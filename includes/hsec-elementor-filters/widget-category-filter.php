<?php
/**
 * Category Filter Widget for HubSpot Events
 *
 * Filters events by event_category from headHtml data-json metadata.
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class HSEC_Category_Filter_Widget extends Widget_Base {

    public function get_name() {
        return 'hsec-category-filter';
    }

    public function get_title() {
        return __('Events Category Filter', 'hsec-elementor-filters');
    }

    public function get_icon() {
        return 'eicon-tags';
    }

    public function get_categories() {
        return ['hsec-filters'];
    }

    public function get_keywords() {
        return ['filter', 'category', 'hubspot', 'events', 'type'];
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
                'default' => __('All Categories', 'hsec-elementor-filters'),
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
                'default' => __('Select Category', 'hsec-elementor-filters'),
                'condition' => [
                    'display_type' => 'dropdown',
                ],
            ]
        );

        $this->add_control(
            'capitalize',
            [
                'label' => __('Capitalize Labels', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
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
                    'display_type' => 'buttons',
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
                    '{{WRAPPER}} .hsec-category-filter-buttons' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hsec-category-btn',
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
                    '{{WRAPPER}} .hsec-category-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_bg',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-category-btn' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .hsec-category-btn.active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_bg_active',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-category-btn.active' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .hsec-category-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .hsec-category-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .hsec-category-btn',
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
                'selector' => '{{WRAPPER}} .hsec-category-select',
            ]
        );

        $this->add_control(
            'dropdown_color',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-category-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'dropdown_bg',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-category-select' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .hsec-category-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .hsec-category-select' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'dropdown_border',
                'selector' => '{{WRAPPER}} .hsec-category-select',
            ]
        );

        $this->add_responsive_control(
            'dropdown_border_radius',
            [
                'label' => __('Border Radius', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-category-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

        $categories = HSEC_Elementor_Filters::get_available_categories();

        // In editor, show preview with sample categories if none exist
        if (empty($categories) && $is_editor) {
            $categories = ['webinar', 'conference', 'workshop'];
        }

        // Get current category from URL
        $current = isset($_GET[HSEC_Elementor_Filters::QUERY_VAR_CATEGORY])
            ? sanitize_text_field($_GET[HSEC_Elementor_Filters::QUERY_VAR_CATEGORY])
            : '';

        if (empty($categories)) {
            echo '<div class="hsec-filter-empty">' . __('No categories found in events.', 'hsec-elementor-filters') . '</div>';
            return;
        }

        $capitalize = $settings['capitalize'] === 'yes';

        $this->add_render_attribute('wrapper', 'class', 'hsec-category-filter');
        $this->add_render_attribute('wrapper', 'data-filter-type', 'category');
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <?php if ($settings['display_type'] === 'dropdown'): ?>
                <?php $this->render_dropdown($settings, $categories, $current, $capitalize); ?>
            <?php else: ?>
                <?php $this->render_buttons($settings, $categories, $current, $capitalize); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_buttons($settings, $categories, $current, $capitalize) {
        ?>
        <div class="hsec-category-filter-buttons">
            <?php if ($settings['show_all_option'] === 'yes'): ?>
                <button type="button"
                        class="hsec-category-btn <?php echo empty($current) || $current === 'all' ? 'active' : ''; ?>"
                        data-category="all">
                    <span class="hsec-category-label"><?php echo esc_html($settings['all_label']); ?></span>
                </button>
            <?php endif; ?>

            <?php foreach ($categories as $cat):
                $is_active = $current === $cat;
                $label = $capitalize ? ucfirst($cat) : $cat;
                ?>
                <button type="button"
                        class="hsec-category-btn <?php echo $is_active ? 'active' : ''; ?>"
                        data-category="<?php echo esc_attr($cat); ?>">
                    <span class="hsec-category-label"><?php echo esc_html($label); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_dropdown($settings, $categories, $current, $capitalize) {
        ?>
        <select class="hsec-category-select" data-filter="category">
            <option value=""><?php echo esc_html($settings['dropdown_placeholder']); ?></option>

            <?php if ($settings['show_all_option'] === 'yes'): ?>
                <option value="all" <?php selected($current, 'all'); ?>>
                    <?php echo esc_html($settings['all_label']); ?>
                </option>
            <?php endif; ?>

            <?php foreach ($categories as $cat):
                $label = $capitalize ? ucfirst($cat) : $cat;
                ?>
                <option value="<?php echo esc_attr($cat); ?>" <?php selected($current, $cat); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
