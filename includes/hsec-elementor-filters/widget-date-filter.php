<?php
/**
 * Date Filter Widget for HubSpot Events
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class HSEC_Date_Filter_Widget extends Widget_Base {

    public function get_name() {
        return 'hsec-date-filter';
    }

    public function get_title() {
        return __('Events Date Filter', 'hsec-elementor-filters');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return ['hsec-filters'];
    }

    public function get_keywords() {
        return ['filter', 'date', 'calendar', 'hubspot', 'events', 'month'];
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
            'label_select_date',
            [
                'label' => __('Label: Select Date', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Select Date', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'label_past',
            [
                'label' => __('Label: Past Events', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Past Events', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'label_upcoming',
            [
                'label' => __('Label: Upcoming Events', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Upcoming Events', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'label_all',
            [
                'label' => __('Label: All Events', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('All Events', 'hsec-elementor-filters'),
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
            'month_picker_label',
            [
                'label' => __('Month Picker Label', 'hsec-elementor-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Choose Month', 'hsec-elementor-filters'),
            ]
        );

        $this->add_control(
            'months_to_show',
            [
                'label' => __('Months to Show', 'hsec-elementor-filters'),
                'type' => Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 3,
                'max' => 36,
                'description' => __('Number of months to show in picker (past and future)', 'hsec-elementor-filters'),
            ]
        );

        $this->end_controls_section();

        // Style - Select
        $this->start_controls_section(
            'section_style_select',
            [
                'label' => __('Select Style', 'hsec-elementor-filters'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'select_typography',
                'selector' => '{{WRAPPER}} .hsec-date-select',
            ]
        );

        $this->add_control(
            'select_color',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-date-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'select_bg',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-date-select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'select_padding',
            [
                'label' => __('Padding', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-date-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'select_width',
            [
                'label' => __('Width', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 100, 'max' => 500],
                    '%' => ['min' => 10, 'max' => 100],
                ],
                'selectors' => [
                    '{{WRAPPER}} .hsec-date-select' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'select_border',
                'selector' => '{{WRAPPER}} .hsec-date-select',
            ]
        );

        $this->add_responsive_control(
            'select_border_radius',
            [
                'label' => __('Border Radius', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-date-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style - Month Picker
        $this->start_controls_section(
            'section_style_month',
            [
                'label' => __('Month Picker Style', 'hsec-elementor-filters'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'month_gap',
            [
                'label' => __('Gap', 'hsec-elementor-filters'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 30],
                ],
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-picker' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'month_typography',
                'selector' => '{{WRAPPER}} .hsec-month-btn',
            ]
        );

        $this->start_controls_tabs('month_tabs');

        $this->start_controls_tab('month_normal', ['label' => __('Normal', 'hsec-elementor-filters')]);

        $this->add_control(
            'month_color',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'month_bg',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('month_active', ['label' => __('Active', 'hsec-elementor-filters')]);

        $this->add_control(
            'month_color_active',
            [
                'label' => __('Text Color', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-btn.active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'month_bg_active',
            [
                'label' => __('Background', 'hsec-elementor-filters'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-btn.active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'month_padding',
            [
                'label' => __('Padding', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'separator' => 'before',
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'month_border_radius',
            [
                'label' => __('Border Radius', 'hsec-elementor-filters'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .hsec-month-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

        $current_filter = isset($_GET[HSEC_Elementor_Filters::QUERY_VAR_DATE_FILTER])
            ? sanitize_text_field($_GET[HSEC_Elementor_Filters::QUERY_VAR_DATE_FILTER])
            : '';

        $current_month = isset($_GET[HSEC_Elementor_Filters::QUERY_VAR_DATE_MONTH])
            ? sanitize_text_field($_GET[HSEC_Elementor_Filters::QUERY_VAR_DATE_MONTH])
            : '';

        // In editor, show month picker expanded for preview
        $show_month_picker = $current_filter === 'month' || $is_editor;

        $this->add_render_attribute('wrapper', 'class', 'hsec-date-filter');
        $this->add_render_attribute('wrapper', 'data-filter-type', 'date');

        if ($is_editor) {
            $this->add_render_attribute('wrapper', 'class', 'hsec-editor-preview');
        }
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <select class="hsec-date-select" data-filter="date_filter">
                <?php if ($settings['show_all_option'] === 'yes'): ?>
                    <option value="all" <?php selected($current_filter, 'all'); ?>>
                        <?php echo esc_html($settings['label_all']); ?>
                    </option>
                <?php endif; ?>
                <option value="month" <?php selected($current_filter, 'month'); ?><?php echo $is_editor ? ' selected' : ''; ?>>
                    <?php echo esc_html($settings['label_select_date']); ?>
                </option>
                <option value="past" <?php selected($current_filter, 'past'); ?>>
                    <?php echo esc_html($settings['label_past']); ?>
                </option>
                <option value="upcoming" <?php selected($current_filter, 'upcoming'); ?>>
                    <?php echo esc_html($settings['label_upcoming']); ?>
                </option>
            </select>

            <div class="hsec-month-picker-wrapper" style="<?php echo !$show_month_picker ? 'display:none;' : ''; ?>">
                <label class="hsec-month-picker-label">
                    <?php echo esc_html($settings['month_picker_label']); ?>
                </label>
                <div class="hsec-month-picker">
                    <?php $this->render_month_buttons($settings, $current_month, $is_editor); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_month_buttons($settings, $current_month, $is_editor = false) {
        $months_count = absint($settings['months_to_show']) ?: 12;
        $available_months = HSEC_Elementor_Filters::get_available_months();

        // In editor, show some sample months as available for preview
        if ($is_editor && empty($available_months)) {
            $current_date = new DateTime();
            $available_months = [
                $current_date->format('Y-m'),
                $current_date->modify('+1 month')->format('Y-m'),
                $current_date->modify('+1 month')->format('Y-m'),
                $current_date->modify('-4 months')->format('Y-m'),
            ];
        }

        // Generate months: past and future
        $months = [];
        $current_date = new DateTime();

        // Past months
        for ($i = $months_count / 2; $i >= 0; $i--) {
            $date = clone $current_date;
            $date->modify("-{$i} months");
            $months[] = $date->format('Y-m');
        }

        // Future months
        for ($i = 1; $i <= $months_count / 2; $i++) {
            $date = clone $current_date;
            $date->modify("+{$i} months");
            $months[] = $date->format('Y-m');
        }

        $months = array_unique($months);
        sort($months);

        // Group by year
        $by_year = [];
        foreach ($months as $month) {
            list($year, $m) = explode('-', $month);
            $by_year[$year][] = $month;
        }

        // In editor, mark current month as active for preview
        $preview_active_month = $is_editor && empty($current_month) ? (new DateTime())->format('Y-m') : $current_month;

        foreach ($by_year as $year => $year_months):
            ?>
            <div class="hsec-year-group">
                <div class="hsec-year-label"><?php echo esc_html($year); ?></div>
                <div class="hsec-months-row">
                    <?php foreach ($year_months as $month):
                        $date = DateTime::createFromFormat('Y-m', $month);
                        $month_name = $date->format('M');
                        $has_events = in_array($month, $available_months);

                        // In editor, enable all months for styling preview
                        if ($is_editor) {
                            $has_events = true;
                        }

                        $is_active = $preview_active_month === $month;
                        ?>
                        <button type="button"
                                class="hsec-month-btn <?php echo $is_active ? 'active' : ''; ?> <?php echo !$has_events ? 'no-events' : ''; ?>"
                                data-month="<?php echo esc_attr($month); ?>"
                                <?php echo !$has_events ? 'disabled' : ''; ?>
                                title="<?php echo esc_attr($date->format('F Y')); ?>">
                            <?php echo esc_html($month_name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach;
    }
}
