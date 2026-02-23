<?php
/**
 * Starter Dashboard Addon Settings Integration
 *
 * @package HubSpot_Events_Connector
 */

defined('ABSPATH') || exit;

class HSEC_Addon_Settings {

    /**
     * Render settings panel in Starter Dashboard
     */
    public static function render_settings() {
        $api_token = get_option('hsec_api_token', '');
        $sync_interval = get_option('hsec_sync_interval', 'hourly');
        $last_sync = get_option('hsec_last_sync_time', null);
        $last_result = get_option('hsec_last_sync_result', []);
        $events_count = wp_count_posts('hs_event');

        // Get sync status
        $sync_manager = HSEC_Sync_Manager::instance();
        $is_syncing = $sync_manager->is_sync_running();
        ?>
        <div class="hsec-addon-settings" data-addon="hubspot-events">
            <style>
                .hsec-addon-settings .bp-addon-settings-section {
                    background: #fff;
                    padding: 20px;
                    margin-bottom: 20px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }

                .hsec-addon-settings .bp-addon-field {
                    margin-bottom: 20px;
                }

                .hsec-addon-settings .bp-addon-field label {
                    display: block;
                    font-weight: 500;
                    margin-bottom: 8px;
                }

                .hsec-addon-settings .bp-addon-field input[type="text"],
                .hsec-addon-settings .bp-addon-field input[type="password"],
                .hsec-addon-settings .bp-addon-field select {
                    width: 100%;
                    max-width: 500px;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }

                .hsec-addon-settings .description {
                    color: #666;
                    font-size: 13px;
                    margin-top: 5px;
                }

                .hsec-addon-settings .button-group {
                    display: flex;
                    gap: 10px;
                    margin-top: 15px;
                }

                .hsec-addon-settings .hsec-status-box {
                    background: #f9f9f9;
                    border-left: 4px solid #2271b1;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 4px;
                }

                .hsec-addon-settings .hsec-status-box strong {
                    display: block;
                    margin-bottom: 8px;
                }

                .hsec-addon-settings .hsec-stat {
                    display: flex;
                    justify-content: space-between;
                    padding: 5px 0;
                    border-bottom: 1px solid #eee;
                }

                .hsec-addon-settings .hsec-stat:last-child {
                    border-bottom: none;
                }

                .hsec-addon-settings .hsec-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin-top: 15px;
                }

                .hsec-addon-settings .button.is-loading {
                    pointer-events: none;
                    opacity: 0.7;
                }

                .hsec-addon-settings .hsec-danger-zone {
                    border-left-color: #dc3232;
                }

                .hsec-addon-settings .button-link-delete {
                    color: #dc3232;
                }

                .hsec-addon-settings .button-link-delete:hover {
                    color: #a00;
                }

                .hsec-sync-progress {
                    display: none;
                    margin: 15px 0;
                    padding: 10px;
                    background: #f0f6fc;
                    border-left: 4px solid #0969da;
                    border-radius: 4px;
                }

                .hsec-sync-progress.active {
                    display: block;
                }
            </style>

            <!-- API Configuration -->
            <div class="bp-addon-settings-section">
                <h3><?php _e('HubSpot API Configuration', 'hubspot-events-connector'); ?></h3>

                <div class="bp-addon-field">
                    <label><?php _e('Private App Access Token', 'hubspot-events-connector'); ?></label>
                    <input type="password"
                           name="api_token"
                           value="<?php echo esc_attr($api_token); ?>"
                           class="regular-text"
                           placeholder="pat-na1-...">
                    <p class="description">
                        <?php _e('Create a private app in HubSpot with "marketing_events_basic_read" scope.', 'hubspot-events-connector'); ?>
                        <a href="https://developers.hubspot.com/docs/api/private-apps" target="_blank">
                            <?php _e('Learn how', 'hubspot-events-connector'); ?> ↗
                        </a>
                    </p>
                </div>

                <div class="bp-addon-field">
                    <label><?php _e('Sync Interval', 'hubspot-events-connector'); ?></label>
                    <select name="sync_interval">
                        <option value="hourly" <?php selected($sync_interval, 'hourly'); ?>>
                            <?php _e('Every Hour', 'hubspot-events-connector'); ?>
                        </option>
                        <option value="twicedaily" <?php selected($sync_interval, 'twicedaily'); ?>>
                            <?php _e('Twice Daily', 'hubspot-events-connector'); ?>
                        </option>
                        <option value="daily" <?php selected($sync_interval, 'daily'); ?>>
                            <?php _e('Once Daily', 'hubspot-events-connector'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('How often to automatically sync events from HubSpot.', 'hubspot-events-connector'); ?>
                    </p>
                </div>

                <div class="button-group">
                    <button type="button" class="button button-primary save-addon-settings">
                        <?php _e('Save Settings', 'hubspot-events-connector'); ?>
                    </button>

                    <button type="button" class="button test-connection">
                        <?php _e('Test Connection', 'hubspot-events-connector'); ?>
                    </button>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="bp-addon-settings-section">
                <h3><?php _e('Advanced Settings', 'hubspot-events-connector'); ?></h3>
                <p class="description">
                    <?php _e('For more advanced configuration options, visit the', 'hubspot-events-connector'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=hsec-settings'); ?>">
                        <?php _e('full settings page', 'hubspot-events-connector'); ?>
                    </a>.
                </p>
            </div>

            <!-- Sync Status & Actions -->
            <div class="bp-addon-settings-section">
                <h3><?php _e('Sync Status & Management', 'hubspot-events-connector'); ?></h3>

                <div class="hsec-status-box">
                    <strong><?php _e('Current Status', 'hubspot-events-connector'); ?></strong>
                    <div class="hsec-stat">
                        <span><?php _e('Total Events:', 'hubspot-events-connector'); ?></span>
                        <span><strong><?php echo $events_count->publish ?? 0; ?></strong></span>
                    </div>
                    <div class="hsec-stat">
                        <span><?php _e('Last Sync:', 'hubspot-events-connector'); ?></span>
                        <span><?php echo $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync) : __('Never', 'hubspot-events-connector'); ?></span>
                    </div>
                    <?php if (!empty($last_result)): ?>
                    <div class="hsec-stat">
                        <span><?php _e('Last Result:', 'hubspot-events-connector'); ?></span>
                        <span>
                            <?php printf(
                                __('Created: %d, Updated: %d, Skipped: %d', 'hubspot-events-connector'),
                                $last_result['created'] ?? 0,
                                $last_result['updated'] ?? 0,
                                $last_result['skipped'] ?? 0
                            ); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="hsec-stat">
                        <span><?php _e('Sync Status:', 'hubspot-events-connector'); ?></span>
                        <span class="hsec-sync-status"><?php echo $is_syncing ? __('Running...', 'hubspot-events-connector') : __('Idle', 'hubspot-events-connector'); ?></span>
                    </div>
                </div>

                <div class="hsec-sync-progress <?php echo $is_syncing ? 'active' : ''; ?>">
                    <p><?php _e('Sync in progress... This may take a few minutes.', 'hubspot-events-connector'); ?></p>
                </div>

                <div class="hsec-actions">
                    <button type="button" class="button sync-now">
                        <?php _e('Sync Now (Incremental)', 'hubspot-events-connector'); ?>
                    </button>

                    <button type="button" class="button sync-full">
                        <?php _e('Full Sync (All Events)', 'hubspot-events-connector'); ?>
                    </button>

                    <button type="button" class="button fetch-images">
                        <?php _e('Fetch Missing Images', 'hubspot-events-connector'); ?>
                    </button>

                    <?php if ($is_syncing): ?>
                    <button type="button" class="button stop-sync">
                        <?php _e('Stop Sync', 'hubspot-events-connector'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <p class="description">
                    <?php _e('Incremental sync only fetches events modified since last sync. Full sync fetches all events from HubSpot.', 'hubspot-events-connector'); ?>
                </p>
            </div>

            <!-- Quick Links -->
            <div class="bp-addon-settings-section">
                <h3><?php _e('Quick Links', 'hubspot-events-connector'); ?></h3>

                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=hs_event'); ?>" class="button">
                        <?php _e('View All Events', 'hubspot-events-connector'); ?>
                    </a>

                    <a href="<?php echo admin_url('edit-tags.php?taxonomy=event_type&post_type=hs_event'); ?>" class="button">
                        <?php _e('Manage Event Types', 'hubspot-events-connector'); ?>
                    </a>
                </p>
            </div>

            <!-- Danger Zone -->
            <div class="bp-addon-settings-section hsec-danger-zone">
                <h3><?php _e('Danger Zone', 'hubspot-events-connector'); ?></h3>

                <p class="description">
                    <?php _e('These actions cannot be undone. Use with caution.', 'hubspot-events-connector'); ?>
                </p>

                <button type="button" class="button button-link-delete delete-all-events">
                    <?php _e('Delete All Synced Events', 'hubspot-events-connector'); ?>
                </button>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var $container = $('.hsec-addon-settings');
                var nonce = '<?php echo wp_create_nonce('hsec_admin_nonce'); ?>';

                // Save settings
                $container.find('.save-addon-settings').on('click', function() {
                    var $btn = $(this);
                    var originalText = $btn.text();

                    var settings = {
                        api_token: $('[name="api_token"]').val(),
                        sync_interval: $('[name="sync_interval"]').val()
                    };

                    $btn.prop('disabled', true).text('<?php _e('Saving...', 'hubspot-events-connector'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'starter_save_addon_settings',
                            nonce: '<?php echo wp_create_nonce('starter_settings_nonce'); ?>',
                            addon_id: 'hubspot-events',
                            settings: settings
                        },
                        success: function(response) {
                            if (response.success) {
                                $btn.text('<?php _e('Saved!', 'hubspot-events-connector'); ?>');
                                setTimeout(function() {
                                    $btn.prop('disabled', false).text(originalText);
                                }, 2000);
                            } else {
                                alert('<?php _e('Error saving settings', 'hubspot-events-connector'); ?>');
                                $btn.prop('disabled', false).text(originalText);
                            }
                        }
                    });
                });

                // Test connection
                $container.find('.test-connection').on('click', function() {
                    var $btn = $(this);
                    var token = $('[name="api_token"]').val();

                    if (!token) {
                        alert('<?php _e('Please enter an API token first', 'hubspot-events-connector'); ?>');
                        return;
                    }

                    $btn.addClass('is-loading').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hsec_test_connection',
                            nonce: nonce,
                            token: token
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                            } else {
                                alert('<?php _e('Connection failed: ', 'hubspot-events-connector'); ?>' + response.data.message);
                            }
                        },
                        complete: function() {
                            $btn.removeClass('is-loading').prop('disabled', false);
                        }
                    });
                });

                // Sync now (incremental)
                $container.find('.sync-now').on('click', function() {
                    var $btn = $(this);
                    $btn.addClass('is-loading').prop('disabled', true);
                    $('.hsec-sync-progress').addClass('active');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hsec_sync_now',
                            nonce: nonce,
                            full_sync: false
                        },
                        success: function(response) {
                            $('.hsec-sync-progress').removeClass('active');
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert('<?php _e('Sync failed: ', 'hubspot-events-connector'); ?>' + response.data.message);
                            }
                        },
                        complete: function() {
                            $btn.removeClass('is-loading').prop('disabled', false);
                        }
                    });
                });

                // Full sync
                $container.find('.sync-full').on('click', function() {
                    if (!confirm('<?php _e('This will fetch all events from HubSpot. Continue?', 'hubspot-events-connector'); ?>')) {
                        return;
                    }

                    var $btn = $(this);
                    $btn.addClass('is-loading').prop('disabled', true);
                    $('.hsec-sync-progress').addClass('active');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hsec_sync_now',
                            nonce: nonce,
                            full_sync: true
                        },
                        success: function(response) {
                            $('.hsec-sync-progress').removeClass('active');
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert('<?php _e('Sync failed: ', 'hubspot-events-connector'); ?>' + response.data.message);
                            }
                        },
                        complete: function() {
                            $btn.removeClass('is-loading').prop('disabled', false);
                        }
                    });
                });

                // Fetch missing images
                $container.find('.fetch-images').on('click', function() {
                    var $btn = $(this);
                    $btn.addClass('is-loading').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hsec_fetch_missing_images',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                            } else {
                                alert('<?php _e('Error: ', 'hubspot-events-connector'); ?>' + response.data.message);
                            }
                        },
                        complete: function() {
                            $btn.removeClass('is-loading').prop('disabled', false);
                        }
                    });
                });

                // Stop sync
                $container.find('.stop-sync').on('click', function() {
                    var $btn = $(this);
                    $btn.addClass('is-loading').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hsec_stop_sync',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                setTimeout(function() { location.reload(); }, 2000);
                            }
                        },
                        complete: function() {
                            $btn.removeClass('is-loading').prop('disabled', false);
                        }
                    });
                });

                // Delete all events
                $container.find('.delete-all-events').on('click', function() {
                    if (!confirm('<?php _e('Are you sure? This will delete ALL synced events!', 'hubspot-events-connector'); ?>')) {
                        return;
                    }

                    var confirmation = prompt('<?php _e('Type "DELETE" to confirm:', 'hubspot-events-connector'); ?>');
                    if (confirmation !== 'DELETE') {
                        return;
                    }

                    var $btn = $(this);
                    $btn.addClass('is-loading').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hsec_delete_all_events',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert('<?php _e('Error: ', 'hubspot-events-connector'); ?>' + response.data.message);
                            }
                        },
                        complete: function() {
                            $btn.removeClass('is-loading').prop('disabled', false);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
}
