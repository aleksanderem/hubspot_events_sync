/**
 * HubSpot Events Connector - Admin JavaScript
 */
(function($) {
    'use strict';

    var HSEC = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle password visibility
            $(document).on('click', '.hsec-toggle-visibility', this.toggleVisibility);

            // Test connection
            $(document).on('click', '.hsec-test-connection', this.testConnection);

            // Sync now (incremental)
            $(document).on('click', '.hsec-sync-now', this.syncNow);

            // Full sync
            $(document).on('click', '.hsec-full-sync', this.fullSync);

            // Stop sync
            $(document).on('click', '.hsec-stop-sync', this.stopSync);

            // Force stop (from status page)
            $(document).on('click', '.hsec-force-stop', this.forceStop);

            // Delete all events
            $(document).on('click', '.hsec-delete-all-events', this.deleteAllEvents);

            // Enable test button when token changes
            $(document).on('input', '#hsec_api_token', this.onTokenChange);
        },

        /**
         * Toggle password field visibility
         */
        toggleVisibility: function(e) {
            e.preventDefault();
            var $button = $(this);
            var targetId = $button.data('target');
            var $input = $('#' + targetId);
            var $icon = $button.find('.dashicons');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('.hsec-connection-status');
            var $tokenInput = $('#hsec_api_token');
            var token = $tokenInput.val().trim();

            if (!token) {
                $status.addClass('error').text(hsecAdmin.strings.noToken || 'Please enter a token first');
                return;
            }

            $button.prop('disabled', true);
            $status.removeClass('success error').text(hsecAdmin.strings.testing);

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_test_connection',
                    nonce: hsecAdmin.nonce,
                    token: token
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(hsecAdmin.strings.success);
                        if (response.data.events_count !== undefined) {
                            $status.append(' (' + response.data.events_count + ' events found)');
                        }
                    } else {
                        $status.addClass('error').text(response.data.message || hsecAdmin.strings.error);
                    }
                },
                error: function() {
                    $status.addClass('error').text(hsecAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Run incremental sync
         */
        syncNow: function(e) {
            e.preventDefault();
            HSEC.runSync(false);
        },

        /**
         * Run full sync
         */
        fullSync: function(e) {
            e.preventDefault();

            if (!confirm(hsecAdmin.strings.confirmFullSync)) {
                return;
            }

            HSEC.runSync(true);
        },

        /**
         * Stop running sync
         */
        stopSync: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $progressText = $('.hsec-progress-text');

            $button.prop('disabled', true);
            $progressText.text(hsecAdmin.strings.stopping || 'Stopping...');

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_stop_sync',
                    nonce: hsecAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $progressText.text(response.data.message);
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Force stop - clears sync lock immediately (from status page)
         */
        forceStop: function(e) {
            e.preventDefault();
            var $button = $(this);

            $button.prop('disabled', true).text(hsecAdmin.strings.stopping || 'Stopping...');

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_force_stop',
                    nonce: hsecAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Error stopping sync');
                        $button.prop('disabled', false).text('Force Stop');
                    }
                },
                error: function() {
                    alert('Error stopping sync');
                    $button.prop('disabled', false).text('Force Stop');
                }
            });
        },

        /**
         * Delete all events
         */
        deleteAllEvents: function(e) {
            e.preventDefault();

            var confirmMsg = hsecAdmin.strings.confirmDeleteAll || 'Are you sure you want to delete ALL synced events? This action cannot be undone!';
            if (!confirm(confirmMsg)) {
                return;
            }

            // Double confirmation for safety
            var doubleConfirmMsg = hsecAdmin.strings.confirmDeleteAllDouble || 'This will permanently delete all HubSpot events from WordPress. Type "DELETE" to confirm:';
            var userInput = prompt(doubleConfirmMsg);
            if (userInput !== 'DELETE') {
                alert(hsecAdmin.strings.deleteAborted || 'Delete aborted.');
                return;
            }

            var $button = $(this);
            var $progress = $('.hsec-sync-progress');
            var $progressText = $('.hsec-progress-text');
            var $result = $('.hsec-sync-result');

            $button.prop('disabled', true);
            $progress.show();
            $progressText.text(hsecAdmin.strings.deleting || 'Deleting events...');
            $result.hide();

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_delete_all_events',
                    nonce: hsecAdmin.nonce
                },
                success: function(response) {
                    $progress.hide();
                    if (response.success) {
                        $result
                            .removeClass('error')
                            .addClass('success')
                            .html('<strong>' + (hsecAdmin.strings.deleteComplete || 'Delete complete!') + '</strong><br>' + response.data.message)
                            .show();

                        // Refresh page after short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        $result
                            .removeClass('success')
                            .addClass('error')
                            .html('<strong>' + (hsecAdmin.strings.deleteError || 'Delete failed') + '</strong><br>' + (response.data.message || 'Unknown error'))
                            .show();
                    }
                },
                error: function(xhr, status, error) {
                    $progress.hide();
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .html('<strong>' + (hsecAdmin.strings.deleteError || 'Delete failed') + '</strong><br>' + error)
                        .show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Run sync operation
         */
        runSync: function(fullSync) {
            var $progress = $('.hsec-sync-progress');
            var $result = $('.hsec-sync-result');
            var $progressText = $('.hsec-progress-text');
            var $buttons = $('.hsec-sync-now, .hsec-full-sync');
            var $stopButton = $('.hsec-stop-sync');

            // Show progress with stop button
            $buttons.prop('disabled', true);
            $stopButton.show().prop('disabled', false);
            $progress.show();
            $result.hide();
            $progressText.text(hsecAdmin.strings.syncing);

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_sync_now',
                    nonce: hsecAdmin.nonce,
                    full_sync: fullSync ? 'true' : 'false'
                },
                timeout: 300000, // 5 minute timeout
                success: function(response) {
                    $progress.hide();
                    $stopButton.hide();

                    if (response.success) {
                        var message = response.data.message;
                        if (response.data.stats && response.data.stats.stopped) {
                            message += '<br><em>' + (response.data.stats.stop_message || 'Sync was stopped') + '</em>';
                        }
                        $result
                            .removeClass('error')
                            .addClass('success')
                            .html('<strong>' + hsecAdmin.strings.syncComplete + '</strong><br>' + message)
                            .show();

                        // Refresh page after short delay to show updated stats
                        if (window.location.href.indexOf('hsec-sync-status') !== -1) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    } else {
                        $result
                            .removeClass('success')
                            .addClass('error')
                            .html('<strong>' + hsecAdmin.strings.syncError + '</strong><br>' + (response.data.message || 'Unknown error'))
                            .show();
                    }
                },
                error: function(xhr, status, error) {
                    $progress.hide();
                    $stopButton.hide();
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .html('<strong>' + hsecAdmin.strings.syncError + '</strong><br>' + error)
                        .show();
                },
                complete: function() {
                    $buttons.prop('disabled', false);
                    $stopButton.hide();
                }
            });
        },

        /**
         * Handle token input change
         */
        onTokenChange: function() {
            var $input = $(this);
            var $testButton = $('.hsec-test-connection');
            var $status = $('.hsec-connection-status');

            // Enable/disable test button based on token presence
            $testButton.prop('disabled', $input.val().trim() === '');

            // Clear status
            $status.removeClass('success error').text('');
        },

        /**
         * Initialize field mappings UI
         */
        initFieldMappings: function() {
            var mappingIndex = $('.hsec-mapping-row').length;

            // Add new mapping
            $(document).on('click', '.hsec-add-mapping', function(e) {
                e.preventDefault();
                var template = $('#hsec-mapping-template').html();
                var newRow = template.replace(/\{\{INDEX\}\}/g, mappingIndex);
                mappingIndex++;

                // Remove "no mappings" message if present
                $('.hsec-no-mappings').remove();

                $('#hsec-mappings-body').append(newRow);
            });

            // Remove mapping
            $(document).on('click', '.hsec-remove-mapping', function(e) {
                e.preventDefault();
                $(this).closest('tr').remove();

                // Show "no mappings" if empty
                if ($('#hsec-mappings-body tr').length === 0) {
                    $('#hsec-mappings-body').append(
                        '<tr class="hsec-mapping-row hsec-no-mappings">' +
                        '<td colspan="3"><em>No custom mappings. Default mappings will be used.</em></td>' +
                        '</tr>'
                    );
                }
            });

            // Screenshot button
            $(document).on('click', '.hsec-screenshot-btn', function(e) {
                e.preventDefault();
                HSEC.takeScreenshot($(this));
            });

            // Fetch missing images button
            $(document).on('click', '.hsec-fetch-missing-images', function(e) {
                e.preventDefault();
                HSEC.fetchMissingImages($(this));
            });
        },

        /**
         * Take screenshot of event page
         */
        /**
         * Fetch missing images for all events
         */
        fetchMissingImages: function($button) {
            var $progress = $('.hsec-sync-progress');
            var $progressText = $('.hsec-progress-text');
            var $result = $('.hsec-sync-result');

            $button.prop('disabled', true);
            $progress.show();
            $progressText.text(hsecAdmin.strings.fetchingImages || 'Fetching missing images...');
            $result.hide();

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_fetch_missing_images',
                    nonce: hsecAdmin.nonce
                },
                timeout: 600000, // 10 minute timeout
                success: function(response) {
                    $progress.hide();
                    if (response.success) {
                        $result
                            .removeClass('error')
                            .addClass('success')
                            .html('<strong>' + (hsecAdmin.strings.fetchImagesComplete || 'Images fetched!') + '</strong><br>' + response.data.message)
                            .show();
                    } else {
                        $result
                            .removeClass('success')
                            .addClass('error')
                            .html('<strong>' + (hsecAdmin.strings.fetchImagesError || 'Error') + '</strong><br>' + (response.data.message || 'Unknown error'))
                            .show();
                    }
                },
                error: function(xhr, status, error) {
                    $progress.hide();
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .html('<strong>Error</strong><br>' + error)
                        .show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Take screenshot of event page
         */
        takeScreenshot: function($button) {
            var postId = $button.data('post-id');
            var url = $button.data('url');

            if (!url) {
                alert('No URL available for screenshot');
                return;
            }

            // Show loading state
            var originalHtml = $button.html();
            $button.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

            $.ajax({
                url: hsecAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hsec_take_screenshot',
                    nonce: hsecAdmin.nonce,
                    post_id: postId,
                    url: url
                },
                success: function(response) {
                    if (response.success) {
                        // Replace button with thumbnail
                        $button.replaceWith(
                            '<a href="' + response.data.url + '" target="_blank">' +
                            '<img src="' + response.data.url + '" width="60" height="60" style="object-fit: cover; border-radius: 4px;" />' +
                            '</a>'
                        );
                    } else {
                        alert(response.data.message || 'Screenshot failed');
                        $button.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    alert('Screenshot request failed');
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        HSEC.init();
        HSEC.initFieldMappings();
    });

})(jQuery);
