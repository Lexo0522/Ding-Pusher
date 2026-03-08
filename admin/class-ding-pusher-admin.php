<?php

/**
 * Ding Pusher 管理类
 */
class Ding_Pusher_Admin {
    const EXPORT_MAX_ROWS = 5000;
    const BULK_MAX_ROWS = 200;
    const EXPORT_RATE_LIMIT_SECONDS = 10;
    const RECORD_DELETED_META_KEY = '_dtpwp_record_deleted';

    /**
     * 单例实例
     *
     * @var Ding_Pusher_Admin|null
     */
    private static $instance = null;

    /**
     * 设置选项
     *
     * @var array
     */
    private $settings = array();

    /**
     * 获取单例
     *
     * @return Ding_Pusher_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->settings = wp_parse_args(
            get_option( DTPWP_OPTION_NAME, array() ),
            $this->get_default_settings()
        );

        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_dtpwp_test_message', array( $this, 'ajax_test_message' ) );
        add_action( 'wp_ajax_dtpwp_mark_as_sent', array( $this, 'ajax_mark_as_sent' ) );
        add_action( 'wp_ajax_dtpwp_bulk_update_records', array( $this, 'ajax_bulk_update_records' ) );
        add_action( 'wp_ajax_dtpwp_prepare_export', array( $this, 'ajax_prepare_export' ) );
        add_action( 'wp_ajax_dtpwp_clear_sent_records', array( $this, 'ajax_clear_sent_records' ) );
        add_action( 'admin_post_dtpwp_export_records', array( $this, 'handle_export_records' ) );
    }

    /**
     * 默认设置
     *
     * @return array
     */
    private function get_default_settings() {
        return array(
            'webhook_url' => '',
            'security_type' => 'keyword',
            'security_keyword' => array(),
            'security_secret' => '',
            'security_ip_whitelist' => array(),
            'message_type' => 'text',
            'custom_message' => '',
            'post_template' => __( "【新文章】\n标题：{title}\n作者：{author}\n链接：{link}", 'ding-pusher' ),
            'user_template' => __( "【新用户注册】\n用户名：{username}\n邮箱：{email}\n注册时间：{register_time}", 'ding-pusher' ),
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
    }

    /**
     * 添加后台菜单
     */
    public function add_admin_menu() {
        $parent_slug = 'ding-pusher';

        add_menu_page(
            __( 'Ding Pusher', 'ding-pusher' ),
            __( 'Ding Pusher', 'ding-pusher' ),
            'manage_options',
            $parent_slug,
            array( $this, 'render_settings_page' ),
            'dashicons-email-alt',
            80
        );

        add_submenu_page(
            $parent_slug,
            __( '设置', 'ding-pusher' ),
            __( '设置', 'ding-pusher' ),
            'manage_options',
            $parent_slug,
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( '推送记录', 'ding-pusher' ),
            __( '推送记录', 'ding-pusher' ),
            'manage_options',
            'ding-pusher-records',
            array( $this, 'render_records_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( '帮助', 'ding-pusher' ),
            __( '帮助', 'ding-pusher' ),
            'manage_options',
            'ding-pusher-help',
            array( $this, 'render_help_page' )
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting(
            'dtpwp_settings',
            DTPWP_OPTION_NAME,
            array( $this, 'validate_settings' )
        );
    }

    /**
     * 验证设置
     *
     * @param array $input 原始输入
     * @return array
     */
    public function validate_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        $valid = array();

        $webhook_url = isset( $input['webhook_url'] ) ? trim( sanitize_text_field( $input['webhook_url'] ) ) : '';
        if ( ! empty( $webhook_url ) && ! preg_match( '/^https:\/\/oapi\.dingtalk\.com\/robot\/send/', $webhook_url ) ) {
            add_settings_error( 'dtpwp_settings', 'invalid_webhook', __( 'Webhook URL 格式不正确，请检查。', 'ding-pusher' ) );
        }
        $valid['webhook_url'] = $webhook_url;

        $valid['security_type'] = isset( $input['security_type'] ) ? sanitize_text_field( $input['security_type'] ) : 'keyword';
        $valid['security_keyword'] = isset( $input['security_keyword'] ) && is_array( $input['security_keyword'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', $input['security_keyword'] ), 'strlen' ) )
            : array();
        $valid['security_secret'] = isset( $input['security_secret'] ) ? trim( sanitize_text_field( $input['security_secret'] ) ) : '';
        $valid['security_ip_whitelist'] = isset( $input['security_ip_whitelist'] ) && is_array( $input['security_ip_whitelist'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', $input['security_ip_whitelist'] ), 'strlen' ) )
            : array();

        $valid['message_type'] = isset( $input['message_type'] ) ? sanitize_text_field( $input['message_type'] ) : 'text';
        $valid['custom_message'] = isset( $input['custom_message'] ) ? sanitize_textarea_field( $input['custom_message'] ) : '';
        $valid['post_template'] = isset( $input['post_template'] ) ? sanitize_textarea_field( $input['post_template'] ) : '';
        $valid['user_template'] = isset( $input['user_template'] ) ? sanitize_textarea_field( $input['user_template'] ) : '';

        $valid['enable_new_post'] = isset( $input['enable_new_post'] ) ? 1 : 0;
        $valid['enable_post_update'] = isset( $input['enable_post_update'] ) ? 1 : 0;
        $valid['enable_custom_post_type'] = isset( $input['enable_custom_post_type'] ) && is_array( $input['enable_custom_post_type'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', $input['enable_custom_post_type'] ), 'strlen' ) )
            : array();
        $valid['enable_new_user'] = isset( $input['enable_new_user'] ) ? 1 : 0;

        $valid['push_interval'] = isset( $input['push_interval'] ) ? absint( $input['push_interval'] ) : 5;
        $valid['push_interval'] = max( 1, min( 60, $valid['push_interval'] ) );

        $valid['retry_count'] = isset( $input['retry_count'] ) ? absint( $input['retry_count'] ) : 3;
        $valid['retry_count'] = max( 1, min( 10, $valid['retry_count'] ) );

        $valid['retry_interval'] = isset( $input['retry_interval'] ) ? absint( $input['retry_interval'] ) : 10;
        $valid['retry_interval'] = max( 1, min( 60, $valid['retry_interval'] ) );

        $valid['deduplicate_days'] = isset( $input['deduplicate_days'] ) ? absint( $input['deduplicate_days'] ) : 30;
        $valid['deduplicate_days'] = max( 1, min( 365, $valid['deduplicate_days'] ) );

        $valid['theme_color'] = isset( $input['theme_color'] ) ? sanitize_hex_color( $input['theme_color'] ) : '#2563eb';
        if ( empty( $valid['theme_color'] ) ) {
            $valid['theme_color'] = '#2563eb';
        }
        $valid['preview_preset'] = isset( $input['preview_preset'] ) ? sanitize_text_field( $input['preview_preset'] ) : 'clean';
        $valid['enable_advanced_features'] = isset( $input['enable_advanced_features'] ) ? 1 : 0;
        $valid['advanced_mode'] = isset( $input['advanced_mode'] ) ? sanitize_text_field( $input['advanced_mode'] ) : 'smart';
        $valid['enable_nested_feature'] = isset( $input['enable_nested_feature'] ) ? 1 : 0;
        $valid['nested_feature_note'] = isset( $input['nested_feature_note'] ) ? sanitize_text_field( $input['nested_feature_note'] ) : '';


        // 更新定时任务
        wp_clear_scheduled_hook( 'dtpwp_check_new_content' );

        $schedule = $valid['push_interval'] . 'm';
        $schedules = wp_get_schedules();
        if ( ! isset( $schedules[ $schedule ] ) ) {
            $schedule = isset( $schedules['hourly'] ) ? 'hourly' : key( $schedules );
        }

        if ( $schedule && ! wp_next_scheduled( 'dtpwp_check_new_content' ) ) {
            wp_schedule_event( time(), $schedule, 'dtpwp_check_new_content' );
        }

        return $valid;
    }

    /**
     * 渲染设置页
     */
    public function render_settings_page() {
        $settings = $this->settings;
        include DTPWP_PLUGIN_DIR . 'admin/views/settings-ui.php';
    }

    /**
     * 渲染推送记录页
     */
    public function render_records_page() {
        $per_page = $this->get_records_per_page();
        $paged = $this->get_records_paged();
        $query = new WP_Query( $this->get_records_query_args( $per_page, $paged ) );
        $total_records = (int) $query->found_posts;
        $total_pages = (int) $query->max_num_pages;
        ?>
        <div class="wrap dtpwp-records" data-per-page="<?php echo esc_attr( $per_page ); ?>">
            <?php $this->render_records_header( $total_records ); ?>

            <?php if ( $query->have_posts() ) : ?>
                <?php $this->render_records_toolbar( $per_page ); ?>
                <?php $this->render_records_table( $query ); ?>
                <?php $this->render_records_pagination( $total_pages, $paged, $per_page ); ?>
            <?php else : ?>
                <?php $this->render_records_empty(); ?>
            <?php endif; ?>
        </div>
        <?php
        wp_reset_postdata();
    }

    private function get_records_per_page() {
        $allowed = $this->get_allowed_per_page();
        $per_page = isset( $_GET['dtpwp_per_page'] ) ? absint( $_GET['dtpwp_per_page'] ) : 20;
        if ( ! in_array( $per_page, $allowed, true ) ) {
            $per_page = 20;
        }
        return $per_page;
    }

    private function get_allowed_per_page() {
        return array( 10, 20, 50, 100 );
    }

    private function get_records_paged() {
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        return max( 1, $paged );
    }

    private function get_records_query_args( $posts_per_page, $paged, $post_ids = array() ) {
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => DTPWP_SENT_META_KEY,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => self::RECORD_DELETED_META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        if ( ! empty( $post_ids ) ) {
            $args['post__in'] = $post_ids;
            $args['orderby'] = 'post__in';
        }

        return $args;
    }

    private function render_records_header( $total_records ) {
        ?>
        <div class="dtpwp-records-header">
            <div>
                <h1><?php esc_html_e( '推送记录', 'ding-pusher' ); ?></h1>
                <p><?php esc_html_e( '查看已推送文章记录、消息内容与推送时间。', 'ding-pusher' ); ?></p>
            </div>
            <div class="dtpwp-records-actions">
                <span class="dtpwp-records-count">
                    <?php esc_html_e( '共', 'ding-pusher' ); ?>
                    <strong id="dtpwp-records-count"><?php echo esc_html( $total_records ); ?></strong>
                    <?php esc_html_e( '条', 'ding-pusher' ); ?>
                </span>
                <button type="button" class="button dtpwp-button-danger" id="dtpwp-clear-records"><?php esc_html_e( '清理所有记录', 'ding-pusher' ); ?></button>
            </div>
        </div>
        <?php
    }

    private function render_records_toolbar( $per_page ) {
        ?>
        <div class="dtpwp-records-toolbar">
            <div class="dtpwp-records-bulk">
                <label for="dtpwp-bulk-action"><?php esc_html_e( '批量操作', 'ding-pusher' ); ?></label>
                <select id="dtpwp-bulk-action">
                    <option value=""><?php esc_html_e( '请选择', 'ding-pusher' ); ?></option>
                    <option value="mark_not_sent"><?php esc_html_e( '取消标记', 'ding-pusher' ); ?></option>
                    <option value="delete_record"><?php esc_html_e( '删除记录', 'ding-pusher' ); ?></option>
                </select>
                <button type="button" class="button button-secondary" id="dtpwp-apply-bulk"><?php esc_html_e( '应用', 'ding-pusher' ); ?></button>
            </div>
            <div class="dtpwp-records-filter">
                <label for="dtpwp-per-page"><?php esc_html_e( '每页', 'ding-pusher' ); ?></label>
                <select id="dtpwp-per-page">
                    <?php foreach ( $this->get_allowed_per_page() as $size ) : ?>
                        <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dtpwp-records-export">
                <label for="dtpwp-export-format"><?php esc_html_e( '导出格式', 'ding-pusher' ); ?></label>
                <select id="dtpwp-export-format">
                    <option value="csv"><?php esc_html_e( 'CSV', 'ding-pusher' ); ?></option>
                    <option value="xlsx"><?php esc_html_e( 'XLSX', 'ding-pusher' ); ?></option>
                </select>
                <button type="button" class="button button-secondary" id="dtpwp-export-selected"><?php esc_html_e( '导出选中', 'ding-pusher' ); ?></button>
                <button type="button" class="button button-secondary" id="dtpwp-export-all"><?php esc_html_e( '导出全部', 'ding-pusher' ); ?></button>
                <details class="dtpwp-export-fields">
                    <summary><?php esc_html_e( '导出字段', 'ding-pusher' ); ?></summary>
                    <div class="dtpwp-export-fields-panel">
                        <label><input type="checkbox" value="id" checked />ID</label>
                        <label><input type="checkbox" value="title" checked /><?php esc_html_e( '标题', 'ding-pusher' ); ?></label>
                        <label><input type="checkbox" value="author" checked /><?php esc_html_e( '作者', 'ding-pusher' ); ?></label>
                        <label><input type="checkbox" value="sent_time" checked /><?php esc_html_e( '推送时间', 'ding-pusher' ); ?></label>
                        <label><input type="checkbox" value="message" checked /><?php esc_html_e( '消息内容', 'ding-pusher' ); ?></label>
                        <label><input type="checkbox" value="link" checked /><?php esc_html_e( '链接', 'ding-pusher' ); ?></label>
                        <div class="dtpwp-export-fields-actions">
                            <button type="button" class="button button-link dtpwp-export-select-all"><?php esc_html_e( '全选', 'ding-pusher' ); ?></button>
                            <button type="button" class="button button-link dtpwp-export-clear"><?php esc_html_e( '清空', 'ding-pusher' ); ?></button>
                        </div>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    private function render_records_table( WP_Query $query ) {
        ?>
        <div class="dtpwp-records-table">
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
                        <th scope="col" class="manage-column column-title"><?php esc_html_e( '文章标题', 'ding-pusher' ); ?></th>
                        <th scope="col" class="manage-column column-author"><?php esc_html_e( '作者', 'ding-pusher' ); ?></th>
                        <th scope="col" class="manage-column column-date"><?php esc_html_e( '推送时间', 'ding-pusher' ); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php esc_html_e( '操作', 'ding-pusher' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $query->have_posts() ) : ?>
                        <?php
                        $query->the_post();
                        $post_id = get_the_ID();
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $post_id ); ?>" />
                            </th>
                            <td class="column-title">
                                <div class="dtpwp-record-title">
                                    <strong>
                                        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php the_title(); ?></a>
                                    </strong>
                                    <span class="dtpwp-record-link">
                                        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '查看文章', 'ding-pusher' ); ?></a>
                                    </span>
                                </div>
                                <div class="dtpwp-sent-message">
                                    <strong><?php esc_html_e( '发送内容', 'ding-pusher' ); ?></strong>
                                    <pre><?php echo esc_html( get_post_meta( $post_id, '_dtpwp_sent_message', true ) ); ?></pre>
                                </div>
                            </td>
                            <td class="column-author">
                                <span class="dtpwp-record-meta"><?php the_author(); ?></span>
                            </td>
                            <td class="column-date">
                                <span class="dtpwp-record-meta"><?php echo esc_html( get_post_meta( $post_id, '_dtpwp_sent_time', true ) ); ?></span>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button button-link-delete dtpwp-mark-as-not-sent" data-post-id="<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( '取消标记', 'ding-pusher' ); ?></button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_records_pagination( $total_pages, $paged, $per_page ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        $base = add_query_arg(
            array(
                'paged' => '%#%',
                'dtpwp_per_page' => $per_page,
            )
        );

        $links = paginate_links(
            array(
                'base' => $base,
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
                'prev_text' => __( '上一页', 'ding-pusher' ),
                'next_text' => __( '下一页', 'ding-pusher' ),
                'type' => 'array',
            )
        );

        if ( empty( $links ) ) {
            return;
        }
        ?>
        <div class="dtpwp-records-pagination">
            <?php foreach ( $links as $link ) : ?>
                <?php echo wp_kses_post( $link ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_records_empty() {
        ?>
        <div class="dtpwp-records-empty">
            <h3><?php esc_html_e( '暂无推送记录', 'ding-pusher' ); ?></h3>
            <p><?php esc_html_e( '当文章触发推送后，记录会显示在这里。', 'ding-pusher' ); ?></p>
        </div>
        <?php
    }

    /**
     * 渲染帮助页
     */
    public function render_help_page() {
        ?>
        <div class="wrap dtpwp-help">
            <div class="dtpwp-help-hero">
                <div>
                    <h1><?php esc_html_e( '帮助与教程', 'ding-pusher' ); ?></h1>
                    <p><?php esc_html_e( '这里汇总常用配置步骤、模板说明与排查清单，帮助你更快完成配置。', 'ding-pusher' ); ?></p>
                </div>
                <div class="dtpwp-help-hero__actions">
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ding-pusher' ) ); ?>">
                        <?php esc_html_e( '打开设置', 'ding-pusher' ); ?>
                    </a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ding-pusher-records' ) ); ?>">
                        <?php esc_html_e( '查看记录', 'ding-pusher' ); ?>
                    </a>
                </div>
            </div>

            <div class="dtpwp-help-grid">
                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e( '快速开始', 'ding-pusher' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( '在钉钉群中创建自定义机器人。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '根据机器人安全设置选择关键词 / 加签 / IP 白名单。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '复制机器人 Webhook 地址，粘贴到设置页并保存。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '点击“发送测试消息”验证配置。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '开启触发场景与模板，观察推送记录。', 'ding-pusher' ); ?></li>
                    </ol>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e( '配置清单', 'ding-pusher' ); ?></h2>
                    <ul>
                        <li><?php esc_html_e( 'Webhook：填入机器人 Webhook 地址，并确认可访问。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '安全校验：按机器人安全设置选择关键词 / 加签 / IP 白名单。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '触发场景：选择新文章、更新或新用户注册。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '消息模板：按需调整文本、链接或 Markdown 模板。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '保存并发送测试消息验证配置。', 'ding-pusher' ); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e( '模板与占位符', 'ding-pusher' ); ?></h2>
                    <div class="dtpwp-help-columns">
                        <div>
                            <h3><?php esc_html_e( '文章模板', 'ding-pusher' ); ?></h3>
                            <p><?php esc_html_e( '可用占位符：', 'ding-pusher' ); ?></p>
                            <p class="dtpwp-help-tags">
                                <code>{title}</code>
                                <code>{author}</code>
                                <code>{link}</code>
                                <code>{excerpt}</code>
                                <code>{category}</code>
                                <code>{categories}</code>
                                <code>{date}</code>
                                <code>{publish_time}</code>
                                <code>{post_date}</code>
                                <code>{post_type}</code>
                            </p>
                        </div>
                        <div>
                            <h3><?php esc_html_e( '用户模板', 'ding-pusher' ); ?></h3>
                            <p><?php esc_html_e( '可用占位符：', 'ding-pusher' ); ?></p>
                            <p class="dtpwp-help-tags">
                                <code>{username}</code>
                                <code>{email}</code>
                                <code>{register_time}</code>
                            </p>
                        </div>
                    </div>
                    <p class="dtpwp-help-note"><?php esc_html_e( '提示：模板支持换行，实际消息会保留换行。', 'ding-pusher' ); ?></p>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e( '触发场景', 'ding-pusher' ); ?></h2>
                    <ul>
                        <li><?php esc_html_e( '新文章发布推送', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '文章更新推送', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '新用户注册提示', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '启用的自定义文章类型', 'ding-pusher' ); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e( '导出与记录', 'ding-pusher' ); ?></h2>
                    <ul>
                        <li><?php esc_html_e( '导出会生成 CSV 或 XLSX 文件，默认保存 24 小时后自动清理。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( 'XLSX 需要服务器启用 ZipArchive 或 PclZip，若不可用可导出 CSV。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '记录页仅展示已推送的文章；如果没有记录，请先触发一次推送。', 'ding-pusher' ); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e( '排查清单', 'ding-pusher' ); ?></h2>
                    <ul>
                        <li><?php esc_html_e( '测试消息失败：检查 Webhook、安全设置与服务器网络。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '新文章未推送：确认已开启触发、文章状态为已发布，并检查 WP-Cron。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '导出失败：检查上传目录权限或改用 CSV。', 'ding-pusher' ); ?></li>
                        <li><?php esc_html_e( '频繁触发限制：导出存在频率限制，稍后再试。', 'ding-pusher' ); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card dtpwp-help-faq">
                    <h2><?php esc_html_e( '常见问题', 'ding-pusher' ); ?></h2>
                    <details>
                        <summary><?php esc_html_e( '如何配置关键词安全？', 'ding-pusher' ); ?></summary>
                        <p><?php esc_html_e( '在钉钉机器人安全设置中添加关键词，同时在插件安全方式选择“关键词”并填入相同关键词。', 'ding-pusher' ); ?></p>
                    </details>
                    <details>
                        <summary><?php esc_html_e( '如何使用加签？', 'ding-pusher' ); ?></summary>
                        <p><?php esc_html_e( '选择“加签”，填写机器人密钥，插件会自动生成签名。', 'ding-pusher' ); ?></p>
                    </details>
                    <details>
                        <summary><?php esc_html_e( '为什么没有推送记录？', 'ding-pusher' ); ?></summary>
                        <p><?php esc_html_e( '记录仅在成功推送后生成，请先触发一次推送并检查日志。', 'ding-pusher' ); ?></p>
                    </details>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * 加载样式和脚本
     *
     * @param string $hook 当前后台页面钩子
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'ding-pusher' ) ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_script(
            'dtpwp-admin-script',
            DTPWP_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
            DTPWP_VERSION,
            true
        );

        wp_localize_script(
            'dtpwp-admin-script',
            'dtpwp_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'dtpwp_nonce' ),
                'settings_updated' => ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) ? 1 : 0,
                'export_url' => admin_url( 'admin-post.php' ),
                'export_nonce' => wp_create_nonce( 'dtpwp_export_records' ),
                'export_max' => self::EXPORT_MAX_ROWS,
                'xlsx_available' => $this->is_xlsx_available() ? 1 : 0,
                'i18n' => array(
                    'webhook_not_configured' => __( 'Webhook: 未配置', 'ding-pusher' ),
                    'webhook_configured' => __( 'Webhook: 已配置', 'ding-pusher' ),
                    'webhook_prefix' => __( 'Webhook: ', 'ding-pusher' ),
                    'expand' => __( '展开', 'ding-pusher' ),
                    'collapse' => __( '收起', 'ding-pusher' ),
                    'site_notice' => __( '站点通知', 'ding-pusher' ),
                    'custom_message' => __( '自定义消息', 'ding-pusher' ),
                    'empty_message' => __( '未设置消息内容', 'ding-pusher' ),
                    'message_notice' => __( '消息通知', 'ding-pusher' ),
                    'markdown_notice' => __( '# Markdown 通知', 'ding-pusher' ),
                    'example_title' => __( '标题：示例内容', 'ding-pusher' ),
                    'example_author' => __( '作者：Admin', 'ding-pusher' ),
                    'example_status' => __( '状态：已发布', 'ding-pusher' ),
                    'nested_disabled' => __( '嵌套：未启用', 'ding-pusher' ),
                    'nested_prefix' => __( '嵌套：', 'ding-pusher' ),
                    'nested_enabled' => __( '已启用', 'ding-pusher' ),
                    'message_type_text' => __( '文本消息', 'ding-pusher' ),
                    'message_type_link' => __( '链接消息', 'ding-pusher' ),
                    'message_type_markdown' => __( 'Markdown 消息', 'ding-pusher' ),
                    'preset_clean' => __( '清爽', 'ding-pusher' ),
                    'preset_compact' => __( '紧凑', 'ding-pusher' ),
                    'preset_bold' => __( '强调', 'ding-pusher' ),
                    'preset_prefix' => __( '预设：', 'ding-pusher' ),
                    'advanced_enabled' => __( '高级功能：开启', 'ding-pusher' ),
                    'advanced_disabled' => __( '高级功能：关闭', 'ding-pusher' ),
                    /* translators: %d: minutes */
                    'push_interval_format' => __( '间隔：%d 分钟', 'ding-pusher' ),
                    'delete' => __( '删除', 'ding-pusher' ),
                    'saving_settings' => __( '正在保存设置...', 'ding-pusher' ),
                    'settings_saved' => __( '设置已保存', 'ding-pusher' ),
                    'sending' => __( '发送中...', 'ding-pusher' ),
                    'test_response_invalid' => __( '测试消息返回格式异常', 'ding-pusher' ),
                    'test_send_failed' => __( '测试消息发送失败，请检查网络连接。', 'ding-pusher' ),
                    'bulk_action_required' => __( '请先选择批量操作类型。', 'ding-pusher' ),
                    'bulk_records_required' => __( '请先勾选需要操作的记录。', 'ding-pusher' ),
                    'bulk_confirm_delete' => __( '确定要删除选中的记录吗？此操作不可恢复。', 'ding-pusher' ),
                    'bulk_confirm_unmark' => __( '确定要取消标记所选记录吗？', 'ding-pusher' ),
                    'processing' => __( '处理中...', 'ding-pusher' ),
                    'record_deleted' => __( '记录已删除', 'ding-pusher' ),
                    'record_unmarked' => __( '记录已取消标记', 'ding-pusher' ),
                    'bulk_failed' => __( '批量操作失败', 'ding-pusher' ),
                    'apply' => __( '应用', 'ding-pusher' ),
                    'export_field_required' => __( '请至少选择一个导出字段。', 'ding-pusher' ),
                    'export_too_many' => __( '记录过多，请先勾选需要导出的记录。', 'ding-pusher' ),
                    'export_generating' => __( '生成中...', 'ding-pusher' ),
                    'export_ready' => __( '导出已准备，开始下载。', 'ding-pusher' ),
                    'export_failed' => __( '导出失败，请重试。', 'ding-pusher' ),
                    'export_all' => __( '导出全部', 'ding-pusher' ),
                    'export_selected' => __( '导出选中', 'ding-pusher' ),
                    'export_records_required' => __( '请先勾选需要导出的记录。', 'ding-pusher' ),
                    'xlsx_unavailable' => __( 'XLSX export requires the PHP ZipArchive extension. Switched to CSV.', 'ding-pusher' ),
                    'mark_confirm' => __( '确定要取消标记这篇文章吗？', 'ding-pusher' ),
                    'action_failed' => __( '操作失败', 'ding-pusher' ),
                    'mark_cancel' => __( '取消标记', 'ding-pusher' ),
                    'clear_confirm' => __( '确定要清理所有推送记录吗？此操作不可恢复。', 'ding-pusher' ),
                    'clearing' => __( '清理中...', 'ding-pusher' ),
                    'clear_failed' => __( '清理失败', 'ding-pusher' ),
                ),
            )
        );

        wp_enqueue_style(
            'dtpwp-admin-style',
            DTPWP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DTPWP_VERSION
        );
    }

    /**
     * AJAX: 发送测试消息
     */
    public function ajax_test_message() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '权限不足。', 'ding-pusher' ) ) );
        }

        $settings = wp_parse_args( get_option( DTPWP_OPTION_NAME, array() ), $this->get_default_settings() );
        $raw_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

        $test_settings = $settings;
        $test_settings['webhook_url'] = isset( $raw_settings['webhook_url'] ) ? trim( esc_url_raw( $raw_settings['webhook_url'] ) ) : $settings['webhook_url'];
        $test_settings['security_type'] = isset( $raw_settings['security_type'] ) ? sanitize_text_field( $raw_settings['security_type'] ) : $settings['security_type'];
        $test_settings['security_secret'] = isset( $raw_settings['security_secret'] ) ? trim( sanitize_text_field( $raw_settings['security_secret'] ) ) : $settings['security_secret'];
        $test_settings['message_type'] = isset( $raw_settings['message_type'] ) ? sanitize_text_field( $raw_settings['message_type'] ) : $settings['message_type'];

        if ( empty( $test_settings['webhook_url'] ) ) {
            wp_send_json_error( array( 'message' => __( '请先填写 Webhook 地址。', 'ding-pusher' ) ) );
        }

        if ( ! preg_match( '/^https:\/\/oapi\.dingtalk\.com\/robot\/send/', $test_settings['webhook_url'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Webhook URL 格式不正确，请检查。', 'ding-pusher' ) ) );
        }

        $message = __( '这是一条测试消息，来自 Ding Pusher 插件。', 'ding-pusher' );
        $core = Ding_Pusher_Core::get_instance();
        $success = $core->send_dingtalk_message( $message, $test_settings );

        if ( $success ) {
            wp_send_json_success( array( 'message' => __( '测试消息发送成功。', 'ding-pusher' ) ) );
        }

        wp_send_json_error( array( 'message' => __( '测试消息发送失败，请检查配置。', 'ding-pusher' ) ) );
    }

    /**
     * AJAX: 取消标记或标记为已推送
     */
    public function ajax_mark_as_sent() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '权限不足。', 'ding-pusher' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $mark_as = isset( $_POST['mark_as'] ) ? sanitize_text_field( wp_unslash( $_POST['mark_as'] ) ) : 'sent';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( '无效的文章 ID。', 'ding-pusher' ) ) );
        }

        if ( 'not_sent' === $mark_as ) {
            delete_post_meta( $post_id, DTPWP_SENT_META_KEY );
            delete_post_meta( $post_id, '_dtpwp_sent_time' );
            delete_post_meta( $post_id, '_dtpwp_sent_message' );
            delete_post_meta( $post_id, self::RECORD_DELETED_META_KEY );
            wp_send_json_success( array( 'message' => __( '已取消标记。', 'ding-pusher' ) ) );
        }

        delete_post_meta( $post_id, self::RECORD_DELETED_META_KEY );
        update_post_meta( $post_id, DTPWP_SENT_META_KEY, 1 );
        update_post_meta( $post_id, '_dtpwp_sent_time', current_time( 'mysql' ) );
        wp_send_json_success( array( 'message' => __( '标记成功。', 'ding-pusher' ) ) );
    }

    /**
     * AJAX: 清理推送记录
     */
    public function ajax_clear_sent_records() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '权限不足。', 'ding-pusher' ) ) );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => DTPWP_SENT_META_KEY ), array( '%s' ) );
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dtpwp_sent_time' ), array( '%s' ) );
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dtpwp_sent_message' ), array( '%s' ) );
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => self::RECORD_DELETED_META_KEY ), array( '%s' ) );
        delete_option( 'dtpwp_sent_title_hashes' );

        wp_send_json_success( array( 'message' => __( '所有记录已清理。', 'ding-pusher' ) ) );
    }

    /**
     * AJAX: 批量更新推送记录
     */
    public function ajax_bulk_update_records() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '权限不足。', 'ding-pusher' ) ) );
        }

        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) )
            : array();
        $post_ids = array_filter( $post_ids );

        if ( empty( $post_ids ) ) {
            wp_send_json_error( array( 'message' => __( '请先选择记录。', 'ding-pusher' ) ) );
        }

        if ( count( $post_ids ) > self::BULK_MAX_ROWS ) {
            wp_send_json_error( array( 'message' => __( '单次最多处理 200 条记录，请分批操作。', 'ding-pusher' ) ) );
        }

        if ( ! in_array( $action, array( 'mark_not_sent', 'delete_record' ), true ) ) {
            wp_send_json_error( array( 'message' => __( '无效的批量操作。', 'ding-pusher' ) ) );
        }

        foreach ( $post_ids as $post_id ) {
            if ( 'delete_record' === $action ) {
                update_post_meta( $post_id, self::RECORD_DELETED_META_KEY, 1 );
                continue;
            }

            delete_post_meta( $post_id, DTPWP_SENT_META_KEY );
            delete_post_meta( $post_id, '_dtpwp_sent_time' );
            delete_post_meta( $post_id, '_dtpwp_sent_message' );
            delete_post_meta( $post_id, self::RECORD_DELETED_META_KEY );
        }

        wp_send_json_success( array( 'message' => __( '批量操作已完成。', 'ding-pusher' ) ) );
    }

    /**
     * AJAX: 生成导出文件
     */
    public function ajax_prepare_export() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '权限不足。', 'ding-pusher' ) ) );
        }

        $format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
        $format = in_array( $format, array( 'csv', 'xlsx' ), true ) ? $format : 'csv';

        $post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) )
            : array();
        $post_ids = array_filter( $post_ids );

        $requested_fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) )
            : array();
        $fields = $this->sanitize_export_fields( $requested_fields );

        if ( ! $this->acquire_export_lock() ) {
            wp_send_json_error( array( 'message' => __( '导出过于频繁，请稍后再试。', 'ding-pusher' ) ) );
        }

        $error_message = '';
        $rows = $this->build_export_rows( $post_ids, $fields, $error_message );
        if ( $error_message ) {
            $this->release_export_lock();
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        $file = $this->prepare_export_file( $rows, $format, $error_message );
        if ( ! $file ) {
            $this->release_export_lock();
            wp_send_json_error( array( 'message' => $error_message ? $error_message : __( '导出失败，请稍后重试。', 'ding-pusher' ) ) );
        }

        wp_send_json_success(
            array(
                'download_url' => $file['url'],
                'filename' => $file['filename'],
            )
        );
    }

    /**
     * 导出推送记录 CSV
     */
    public function handle_export_records() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( '权限不足。', 'ding-pusher' ) );
        }

        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'dtpwp_export_records' ) ) {
            wp_die( __( '非法请求。', 'ding-pusher' ) );
        }

        $format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'csv';
        $format = in_array( $format, array( 'csv', 'xlsx' ), true ) ? $format : 'csv';

        $post_ids = isset( $_GET['post_ids'] ) && is_array( $_GET['post_ids'] )
            ? array_map( 'absint', wp_unslash( $_GET['post_ids'] ) )
            : array();
        $post_ids = array_filter( $post_ids );

        $requested_fields = isset( $_GET['fields'] ) && is_array( $_GET['fields'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_GET['fields'] ) )
            : array();
        $fields = $this->sanitize_export_fields( $requested_fields );

        if ( ! $this->acquire_export_lock() ) {
            wp_die( __( '导出过于频繁，请稍后再试。', 'ding-pusher' ) );
        }

        $error_message = '';
        $rows = $this->build_export_rows( $post_ids, $fields, $error_message );
        if ( $error_message ) {
            $this->release_export_lock();
            wp_die( $error_message );
        }

        if ( 'xlsx' === $format ) {
            $this->output_records_xlsx( $rows );
            exit;
        }

        $this->output_records_csv( $rows );
        exit;
    }

    private function sanitize_export_fields( $requested_fields ) {
        $allowed_fields = $this->get_export_allowed_fields();
        $fields = array();

        foreach ( $requested_fields as $field ) {
            if ( isset( $allowed_fields[ $field ] ) ) {
                $fields[] = $field;
            }
        }

        if ( empty( $fields ) ) {
            $fields = array_keys( $allowed_fields );
        }

        return $fields;
    }

    private function get_export_allowed_fields() {
        return array(
            'id' => __( 'ID', 'ding-pusher' ),
            'title' => __( '标题', 'ding-pusher' ),
            'author' => __( '作者', 'ding-pusher' ),
            'sent_time' => __( '推送时间', 'ding-pusher' ),
            'message' => __( '消息内容', 'ding-pusher' ),
            'link' => __( '链接', 'ding-pusher' ),
        );
    }

    private function build_export_rows( $post_ids, $fields, &$error_message ) {
        $error_message = '';
        $max_rows = self::EXPORT_MAX_ROWS;

        if ( ! empty( $post_ids ) && count( $post_ids ) > $max_rows ) {
            $error_message = __( '导出数量过多，请减少选择或分批导出。', 'ding-pusher' );
            return array();
        }

        $args = $this->get_records_query_args( $max_rows + 1, 1, $post_ids );
        $args['no_found_rows'] = true;
        $query = new WP_Query( $args );

        if ( $query->post_count > $max_rows ) {
            $error_message = __( '导出数量过多，请缩小范围或改为导出选中记录。', 'ding-pusher' );
            wp_reset_postdata();
            return array();
        }

        $allowed_fields = $this->get_export_allowed_fields();
        $rows = array();
        $header = array();
        foreach ( $fields as $field ) {
            $header[] = $allowed_fields[ $field ];
        }
        $rows[] = $header;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $author = get_the_author();
                $sent_time = get_post_meta( $post_id, '_dtpwp_sent_time', true );
                $sent_message = get_post_meta( $post_id, '_dtpwp_sent_message', true );
                $link = get_permalink( $post_id );

                $data = array(
                    'id' => $post_id,
                    'title' => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
                    'author' => $author,
                    'sent_time' => $sent_time,
                    'message' => $sent_message,
                    'link' => $link,
                );

                $row = array();
                foreach ( $fields as $field ) {
                    $row[] = isset( $data[ $field ] ) ? $data[ $field ] : '';
                }
                $rows[] = $row;
            }
            wp_reset_postdata();
        }

        return $rows;
    }

    private function acquire_export_lock() {
        $user_id = get_current_user_id();
        $key = 'dtpwp_export_lock_' . $user_id;
        if ( get_transient( $key ) ) {
            return false;
        }
        set_transient( $key, time(), self::EXPORT_RATE_LIMIT_SECONDS );
        return true;
    }

    private function release_export_lock() {
        $user_id = get_current_user_id();
        $key = 'dtpwp_export_lock_' . $user_id;
        delete_transient( $key );
    }

    private function is_xlsx_available() {
        if ( class_exists( 'ZipArchive' ) ) {
            return true;
        }

        if ( class_exists( 'PclZip' ) ) {
            return true;
        }

        $pclzip_path = ABSPATH . 'wp-admin/includes/class-pclzip.php';
        if ( file_exists( $pclzip_path ) ) {
            require_once $pclzip_path;
        }

        return class_exists( 'PclZip' );
    }

    private function delete_temp_dir( $dir ) {
        if ( empty( $dir ) || ! is_dir( $dir ) ) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $items as $item ) {
            if ( $item->isDir() ) {
                @rmdir( $item->getPathname() );
            } else {
                @unlink( $item->getPathname() );
            }
        }

        @rmdir( $dir );
    }

    private function prepare_export_file( $rows, $format, &$error_message ) {
        $error_message = '';
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            $error_message = __( '无法写入上传目录，请检查服务器权限。', 'ding-pusher' );
            return false;
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'dtpwp-exports';
        if ( ! wp_mkdir_p( $dir ) ) {
            $error_message = __( '无法创建导出目录。', 'ding-pusher' );
            return false;
        }

        $this->cleanup_export_files( $dir );

        $filename = 'ding-pusher-records-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_uuid4() . '.' . $format;
        $path = trailingslashit( $dir ) . $filename;

        if ( 'xlsx' === $format ) {
            if ( ! $this->generate_xlsx_file( $rows, $path, $error_message ) ) {
                return false;
            }
        } elseif ( ! $this->write_csv_file( $rows, $path, $error_message ) ) {
            return false;
        }

        $url = trailingslashit( $uploads['baseurl'] ) . 'dtpwp-exports/' . $filename;

        return array(
            'path' => $path,
            'url' => $url,
            'filename' => $filename,
        );
    }

    private function cleanup_export_files( $dir ) {
        $files = glob( trailingslashit( $dir ) . 'ding-pusher-records-*' );
        if ( empty( $files ) ) {
            return;
        }

        $expire = time() - DAY_IN_SECONDS;
        foreach ( $files as $file ) {
            if ( is_file( $file ) && filemtime( $file ) < $expire ) {
                @unlink( $file );
            }
        }
    }

    private function output_records_csv( $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename=ding-pusher-records.csv' );

        echo "\xEF\xBB\xBF";

        $output = fopen( 'php://output', 'w' );
        foreach ( $rows as $row ) {
            fputcsv( $output, $row );
        }
        fclose( $output );
    }

    private function write_csv_file( $rows, $path, &$error_message ) {
        $error_message = '';
        $handle = fopen( $path, 'wb' );
        if ( ! $handle ) {
            $error_message = __( '无法写入 CSV 文件。', 'ding-pusher' );
            return false;
        }

        fwrite( $handle, "\xEF\xBB\xBF" );
        foreach ( $rows as $row ) {
            fputcsv( $handle, $row );
        }
        fclose( $handle );
        return true;
    }

    private function generate_xlsx_file( $rows, $file_path, &$error_message ) {
        $error_message = '';

        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $file_path, ZipArchive::OVERWRITE ) ) {
                $error_message = __( 'Failed to generate XLSX file.', 'ding-pusher' );
                return false;
            }

            $zip->addFromString( '[Content_Types].xml', $this->get_xlsx_content_types() );
            $zip->addFromString( '_rels/.rels', $this->get_xlsx_root_rels() );
            $zip->addFromString( 'xl/workbook.xml', $this->get_xlsx_workbook() );
            $zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->get_xlsx_workbook_rels() );
            $zip->addFromString( 'xl/styles.xml', $this->get_xlsx_styles() );
            $zip->addFromString( 'xl/worksheets/sheet1.xml', $this->get_xlsx_sheet( $rows ) );
            $zip->close();

            return true;
        }

        return $this->generate_xlsx_file_with_pclzip( $rows, $file_path, $error_message );
    }

    private function generate_xlsx_file_with_pclzip( $rows, $file_path, &$error_message ) {
        $error_message = '';
        $generic_error = __( 'XLSX export failed. Please enable PHP ZipArchive or PclZip.', 'ding-pusher' );

        if ( ! class_exists( 'PclZip' ) ) {
            $pclzip_path = ABSPATH . 'wp-admin/includes/class-pclzip.php';
            if ( file_exists( $pclzip_path ) ) {
                require_once $pclzip_path;
            }
        }

        if ( ! class_exists( 'PclZip' ) ) {
            $error_message = $generic_error;
            return false;
        }

        $temp_dir = trailingslashit( get_temp_dir() ) . 'dtpwp-xlsx-' . wp_generate_uuid4();
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            $error_message = $generic_error;
            return false;
        }

        $files = array(
            '[Content_Types].xml' => $this->get_xlsx_content_types(),
            '_rels/.rels' => $this->get_xlsx_root_rels(),
            'xl/workbook.xml' => $this->get_xlsx_workbook(),
            'xl/_rels/workbook.xml.rels' => $this->get_xlsx_workbook_rels(),
            'xl/styles.xml' => $this->get_xlsx_styles(),
            'xl/worksheets/sheet1.xml' => $this->get_xlsx_sheet( $rows ),
        );

        foreach ( $files as $relative_path => $content ) {
            $target = $temp_dir . '/' . $relative_path;
            $dir = dirname( $target );
            if ( ! wp_mkdir_p( $dir ) ) {
                $error_message = $generic_error;
                $this->delete_temp_dir( $temp_dir );
                return false;
            }

            if ( false === file_put_contents( $target, $content ) ) {
                $error_message = $generic_error;
                $this->delete_temp_dir( $temp_dir );
                return false;
            }
        }

        $zip = new PclZip( $file_path );
        $result = $zip->create( $temp_dir, PCLZIP_OPT_REMOVE_PATH, $temp_dir );

        $this->delete_temp_dir( $temp_dir );

        if ( 0 === $result ) {
            $error_message = __( 'Failed to generate XLSX file.', 'ding-pusher' );
            return false;
        }

        return true;
    }

    /**
     * 输出 XLSX
     *
     * @param array $rows 行数据
     */
    private function output_records_xlsx( $rows ) {
        $temp_file = wp_tempnam( 'dtpwp-records' );
        if ( ! $temp_file ) {
            wp_die( __( '无法创建临时文件。', 'ding-pusher' ) );
        }

        $error_message = '';
        if ( ! $this->generate_xlsx_file( $rows, $temp_file, $error_message ) ) {
            wp_die( $error_message ? $error_message : __( '无法创建 XLSX 文件。', 'ding-pusher' ) );
        }

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename=ding-pusher-records.xlsx' );
        header( 'Content-Length: ' . filesize( $temp_file ) );

        readfile( $temp_file );
        @unlink( $temp_file );
    }

    private function get_xlsx_content_types() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
    }

    private function get_xlsx_root_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function get_xlsx_workbook() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Records" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';
    }

    private function get_xlsx_workbook_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
    }

    private function get_xlsx_styles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>' .
            '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' .
            '</styleSheet>';
    }

    private function get_xlsx_sheet( $rows ) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $row_index = 1;
        foreach ( $rows as $row ) {
            $xml .= '<row r="' . $row_index . '">';
            $col_index = 1;
            foreach ( $row as $cell ) {
                $cell_ref = $this->xlsx_column_letter( $col_index ) . $row_index;
                $value = htmlspecialchars( (string) $cell, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
                $xml .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
                $col_index++;
            }
            $xml .= '</row>';
            $row_index++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function xlsx_column_letter( $index ) {
        $letters = '';
        while ( $index > 0 ) {
            $index--;
            $letters = chr( 65 + ( $index % 26 ) ) . $letters;
            $index = (int) ( $index / 26 );
        }
        return $letters;
    }
}
