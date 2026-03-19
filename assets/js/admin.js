jQuery(document).ready(function($) {
    'use strict';

    var storageKey = 'dtpwp_admin_ui_state_v1';
    var recordsStorageKey = 'dtpwp_records_ui_state_v1';
    var ajaxConfig = window.dtpwp_ajax || {};
    var i18n = ajaxConfig.i18n || {};
    var toastTimer = null;

    function t(key, fallback) {
        if (i18n && Object.prototype.hasOwnProperty.call(i18n, key)) {
            return i18n[key];
        }
        return fallback || '';
    }

    function format(template, value) {
        return String(template || '')
            .replace('%s', value)
            .replace('%d', value);
    }

    function getState() {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) {
                return { activeTab: 'basic', collapsed: {}, order: {} };
            }
            var parsed = JSON.parse(raw);
            return {
                activeTab: parsed.activeTab || 'basic',
                collapsed: parsed.collapsed || {},
                order: parsed.order || {}
            };
        } catch (error) {
            return { activeTab: 'basic', collapsed: {}, order: {} };
        }
    }

    function saveState(state) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            // Ignore storage errors.
        }
    }

    function getRecordsState() {
        try {
            var raw = localStorage.getItem(recordsStorageKey);
            if (!raw) {
                return { exportFields: [], exportFormat: 'csv', perPage: null };
            }
            var parsed = JSON.parse(raw);
            return {
                exportFields: Array.isArray(parsed.exportFields) ? parsed.exportFields : [],
                exportFormat: parsed.exportFormat || 'csv',
                perPage: parsed.perPage || null
            };
        } catch (error) {
            return { exportFields: [], exportFormat: 'csv', perPage: null };
        }
    }

    function saveRecordsState(state) {
        try {
            localStorage.setItem(recordsStorageKey, JSON.stringify(state));
        } catch (error) {
            // Ignore storage errors.
        }
    }

    function showToast(message, type, duration) {
        var $toast = $('#dtpwp-toast');
        if (!$toast.length) {
            return;
        }

        if (toastTimer) {
            clearTimeout(toastTimer);
        }

        $toast
            .removeClass('is-success is-error is-visible')
            .text(message)
            .addClass(type === 'error' ? 'is-error' : 'is-success');

        void $toast[0].offsetWidth;
        $toast.addClass('is-visible');

        toastTimer = setTimeout(function() {
            $toast.removeClass('is-visible');
        }, duration || 2800);
    }

    function safeUpper(value, fallback) {
        if (!value) {
            return fallback;
        }
        return String(value).toUpperCase();
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getSelectedRecordIds() {
        return $('.dtpwp-records-table tbody input[type="checkbox"]:checked').map(function() {
            return $(this).val();
        }).get();
    }

    function updateRecordsCount(delta) {
        var $count = $('#dtpwp-records-count');
        if (!$count.length) {
            return;
        }
        var current = parseInt($count.text(), 10);
        if (isNaN(current)) {
            return;
        }
        var nextValue = current;
        if (typeof delta === 'number') {
            nextValue = Math.max(0, current + delta);
        }
        $count.text(nextValue);
        if (nextValue === 0 && $('.dtpwp-records-empty').length === 0) {
            window.location.reload();
        }
    }

    function getExportFields() {
        return $('.dtpwp-export-fields-panel input[type="checkbox"]:checked').map(function() {
            return $(this).val();
        }).get();
    }

    function buildExportUrl(ids, fields, format) {
        var baseUrl = ajaxConfig.export_url || '';
        if (!baseUrl) {
            return '';
        }
        var query = ['action=dtpwp_export_records', 'nonce=' + encodeURIComponent(ajaxConfig.export_nonce || '')];
        if (format) {
            query.push('format=' + encodeURIComponent(format));
        }
        if (Array.isArray(ids) && ids.length) {
            ids.forEach(function(id) {
                query.push('post_ids[]=' + encodeURIComponent(id));
            });
        }
        if (Array.isArray(fields) && fields.length) {
            fields.forEach(function(field) {
                query.push('fields[]=' + encodeURIComponent(field));
            });
        }
        return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + query.join('&');
    }

    function updateQueryParam(url, key, value) {
        try {
            var nextUrl = new URL(url);
            nextUrl.searchParams.set(key, value);
            return nextUrl.toString();
        } catch (error) {
            return url;
        }
    }

    function formatWebhookLabel(url) {
        if (!url) {
            return t('webhook_not_configured');
        }
        var match = String(url).match(/^https?:\/\/([^/]+)/i);
        if (match && match[1]) {
            return t('webhook_prefix') + match[1];
        }
        return t('webhook_configured');
    }

    function parseTemplateLines(text) {
        return String(text || '')
            .replace(/\r\n/g, '\n')
            .split('\n')
            .map(function(line) {
                return line.trim();
            })
            .filter(function(line) {
                return Boolean(line);
            });
    }

    function formatPreviewTime() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        return h + ':' + m;
    }

    function setGroupVisibility($group, isVisible, noAnimation) {
        if (!$group.length) {
            return;
        }

        if (isVisible) {
            $group.removeClass('is-hidden');
            if (noAnimation) {
                $group.show();
            } else {
                $group.stop(true, true).slideDown(160);
            }
        } else if (noAnimation) {
            $group.hide().addClass('is-hidden');
        } else {
            $group.stop(true, true).slideUp(160, function() {
                $group.addClass('is-hidden');
            });
        }
    }

    var uiState = getState();

    function switchTab(tabName) {
        if (!tabName) {
            return;
        }

        var $tab = $('.dtpwp-tab[data-tab="' + tabName + '"]');
        if (!$tab.length) {
            return;
        }

        $('.dtpwp-tab')
            .removeClass('is-active')
            .attr('aria-selected', 'false');

        $tab
            .addClass('is-active')
            .attr('aria-selected', 'true');

        $('.dtpwp-tab-panel').removeClass('is-active');
        $('.dtpwp-tab-panel[data-tab-panel="' + tabName + '"]').addClass('is-active');

        uiState.activeTab = tabName;
        saveState(uiState);
    }

    function setPanelCollapsed($panel, collapsed, skipStore) {
        var $toggle = $panel.find('.dtpwp-panel-toggle').first();
        $panel.toggleClass('is-collapsed', collapsed);
        $toggle.attr('aria-expanded', collapsed ? 'false' : 'true');
        $toggle.text(collapsed ? t('expand') : t('collapse'));

        if (!skipStore) {
            uiState.collapsed[$panel.data('panel-id')] = collapsed;
            saveState(uiState);
        }
    }

    function applyStoredOrder($list) {
        var tabName = String($list.data('tab'));
        var order = uiState.order[tabName];
        if (!Array.isArray(order) || !order.length) {
            return;
        }

        order.forEach(function(panelId) {
            var $panel = $list.children('.dtpwp-settings-panel[data-panel-id="' + panelId + '"]');
            if ($panel.length) {
                $list.append($panel);
            }
        });
    }

    function storePanelOrder($list) {
        var tabName = String($list.data('tab'));
        uiState.order[tabName] = $list.children('.dtpwp-settings-panel').map(function() {
            return String($(this).data('panel-id'));
        }).get();
        saveState(uiState);
    }

    function syncSecurityFields(noAnimation) {
        var type = $('#dtpwp-security-type').val();
        setGroupVisibility($('#dtpwp-security-keyword'), type === 'keyword', noAnimation);
        setGroupVisibility($('#dtpwp-security-secret'), type === 'secret', noAnimation);
        setGroupVisibility($('#dtpwp-security-ip-whitelist'), type === 'ip_whitelist', noAnimation);
    }

    function syncAdvancedFields(noAnimation) {
        var advancedEnabled = $('#dtpwp-enable-advanced-features').is(':checked');
        var nestedEnabled = $('#dtpwp-enable-nested-feature').is(':checked');
        setGroupVisibility($('#dtpwp-advanced-feature-fields'), advancedEnabled, noAnimation);
        setGroupVisibility($('#dtpwp-nested-feature-fields'), advancedEnabled && nestedEnabled, noAnimation);
    }

    function updateRangeValue(selector, valueSelector) {
        $(valueSelector).text($(selector).val());
    }

    function updatePreview() {
        var webhookUrl = $('#dtpwp-webhook-url').val() || '';
        var customMessage = $.trim($('#dtpwp-custom-message').val() || '');
        var templateText = $('#dtpwp-post-template').val() || '';
        var templateLines = parseTemplateLines(templateText);

        var pushInterval = $('#dtpwp-push-interval').val() || '5';
        var messageType = ($('#dtpwp-message-type').val() || 'text').toLowerCase();
        var preset = ($('#dtpwp-preview-preset').val() || 'clean').toLowerCase();
        var advancedEnabled = $('#dtpwp-enable-advanced-features').is(':checked');
        var nestedEnabled = $('#dtpwp-enable-nested-feature').is(':checked');
        var nestedNote = $('#dtpwp-nested-feature-note').val() || '';
        var color = $('#dtpwp-theme-color').val() || '#2563eb';

        if (['text', 'link', 'markdown'].indexOf(messageType) === -1) {
            messageType = 'text';
        }
        if (['clean', 'compact', 'bold'].indexOf(preset) === -1) {
            preset = 'clean';
        }

        var fallbackTitle = templateLines[0] || t('site_notice');
        var title = customMessage ? t('custom_message') : fallbackTitle;
        title = title.replace(/^[#*\-\s【】]+/g, '').trim() || t('site_notice');

        var body = customMessage || templateLines.slice(1).join('\n') || templateLines[0] || t('empty_message');
        var urlMatch = (body + '\n' + templateText).match(/https?:\/\/[^\s]+/);
        var linkUrl = urlMatch ? urlMatch[0] : 'https://example.com/post/123';
        var linkTitle = title || t('message_notice');

        var mdLines = customMessage ? parseTemplateLines(customMessage) : templateLines;
        var mdTitle = mdLines[0] || t('markdown_notice');
        if (mdTitle.indexOf('#') !== 0) {
            mdTitle = '# ' + mdTitle;
        }
        var mdItems = mdLines.slice(1, 4);
        if (!mdItems.length) {
            mdItems = [t('example_title'), t('example_author'), t('example_status')];
        }

        var nestedText = t('nested_disabled');
        if (advancedEnabled && nestedEnabled) {
            nestedText = t('nested_prefix') + ($.trim(nestedNote) || t('nested_enabled'));
        }

        var messageTypeLabels = {
            text: t('message_type_text'),
            link: t('message_type_link'),
            markdown: t('message_type_markdown')
        };
        var modeText = messageTypeLabels[messageType] || t('message_type_text');
        var presetLabels = {
            clean: t('preset_clean'),
            compact: t('preset_compact'),
            bold: t('preset_bold')
        };
        var presetText = presetLabels[preset] || t('preset_clean');

        var $bubble = $('#dtpwp-preview-bubble');
        $bubble
            .removeClass('is-type-text is-type-link is-type-markdown is-preset-clean is-preset-compact is-preset-bold')
            .addClass('is-type-' + messageType)
            .addClass('is-preset-' + preset);

        $('#dtpwp-preview-time').text(formatPreviewTime());
        $('#dtpwp-preview-meta').text(modeText + ' | ' + presetText);
        $('#dtpwp-preview-title').text(title);
        $('#dtpwp-preview-text').text(body);
        $('#dtpwp-preview-link-title').text(linkTitle);
        $('#dtpwp-preview-link-url').text(linkUrl);
        $('#dtpwp-preview-md-title').text(mdTitle);
        $('#dtpwp-preview-md-list').html(mdItems.map(function(item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join(''));

        $('#dtpwp-preview-mode').text(modeText);
        $('#dtpwp-preview-preset-badge').text(t('preset_prefix') + presetText);
        $('#dtpwp-preview-type-inline').text(modeText);
        $('#dtpwp-preview-advanced').text(advancedEnabled ? t('advanced_enabled') : t('advanced_disabled'));
        $('#dtpwp-preview-nested').text(nestedText);
        $('#dtpwp-preview-webhook').text(formatWebhookLabel(webhookUrl));
        $('#dtpwp-preview-push').text(format(t('push_interval_format', 'Interval: %d minutes'), pushInterval));
        $('#dtpwp-preview-color').text(color);
        $('#dtpwp-preview-preset-text').text(presetText);

        $('.dtpwp-settings-shell').css('--dtpwp-accent', color);
    }

    function createKeywordItem(value) {
        return $('<div class="keyword-item">').append(
            $('<input>', {
                type: 'text',
                name: 'dtpwp_dingtalk_settings[security_keyword][]',
                value: value || ''
            }),
            $('<button>', {
                type: 'button',
                class: 'button button-link-delete dtpwp-remove-keyword',
                text: t('delete')
            })
        );
    }

    function createIpItem(value) {
        return $('<div class="ip-item">').append(
            $('<input>', {
                type: 'text',
                name: 'dtpwp_dingtalk_settings[security_ip_whitelist][]',
                value: value || ''
            }),
            $('<button>', {
                type: 'button',
                class: 'button button-link-delete dtpwp-remove-ip',
                text: t('delete')
            })
        );
    }

    function ensureListHasAtLeastOneItem($list, creator) {
        if (!$list.children().length) {
            $list.append(creator(''));
        }
    }

    function initSettingsPageUI() {
        if (!$('#dtpwp-settings-form').length) {
            return;
        }

        $('.dtpwp-tab').on('click', function() {
            switchTab($(this).data('tab'));
        });
        switchTab(uiState.activeTab || 'basic');

        $('.dtpwp-settings-panel').each(function() {
            var $panel = $(this);
            var panelId = String($panel.data('panel-id'));
            setPanelCollapsed($panel, Boolean(uiState.collapsed[panelId]), true);
        });

        $(document).on('click', '.dtpwp-panel-toggle', function() {
            var $panel = $(this).closest('.dtpwp-settings-panel');
            setPanelCollapsed($panel, !$panel.hasClass('is-collapsed'));
        });

        $('.dtpwp-accordion-list').each(function() {
            var $list = $(this);
            applyStoredOrder($list);
            if ($.fn.sortable) {
                $list.sortable({
                    handle: '.dtpwp-drag-handle',
                    items: '> .dtpwp-settings-panel',
                    placeholder: 'dtpwp-sort-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        storePanelOrder($list);
                    }
                });
            }
            storePanelOrder($list);
        });

        $('#dtpwp-security-type').on('change', function() {
            syncSecurityFields(false);
            updatePreview();
        });
        syncSecurityFields(true);

        $('#dtpwp-enable-advanced-features, #dtpwp-enable-nested-feature').on('change', function() {
            syncAdvancedFields(false);
            updatePreview();
        });
        syncAdvancedFields(true);

        $('.dtpwp-add-keyword').on('click', function() {
            $('#dtpwp-keyword-list').append(createKeywordItem(''));
        });

        $(document).on('click', '.dtpwp-remove-keyword', function() {
            var $list = $('#dtpwp-keyword-list');
            $(this).closest('.keyword-item').remove();
            ensureListHasAtLeastOneItem($list, createKeywordItem);
        });

        $('.dtpwp-add-ip').on('click', function() {
            $('#dtpwp-ip-list').append(createIpItem(''));
        });

        $(document).on('click', '.dtpwp-remove-ip', function() {
            var $list = $('#dtpwp-ip-list');
            $(this).closest('.ip-item').remove();
            ensureListHasAtLeastOneItem($list, createIpItem);
        });

        if ($.fn.wpColorPicker) {
            $('.dtpwp-color-field').wpColorPicker({
                change: function(event, ui) {
                    if (ui && ui.color) {
                        $('.dtpwp-settings-shell').css('--dtpwp-accent', ui.color.toString());
                    }
                    updatePreview();
                },
                clear: function() {
                    updatePreview();
                }
            });
        }

        $('#dtpwp-push-interval').on('input change', function() {
            updateRangeValue('#dtpwp-push-interval', '#dtpwp-push-interval-value');
            updatePreview();
        });

        $('#dtpwp-retry-count').on('input change', function() {
            updateRangeValue('#dtpwp-retry-count', '#dtpwp-retry-count-value');
            updatePreview();
        });

        updateRangeValue('#dtpwp-push-interval', '#dtpwp-push-interval-value');
        updateRangeValue('#dtpwp-retry-count', '#dtpwp-retry-count-value');

        $('#dtpwp-settings-form').on('input change', 'input, select, textarea', function() {
            updatePreview();
        });

        $('#dtpwp-settings-form').on('submit', function() {
            showToast(t('saving_settings'), 'success', 1200);
        });

        $('#dtpwp-reset-layout').on('click', function() {
            localStorage.removeItem(storageKey);
            window.location.reload();
        });

        if (parseInt(ajaxConfig.settings_updated || 0, 10) === 1) {
            showToast(t('settings_saved'), 'success', 2800);
        }

        updatePreview();
        setInterval(function() {
            $('#dtpwp-preview-time').text(formatPreviewTime());
        }, 60000);
    }

    function initRecordsPageUI() {
        var $records = $('.dtpwp-records');
        if (!$records.length) {
            return;
        }

        var state = getRecordsState();
        var $format = $('#dtpwp-export-format');
        var $fields = $('.dtpwp-export-fields-panel input[type="checkbox"]');
        var $perPage = $('#dtpwp-per-page');
        var currentPerPage = parseInt($records.data('per-page'), 10);
        var xlsxAvailable = parseInt(ajaxConfig.xlsx_available || 0, 10) === 1;

        if ($format.length && state.exportFormat) {
            $format.val(state.exportFormat);
        }

        if ($format.length && !xlsxAvailable) {
            var $xlsxOption = $format.find('option[value="xlsx"]');
            if ($xlsxOption.length) {
                $xlsxOption.prop('disabled', true);
                if ($format.val() === 'xlsx') {
                    $format.val('csv');
                    state.exportFormat = 'csv';
                    saveRecordsState(state);
                    showToast(t('xlsx_unavailable', 'XLSX export requires the PHP ZipArchive extension. Switched to CSV.'), 'error', 3200);
                }
            }
        }

        if ($fields.length && state.exportFields.length) {
            $fields.each(function() {
                var $field = $(this);
                $field.prop('checked', state.exportFields.indexOf($field.val()) !== -1);
            });
        } else if ($fields.length) {
            state.exportFields = getExportFields();
        }

        if ($perPage.length && state.perPage) {
            var storedPerPage = parseInt(state.perPage, 10);
            if (!isNaN(storedPerPage) && storedPerPage !== currentPerPage) {
                var nextUrl = updateQueryParam(window.location.href, 'dtpwp_per_page', storedPerPage);
                nextUrl = updateQueryParam(nextUrl, 'paged', 1);
                window.location.href = nextUrl;
                return;
            }
        }

        if ($perPage.length) {
            state.perPage = $perPage.val();
        }
        state.exportFormat = $format.val() || state.exportFormat;
        state.exportFields = getExportFields();
        saveRecordsState(state);

        $format.on('change', function() {
            state.exportFormat = $(this).val();
            saveRecordsState(state);
        });

        $fields.on('change', function() {
            state.exportFields = getExportFields();
            saveRecordsState(state);
        });

        $perPage.on('change', function() {
            state.perPage = $(this).val();
            saveRecordsState(state);
            var nextUrl = updateQueryParam(window.location.href, 'dtpwp_per_page', state.perPage);
            nextUrl = updateQueryParam(nextUrl, 'paged', 1);
            window.location.href = nextUrl;
        });
    }

    function initAjaxActions() {
        $('#dtpwp-test-message').on('click', function() {
            var $button = $(this);
            var originalText = $button.text();

            $button.prop('disabled', true).text(t('sending'));

            $.ajax({
                url: ajaxConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_test_message',
                    nonce: ajaxConfig.nonce,
                    settings: {
                        webhook_url: $('#dtpwp-webhook-url').val(),
                        security_type: $('#dtpwp-security-type').val(),
                        security_secret: $('#dtpwp-security-secret-input').val(),
                        message_type: $('#dtpwp-message-type').val()
                    }
                }
            }).done(function(response) {
                if (response && response.data && response.data.message) {
                    showToast(response.data.message, response.success ? 'success' : 'error', 3000);
                } else {
                    showToast(t('test_response_invalid'), 'error', 3000);
                }
            }).fail(function() {
                showToast(t('test_send_failed'), 'error', 3000);
            }).always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        });

        $(document).on('change', '.dtpwp-records-table thead .check-column input[type="checkbox"]', function() {
            var isChecked = $(this).is(':checked');
            $('.dtpwp-records-table tbody input[type="checkbox"]').prop('checked', isChecked);
        });

        $(document).on('click', '#dtpwp-apply-bulk', function() {
            var bulkAction = $('#dtpwp-bulk-action').val() || '';
            var ids = getSelectedRecordIds();

            if (!bulkAction) {
                showToast(t('bulk_action_required'), 'error', 2400);
                return;
            }

            if (!ids.length) {
                showToast(t('bulk_records_required'), 'error', 2400);
                return;
            }

            var confirmText = bulkAction === 'delete_record'
                ? 'Deleting records will not delete posts, only remove push records. Continue?'
                : 'Confirm bulk operation on selected records?';
            if (!window.confirm(confirmText)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text(t('processing'));

            $.ajax({
                url: ajaxConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_bulk_update_records',
                    nonce: ajaxConfig.nonce,
                    bulk_action: bulkAction,
                    post_ids: ids
                }
            }).done(function(response) {
                if (response.success) {
                    ids.forEach(function(id) {
                        $('.dtpwp-records-table tbody input[value="' + id + '"]').closest('tr').fadeOut(220, function() {
                            $(this).remove();
                            updateRecordsCount(-1);
                        });
                    });
                    var actionLabel = bulkAction === 'delete_record' ? t('record_deleted') : t('record_unmarked');
                    showToast(actionLabel, 'success', 2600);
                } else {
                    showToast(response.data && response.data.message ? response.data.message : t('bulk_failed'), 'error', 2800);
                }
            }).fail(function() {
                showToast(t('bulk_failed'), 'error', 2800);
            }).always(function() {
                $button.prop('disabled', false).text(t('apply'));
            });
        });

        function requestExport(ids, $button) {
            var fields = getExportFields();
            var format = $('#dtpwp-export-format').val() || 'csv';
            var exportMax = parseInt(ajaxConfig.export_max || 0, 10);
            var totalCount = parseInt($('#dtpwp-records-count').text(), 10);

            if (!fields.length) {
                showToast(t('export_field_required'), 'error', 2400);
                return;
            }

            if (!ids.length && exportMax && totalCount > exportMax) {
                showToast(t('export_too_many'), 'error', 2800);
                return;
            }

            if ($button && $button.length) {
                $button.prop('disabled', true).text(t('export_generating'));
            }

            $.ajax({
                url: ajaxConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_prepare_export',
                    nonce: ajaxConfig.nonce,
                    post_ids: ids,
                    fields: fields,
                    format: format
                }
            }).done(function(response) {
                if (response && response.success && response.data && response.data.download_url) {
                    showToast(t('export_ready'), 'success', 2600);
                    window.location.href = response.data.download_url;
                    return;
                }

                var message = response && response.data && response.data.message
                    ? response.data.message
                    : t('export_failed');
                showToast(message, 'error', 2800);
            }).fail(function() {
                var fallbackUrl = buildExportUrl(ids, fields, format);
                if (fallbackUrl) {
                    window.location.href = fallbackUrl;
                } else {
                    showToast(t('export_failed'), 'error', 2800);
                }
            }).always(function() {
                if ($button && $button.length) {
                    var label = $button.attr('id') === 'dtpwp-export-all' ? t('export_all') : t('export_selected');
                    $button.prop('disabled', false).text(label);
                }
            });
        }

        $(document).on('click', '#dtpwp-export-selected', function() {
            var ids = getSelectedRecordIds();
            if (!ids.length) {
                showToast(t('export_records_required'), 'error', 2400);
                return;
            }
            requestExport(ids, $(this));
        });

        $(document).on('click', '#dtpwp-export-all', function() {
            requestExport([], $(this));
        });

        $(document).on('click', '.dtpwp-export-select-all', function() {
            $('.dtpwp-export-fields-panel input[type="checkbox"]').prop('checked', true).trigger('change');
        });

        $(document).on('click', '.dtpwp-export-clear', function() {
            $('.dtpwp-export-fields-panel input[type="checkbox"]').prop('checked', false).trigger('change');
        });

        $(document).on('click', '.dtpwp-mark-as-not-sent', function() {
            var $button = $(this);
            var postId = $button.data('post-id');

            if (!window.confirm(t('mark_confirm'))) {
                return;
            }

            $button.prop('disabled', true).text(t('processing'));

            $.ajax({
                url: ajaxConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_mark_as_sent',
                    nonce: ajaxConfig.nonce,
                    post_id: postId,
                    mark_as: 'not_sent'
                }
            }).done(function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut(220, function() {
                        $(this).remove();
                        updateRecordsCount(-1);
                    });
                    showToast(t('record_unmarked'), 'success', 2600);
                } else {
                    showToast(response.data && response.data.message ? response.data.message : t('action_failed'), 'error', 2800);
                }
            }).fail(function() {
                showToast(t('action_failed'), 'error', 2800);
            }).always(function() {
                $button.prop('disabled', false).text(t('mark_cancel'));
            });
        });

        $('#dtpwp-clear-records').on('click', function() {
            var $button = $(this);
            var originalText = $button.text();

            if (!window.confirm(t('clear_confirm'))) {
                return;
            }

            $button.prop('disabled', true).text(t('clearing'));

            $.ajax({
                url: ajaxConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_clear_sent_records',
                    nonce: ajaxConfig.nonce
                }
            }).done(function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    showToast(response.data && response.data.message ? response.data.message : t('clear_failed'), 'error', 3000);
                }
            }).fail(function() {
                showToast(t('clear_failed'), 'error', 3000);
            }).always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        });
    }

    initSettingsPageUI();
    initRecordsPageUI();
    initAjaxActions();
});
