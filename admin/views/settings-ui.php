<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = isset( $settings ) && is_array( $settings ) ? $settings : array();

$defaults = array(
    'webhook_url' => '',
    'security_type' => 'keyword',
    'security_keyword' => array( '' ),
    'security_secret' => '',
    'security_ip_whitelist' => array( '' ),
    'message_type' => 'text',
    'custom_message' => '',
    'post_template' => "[New Post]\nTitle: {title}\nAuthor: {author}\nLink: {link}",
    'user_template' => "[New User Registration]\nUsername: {username}\nEmail: {email}\nRegistration Time: {register_time}",
    'enable_new_post' => 1,
    'enable_post_update' => 0,
    'enable_custom_post_type' => array(),
    'enable_new_user' => 1,
    'push_interval' => 5,
    'retry_count' => 3,
    'retry_interval' => 10,
    'deduplicate_days' => 30,
    'theme_color' => '#2563eb',
    'preview_preset' => 'clean',
    'enable_advanced_features' => 0,
    'advanced_mode' => 'smart',
    'enable_nested_feature' => 0,
    'nested_feature_note' => '',
);

$settings = wp_parse_args( $settings, $defaults );
$security_keywords = is_array( $settings['security_keyword'] ) && ! empty( $settings['security_keyword'] ) ? $settings['security_keyword'] : array( '' );
$security_ips = is_array( $settings['security_ip_whitelist'] ) && ! empty( $settings['security_ip_whitelist'] ) ? $settings['security_ip_whitelist'] : array( '' );
$custom_post_types = is_array( $settings['enable_custom_post_type'] ) ? $settings['enable_custom_post_type'] : array();
$post_types = get_post_types( array( 'public' => true ), 'objects' );
$settings_updated = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
?>
<div class="wrap dtpwp-settings-shell" data-settings-updated="<?php echo $settings_updated ? '1' : '0'; ?>">
    <div class="dtpwp-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p><?php echo esc_html('Welcome to your dashboard!'); ?></p>
    </div>

    <?php settings_errors( 'dtpwp_settings' ); ?>

    <form method="post" action="options.php" id="dtpwp-settings-form">
        <?php settings_fields( 'dtpwp_settings' ); ?>

        <div class="dtpwp-tab-nav" role="tablist" aria-label="<?php echo esc_attr('Settings Groups'); ?>">
            <button type="button" class="dtpwp-tab is-active" data-tab="basic" role="tab" aria-selected="true"><?php echo esc_html('Basic Settings'); ?></button>
            <button type="button" class="dtpwp-tab" data-tab="advanced" role="tab" aria-selected="false"><?php echo esc_html('Advanced Settings'); ?></button>
        </div>

        <div class="dtpwp-layout">
            <div class="dtpwp-main">
                <section class="dtpwp-tab-panel is-active" data-tab-panel="basic">
                    <div class="dtpwp-accordion-list" data-tab="basic">
                        <article class="dtpwp-settings-panel" data-panel-id="basic-access">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php echo esc_html('Connection & Authentication'); ?></h2>
                                        <p><?php echo esc_html('Configure DingTalk bot Webhook and authentication.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php echo esc_html('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-webhook-url"><?php echo esc_html('Webhook URL'); ?></label>
                                    <input type="text" id="dtpwp-webhook-url" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[webhook_url]" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx" />
                                </div>
                                <div class="dtpwp-field">
                                    <button type="button" class="button button-secondary" id="dtpwp-test-message"><?php echo esc_html('Send Test Message'); ?></button>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="basic-message">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php echo esc_html('Message Content'); ?></h2>
                                        <p><?php echo esc_html('Control message type, templates, and preset styles.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php echo esc_html('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-message-type"><?php echo esc_html('Message Type'); ?></label>
                                        <select id="dtpwp-message-type" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[message_type]">
                                            <option value="text" <?php selected( $settings['message_type'], 'text' ); ?>><?php echo esc_html('Text Message'); ?></option>
                                            <option value="link" <?php selected( $settings['message_type'], 'link' ); ?>><?php echo esc_html('Link Message'); ?></option>
                                            <option value="markdown" <?php selected( $settings['message_type'], 'markdown' ); ?>><?php echo esc_html('Markdown Message'); ?></option>
                                        </select>
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-preview-preset"><?php echo esc_html('Preset Style'); ?></label>
                                        <select id="dtpwp-preview-preset" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[preview_preset]">
                                            <option value="clean" <?php selected( $settings['preview_preset'], 'clean' ); ?>><?php echo esc_html('Clean'); ?></option>
                                            <option value="compact" <?php selected( $settings['preview_preset'], 'compact' ); ?>><?php echo esc_html('Compact'); ?></option>
                                            <option value="bold" <?php selected( $settings['preview_preset'], 'bold' ); ?>><?php echo esc_html('Bold'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="dtpwp-field">
                                    <label for="dtpwp-custom-message"><?php echo esc_html('Custom Message'); ?></label>
                                    <input type="text" id="dtpwp-custom-message" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[custom_message]" value="<?php echo esc_attr( $settings['custom_message'] ); ?>" placeholder="<?php echo esc_attr('Example: Welcome to Kate522 Notifier for DingTalk Settings Center'); ?>" />
                                </div>
                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-post-template"><?php echo esc_html('Post Push Template'); ?></label>
                                        <textarea id="dtpwp-post-template" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[post_template]" rows="4"><?php echo esc_textarea( $settings['post_template'] ); ?></textarea>
                                        <p class="description"><?php echo esc_html('Available Placeholders: {title}, {author}, {link}, {excerpt}, {category}, {categories}, {date}, {publish_time}, {post_date}, {post_type}'); ?></p>
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-user-template"><?php echo esc_html('User Notification Template'); ?></label>
                                        <textarea id="dtpwp-user-template" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[user_template]" rows="4"><?php echo esc_textarea( $settings['user_template'] ); ?></textarea>
                                        <p class="description"><?php echo esc_html('Available Placeholders: {username}, {email}, {register_time}'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="basic-trigger">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php echo esc_html('Trigger Scenarios'); ?></h2>
                                        <p><?php echo esc_html('Control post and user notification triggers.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php echo esc_html('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-new-post">
                                        <span><?php echo esc_html('New Post Publishing'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-new-post" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_new_post]" value="1" <?php checked( $settings['enable_new_post'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-post-update">
                                        <span><?php echo esc_html('Post Update Push'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-post-update" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_post_update]" value="1" <?php checked( $settings['enable_post_update'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-new-user">
                                        <span><?php echo esc_html('New User Registration'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-new-user" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_new_user]" value="1" <?php checked( $settings['enable_new_user'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>

                                <div class="dtpwp-field">
                                    <label><?php echo esc_html('Enabled Custom Post Types'); ?></label>
                                    <div class="dtpwp-checkbox-grid">
                                        <?php foreach ( $post_types as $post_type ) : ?>
                                            <?php if ( 'post' === $post_type->name || 'page' === $post_type->name ) : ?>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <label>
                                                <input type="checkbox" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_custom_post_type][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $custom_post_types, true ) ); ?> />
                                                <span><?php echo esc_html( $post_type->label ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="dtpwp-tab-panel" data-tab-panel="advanced">
                    <div class="dtpwp-accordion-list" data-tab="advanced">
                        <article class="dtpwp-settings-panel" data-panel-id="advanced-security">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php echo esc_html('Security Verification'); ?></h2>
                                        <p><?php echo esc_html('Display configuration fields based on security method.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php echo esc_html('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-security-type"><?php echo esc_html('Security Verification Method'); ?></label>
                                    <select id="dtpwp-security-type" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_type]">
                                        <option value="keyword" <?php selected( $settings['security_type'], 'keyword' ); ?>><?php echo esc_html('Keyword'); ?></option>
                                        <option value="secret" <?php selected( $settings['security_type'], 'secret' ); ?>><?php echo esc_html('Signature'); ?></option>
                                        <option value="ip_whitelist" <?php selected( $settings['security_type'], 'ip_whitelist' ); ?>><?php echo esc_html('IP Whitelist'); ?></option>
                                    </select>
                                </div>

                                <div id="dtpwp-security-keyword" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label><?php echo esc_html('Keyword List'); ?></label>
                                        <div id="dtpwp-keyword-list">
                                            <?php foreach ( $security_keywords as $keyword ) : ?>
                                                <div class="keyword-item">
                                                    <input type="text" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_keyword][]" value="<?php echo esc_attr( $keyword ); ?>" />
                                                    <button type="button" class="button button-link-delete dtpwp-remove-keyword"><?php echo esc_html('Delete'); ?></button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary dtpwp-add-keyword"><?php echo esc_html('Add Keyword'); ?></button>
                                    </div>
                                </div>

                                <div id="dtpwp-security-secret" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-security-secret-input"><?php echo esc_html('Signature Secret'); ?></label>
                                        <input type="text" id="dtpwp-security-secret-input" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_secret]" value="<?php echo esc_attr( $settings['security_secret'] ); ?>" />
                                    </div>
                                </div>

                                <div id="dtpwp-security-ip-whitelist" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label><?php echo esc_html('IP Whitelist'); ?></label>
                                        <div id="dtpwp-ip-list">
                                            <?php foreach ( $security_ips as $ip ) : ?>
                                                <div class="ip-item">
                                                    <input type="text" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_ip_whitelist][]" value="<?php echo esc_attr( $ip ); ?>" />
                                                    <button type="button" class="button button-link-delete dtpwp-remove-ip"><?php echo esc_html('Delete'); ?></button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary dtpwp-add-ip"><?php echo esc_html('Add IP'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="advanced-options">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php echo esc_html('Advanced Features'); ?></h2>
                                        <p><?php echo esc_html('Show more granular configuration linkage, enable as needed.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php echo esc_html('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-advanced-features">
                                        <span><?php echo esc_html('Enable Advanced Features'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-advanced-features" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_advanced_features]" value="1" <?php checked( $settings['enable_advanced_features'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>

                                <div id="dtpwp-advanced-feature-fields" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field-grid">
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-advanced-mode"><?php echo esc_html('Advanced Mode'); ?></label>
                                            <select id="dtpwp-advanced-mode" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[advanced_mode]">
                                                <option value="smart" <?php selected( $settings['advanced_mode'], 'smart' ); ?>><?php echo esc_html('Smart'); ?></option>
                                                <option value="strict" <?php selected( $settings['advanced_mode'], 'strict' ); ?>><?php echo esc_html('Strict'); ?></option>
                                                <option value="performance" <?php selected( $settings['advanced_mode'], 'performance' ); ?>><?php echo esc_html('Performance'); ?></option>
                                            </select>
                                        </div>
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-theme-color"><?php echo esc_html('Theme Color'); ?></label>
                                            <input type="text" id="dtpwp-theme-color" class="dtpwp-color-field" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[theme_color]" value="<?php echo esc_attr( $settings['theme_color'] ); ?>" data-default-color="#2563eb" />
                                        </div>
                                    </div>

                                    <div class="dtpwp-switch-list">
                                        <label class="dtpwp-switch-row" for="dtpwp-enable-nested-feature">
                                            <span><?php echo esc_html('Enable Nested Options'); ?></span>
                                            <span class="dtpwp-switch">
                                                <input type="checkbox" id="dtpwp-enable-nested-feature" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_nested_feature]" value="1" <?php checked( $settings['enable_nested_feature'], 1 ); ?> />
                                                <span class="dtpwp-switch-ui"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div id="dtpwp-nested-feature-fields" class="dtpwp-conditional-group dtpwp-nested-group">
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-nested-feature-note"><?php echo esc_html('Sub-option Description'); ?></label>
                                            <input type="text" id="dtpwp-nested-feature-note" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[nested_feature_note]" value="<?php echo esc_attr( $settings['nested_feature_note'] ); ?>" placeholder="<?php echo esc_attr('Example: Enable strict mode only for VIP channels'); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="advanced-runtime">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php echo esc_html('Runtime Parameters'); ?></h2>
                                        <p><?php echo esc_html('Adjust polling, retry, and deduplication strategies, reflected in preview in real-time.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php echo esc_html('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-push-interval"><?php echo esc_html('Push Interval (minutes)'); ?></label>
                                    <div class="dtpwp-range-row">
                                        <input type="range" id="dtpwp-push-interval" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[push_interval]" min="1" max="60" value="<?php echo esc_attr( $settings['push_interval'] ); ?>" />
                                        <span class="dtpwp-range-value" id="dtpwp-push-interval-value"><?php echo esc_html( $settings['push_interval'] ); ?></span>
                                    </div>
                                </div>

                                <div class="dtpwp-field">
                                    <label for="dtpwp-retry-count"><?php echo esc_html('Retry Count'); ?></label>
                                    <div class="dtpwp-range-row">
                                        <input type="range" id="dtpwp-retry-count" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[retry_count]" min="1" max="10" value="<?php echo esc_attr( $settings['retry_count'] ); ?>" />
                                        <span class="dtpwp-range-value" id="dtpwp-retry-count-value"><?php echo esc_html( $settings['retry_count'] ); ?></span>
                                    </div>
                                </div>

                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-retry-interval"><?php echo esc_html('Retry Interval (seconds)'); ?></label>
                                        <input type="number" id="dtpwp-retry-interval" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[retry_interval]" min="1" max="60" value="<?php echo esc_attr( $settings['retry_interval'] ); ?>" />
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-deduplicate-days"><?php echo esc_html('Deduplication Days'); ?></label>
                                        <input type="number" id="dtpwp-deduplicate-days" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[deduplicate_days]" min="1" max="365" value="<?php echo esc_attr( $settings['deduplicate_days'] ); ?>" />
                                    </div>
                                </div>

                                                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="dtpwp-preview">
                <h2><?php echo esc_html('Live Preview'); ?></h2>
                <p><?php echo esc_html('High-fidelity message card, close to real receiver display.'); ?></p>
                <div class="dtpwp-preview-device" id="dtpwp-preview-device">
                    <div class="dtpwp-preview-device-head">
                        <span><?php echo esc_html('DingTalk'); ?></span>
                        <span id="dtpwp-preview-time">09:41</span>
                    </div>

                    <div class="dtpwp-preview-chat">
                        <div class="dtpwp-preview-avatar">DP</div>
                        <div class="dtpwp-preview-bubble is-type-text is-preset-clean" id="dtpwp-preview-bubble">
                            <div class="dtpwp-preview-bubble-head">
                                <strong><?php echo esc_html('Kate522 Notifier for DingTalk Bot'); ?></strong>
                                <span id="dtpwp-preview-meta"><?php echo esc_html('Text Message | Clean'); ?></span>
                            </div>

                            <h3 class="dtpwp-preview-title" id="dtpwp-preview-title"><?php echo esc_html('Site Notification'); ?></h3>
                            <p class="dtpwp-preview-text" id="dtpwp-preview-text"><?php echo esc_html('No Message Content Set'); ?></p>

                            <div class="dtpwp-preview-link" id="dtpwp-preview-link">
                                <div class="dtpwp-preview-link-cover"></div>
                                <div class="dtpwp-preview-link-main">
                                    <strong id="dtpwp-preview-link-title"><?php echo esc_html('Post Update Notification'); ?></strong>
                                    <span id="dtpwp-preview-link-url">https://example.com/post/123</span>
                                </div>
                            </div>

                            <div class="dtpwp-preview-markdown" id="dtpwp-preview-markdown">
                                <h4 id="dtpwp-preview-md-title"><?php echo esc_html('# Markdown Notification'); ?></h4>
                                <ul id="dtpwp-preview-md-list">
                                    <li><?php echo esc_html('Title: Sample Content'); ?></li>
                                    <li><?php echo esc_html('Author: Admin'); ?></li>
                                    <li><?php echo esc_html('Status: Published'); ?></li>
                                </ul>
                            </div>

                            <div class="dtpwp-preview-badges">
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-mode"><?php echo esc_html('Text Message'); ?></span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-preset-badge"><?php echo esc_html('Preset: Clean'); ?></span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-advanced"><?php echo esc_html('Advanced Features: Off'); ?></span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-nested"><?php echo esc_html('Nested: Not Enabled'); ?></span>
                            </div>

                            <div class="dtpwp-preview-footer">
                                <span id="dtpwp-preview-webhook"><?php echo esc_html('Webhook: Not Configured'); ?></span>
                                <span id="dtpwp-preview-push"><?php echo esc_html('Interval: 5 minutes'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dtpwp-preview-quick">
                    <div><span><?php echo esc_html('Theme'); ?></span><strong id="dtpwp-preview-color">#2563eb</strong></div>
                    <div><span><?php echo esc_html('Preset'); ?></span><strong id="dtpwp-preview-preset-text"><?php echo esc_html('Clean'); ?></strong></div>
                    <div><span><?php echo esc_html('Type'); ?></span><strong id="dtpwp-preview-type-inline"><?php echo esc_html('Text Message'); ?></strong></div>
                </div>
            </aside>
        </div>

        <div class="dtpwp-actions">
            <button type="submit" class="button button-primary button-hero" id="dtpwp-save-settings"><?php echo esc_html('Save Settings'); ?></button>
            <button type="button" class="button button-secondary" id="dtpwp-reset-layout"><?php echo esc_html('Reset Panel Layout'); ?></button>
        </div>
    </form>

    <div class="dtpwp-toast" id="dtpwp-toast" role="status" aria-live="polite"></div>
</div>
