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
        <p><?php esc_html_e('Welcome to your dashboard!'); ?></p>
    </div>

    <?php settings_errors( 'dtpwp_settings' ); ?>

    <form method="post" action="options.php" id="dtpwp-settings-form">
        <?php settings_fields( 'dtpwp_settings' ); ?>

        <div class="dtpwp-tab-nav" role="tablist" aria-label="<?php echo esc_attr('Settings Groups'); ?>">
            <button type="button" class="dtpwp-tab is-active" data-tab="basic" role="tab" aria-selected="true"><?php esc_html_e('Basic Settings'); ?></button>
            <button type="button" class="dtpwp-tab" data-tab="advanced" role="tab" aria-selected="false"><?php esc_html_e('Advanced Settings'); ?></button>
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
                                        <h2><?php esc_html_e('Connection & Authentication'); ?></h2>
                                        <p><?php esc_html_e('Configure DingTalk bot Webhook and authentication.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php esc_html_e('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-webhook-url"><?php esc_html_e('Webhook URL'); ?></label>
                                    <input type="text" id="dtpwp-webhook-url" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[webhook_url]" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx" />
                                </div>
                                <div class="dtpwp-field">
                                    <button type="button" class="button button-secondary" id="dtpwp-test-message"><?php esc_html_e('Send Test Message'); ?></button>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="basic-message">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php esc_html_e('Message Content'); ?></h2>
                                        <p><?php esc_html_e('Control message type, templates, and preset styles.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php esc_html_e('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-message-type"><?php esc_html_e('Message Type'); ?></label>
                                        <select id="dtpwp-message-type" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[message_type]">
                                            <option value="text" <?php selected( $settings['message_type'], 'text' ); ?>><?php esc_html_e('Text Message'); ?></option>
                                            <option value="link" <?php selected( $settings['message_type'], 'link' ); ?>><?php esc_html_e('Link Message'); ?></option>
                                            <option value="markdown" <?php selected( $settings['message_type'], 'markdown' ); ?>><?php esc_html_e('Markdown Message'); ?></option>
                                        </select>
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-preview-preset"><?php esc_html_e('Preset Style'); ?></label>
                                        <select id="dtpwp-preview-preset" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[preview_preset]">
                                            <option value="clean" <?php selected( $settings['preview_preset'], 'clean' ); ?>><?php esc_html_e('Clean'); ?></option>
                                            <option value="compact" <?php selected( $settings['preview_preset'], 'compact' ); ?>><?php esc_html_e('Compact'); ?></option>
                                            <option value="bold" <?php selected( $settings['preview_preset'], 'bold' ); ?>><?php esc_html_e('Bold'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="dtpwp-field">
                                    <label for="dtpwp-custom-message"><?php esc_html_e('Custom Message'); ?></label>
                                    <input type="text" id="dtpwp-custom-message" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[custom_message]" value="<?php echo esc_attr( $settings['custom_message'] ); ?>" placeholder="<?php echo esc_attr('Example: Welcome to Kate522 Notifier for DingTalk Settings Center'); ?>" />
                                </div>
                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-post-template"><?php esc_html_e('Post Push Template'); ?></label>
                                        <textarea id="dtpwp-post-template" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[post_template]" rows="4"><?php echo esc_textarea( $settings['post_template'] ); ?></textarea>
                                        <p class="description"><?php esc_html_e('Available Placeholders: {title}, {author}, {link}, {excerpt}, {category}, {categories}, {date}, {publish_time}, {post_date}, {post_type}'); ?></p>
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-user-template"><?php esc_html_e('User Notification Template'); ?></label>
                                        <textarea id="dtpwp-user-template" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[user_template]" rows="4"><?php echo esc_textarea( $settings['user_template'] ); ?></textarea>
                                        <p class="description"><?php esc_html_e('Available Placeholders: {username}, {email}, {register_time}'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="basic-trigger">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php esc_html_e('Trigger Scenarios'); ?></h2>
                                        <p><?php esc_html_e('Control post and user notification triggers.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php esc_html_e('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-new-post">
                                        <span><?php esc_html_e('New Post Publishing'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-new-post" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_new_post]" value="1" <?php checked( $settings['enable_new_post'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-post-update">
                                        <span><?php esc_html_e('Post Update Push'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-post-update" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_post_update]" value="1" <?php checked( $settings['enable_post_update'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-new-user">
                                        <span><?php esc_html_e('New User Registration'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-new-user" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_new_user]" value="1" <?php checked( $settings['enable_new_user'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>

                                <div class="dtpwp-field">
                                    <label><?php esc_html_e('Enabled Custom Post Types'); ?></label>
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
                                        <h2><?php esc_html_e('Security Verification'); ?></h2>
                                        <p><?php esc_html_e('Display configuration fields based on security method.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php esc_html_e('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-security-type"><?php esc_html_e('Security Verification Method'); ?></label>
                                    <select id="dtpwp-security-type" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_type]">
                                        <option value="keyword" <?php selected( $settings['security_type'], 'keyword' ); ?>><?php esc_html_e('Keyword'); ?></option>
                                        <option value="secret" <?php selected( $settings['security_type'], 'secret' ); ?>><?php esc_html_e('Signature'); ?></option>
                                        <option value="ip_whitelist" <?php selected( $settings['security_type'], 'ip_whitelist' ); ?>><?php esc_html_e('IP Whitelist'); ?></option>
                                    </select>
                                </div>

                                <div id="dtpwp-security-keyword" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label><?php esc_html_e('Keyword List'); ?></label>
                                        <div id="dtpwp-keyword-list">
                                            <?php foreach ( $security_keywords as $keyword ) : ?>
                                                <div class="keyword-item">
                                                    <input type="text" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_keyword][]" value="<?php echo esc_attr( $keyword ); ?>" />
                                                    <button type="button" class="button button-link-delete dtpwp-remove-keyword"><?php esc_html_e('Delete'); ?></button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary dtpwp-add-keyword"><?php esc_html_e('Add Keyword'); ?></button>
                                    </div>
                                </div>

                                <div id="dtpwp-security-secret" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-security-secret-input"><?php esc_html_e('Signature Secret'); ?></label>
                                        <input type="text" id="dtpwp-security-secret-input" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_secret]" value="<?php echo esc_attr( $settings['security_secret'] ); ?>" />
                                    </div>
                                </div>

                                <div id="dtpwp-security-ip-whitelist" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label><?php esc_html_e('IP Whitelist'); ?></label>
                                        <div id="dtpwp-ip-list">
                                            <?php foreach ( $security_ips as $ip ) : ?>
                                                <div class="ip-item">
                                                    <input type="text" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_ip_whitelist][]" value="<?php echo esc_attr( $ip ); ?>" />
                                                    <button type="button" class="button button-link-delete dtpwp-remove-ip"><?php esc_html_e('Delete'); ?></button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary dtpwp-add-ip"><?php esc_html_e('Add IP'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="advanced-options">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="<?php echo esc_attr( 'Drag to reorder' ); ?>">::</span>
                                    <div>
                                        <h2><?php esc_html_e('Advanced Features'); ?></h2>
                                        <p><?php esc_html_e('Show more granular configuration linkage, enable as needed.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php esc_html_e('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-advanced-features">
                                        <span><?php esc_html_e('Enable Advanced Features'); ?></span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-advanced-features" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_advanced_features]" value="1" <?php checked( $settings['enable_advanced_features'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>

                                <div id="dtpwp-advanced-feature-fields" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field-grid">
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-advanced-mode"><?php esc_html_e('Advanced Mode'); ?></label>
                                            <select id="dtpwp-advanced-mode" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[advanced_mode]">
                                                <option value="smart" <?php selected( $settings['advanced_mode'], 'smart' ); ?>><?php esc_html_e('Smart'); ?></option>
                                                <option value="strict" <?php selected( $settings['advanced_mode'], 'strict' ); ?>><?php esc_html_e('Strict'); ?></option>
                                                <option value="performance" <?php selected( $settings['advanced_mode'], 'performance' ); ?>><?php esc_html_e('Performance'); ?></option>
                                            </select>
                                        </div>
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-theme-color"><?php esc_html_e('Theme Color'); ?></label>
                                            <input type="text" id="dtpwp-theme-color" class="dtpwp-color-field" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[theme_color]" value="<?php echo esc_attr( $settings['theme_color'] ); ?>" data-default-color="#2563eb" />
                                        </div>
                                    </div>

                                    <div class="dtpwp-switch-list">
                                        <label class="dtpwp-switch-row" for="dtpwp-enable-nested-feature">
                                            <span><?php esc_html_e('Enable Nested Options'); ?></span>
                                            <span class="dtpwp-switch">
                                                <input type="checkbox" id="dtpwp-enable-nested-feature" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_nested_feature]" value="1" <?php checked( $settings['enable_nested_feature'], 1 ); ?> />
                                                <span class="dtpwp-switch-ui"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div id="dtpwp-nested-feature-fields" class="dtpwp-conditional-group dtpwp-nested-group">
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-nested-feature-note"><?php esc_html_e('Sub-option Description'); ?></label>
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
                                        <h2><?php esc_html_e('Runtime Parameters'); ?></h2>
                                        <p><?php esc_html_e('Adjust polling, retry, and deduplication strategies, reflected in preview in real-time.'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true"><?php esc_html_e('Collapse'); ?></button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-push-interval"><?php esc_html_e('Push Interval (minutes)'); ?></label>
                                    <div class="dtpwp-range-row">
                                        <input type="range" id="dtpwp-push-interval" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[push_interval]" min="1" max="60" value="<?php echo esc_attr( $settings['push_interval'] ); ?>" />
                                        <span class="dtpwp-range-value" id="dtpwp-push-interval-value"><?php echo esc_html( $settings['push_interval'] ); ?></span>
                                    </div>
                                </div>

                                <div class="dtpwp-field">
                                    <label for="dtpwp-retry-count"><?php esc_html_e('Retry Count'); ?></label>
                                    <div class="dtpwp-range-row">
                                        <input type="range" id="dtpwp-retry-count" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[retry_count]" min="1" max="10" value="<?php echo esc_attr( $settings['retry_count'] ); ?>" />
                                        <span class="dtpwp-range-value" id="dtpwp-retry-count-value"><?php echo esc_html( $settings['retry_count'] ); ?></span>
                                    </div>
                                </div>

                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-retry-interval"><?php esc_html_e('Retry Interval (seconds)'); ?></label>
                                        <input type="number" id="dtpwp-retry-interval" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[retry_interval]" min="1" max="60" value="<?php echo esc_attr( $settings['retry_interval'] ); ?>" />
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-deduplicate-days"><?php esc_html_e('Deduplication Days'); ?></label>
                                        <input type="number" id="dtpwp-deduplicate-days" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[deduplicate_days]" min="1" max="365" value="<?php echo esc_attr( $settings['deduplicate_days'] ); ?>" />
                                    </div>
                                </div>

                                                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="dtpwp-preview">
                <h2><?php esc_html_e('Live Preview'); ?></h2>
                <p><?php esc_html_e('High-fidelity message card, close to real receiver display.'); ?></p>
                <div class="dtpwp-preview-device" id="dtpwp-preview-device">
                    <div class="dtpwp-preview-device-head">
                        <span><?php esc_html_e('DingTalk'); ?></span>
                        <span id="dtpwp-preview-time">09:41</span>
                    </div>

                    <div class="dtpwp-preview-chat">
                        <div class="dtpwp-preview-avatar">DP</div>
                        <div class="dtpwp-preview-bubble is-type-text is-preset-clean" id="dtpwp-preview-bubble">
                            <div class="dtpwp-preview-bubble-head">
                                <strong><?php esc_html_e('Kate522 Notifier for DingTalk Bot'); ?></strong>
                                <span id="dtpwp-preview-meta"><?php esc_html_e('Text Message | Clean'); ?></span>
                            </div>

                            <h3 class="dtpwp-preview-title" id="dtpwp-preview-title"><?php esc_html_e('Site Notification'); ?></h3>
                            <p class="dtpwp-preview-text" id="dtpwp-preview-text"><?php esc_html_e('No Message Content Set'); ?></p>

                            <div class="dtpwp-preview-link" id="dtpwp-preview-link">
                                <div class="dtpwp-preview-link-cover"></div>
                                <div class="dtpwp-preview-link-main">
                                    <strong id="dtpwp-preview-link-title"><?php esc_html_e('Post Update Notification'); ?></strong>
                                    <span id="dtpwp-preview-link-url">https://example.com/post/123</span>
                                </div>
                            </div>

                            <div class="dtpwp-preview-markdown" id="dtpwp-preview-markdown">
                                <h4 id="dtpwp-preview-md-title"><?php esc_html_e('# Markdown Notification'); ?></h4>
                                <ul id="dtpwp-preview-md-list">
                                    <li><?php esc_html_e('Title: Sample Content'); ?></li>
                                    <li><?php esc_html_e('Author: Admin'); ?></li>
                                    <li><?php esc_html_e('Status: Published'); ?></li>
                                </ul>
                            </div>

                            <div class="dtpwp-preview-badges">
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-mode"><?php esc_html_e('Text Message'); ?></span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-preset-badge"><?php esc_html_e('Preset: Clean'); ?></span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-advanced"><?php esc_html_e('Advanced Features: Off'); ?></span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-nested"><?php esc_html_e('Nested: Not Enabled'); ?></span>
                            </div>

                            <div class="dtpwp-preview-footer">
                                <span id="dtpwp-preview-webhook"><?php esc_html_e('Webhook: Not Configured'); ?></span>
                                <span id="dtpwp-preview-push"><?php esc_html_e('Interval: 5 minutes'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dtpwp-preview-quick">
                    <div><span><?php esc_html_e('Theme'); ?></span><strong id="dtpwp-preview-color">#2563eb</strong></div>
                    <div><span><?php esc_html_e('Preset'); ?></span><strong id="dtpwp-preview-preset-text"><?php esc_html_e('Clean'); ?></strong></div>
                    <div><span><?php esc_html_e('Type'); ?></span><strong id="dtpwp-preview-type-inline"><?php esc_html_e('Text Message'); ?></strong></div>
                </div>
            </aside>
        </div>

        <div class="dtpwp-actions">
            <button type="submit" class="button button-primary button-hero" id="dtpwp-save-settings"><?php esc_html_e('Save Settings'); ?></button>
            <button type="button" class="button button-secondary" id="dtpwp-reset-layout"><?php esc_html_e('Reset Panel Layout'); ?></button>
        </div>
    </form>

    <div class="dtpwp-toast" id="dtpwp-toast" role="status" aria-live="polite"></div>
</div>
