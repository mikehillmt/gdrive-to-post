/**
 * GDrive to Post Admin Scripts
 */

(function($) {
    'use strict';

    var GDTP = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Sync
            $('#gdtp-sync-now').on('click', this.runSync);

            // OAuth management
            $('#gdtp-save-oauth-creds').on('click', this.saveOAuthCreds);
            $('#gdtp-remove-oauth-creds').on('click', this.removeOAuthCreds);
            $('#gdtp-disconnect-google').on('click', this.disconnectGoogle);

            // Connection test
            $('#gdtp-test-connection').on('click', this.testConnection);

            // Folder browsing
            $('#gdtp-browse-folders').on('click', this.browseFolders);
            $(document).on('click', '.gdtp-folder-item .gdtp-folder-name', this.openFolder);
            $(document).on('click', '.gdtp-select-folder', this.selectFolder);
            $(document).on('click', '.gdtp-breadcrumb', this.navigateBreadcrumb);

            // Test email
            $('#gdtp-test-email').on('click', this.sendTestEmail);

            // AI Image Generation
            $('#gdtp-save-openai-key').on('click', this.saveOpenAIKey);
            $('#gdtp-remove-openai-key').on('click', this.removeOpenAIKey);
            $('#gdtp-test-openai').on('click', this.testOpenAI);
            $('#gdtp-test-image-gen').on('click', this.testImageGen);
        },

        runSync: function() {
            var $btn = $(this);
            var $message = $('#gdtp-sync-message');
            var $icon = $btn.find('.dashicons');

            $btn.prop('disabled', true);
            $icon.addClass('gdtp-spinning');
            $message.text(gdtpAdmin.strings.syncing).removeClass('success error');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_run_sync',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        // Reload page after short delay to show updated data
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $message.text(response.data.message || gdtpAdmin.strings.error).addClass('error');
                        $btn.prop('disabled', false);
                        $icon.removeClass('gdtp-spinning');
                    }
                },
                error: function() {
                    $message.text(gdtpAdmin.strings.error).addClass('error');
                    $btn.prop('disabled', false);
                    $icon.removeClass('gdtp-spinning');
                }
            });
        },

        saveOAuthCreds: function() {
            var $btn = $(this);
            var $message = $('#gdtp-oauth-message');
            var clientId = $('#gdtp-oauth-client-id').val().trim();
            var clientSecret = $('#gdtp-oauth-client-secret').val().trim();

            if (!clientId || !clientSecret) {
                $message.text('Both Client ID and Client Secret are required.').addClass('error');
                return;
            }

            $btn.prop('disabled', true).text(gdtpAdmin.strings.savingKey);
            $message.text('').removeClass('success error');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_save_oauth_creds',
                    nonce: gdtpAdmin.nonce,
                    client_id: clientId,
                    client_secret: clientSecret
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        // Redirect to Google OAuth
                        if (response.data.auth_url) {
                            window.location.href = response.data.auth_url;
                        }
                    } else {
                        $message.text(response.data.message).addClass('error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span> Save & Connect with Google');
                    }
                },
                error: function() {
                    $message.text(gdtpAdmin.strings.error).addClass('error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span> Save & Connect with Google');
                }
            });
        },

        removeOAuthCreds: function() {
            if (!confirm(gdtpAdmin.strings.confirmDisconnect)) {
                return;
            }

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_remove_oauth_creds',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                },
                error: function() {
                    alert(gdtpAdmin.strings.error);
                }
            });
        },

        disconnectGoogle: function() {
            if (!confirm(gdtpAdmin.strings.confirmDisconnect)) {
                return;
            }

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_disconnect_google',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                },
                error: function() {
                    alert(gdtpAdmin.strings.error);
                }
            });
        },

        testConnection: function() {
            var $btn = $(this);
            var $message = $('#gdtp-connection-message');

            $btn.prop('disabled', true).text(gdtpAdmin.strings.testing);
            $message.text('').removeClass('success error');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_test_connection',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                    } else {
                        $message.text(response.data.message).addClass('error');
                    }
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span> Test Connection');
                },
                error: function() {
                    $message.text(gdtpAdmin.strings.error).addClass('error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span> Test Connection');
                }
            });
        },

        browseFolders: function() {
            var parentId = $(this).data('parent-id') || 'root';
            GDTP.loadFolders(parentId);
        },

        loadFolders: function(parentId) {
            var $list = $('#gdtp-folder-list');
            $list.html('<p style="padding: 15px; text-align: center;">Loading...</p>');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_browse_folders',
                    nonce: gdtpAdmin.nonce,
                    parent_id: parentId
                },
                success: function(response) {
                    if (response.success) {
                        GDTP.renderFolders(response.data.folders, parentId);
                    } else {
                        $list.html('<p class="gdtp-folder-empty">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $list.html('<p class="gdtp-folder-empty">' + gdtpAdmin.strings.error + '</p>');
                }
            });
        },

        renderFolders: function(folders, parentId) {
            var $list = $('#gdtp-folder-list');
            $list.empty();

            if (folders.length === 0) {
                $list.html('<p class="gdtp-folder-empty">No subfolders found. You can select this folder.</p>');
                // Show select button for current parent if it's not root
                if (parentId !== 'root') {
                    $list.append('<div style="text-align: center; padding: 10px;"><button type="button" class="button button-primary gdtp-select-folder" data-folder-id="' + parentId + '" data-folder-name="Current folder">' + gdtpAdmin.strings.selectFolder + '</button></div>');
                }
                return;
            }

            $.each(folders, function(i, folder) {
                var $item = $('<div class="gdtp-folder-item">' +
                    '<span class="gdtp-folder-name" data-folder-id="' + folder.id + '">' +
                    '<span class="dashicons dashicons-portfolio"></span> ' +
                    '<span>' + $('<span>').text(folder.name).html() + '</span>' +
                    '</span>' +
                    '<span class="gdtp-folder-actions">' +
                    '<button type="button" class="button button-small gdtp-select-folder" data-folder-id="' + folder.id + '" data-folder-name="' + $('<span>').text(folder.name).html() + '">' + gdtpAdmin.strings.selectFolder + '</button>' +
                    '</span>' +
                    '</div>');
                $list.append($item);
            });
        },

        openFolder: function(e) {
            e.preventDefault();
            var folderId = $(this).data('folder-id');
            var folderName = $(this).find('span:last').text();

            // Add breadcrumb
            var $crumbs = $('#gdtp-folder-breadcrumbs');
            $crumbs.append(' <a href="#" data-folder-id="' + folderId + '" class="gdtp-breadcrumb">' + $('<span>').text(folderName).html() + '</a>');

            GDTP.loadFolders(folderId);
        },

        navigateBreadcrumb: function(e) {
            e.preventDefault();
            var folderId = $(this).data('folder-id');

            // Remove breadcrumbs after this one
            $(this).nextAll('.gdtp-breadcrumb').remove();

            GDTP.loadFolders(folderId);
        },

        selectFolder: function(e) {
            e.stopPropagation();
            var $btn = $(this);
            var folderId = $btn.data('folder-id');
            var folderName = $btn.data('folder-name');

            $btn.prop('disabled', true);

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_select_folder',
                    nonce: gdtpAdmin.nonce,
                    folder_id: folderId,
                    folder_name: folderName
                },
                success: function(response) {
                    if (response.success) {
                        GDTP.showNotice(response.data.message, 'success');
                        // Update the folder status display
                        $('#gdtp-folder-status').html(
                            '<p><span class="gdtp-status gdtp-status-active">Selected</span> ' +
                            '<strong>' + $('<span>').text(folderName).html() + '</strong> ' +
                            '<code style="font-size: 11px;">' + $('<span>').text(folderId).html() + '</code></p>'
                        );
                    } else {
                        GDTP.showNotice(response.data.message, 'error');
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    GDTP.showNotice(gdtpAdmin.strings.error, 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        sendTestEmail: function() {
            var $btn = $(this);
            var $message = $('#gdtp-email-message');

            $btn.prop('disabled', true).text(gdtpAdmin.strings.sending);
            $message.text('').removeClass('success error');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_send_test_email',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                    } else {
                        $message.text(response.data.message).addClass('error');
                    }
                    $btn.prop('disabled', false).text('Send Test Email');
                },
                error: function() {
                    $message.text(gdtpAdmin.strings.error).addClass('error');
                    $btn.prop('disabled', false).text('Send Test Email');
                }
            });
        },

        saveOpenAIKey: function() {
            var $btn = $(this);
            var $message = $('#gdtp-openai-message');
            var apiKey = $('#gdtp-openai-key-input').val().trim();

            if (!apiKey) {
                $message.text('Please enter an API key.').addClass('error');
                return;
            }

            $btn.prop('disabled', true).text(gdtpAdmin.strings.savingKey);
            $message.text('').removeClass('success error');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_save_openai_key',
                    nonce: gdtpAdmin.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $message.text(response.data.message).addClass('error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span> Save API Key');
                    }
                },
                error: function() {
                    $message.text(gdtpAdmin.strings.error).addClass('error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span> Save API Key');
                }
            });
        },

        removeOpenAIKey: function() {
            if (!confirm(gdtpAdmin.strings.confirmRemoveOpenAIKey)) {
                return;
            }

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_remove_openai_key',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                },
                error: function() {
                    alert(gdtpAdmin.strings.error);
                }
            });
        },

        testOpenAI: function() {
            var $btn = $(this);
            var $message = $('#gdtp-openai-message');

            $btn.prop('disabled', true).text(gdtpAdmin.strings.testingOpenAI);
            $message.text('').removeClass('success error');

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdtp_test_openai',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                    } else {
                        $message.text(response.data.message).addClass('error');
                    }
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span> Test Connection');
                },
                error: function() {
                    $message.text(gdtpAdmin.strings.error).addClass('error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span> Test Connection');
                }
            });
        },

        testImageGen: function() {
            var $btn = $(this);
            var $message = $('#gdtp-test-image-message');
            var $preview = $('#gdtp-test-image-preview');

            $btn.prop('disabled', true);
            $message.text(gdtpAdmin.strings.generatingImage).removeClass('success error');
            $preview.hide();

            $.ajax({
                url: gdtpAdmin.ajaxUrl,
                type: 'POST',
                timeout: 130000,
                data: {
                    action: 'gdtp_test_image_gen',
                    nonce: gdtpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        if (response.data.image_url) {
                            $preview.find('img').attr('src', response.data.image_url);
                            $preview.show();
                        }
                    } else {
                        $message.text(response.data.message).addClass('error');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr, status) {
                    var msg = status === 'timeout' ? 'Image generation timed out. Please try again.' : gdtpAdmin.strings.error;
                    $message.text(msg).addClass('error');
                    $btn.prop('disabled', false);
                }
            });
        },

        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').first().after($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        GDTP.init();
    });

})(jQuery);
