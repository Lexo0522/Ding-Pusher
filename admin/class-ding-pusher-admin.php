<?php

/**
 * Ding Pusher 管理类
 */
class Ding_Pusher_Admin {
    
    /**
     * 单例实例
     */
    private static $instance;
    
    /**
     * 设置选项
     */
    private $settings;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->settings = get_option( DTPWP_OPTION_NAME );
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 后台菜单
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // 设置页面
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // 加载样式和脚本
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX处理
        add_action( 'wp_ajax_dtpwp_test_message', array( $this, 'ajax_test_message' ) );
        add_action( 'wp_ajax_dtpwp_mark_as_sent', array( $this, 'ajax_mark_as_sent' ) );
        add_action( 'wp_ajax_dtpwp_clear_sent_records', array( $this, 'ajax_clear_sent_records' ) );
    }
    
    /**
     * 添加后台菜单
     */
    public function add_admin_menu() {
        // 主菜单
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
        
        // 设置子菜单
        add_submenu_page(
            $parent_slug,
            __( '设置', 'ding-pusher' ),
            __( '设置', 'ding-pusher' ),
            'manage_options',
            $parent_slug,
            array( $this, 'render_settings_page' )
        );
        
        // 推送记录子菜单
        add_submenu_page(
            $parent_slug,
            __( '推送记录', 'ding-pusher' ),
            __( '推送记录', 'ding-pusher' ),
            'manage_options',
            'ding-pusher-records',
            array( $this, 'render_records_page' )
        );
        
        // 帮助子菜单
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
        register_setting( 'dtpwp_settings', DTPWP_OPTION_NAME, array( $this, 'validate_settings' ) );
        
        // 基本设置
        add_settings_section(
            'dtpwp_basic_section',
            __( '基本设置', 'ding-pusher' ),
            array( $this, 'render_basic_section' ),
            'ding-pusher'
        );
        
        add_settings_field(
            'webhook_url',
            __( '钉钉Webhook地址', 'ding-pusher' ),
            array( $this, 'render_webhook_url_field' ),
            'ding-pusher',
            'dtpwp_basic_section'
        );
        
        // 安全设置
        add_settings_section(
            'dtpwp_security_section',
            __( '安全设置', 'ding-pusher' ),
            array( $this, 'render_security_section' ),
            'ding-pusher'
        );
        
        add_settings_field(
            'security_type',
            __( '安全验证方式', 'ding-pusher' ),
            array( $this, 'render_security_type_field' ),
            'ding-pusher',
            'dtpwp_security_section'
        );
        
        add_settings_field(
            'security_keyword',
            __( '关键词', 'ding-pusher' ),
            array( $this, 'render_security_keyword_field' ),
            'ding-pusher',
            'dtpwp_security_section'
        );
        
        add_settings_field(
            'security_secret',
            __( '加签密钥', 'ding-pusher' ),
            array( $this, 'render_security_secret_field' ),
            'ding-pusher',
            'dtpwp_security_section'
        );
        
        add_settings_field(
            'security_ip_whitelist',
            __( 'IP白名单', 'ding-pusher' ),
            array( $this, 'render_security_ip_whitelist_field' ),
            'ding-pusher',
            'dtpwp_security_section'
        );
        
        // 消息设置
        add_settings_section(
            'dtpwp_message_section',
            __( '消息设置', 'ding-pusher' ),
            array( $this, 'render_message_section' ),
            'ding-pusher'
        );
        
        add_settings_field(
            'message_type',
            __( '消息类型', 'ding-pusher' ),
            array( $this, 'render_message_type_field' ),
            'ding-pusher',
            'dtpwp_message_section'
        );
        
        add_settings_field(
            'post_template',
            __( '文章推送模板', 'ding-pusher' ),
            array( $this, 'render_post_template_field' ),
            'ding-pusher',
            'dtpwp_message_section'
        );
        
        add_settings_field(
            'user_template',
            __( '用户提示模板', 'ding-pusher' ),
            array( $this, 'render_user_template_field' ),
            'ding-pusher',
            'dtpwp_message_section'
        );
        
        // 触发设置
        add_settings_section(
            'dtpwp_trigger_section',
            __( '触发设置', 'ding-pusher' ),
            array( $this, 'render_trigger_section' ),
            'ding-pusher'
        );
        
        add_settings_field(
            'enable_new_post',
            __( '新文章推送', 'ding-pusher' ),
            array( $this, 'render_enable_new_post_field' ),
            'ding-pusher',
            'dtpwp_trigger_section'
        );
        
        add_settings_field(
            'enable_post_update',
            __( '文章更新推送', 'ding-pusher' ),
            array( $this, 'render_enable_post_update_field' ),
            'ding-pusher',
            'dtpwp_trigger_section'
        );
        
        add_settings_field(
            'enable_custom_post_type',
            __( '自定义文章类型', 'ding-pusher' ),
            array( $this, 'render_enable_custom_post_type_field' ),
            'ding-pusher',
            'dtpwp_trigger_section'
        );
        
        add_settings_field(
            'enable_new_user',
            __( '新用户提示', 'ding-pusher' ),
            array( $this, 'render_enable_new_user_field' ),
            'ding-pusher',
            'dtpwp_trigger_section'
        );
        
        // 高级设置
        add_settings_section(
            'dtpwp_advanced_section',
            __( '高级设置', 'ding-pusher' ),
            array( $this, 'render_advanced_section' ),
            'ding-pusher'
        );
        
        add_settings_field(
            'push_interval',
            __( '推送间隔（分钟）', 'ding-pusher' ),
            array( $this, 'render_push_interval_field' ),
            'ding-pusher',
            'dtpwp_advanced_section'
        );
        
        add_settings_field(
            'retry_count',
            __( '重试次数', 'ding-pusher' ),
            array( $this, 'render_retry_count_field' ),
            'ding-pusher',
            'dtpwp_advanced_section'
        );
        
        add_settings_field(
            'deduplicate_days',
            __( '去重记录保留天数', 'ding-pusher' ),
            array( $this, 'render_deduplicate_days_field' ),
            'ding-pusher',
            'dtpwp_advanced_section'
        );
        
        add_settings_field(
            'enable_auto_update',
            __( '启用插件自动更新', 'ding-pusher' ),
            array( $this, 'render_enable_auto_update_field' ),
            'ding-pusher',
            'dtpwp_advanced_section'
        );
    }
    
    /**
     * 验证设置
     */
    public function validate_settings( $input ) {
        $valid = array();
        
        // 验证Webhook URL
        $webhook_url = sanitize_text_field( $input['webhook_url'] );
        if ( ! empty( $webhook_url ) && ! preg_match( '/^https:\/\/oapi\.dingtalk\.com\/robot\/send/', $webhook_url ) ) {
            add_settings_error( 'dtpwp_settings', 'invalid_webhook', __( 'Webhook URL格式不正确，请检查。', 'ding-pusher' ) );
        }
        $valid['webhook_url'] = $webhook_url;
        
        // 验证安全设置
        $valid['security_type'] = sanitize_text_field( $input['security_type'] );
        $valid['security_keyword'] = isset( $input['security_keyword'] ) ? array_map( 'sanitize_text_field', $input['security_keyword'] ) : array();
        $valid['security_secret'] = sanitize_text_field( $input['security_secret'] );
        $valid['security_ip_whitelist'] = isset( $input['security_ip_whitelist'] ) ? array_map( 'sanitize_text_field', $input['security_ip_whitelist'] ) : array();
        
        // 验证消息设置
        $valid['message_type'] = sanitize_text_field( $input['message_type'] );
        $valid['post_template'] = sanitize_text_field( $input['post_template'] );
        $valid['user_template'] = sanitize_text_field( $input['user_template'] );
        
        // 验证触发设置
        $valid['enable_new_post'] = isset( $input['enable_new_post'] ) ? 1 : 0;
        $valid['enable_post_update'] = isset( $input['enable_post_update'] ) ? 1 : 0;
        $valid['enable_custom_post_type'] = isset( $input['enable_custom_post_type'] ) ? array_map( 'sanitize_text_field', $input['enable_custom_post_type'] ) : array();
        $valid['enable_new_user'] = isset( $input['enable_new_user'] ) ? 1 : 0;
        
        // 验证高级设置
        $valid['push_interval'] = absint( $input['push_interval'] );
        if ( $valid['push_interval'] < 1 ) {
            $valid['push_interval'] = 5;
        }
        
        $valid['retry_count'] = absint( $input['retry_count'] );
        if ( $valid['retry_count'] < 1 ) {
            $valid['retry_count'] = 3;
        }
        
        $valid['deduplicate_days'] = absint( $input['deduplicate_days'] );
        if ( $valid['deduplicate_days'] < 1 ) {
            $valid['deduplicate_days'] = 30;
        }
        
        $valid['enable_auto_update'] = isset( $input['enable_auto_update'] ) ? 1 : 0;
        
        // 更新定时任务
        wp_clear_scheduled_hook( 'dtpwp_check_new_content' );
        if ( ! wp_next_scheduled( 'dtpwp_check_new_content' ) ) {
            wp_schedule_event( time(), $valid['push_interval'] . 'm', 'dtpwp_check_new_content' );
        }
        
        return $valid;
    }
    
    /**
     * 渲染基本设置区块
     */
    public function render_basic_section() {
        echo '<p>' . __( '配置钉钉机器人的基本信息。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染Webhook URL字段
     */
    public function render_webhook_url_field() {
        $webhook_url = isset( $this->settings['webhook_url'] ) ? $this->settings['webhook_url'] : '';
        echo '<input type="text" name="' . DTPWP_OPTION_NAME . '[webhook_url]" value="' . esc_attr( $webhook_url ) . '" class="regular-text" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx" />';
        echo '<p class="description">' . __( '请输入钉钉机器人的Webhook地址，可在钉钉群机器人设置中获取。', 'ding-pusher' ) . '</p>';
        echo '<button type="button" class="button button-secondary" id="dtpwp-test-message">' . __( '发送测试消息', 'ding-pusher' ) . '</button>';
    }
    
    /**
     * 渲染安全设置区块
     */
    public function render_security_section() {
        echo '<p>' . __( '配置钉钉机器人的安全验证方式，可选择关键词、加签或IP白名单。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染安全类型字段
     */
    public function render_security_type_field() {
        $security_type = isset( $this->settings['security_type'] ) ? $this->settings['security_type'] : 'keyword';
        $options = array(
            'keyword' => __( '关键词', 'ding-pusher' ),
            'secret' => __( '加签', 'ding-pusher' ),
            'ip_whitelist' => __( 'IP白名单', 'ding-pusher' )
        );
        
        echo '<select name="' . DTPWP_OPTION_NAME . '[security_type]" id="dtpwp-security-type" class="regular-text">';
        foreach ( $options as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $security_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * 渲染关键词字段
     */
    public function render_security_keyword_field() {
        $security_keyword = isset( $this->settings['security_keyword'] ) ? $this->settings['security_keyword'] : array();
        echo '<div id="dtpwp-keyword-list">';
        foreach ( $security_keyword as $index => $keyword ) {
            echo '<div class="keyword-item">';
            echo '<input type="text" name="' . DTPWP_OPTION_NAME . '[security_keyword][]" value="' . esc_attr( $keyword ) . '" class="regular-text" />';
            echo '<button type="button" class="button button-link-delete dtpwp-remove-keyword">' . __( '删除', 'ding-pusher' ) . '</button>';
            echo '</div>';
        }
        if ( empty( $security_keyword ) ) {
            echo '<div class="keyword-item">';
            echo '<input type="text" name="' . DTPWP_OPTION_NAME . '[security_keyword][]" value="" class="regular-text" />';
            echo '<button type="button" class="button button-link-delete dtpwp-remove-keyword">' . __( '删除', 'ding-pusher' ) . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="button button-secondary dtpwp-add-keyword">' . __( '添加关键词', 'ding-pusher' ) . '</button>';
    }
    
    /**
     * 渲染加签密钥字段
     */
    public function render_security_secret_field() {
        $security_secret = isset( $this->settings['security_secret'] ) ? $this->settings['security_secret'] : '';
        echo '<input type="text" name="' . DTPWP_OPTION_NAME . '[security_secret]" value="' . esc_attr( $security_secret ) . '" class="regular-text" />';
        echo '<p class="description">' . __( '请输入钉钉机器人的加签密钥，用于生成签名。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染IP白名单字段
     */
    public function render_security_ip_whitelist_field() {
        $security_ip_whitelist = isset( $this->settings['security_ip_whitelist'] ) ? $this->settings['security_ip_whitelist'] : array();
        echo '<div id="dtpwp-ip-list">';
        foreach ( $security_ip_whitelist as $index => $ip ) {
            echo '<div class="ip-item">';
            echo '<input type="text" name="' . DTPWP_OPTION_NAME . '[security_ip_whitelist][]" value="' . esc_attr( $ip ) . '" class="regular-text" />';
            echo '<button type="button" class="button button-link-delete dtpwp-remove-ip">' . __( '删除', 'ding-pusher' ) . '</button>';
            echo '</div>';
        }
        if ( empty( $security_ip_whitelist ) ) {
            echo '<div class="ip-item">';
            echo '<input type="text" name="' . DTPWP_OPTION_NAME . '[security_ip_whitelist][]" value="" class="regular-text" />';
            echo '<button type="button" class="button button-link-delete dtpwp-remove-ip">' . __( '删除', 'ding-pusher' ) . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="button button-secondary dtpwp-add-ip">' . __( '添加IP', 'ding-pusher' ) . '</button>';
    }
    
    /**
     * 渲染消息设置区块
     */
    public function render_message_section() {
        echo '<p>' . __( '配置推送消息的类型和模板。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染消息类型字段
     */
    public function render_message_type_field() {
        $message_type = isset( $this->settings['message_type'] ) ? $this->settings['message_type'] : 'text';
        $options = array(
            'text' => __( '文本', 'ding-pusher' ),
            'link' => __( '链接', 'ding-pusher' ),
            'markdown' => __( 'Markdown', 'ding-pusher' )
        );
        
        echo '<select name="' . DTPWP_OPTION_NAME . '[message_type]" class="regular-text">';
        foreach ( $options as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $message_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * 渲染文章推送模板字段
     */
    public function render_post_template_field() {
        $post_template = isset( $this->settings['post_template'] ) ? $this->settings['post_template'] : '【新文章】\n标题：{title}\n作者：{author}\n链接：{link}';
        echo '<textarea name="' . DTPWP_OPTION_NAME . '[post_template]" rows="5" cols="50" class="large-text">' . esc_textarea( $post_template ) . '</textarea>';
        echo '<p class="description">' . __( '支持的变量：{title}、{author}、{link}、{date}、{category}、{post_id}', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染用户提示模板字段
     */
    public function render_user_template_field() {
        $user_template = isset( $this->settings['user_template'] ) ? $this->settings['user_template'] : '【新用户注册】\n用户名：{username}\n邮箱：{email}\n注册时间：{register_time}';
        echo '<textarea name="' . DTPWP_OPTION_NAME . '[user_template]" rows="5" cols="50" class="large-text">' . esc_textarea( $user_template ) . '</textarea>';
        echo '<p class="description">' . __( '支持的变量：{username}、{email}、{register_time}、{user_id}', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染触发设置区块
     */
    public function render_trigger_section() {
        echo '<p>' . __( '配置推送的触发场景和条件。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染新文章推送字段
     */
    public function render_enable_new_post_field() {
        $enable_new_post = isset( $this->settings['enable_new_post'] ) ? $this->settings['enable_new_post'] : 1;
        echo '<input type="checkbox" name="' . DTPWP_OPTION_NAME . '[enable_new_post]" value="1" ' . checked( 1, $enable_new_post, false ) . ' />';
        echo '<label for="' . DTPWP_OPTION_NAME . '[enable_new_post]">' . __( '开启新文章推送', 'ding-pusher' ) . '</label>';
    }
    
    /**
     * 渲染文章更新推送字段
     */
    public function render_enable_post_update_field() {
        $enable_post_update = isset( $this->settings['enable_post_update'] ) ? $this->settings['enable_post_update'] : 0;
        echo '<input type="checkbox" name="' . DTPWP_OPTION_NAME . '[enable_post_update]" value="1" ' . checked( 1, $enable_post_update, false ) . ' />';
        echo '<label for="' . DTPWP_OPTION_NAME . '[enable_post_update]">' . __( '开启文章更新重新推送', 'ding-pusher' ) . '</label>';
    }
    
    /**
     * 渲染自定义文章类型字段
     */
    public function render_enable_custom_post_type_field() {
        $enable_custom_post_type = isset( $this->settings['enable_custom_post_type'] ) ? $this->settings['enable_custom_post_type'] : array();
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        
        echo '<div>';
        foreach ( $post_types as $post_type ) {
            if ( $post_type->name == 'post' || $post_type->name == 'page' ) {
                continue;
            }
            $checked = in_array( $post_type->name, $enable_custom_post_type ) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . DTPWP_OPTION_NAME . '[enable_custom_post_type][]" value="' . esc_attr( $post_type->name ) . '" ' . $checked . ' /> ' . esc_html( $post_type->label ) . '</label><br />';
        }
        echo '</div>';
    }
    
    /**
     * 渲染新用户提示字段
     */
    public function render_enable_new_user_field() {
        $enable_new_user = isset( $this->settings['enable_new_user'] ) ? $this->settings['enable_new_user'] : 1;
        echo '<input type="checkbox" name="' . DTPWP_OPTION_NAME . '[enable_new_user]" value="1" ' . checked( 1, $enable_new_user, false ) . ' />';
        echo '<label for="' . DTPWP_OPTION_NAME . '[enable_new_user]">' . __( '开启新用户注册提示', 'ding-pusher' ) . '</label>';
    }
    
    /**
     * 渲染高级设置区块
     */
    public function render_advanced_section() {
        echo '<p>' . __( '配置插件的高级选项。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染推送间隔字段
     */
    public function render_push_interval_field() {
        $push_interval = isset( $this->settings['push_interval'] ) ? $this->settings['push_interval'] : 5;
        echo '<input type="number" name="' . DTPWP_OPTION_NAME . '[push_interval]" value="' . esc_attr( $push_interval ) . '" min="1" max="60" class="small-text" />';
        echo '<p class="description">' . __( '设置检查新内容的间隔时间，单位为分钟。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染重试次数字段
     */
    public function render_retry_count_field() {
        $retry_count = isset( $this->settings['retry_count'] ) ? $this->settings['retry_count'] : 3;
        echo '<input type="number" name="' . DTPWP_OPTION_NAME . '[retry_count]" value="' . esc_attr( $retry_count ) . '" min="1" max="10" class="small-text" />';
        echo '<p class="description">' . __( '设置推送失败后的重试次数。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染去重记录保留天数字段
     */
    public function render_deduplicate_days_field() {
        $deduplicate_days = isset( $this->settings['deduplicate_days'] ) ? $this->settings['deduplicate_days'] : 30;
        echo '<input type="number" name="' . DTPWP_OPTION_NAME . '[deduplicate_days]" value="' . esc_attr( $deduplicate_days ) . '" min="1" max="365" class="small-text" />';
        echo '<p class="description">' . __( '设置去重记录的保留天数，过期记录将自动清理。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染启用自动更新字段
     */
    public function render_enable_auto_update_field() {
        $enable_auto_update = isset( $this->settings['enable_auto_update'] ) ? $this->settings['enable_auto_update'] : 1;
        echo '<input type="checkbox" name="' . DTPWP_OPTION_NAME . '[enable_auto_update]" value="1" ' . checked( 1, $enable_auto_update, false ) . ' />';
        echo '<label for="' . DTPWP_OPTION_NAME . '[enable_auto_update]"> ' . __( '启用插件自动更新功能', 'ding-pusher' ) . '</label>';
        echo '<p class="description">' . __( '启用后，插件将自动检测并提示更新，支持WordPress后台一键升级。', 'ding-pusher' ) . '</p>';
    }
    
    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        ?>        
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'dtpwp_settings' );
                do_settings_sections( 'ding-pusher' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 渲染推送记录页面
     */
    public function render_records_page() {
        // 获取已推送文章列表
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => DTPWP_SENT_META_KEY,
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $query = new WP_Query( $args );
        
        ?>        
        <div class="wrap">
            <h1><?php _e( '推送记录', 'ding-pusher' ); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="button" class="button button-secondary" id="dtpwp-clear-records"><?php _e( '清理所有记录', 'ding-pusher' ); ?></button>
                </div>
                <br class="clear" />
            </div>
            
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
                        <th scope="col" class="manage-column column-title"><?php _e( '文章标题', 'ding-pusher' ); ?></th>
                        <th scope="col" class="manage-column column-author"><?php _e( '作者', 'ding-pusher' ); ?></th>
                        <th scope="col" class="manage-column column-date"><?php _e( '推送时间', 'ding-pusher' ); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php _e( '操作', 'ding-pusher' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $query->have_posts() ) : ?>
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo get_the_ID(); ?>" /></th>
                                <td class="column-title">
                                    <strong><a href="<?php echo get_permalink(); ?>" target="_blank"><?php the_title(); ?></a></strong>
                                    <div class="row-actions">
                                        <span class="view"><a href="<?php echo get_permalink(); ?>" target="_blank"><?php _e( '查看文章', 'ding-pusher' ); ?></a></span>
                                    </div>
                                    <!-- 显示发送的消息内容 -->
                                    <div class="dtpwp-sent-message">
                                        <strong><?php _e( '发送内容:', 'ding-pusher' ); ?></strong>
                                        <pre><?php echo esc_html( get_post_meta( get_the_ID(), '_dtpwp_sent_message', true ) ); ?></pre>
                                    </div>
                                </td>
                                <td class="column-author"><?php the_author(); ?></td>
                                <td class="column-date"><?php echo get_post_meta( get_the_ID(), '_dtpwp_sent_time', true ); ?></td>
                                <td class="column-actions">
                                    <button type="button" class="button button-link-delete dtpwp-mark-as-not-sent" data-post-id="<?php echo get_the_ID(); ?>"><?php _e( '取消标记', 'ding-pusher' ); ?></button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" class="colspanchange"><?php _e( '暂无推送记录。', 'ding-pusher' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        
        wp_reset_postdata();
    }
    
    /**
     * 渲染帮助页面
     */
    public function render_help_page() {
        ?>        
        <div class="wrap">
            <h1><?php _e( '帮助与教程', 'ding-pusher' ); ?></h1>
            
            <h2><?php _e( '钉钉机器人创建教程', 'ding-pusher' ); ?></h2>
            <ol>
                <li><?php _e( '打开钉钉群，点击群设置 > 智能群助手 > 添加机器人', 'ding-pusher' ); ?></li>
                <li><?php _e( '选择"自定义"机器人，点击"添加"', 'ding-pusher' ); ?></li>
                <li><?php _e( '填写机器人名称，选择安全设置，点击"完成"', 'ding-pusher' ); ?></li>
                <li><?php _e( '复制生成的Webhook地址，粘贴到插件设置中', 'ding-pusher' ); ?></li>
            </ol>
            
            <h2><?php _e( '常见问题', 'ding-pusher' ); ?></h2>
            <h3><?php _e( '推送失败怎么办？', 'ding-pusher' ); ?></h3>
            <ul>
                <li><?php _e( '检查Webhook地址是否正确', 'ding-pusher' ); ?></li>
                <li><?php _e( '检查安全设置是否匹配（关键词、加签、IP白名单）', 'ding-pusher' ); ?></li>
                <li><?php _e( '检查网络连接是否正常', 'ding-pusher' ); ?></li>
                <li><?php _e( '查看WordPress日志获取详细错误信息', 'ding-pusher' ); ?></li>
            </ul>
            
            <h3><?php _e( '如何重新推送已推送的文章？', 'ding-pusher' ); ?></h3>
            <p><?php _e( '在"推送记录"页面，点击"取消标记"按钮，然后重新发布文章或等待定时任务检查。', 'ding-pusher' ); ?></p>
        </div>
        <?php
    }
    
    /**
     * 加载样式和脚本
     */
    public function enqueue_assets( $hook ) {
        // 只在插件页面加载
        if ( strpos( $hook, 'ding-pusher' ) === false ) {
            return;
        }
        
        // 加载自定义脚本
        wp_enqueue_script( 'dtpwp-admin-script', DTPWP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), DTPWP_VERSION, true );
        wp_localize_script( 'dtpwp-admin-script', 'dtpwp_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'dtpwp_nonce' )
        ) );
        
        // 加载自定义样式
        wp_enqueue_style( 'dtpwp-admin-style', DTPWP_PLUGIN_URL . 'assets/css/admin.css', array(), DTPWP_VERSION );
    }
    
    /**
     * AJAX测试消息
     */
    public function ajax_test_message() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );
        
        $settings = get_option( DTPWP_OPTION_NAME );
        if ( empty( $settings['webhook_url'] ) ) {
            wp_send_json_error( array( 'message' => __( '请先填写Webhook地址', 'ding-pusher' ) ) );
        }
        
        // 发送测试消息
        $message = __( '这是一条测试消息，来自Ding Pusher插件', 'ding-pusher' );
        $core = Ding_Pusher_Core::get_instance();
        $success = $core->send_dingtalk_message( $message );
        
        if ( $success ) {
            wp_send_json_success( array( 'message' => __( '测试消息发送成功', 'ding-pusher' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( '测试消息发送失败，请检查配置', 'ding-pusher' ) ) );
        }
    }
    
    /**
     * AJAX标记为已推送或取消标记
     */
    public function ajax_mark_as_sent() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $mark_as = isset( $_POST['mark_as'] ) ? sanitize_text_field( $_POST['mark_as'] ) : 'sent';
        
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( '无效的文章ID', 'ding-pusher' ) ) );
        }
        
        if ( $mark_as == 'not_sent' ) {
            // 取消标记：删除推送记录元数据
            delete_post_meta( $post_id, DTPWP_SENT_META_KEY );
            delete_post_meta( $post_id, '_dtpwp_sent_time' );
            delete_post_meta( $post_id, '_dtpwp_sent_message' );
            
            wp_send_json_success( array( 'message' => __( '取消标记成功', 'ding-pusher' ) ) );
        } else {
            // 标记为已推送
            update_post_meta( $post_id, DTPWP_SENT_META_KEY, 1 );
            update_post_meta( $post_id, '_dtpwp_sent_time', current_time( 'mysql' ) );
            
            wp_send_json_success( array( 'message' => __( '标记成功', 'ding-pusher' ) ) );
        }
    }
    
    /**
     * AJAX清理推送记录
     */
    public function ajax_clear_sent_records() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );
        
        // 清理所有推送记录
        global $wpdb;
        $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '" . DTPWP_SENT_META_KEY . "' OR meta_key = '_dtpwp_sent_time'" );
        delete_option( 'dtpwp_sent_title_hashes' );
        
        wp_send_json_success( array( 'message' => __( '所有记录已清理', 'ding-pusher' ) ) );
    }
}
