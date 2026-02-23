/**
 * HubSpot Events Elementor Filters - JavaScript with AJAX
 */

(function($) {
    'use strict';

    const HSEC_Filters = {
        config: window.hsecFilters || {},
        currentFilters: {},
        loopGrid: null,
        isLoading: false,

        init: function() {
            this.queryVars = this.config.queryVars || {
                language: 'hsec_lang',
                dateFilter: 'hsec_date_filter',
                dateMonth: 'hsec_month',
                ondemand: 'hsec_ondemand'
            };

            // Find Loop Grid widget
            this.findLoopGrid();

            // Load current filters from URL
            this.loadFiltersFromUrl();

            // Bind events
            this.bindEvents();

            console.log('HSEC Filters: Initialized', {
                loopGrid: this.loopGrid,
                filters: this.currentFilters
            });
        },

        findLoopGrid: function() {
            // Find Elementor Loop Grid that displays hs_event
            var $loopGrid = $('.elementor-loop-container').closest('.elementor-widget-loop-grid');

            if ($loopGrid.length) {
                var widgetId = $loopGrid.data('id');
                var postId = this.getPostId();

                console.log('HSEC Filters: Found Loop Grid', {
                    widgetId: widgetId,
                    postId: postId,
                    element: $loopGrid[0]
                });

                this.loopGrid = {
                    $element: $loopGrid,
                    $container: $loopGrid.find('.elementor-loop-container'),
                    widgetId: widgetId,
                    postId: postId
                };
            } else {
                console.log('HSEC Filters: No Loop Grid found');
            }
        },

        getPostId: function() {
            // Use archive template ID from PHP if available
            if (this.config.archiveTemplateId) {
                console.log('HSEC Filters: Using archive template ID from PHP:', this.config.archiveTemplateId);
                return this.config.archiveTemplateId;
            }

            // Fallback: try to detect from page
            var postId = $('body').data('elementor-post-id') || 0;
            if (postId) {
                console.log('HSEC Filters: Using post ID from body:', postId);
                return postId;
            }

            console.log('HSEC Filters: No archive template ID found');
            return 0;
        },

        loadFiltersFromUrl: function() {
            var params = new URLSearchParams(window.location.search);

            // Get default language from widget data attribute
            var $langFilter = $('.hsec-lang-filter');
            var defaultLang = $langFilter.data('default-lang') || '';

            this.currentFilters = {
                language: params.get(this.queryVars.language) || defaultLang,
                dateFilter: params.get(this.queryVars.dateFilter) || '',
                dateMonth: params.get(this.queryVars.dateMonth) || '',
                ondemand: params.get(this.queryVars.ondemand) || ''
            };

            // If no URL param but we have default, apply filter on first load
            if (!params.has(this.queryVars.language) && defaultLang) {
                this.currentFilters.language = defaultLang;
            }
        },

        bindEvents: function() {
            var self = this;

            // Language filter - buttons
            $(document).on('click', '.hsec-lang-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var lang = $btn.data('lang');

                // Update active state
                $btn.closest('.hsec-lang-filter-buttons').find('.hsec-lang-btn').removeClass('active');
                $btn.addClass('active');

                self.currentFilters.language = (lang === 'all') ? '' : lang;
                self.applyFilters();
            });

            // Language filter - dropdown
            $(document).on('change', '.hsec-lang-select', function(e) {
                var lang = $(this).val();
                self.currentFilters.language = (lang === 'all') ? '' : lang;
                self.applyFilters();
            });

            // Date filter - select
            $(document).on('change', '.hsec-date-select', function(e) {
                var filter = $(this).val();
                var $wrapper = $(this).closest('.hsec-date-filter');
                var $monthPicker = $wrapper.find('.hsec-month-picker-wrapper');

                // Show/hide month picker
                if (filter === 'month') {
                    $monthPicker.slideDown(200);
                    return; // Wait for month selection
                } else {
                    $monthPicker.slideUp(200);
                }

                self.currentFilters.dateFilter = (filter === 'all') ? '' : filter;
                self.currentFilters.dateMonth = '';
                self.applyFilters();
            });

            // Date filter - month buttons
            $(document).on('click', '.hsec-month-btn:not(:disabled)', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var month = $btn.data('month');

                // Update active state
                $btn.closest('.hsec-month-picker').find('.hsec-month-btn').removeClass('active');
                $btn.addClass('active');

                self.currentFilters.dateFilter = 'month';
                self.currentFilters.dateMonth = month;
                self.applyFilters();
            });

            // On-demand toggle
            $(document).on('change', '.hsec-ondemand-checkbox', function(e) {
                var wasChecked = self.currentFilters.ondemand === '1';
                var isChecked = $(this).is(':checked');
                self.currentFilters.ondemand = isChecked ? '1' : '0';
                console.log('HSEC Filters: On-demand toggle changed', {
                    wasChecked: wasChecked,
                    isChecked: isChecked,
                    newValue: self.currentFilters.ondemand
                });
                self.applyFilters();
            });

            // Label click for toggle
            $(document).on('click', '.hsec-ondemand-label', function() {
                $(this).closest('.hsec-ondemand-toggle').find('.hsec-ondemand-checkbox').trigger('click');
            });
        },

        applyFilters: function() {
            // Update URL
            this.updateUrl();

            // Use AJAX if we have all required info
            if (this.loopGrid && this.loopGrid.$container && this.loopGrid.postId && this.loopGrid.widgetId && this.config.ajaxUrl) {
                console.log('HSEC Filters: Using AJAX', this.loopGrid);
                this.ajaxFilter();
            } else {
                console.log('HSEC Filters: Falling back to reload', {
                    loopGrid: this.loopGrid,
                    ajaxUrl: this.config.ajaxUrl
                });
                // Fallback to page reload
                window.location.reload();
            }
        },

        updateUrl: function() {
            var url = new URL(window.location.href);
            var params = url.searchParams;

            // Update params
            if (this.currentFilters.language) {
                params.set(this.queryVars.language, this.currentFilters.language);
            } else {
                params.delete(this.queryVars.language);
            }

            if (this.currentFilters.dateFilter) {
                params.set(this.queryVars.dateFilter, this.currentFilters.dateFilter);
            } else {
                params.delete(this.queryVars.dateFilter);
            }

            if (this.currentFilters.dateMonth) {
                params.set(this.queryVars.dateMonth, this.currentFilters.dateMonth);
            } else {
                params.delete(this.queryVars.dateMonth);
            }

            if (this.currentFilters.ondemand) {
                params.set(this.queryVars.ondemand, this.currentFilters.ondemand);
            } else {
                params.delete(this.queryVars.ondemand);
            }

            // Reset pagination
            params.delete('paged');
            params.delete('page');

            // Update browser URL without reload
            var newUrl = url.pathname + (params.toString() ? '?' + params.toString() : '') + url.hash;
            window.history.pushState({filters: this.currentFilters}, '', newUrl);
        },

        ajaxFilter: function() {
            var self = this;

            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            this.loopGrid.$element.addClass('hsec-filter-loading');

            var postData = {
                action: 'hsec_filter_events',
                nonce: this.config.nonce,
                post_id: this.loopGrid.postId,
                widget_id: this.loopGrid.widgetId
            };

            // Add filter values
            postData[this.queryVars.language] = this.currentFilters.language;
            postData[this.queryVars.dateFilter] = this.currentFilters.dateFilter;
            postData[this.queryVars.dateMonth] = this.currentFilters.dateMonth;
            postData[this.queryVars.ondemand] = this.currentFilters.ondemand;

            console.log('HSEC Filters: Sending AJAX', postData);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: postData,
                success: function(response) {
                    console.log('HSEC Filters: AJAX response', response);

                    if (response.success && response.data.html) {
                        if (response.data.full_widget) {
                            // Replace entire widget
                            self.loopGrid.$element.html($(response.data.html).html());
                            // Re-find the container
                            self.loopGrid.$container = self.loopGrid.$element.find('.elementor-loop-container');
                        } else {
                            // Replace just Loop Grid content
                            self.loopGrid.$container.html(response.data.html);
                        }

                        // Trigger Elementor frontend handlers for any dynamic content
                        if (window.elementorFrontend && window.elementorFrontend.elementsHandler) {
                            window.elementorFrontend.elementsHandler.runReadyTrigger(self.loopGrid.$element);
                        }

                        console.log('HSEC Filters: Content updated');
                    } else {
                        console.error('HSEC Filters: AJAX error', response);
                        // NO fallback - stay on page for debugging
                    }
                },
                error: function(xhr, status, error) {
                    console.error('HSEC Filters: AJAX failed', error, xhr.responseText);
                    // NO fallback - stay on page for debugging
                },
                complete: function() {
                    self.isLoading = false;
                    self.loopGrid.$element.removeClass('hsec-filter-loading');
                }
            });
        },

        // Public API
        getFilters: function() {
            return this.currentFilters;
        },

        setFilter: function(key, value) {
            if (this.currentFilters.hasOwnProperty(key)) {
                this.currentFilters[key] = value;
                this.applyFilters();
            }
        },

        clearFilters: function() {
            this.currentFilters = {
                language: '',
                dateFilter: '',
                dateMonth: '',
                ondemand: ''
            };
            this.applyFilters();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        HSEC_Filters.init();
    });

    // Handle browser back/forward buttons
    $(window).on('popstate', function(e) {
        if (e.originalEvent.state && e.originalEvent.state.filters) {
            HSEC_Filters.currentFilters = e.originalEvent.state.filters;
            HSEC_Filters.ajaxFilter();
        } else {
            HSEC_Filters.loadFiltersFromUrl();
            HSEC_Filters.ajaxFilter();
        }
    });

    // Expose to global scope
    window.HSEC_Filters = HSEC_Filters;

})(jQuery);
