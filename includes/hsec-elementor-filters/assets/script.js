/**
 * HubSpot Events Elementor Filters
 *
 * URL-based filtering — each filter change navigates with query params.
 * Server-side elementor/query/query_args hook applies the filters.
 */
(function($) {
    'use strict';

    var HSEC_Filters = {
        config: window.hsecFilters || {},
        currentFilters: {},

        init: function() {
            this.queryVars = this.config.queryVars || {
                language: 'hsec_lang',
                dateFilter: 'hsec_date_filter',
                dateMonth: 'hsec_month',
                ondemand: 'hsec_ondemand',
                category: 'hsec_category'
            };

            this.loadFiltersFromUrl();
            this.bindEvents();
            this.updateClearButton();
        },

        loadFiltersFromUrl: function() {
            var params = new URLSearchParams(window.location.search);
            var $langFilter = $('.hsec-lang-filter');
            var defaultLang = $langFilter.data('default-lang') || '';

            this.currentFilters = {
                language: params.get(this.queryVars.language) || defaultLang,
                dateFilter: params.get(this.queryVars.dateFilter) || '',
                dateMonth: params.get(this.queryVars.dateMonth) || '',
                ondemand: params.get(this.queryVars.ondemand) || '',
                category: params.get(this.queryVars.category) || ''
            };

            if (!params.has(this.queryVars.language) && defaultLang) {
                this.currentFilters.language = defaultLang;
            }
        },

        hasActiveFilters: function() {
            var f = this.currentFilters;
            var $langFilter = $('.hsec-lang-filter');
            var defaultLang = $langFilter.data('default-lang') || '';

            // Language only counts as active if it differs from widget default
            var langActive = f.language && f.language !== defaultLang;
            return langActive || f.dateFilter || f.dateMonth || f.ondemand || f.category;
        },

        updateClearButton: function() {
            var $btn = $('.hsec-clear-filters');
            if (this.hasActiveFilters()) {
                $btn.addClass('hsec-clear-visible');
            } else {
                $btn.removeClass('hsec-clear-visible');
            }
        },

        bindEvents: function() {
            var self = this;

            // Language - buttons
            $(document).on('click', '.hsec-lang-btn', function(e) {
                e.preventDefault();
                var lang = $(this).data('lang');
                $(this).closest('.hsec-lang-filter-buttons').find('.hsec-lang-btn').removeClass('active');
                $(this).addClass('active');
                self.currentFilters.language = (lang === 'all') ? '' : lang;
                self.applyFilters();
            });

            // Language - dropdown
            $(document).on('change', '.hsec-lang-select', function() {
                var lang = $(this).val();
                self.currentFilters.language = (lang === 'all') ? '' : lang;
                self.applyFilters();
            });

            // Date - select
            $(document).on('change', '.hsec-date-select', function() {
                var filter = $(this).val();
                var $monthPicker = $(this).closest('.hsec-date-filter').find('.hsec-month-picker-wrapper');
                if (filter === 'month') {
                    $monthPicker.slideDown(200);
                    return;
                }
                $monthPicker.slideUp(200);
                self.currentFilters.dateFilter = (filter === 'all') ? '' : filter;
                self.currentFilters.dateMonth = '';
                self.applyFilters();
            });

            // Date - month buttons
            $(document).on('click', '.hsec-month-btn:not(:disabled)', function(e) {
                e.preventDefault();
                $(this).closest('.hsec-month-picker').find('.hsec-month-btn').removeClass('active');
                $(this).addClass('active');
                self.currentFilters.dateFilter = 'month';
                self.currentFilters.dateMonth = $(this).data('month');
                self.applyFilters();
            });

            // Category - buttons
            $(document).on('click', '.hsec-category-btn', function(e) {
                e.preventDefault();
                var cat = $(this).data('category');
                $(this).closest('.hsec-category-filter-buttons').find('.hsec-category-btn').removeClass('active');
                $(this).addClass('active');
                self.currentFilters.category = (cat === 'all') ? '' : cat;
                self.applyFilters();
            });

            // Category - dropdown
            $(document).on('change', '.hsec-category-select', function() {
                var cat = $(this).val();
                self.currentFilters.category = (cat === 'all') ? '' : cat;
                self.applyFilters();
            });

            // On-demand toggle
            $(document).on('change', '.hsec-ondemand-checkbox', function() {
                self.currentFilters.ondemand = $(this).is(':checked') ? '1' : '0';
                self.applyFilters();
            });

            $(document).on('click', '.hsec-ondemand-label', function() {
                $(this).closest('.hsec-ondemand-toggle').find('.hsec-ondemand-checkbox').trigger('click');
            });

            // Clear all filters
            $(document).on('click', '.hsec-clear-filters', function(e) {
                e.preventDefault();
                self.clearFilters();
            });
        },

        applyFilters: function() {
            var url = new URL(window.location.href);
            var params = url.searchParams;
            var filters = this.currentFilters;
            var vars = this.queryVars;

            var mapping = [
                [vars.language, filters.language],
                [vars.dateFilter, filters.dateFilter],
                [vars.dateMonth, filters.dateMonth],
                [vars.ondemand, filters.ondemand],
                [vars.category, filters.category]
            ];

            for (var i = 0; i < mapping.length; i++) {
                if (mapping[i][1]) {
                    params.set(mapping[i][0], mapping[i][1]);
                } else {
                    params.delete(mapping[i][0]);
                }
            }

            params.delete('paged');
            params.delete('page');

            window.location.href = url.pathname + (params.toString() ? '?' + params.toString() : '');
        },

        clearFilters: function() {
            this.currentFilters = {
                language: '',
                dateFilter: '',
                dateMonth: '',
                ondemand: '',
                category: ''
            };
            // Navigate to clean URL
            window.location.href = window.location.pathname;
        },

        // Public API
        getFilters: function() { return this.currentFilters; },
        setFilter: function(key, value) {
            if (this.currentFilters.hasOwnProperty(key)) {
                this.currentFilters[key] = value;
                this.applyFilters();
            }
        }
    };

    $(document).ready(function() {
        HSEC_Filters.init();
    });

    window.HSEC_Filters = HSEC_Filters;

})(jQuery);
