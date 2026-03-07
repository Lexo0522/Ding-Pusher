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
    'post_template' => "【新文章】\n标题：{title}\n作者：{author}\n链接：{link}",
    'user_template' => "【新用户注册】\n用户名：{username}\n邮箱：{email}\n注册时间：{register_time}",
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
    'enable_auto_update' => 1,
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
        <p>作者主页：https://www.rutua.cn/</p>
    </div>

    <?php settings_errors( 'dtpwp_settings' ); ?>

    <form method="post" action="options.php" id="dtpwp-settings-form">
        <?php settings_fields( 'dtpwp_settings' ); ?>

        <div class="dtpwp-tab-nav" role="tablist" aria-label="设置分组">
            <button type="button" class="dtpwp-tab is-active" data-tab="basic" role="tab" aria-selected="true">基础设置</button>
            <button type="button" class="dtpwp-tab" data-tab="advanced" role="tab" aria-selected="false">高级设置</button>
        </div>

        <div class="dtpwp-layout">
            <div class="dtpwp-main">
                <section class="dtpwp-tab-panel is-active" data-tab-panel="basic">
                    <div class="dtpwp-accordion-list" data-tab="basic">
                        <article class="dtpwp-settings-panel" data-panel-id="basic-access">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="拖拽排序">⋮⋮</span>
                                    <div>
                                        <h2>连接与认证</h2>
                                        <p>配置钉钉机器人 Webhook 与认证信息。</p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true">收起</button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-webhook-url">Webhook URL</label>
                                    <input type="text" id="dtpwp-webhook-url" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[webhook_url]" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx" />
                                </div>
                                <div class="dtpwp-field">
                                    <button type="button" class="button button-secondary" id="dtpwp-test-message">发送测试消息</button>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="basic-message">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="拖拽排序">⋮⋮</span>
                                    <div>
                                        <h2>消息内容</h2>
                                        <p>控制消息类型、模板与预设风格。</p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true">收起</button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-message-type">消息类型</label>
                                        <select id="dtpwp-message-type" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[message_type]">
                                            <option value="text" <?php selected( $settings['message_type'], 'text' ); ?>>文本消息</option>
                                            <option value="link" <?php selected( $settings['message_type'], 'link' ); ?>>链接消息</option>
                                            <option value="markdown" <?php selected( $settings['message_type'], 'markdown' ); ?>>Markdown 消息</option>
                                        </select>
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-preview-preset">预设样式</label>
                                        <select id="dtpwp-preview-preset" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[preview_preset]">
                                            <option value="clean" <?php selected( $settings['preview_preset'], 'clean' ); ?>>清爽</option>
                                            <option value="compact" <?php selected( $settings['preview_preset'], 'compact' ); ?>>紧凑</option>
                                            <option value="bold" <?php selected( $settings['preview_preset'], 'bold' ); ?>>强调</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="dtpwp-field">
                                    <label for="dtpwp-custom-message">自定义消息</label>
                                    <input type="text" id="dtpwp-custom-message" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[custom_message]" value="<?php echo esc_attr( $settings['custom_message'] ); ?>" placeholder="例如：欢迎使用 Ding Pusher 设置中心" />
                                </div>
                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-post-template">文章推送模板</label>
                                        <textarea id="dtpwp-post-template" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[post_template]" rows="4"><?php echo esc_textarea( $settings['post_template'] ); ?></textarea>
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-user-template">用户提示模板</label>
                                        <textarea id="dtpwp-user-template" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[user_template]" rows="4"><?php echo esc_textarea( $settings['user_template'] ); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="basic-trigger">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="拖拽排序">⋮⋮</span>
                                    <div>
                                        <h2>触发场景</h2>
                                        <p>控制文章与用户通知触发条件。</p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true">收起</button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-new-post">
                                        <span>新文章发布推送</span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-new-post" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_new_post]" value="1" <?php checked( $settings['enable_new_post'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-post-update">
                                        <span>文章更新推送</span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-post-update" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_post_update]" value="1" <?php checked( $settings['enable_post_update'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-new-user">
                                        <span>新用户注册提示</span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-new-user" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_new_user]" value="1" <?php checked( $settings['enable_new_user'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>

                                <div class="dtpwp-field">
                                    <label>启用的自定义文章类型</label>
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
                                    <span class="dtpwp-drag-handle" title="拖拽排序">⋮⋮</span>
                                    <div>
                                        <h2>安全验证</h2>
                                        <p>根据安全方式显示对应的配置字段。</p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true">收起</button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-security-type">安全验证方式</label>
                                    <select id="dtpwp-security-type" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_type]">
                                        <option value="keyword" <?php selected( $settings['security_type'], 'keyword' ); ?>>关键词</option>
                                        <option value="secret" <?php selected( $settings['security_type'], 'secret' ); ?>>加签</option>
                                        <option value="ip_whitelist" <?php selected( $settings['security_type'], 'ip_whitelist' ); ?>>IP 白名单</option>
                                    </select>
                                </div>

                                <div id="dtpwp-security-keyword" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label>关键词列表</label>
                                        <div id="dtpwp-keyword-list">
                                            <?php foreach ( $security_keywords as $keyword ) : ?>
                                                <div class="keyword-item">
                                                    <input type="text" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_keyword][]" value="<?php echo esc_attr( $keyword ); ?>" />
                                                    <button type="button" class="button button-link-delete dtpwp-remove-keyword">删除</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary dtpwp-add-keyword">添加关键词</button>
                                    </div>
                                </div>

                                <div id="dtpwp-security-secret" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-security-secret-input">加签密钥</label>
                                        <input type="text" id="dtpwp-security-secret-input" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_secret]" value="<?php echo esc_attr( $settings['security_secret'] ); ?>" />
                                    </div>
                                </div>

                                <div id="dtpwp-security-ip-whitelist" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field">
                                        <label>IP 白名单</label>
                                        <div id="dtpwp-ip-list">
                                            <?php foreach ( $security_ips as $ip ) : ?>
                                                <div class="ip-item">
                                                    <input type="text" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[security_ip_whitelist][]" value="<?php echo esc_attr( $ip ); ?>" />
                                                    <button type="button" class="button button-link-delete dtpwp-remove-ip">删除</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary dtpwp-add-ip">添加 IP</button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="advanced-options">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="拖拽排序">⋮⋮</span>
                                    <div>
                                        <h2>高级能力</h2>
                                        <p>用于展示更细粒度的配置联动，可按需启用。</p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true">收起</button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-advanced-features">
                                        <span>启用高级功能</span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-advanced-features" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_advanced_features]" value="1" <?php checked( $settings['enable_advanced_features'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>

                                <div id="dtpwp-advanced-feature-fields" class="dtpwp-conditional-group">
                                    <div class="dtpwp-field-grid">
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-advanced-mode">高级模式</label>
                                            <select id="dtpwp-advanced-mode" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[advanced_mode]">
                                                <option value="smart" <?php selected( $settings['advanced_mode'], 'smart' ); ?>>智能（Smart）</option>
                                                <option value="strict" <?php selected( $settings['advanced_mode'], 'strict' ); ?>>严格（Strict）</option>
                                                <option value="performance" <?php selected( $settings['advanced_mode'], 'performance' ); ?>>性能优先（Performance）</option>
                                            </select>
                                        </div>
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-theme-color">主题色</label>
                                            <input type="text" id="dtpwp-theme-color" class="dtpwp-color-field" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[theme_color]" value="<?php echo esc_attr( $settings['theme_color'] ); ?>" data-default-color="#2563eb" />
                                        </div>
                                    </div>

                                    <div class="dtpwp-switch-list">
                                        <label class="dtpwp-switch-row" for="dtpwp-enable-nested-feature">
                                            <span>启用嵌套选项</span>
                                            <span class="dtpwp-switch">
                                                <input type="checkbox" id="dtpwp-enable-nested-feature" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_nested_feature]" value="1" <?php checked( $settings['enable_nested_feature'], 1 ); ?> />
                                                <span class="dtpwp-switch-ui"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div id="dtpwp-nested-feature-fields" class="dtpwp-conditional-group dtpwp-nested-group">
                                        <div class="dtpwp-field">
                                            <label for="dtpwp-nested-feature-note">子选项说明</label>
                                            <input type="text" id="dtpwp-nested-feature-note" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[nested_feature_note]" value="<?php echo esc_attr( $settings['nested_feature_note'] ); ?>" placeholder="例如：仅对 VIP 频道启用严格模式" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="dtpwp-settings-panel" data-panel-id="advanced-runtime">
                            <header class="dtpwp-panel-header">
                                <div class="dtpwp-panel-header-main">
                                    <span class="dtpwp-drag-handle" title="拖拽排序">⋮⋮</span>
                                    <div>
                                        <h2>运行参数</h2>
                                        <p>调整轮询、重试与去重策略，并实时反映到预览区。</p>
                                    </div>
                                </div>
                                <button type="button" class="dtpwp-panel-toggle" aria-expanded="true">收起</button>
                            </header>
                            <div class="dtpwp-panel-body">
                                <div class="dtpwp-field">
                                    <label for="dtpwp-push-interval">推送间隔（分钟）</label>
                                    <div class="dtpwp-range-row">
                                        <input type="range" id="dtpwp-push-interval" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[push_interval]" min="1" max="60" value="<?php echo esc_attr( $settings['push_interval'] ); ?>" />
                                        <span class="dtpwp-range-value" id="dtpwp-push-interval-value"><?php echo esc_html( $settings['push_interval'] ); ?></span>
                                    </div>
                                </div>

                                <div class="dtpwp-field">
                                    <label for="dtpwp-retry-count">重试次数</label>
                                    <div class="dtpwp-range-row">
                                        <input type="range" id="dtpwp-retry-count" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[retry_count]" min="1" max="10" value="<?php echo esc_attr( $settings['retry_count'] ); ?>" />
                                        <span class="dtpwp-range-value" id="dtpwp-retry-count-value"><?php echo esc_html( $settings['retry_count'] ); ?></span>
                                    </div>
                                </div>

                                <div class="dtpwp-field-grid">
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-retry-interval">重试间隔（秒）</label>
                                        <input type="number" id="dtpwp-retry-interval" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[retry_interval]" min="1" max="60" value="<?php echo esc_attr( $settings['retry_interval'] ); ?>" />
                                    </div>
                                    <div class="dtpwp-field">
                                        <label for="dtpwp-deduplicate-days">去重保留天数</label>
                                        <input type="number" id="dtpwp-deduplicate-days" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[deduplicate_days]" min="1" max="365" value="<?php echo esc_attr( $settings['deduplicate_days'] ); ?>" />
                                    </div>
                                </div>

                                <div class="dtpwp-switch-list">
                                    <label class="dtpwp-switch-row" for="dtpwp-enable-auto-update">
                                        <span>启用插件自动更新</span>
                                        <span class="dtpwp-switch">
                                            <input type="checkbox" id="dtpwp-enable-auto-update" name="<?php echo esc_attr( DTPWP_OPTION_NAME ); ?>[enable_auto_update]" value="1" <?php checked( $settings['enable_auto_update'], 1 ); ?> />
                                            <span class="dtpwp-switch-ui"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="dtpwp-preview">
                <h2>实时预览</h2>
                <p>高保真消息卡片，接近真实接收端展示效果。</p>
                <div class="dtpwp-preview-device" id="dtpwp-preview-device">
                    <div class="dtpwp-preview-device-head">
                        <span>钉钉</span>
                        <span id="dtpwp-preview-time">09:41</span>
                    </div>

                    <div class="dtpwp-preview-chat">
                        <div class="dtpwp-preview-avatar">DP</div>
                        <div class="dtpwp-preview-bubble is-type-text is-preset-clean" id="dtpwp-preview-bubble">
                            <div class="dtpwp-preview-bubble-head">
                                <strong>Ding Pusher 机器人</strong>
                                <span id="dtpwp-preview-meta">文本消息 | 清爽</span>
                            </div>

                            <h3 class="dtpwp-preview-title" id="dtpwp-preview-title">站点通知</h3>
                            <p class="dtpwp-preview-text" id="dtpwp-preview-text">未设置消息内容</p>

                            <div class="dtpwp-preview-link" id="dtpwp-preview-link">
                                <div class="dtpwp-preview-link-cover"></div>
                                <div class="dtpwp-preview-link-main">
                                    <strong id="dtpwp-preview-link-title">文章更新通知</strong>
                                    <span id="dtpwp-preview-link-url">https://example.com/post/123</span>
                                </div>
                            </div>

                            <div class="dtpwp-preview-markdown" id="dtpwp-preview-markdown">
                                <h4 id="dtpwp-preview-md-title"># Markdown 通知</h4>
                                <ul id="dtpwp-preview-md-list">
                                    <li>标题：示例内容</li>
                                    <li>作者：Admin</li>
                                    <li>状态：已发布</li>
                                </ul>
                            </div>

                            <div class="dtpwp-preview-badges">
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-mode">文本消息</span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-preset-badge">预设：清爽</span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-advanced">高级功能：关闭</span>
                                <span class="dtpwp-preview-tag" id="dtpwp-preview-nested">嵌套：未启用</span>
                            </div>

                            <div class="dtpwp-preview-footer">
                                <span id="dtpwp-preview-webhook">Webhook: 未配置</span>
                                <span id="dtpwp-preview-push">间隔：5 分钟</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dtpwp-preview-quick">
                    <div><span>主题</span><strong id="dtpwp-preview-color">#2563eb</strong></div>
                    <div><span>预设</span><strong id="dtpwp-preview-preset-text">清爽</strong></div>
                    <div><span>类型</span><strong id="dtpwp-preview-type-inline">文本消息</strong></div>
                </div>
            </aside>
        </div>

        <div class="dtpwp-actions">
            <button type="submit" class="button button-primary button-hero" id="dtpwp-save-settings">保存设置</button>
            <button type="button" class="button button-secondary" id="dtpwp-reset-layout">重置面板布局</button>
        </div>
    </form>

    <div class="dtpwp-toast" id="dtpwp-toast" role="status" aria-live="polite"></div>
</div>
