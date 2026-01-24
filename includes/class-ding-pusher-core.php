<?php

/**
 * Ding Pusher 核心类
 */
class Ding_Pusher_Core {
    
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
        // 新文章发布钩子
        add_action( 'publish_post', array( $this, 'handle_new_post' ) );
        
        // 文章更新钩子
        add_action( 'post_updated', array( $this, 'handle_post_update' ), 10, 3 );
        
        // 新用户注册钩子
        add_action( 'user_register', array( $this, 'handle_new_user' ) );
        
        // 定时检查任务
        add_action( 'dtpwp_check_new_content', array( $this, 'check_new_content' ) );
        
        // 自定义文章类型支持
        add_action( 'publish_{custom_post_type}', array( $this, 'handle_new_post' ) );
    }
    
    /**
     * 激活插件
     */
    public static function activate() {
        // 设置默认选项
        $default_settings = array(
            'webhook_url' => '',
            'security_type' => 'keyword',
            'security_keyword' => array(),
            'security_secret' => '',
            'security_ip_whitelist' => array(),
            'message_type' => 'text',
            'post_template' => '【新文章】\n标题：{title}\n作者：{author}\n链接：{link}\n分类：{category}\n发布时间：{date}',
            'user_template' => '【新用户注册】\n用户名：{username}\n邮箱：{email}\n注册时间：{register_time}',
            'enable_new_post' => 1,
            'enable_post_update' => 0,
            'enable_custom_post_type' => array(),
            'enable_new_user' => 1,
            'push_interval' => 5,
            'retry_count' => 3,
            'retry_interval' => 10,
            'deduplicate_days' => 30,
            'enable_test_message' => 1,
            'enable_auto_update' => 1
        );
        
        add_option( DTPWP_OPTION_NAME, $default_settings );
        
        // 设置最后推送的文章ID和用户ID
        add_option( DTPWP_LAST_POST_KEY, 0 );
        add_option( DTPWP_LAST_USER_KEY, 0 );
        
        // 创建定时任务
        if ( ! wp_next_scheduled( 'dtpwp_check_new_content' ) ) {
            wp_schedule_event( time(), '5m', 'dtpwp_check_new_content' );
        }
        
        // 发送插件激活通知
        self::send_activation_notice();
    }
    
    /**
     * 停用插件
     */
    public static function deactivate() {
        // 删除定时任务
        wp_clear_scheduled_hook( 'dtpwp_check_new_content' );
    }
    
    /**
     * 发送插件激活通知
     */
    private static function send_activation_notice() {
        $settings = get_option( DTPWP_OPTION_NAME );
        if ( ! empty( $settings['webhook_url'] ) ) {
            $message = '【Ding Pusher 插件已激活】\n请登录 WordPress 后台配置插件设置，确保推送功能正常工作。';
            self::send_dingtalk_message( $message, $settings );
        }
    }
    
    /**
     * 处理新文章发布
     */
    public function handle_new_post( $post_id ) {
        // 检查是否为自动保存或修订版本
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }
        
        // 检查设置
        if ( ! isset( $this->settings['enable_new_post'] ) || ! $this->settings['enable_new_post'] ) {
            return;
        }
        
        // 获取文章信息
        $post = get_post( $post_id );
        
        // 检查文章是否刚刚发布（不是更新）
        $post_before = wp_get_post_revision( $post_id );
        if ( $post_before ) {
            // 如果是更新，跳过此钩子，由 post_updated 钩子处理
            return;
        }
        
        // 检查文章类型
        if ( $post->post_type != 'post' ) {
            if ( ! isset( $this->settings['enable_custom_post_type'] ) || ! in_array( $post->post_type, $this->settings['enable_custom_post_type'] ) ) {
                return;
            }
        }
        
        // 检查是否已推送
        if ( $this->is_post_sent( $post_id ) ) {
            return;
        }
        
        // 推送文章
        $this->push_post_to_dingtalk( $post_id );
    }
    
    /**
     * 处理文章更新
     */
    public function handle_post_update( $post_id, $post_after, $post_before ) {
        // 检查设置
        if ( ! isset( $this->settings['enable_post_update'] ) || ! $this->settings['enable_post_update'] ) {
            return;
        }
        
        // 检查是否为发布状态
        if ( $post_after->post_status != 'publish' ) {
            return;
        }
        
        // 检查是否刚刚推送过（防止短时间内重复推送）
        $last_sent_time = get_post_meta( $post_id, '_dtpwp_sent_time', true );
        if ( $last_sent_time ) {
            $last_sent_timestamp = strtotime( $last_sent_time );
            $current_timestamp = time();
            // 10分钟内不重复推送
            if ( ( $current_timestamp - $last_sent_timestamp ) < 600 ) {
                return;
            }
        }
        
        // 推送更新后的文章
        $this->push_post_to_dingtalk( $post_id, true );
    }
    
    /**
     * 处理新用户注册
     */
    public function handle_new_user( $user_id ) {
        // 检查设置
        if ( ! isset( $this->settings['enable_new_user'] ) || ! $this->settings['enable_new_user'] ) {
            return;
        }
        
        // 推送新用户通知
        $this->push_new_user_to_dingtalk( $user_id );
    }
    
    /**
     * 检查新内容
     */
    public function check_new_content() {
        // 检查新文章
        $this->check_new_posts();
        
        // 检查新用户
        $this->check_new_users();
        
        // 清理过期去重记录
        $this->cleanup_deduplicate_records();
    }
    
    /**
     * 检查新文章
     */
    private function check_new_posts() {
        $last_post_id = get_option( DTPWP_LAST_POST_KEY );
        
        // 查询最近发布的文章
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'post__not_in' => get_option( 'sticky_posts' )
        );
        
        // 检查自定义文章类型
        if ( isset( $this->settings['enable_custom_post_type'] ) && ! empty( $this->settings['enable_custom_post_type'] ) ) {
            $args['post_type'] = array_merge( array( 'post' ), $this->settings['enable_custom_post_type'] );
        }
        
        if ( $last_post_id > 0 ) {
            $args['post__not_in'] = get_option( 'sticky_posts' );
        }
        
        $query = new WP_Query( $args );
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // 确保只推送ID大于最后推送ID的文章
                if ( $post_id > $last_post_id ) {
                    // 检查是否刚刚推送过（防止短时间内重复推送）
                    $last_sent_time = get_post_meta( $post_id, '_dtpwp_sent_time', true );
                    if ( $last_sent_time ) {
                        $last_sent_timestamp = strtotime( $last_sent_time );
                        $current_timestamp = time();
                        // 10分钟内不重复推送
                        if ( ( $current_timestamp - $last_sent_timestamp ) < 600 ) {
                            continue;
                        }
                    }
                    
                    $this->push_post_to_dingtalk( $post_id );
                    
                    // 更新最后推送的文章ID
                    update_option( DTPWP_LAST_POST_KEY, $post_id );
                }
            }
        }
        
        wp_reset_postdata();
    }
    
    /**
     * 检查新用户
     */
    private function check_new_users() {
        $last_user_id = get_option( DTPWP_LAST_USER_KEY );
        
        // 查询最近注册的用户
        $args = array(
            'number' => -1,
            'offset' => 0,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'all'
        );
        
        $users = get_users( $args );
        
        foreach ( $users as $user ) {
            $user_id = $user->ID;
            
            // 确保只提示ID大于最后提示ID的用户
            if ( $user_id > $last_user_id ) {
                $this->push_new_user_to_dingtalk( $user_id );
                
                // 更新最后提示的用户ID
                update_option( DTPWP_LAST_USER_KEY, $user_id );
            }
        }
    }
    
    /**
     * 推送文章到钉钉
     */
    private function push_post_to_dingtalk( $post_id, $is_update = false ) {
        if ( empty( $this->settings['webhook_url'] ) ) {
            return false;
        }
        
        // 获取文章信息
        $post = get_post( $post_id );
        $author = get_the_author_meta( 'display_name', $post->post_author );
        $post_link = get_permalink( $post_id );
        $post_date = get_the_date( 'Y-m-d H:i:s', $post_id );
        $categories = get_the_category( $post_id );
        $category_names = array();
        foreach ( $categories as $category ) {
            $category_names[] = $category->name;
        }
        $category_str = implode( ', ', $category_names );
        
        // 替换模板变量
        $template = $this->settings['post_template'];
        $message = str_replace( '{title}', $post->post_title, $template );
        $message = str_replace( '{author}', $author, $message );
        $message = str_replace( '{link}', $post_link, $message );
        $message = str_replace( '{date}', $post_date, $message );
        $message = str_replace( '{category}', $category_str, $message );
        $message = str_replace( '{post_id}', $post_id, $message );
        
        // 添加更新标记
        if ( $is_update ) {
            $message = '【文章更新】' . $message;
        }
        
        // 发送请求
        $success = $this->send_dingtalk_message( $message );
        
        // 标记为已推送
        if ( $success ) {
            $this->mark_post_as_sent( $post_id, $message );
        }
        
        return $success;
    }
    
    /**
     * 推送新用户到钉钉
     */
    private function push_new_user_to_dingtalk( $user_id ) {
        if ( empty( $this->settings['webhook_url'] ) ) {
            return false;
        }
        
        // 获取用户信息
        $user = get_user_by( 'id', $user_id );
        $username = $user->user_login;
        $email = $user->user_email;
        $register_time = date( 'Y-m-d H:i:s', strtotime( $user->user_registered ) );
        
        // 替换模板变量
        $template = $this->settings['user_template'];
        $message = str_replace( '{username}', $username, $template );
        $message = str_replace( '{email}', $email, $message );
        $message = str_replace( '{register_time}', $register_time, $message );
        $message = str_replace( '{user_id}', $user_id, $message );
        
        // 发送请求
        return $this->send_dingtalk_message( $message );
    }
    
    /**
     * 发送钉钉消息
     */
    public function send_dingtalk_message( $content, $settings = null ) {
        if ( is_null( $settings ) ) {
            $settings = $this->settings;
        }
        
        $webhook_url = $settings['webhook_url'];
        $message_type = $settings['message_type'];
        
        // 处理换行符，确保在钉钉消息中正确显示
        // 1. 替换转义的换行符 \n 为实际换行符
        $content = str_replace( '\n', "\n", $content );
        // 2. 确保直接输入的换行符能正确显示
        $content = str_replace( "\r\n", "\n", $content );
        $content = str_replace( "\r", "\n", $content );
        
        // 构建消息数据
        $data = array(
            'msgtype' => $message_type
        );
        
        // 根据消息类型构建不同的消息格式
        switch ( $message_type ) {
            case 'text':
                $data['text'] = array(
                    'content' => $content
                );
                break;
                
            case 'link':
                // 解析链接内容
                preg_match( '/链接：(https?:\\/\\/[^\\s]+)/', $content, $link_matches );
                $link = isset( $link_matches[1] ) ? $link_matches[1] : '';
                
                preg_match( '/标题：([^\\n]+)/', $content, $title_matches );
                $title = isset( $title_matches[1] ) ? $title_matches[1] : '';
                
                $data['link'] = array(
                    'text' => $content,
                    'title' => $title,
                    'picUrl' => '',
                    'messageUrl' => $link
                );
                break;
                
            case 'markdown':
                $data['markdown'] = array(
                    'title' => __( '新通知', 'ding-pusher' ),
                    'text' => $content
                );
                break;
        }
        
        // 添加安全验证
        $this->add_security_validation( $data, $webhook_url, $settings );
        
        // 发送请求
        $retry_count = isset( $settings['retry_count'] ) ? $settings['retry_count'] : 3;
        $retry_interval = isset( $settings['retry_interval'] ) ? $settings['retry_interval'] : 10;
        
        for ( $i = 0; $i < $retry_count; $i++ ) {
            $response = wp_remote_post( $webhook_url, array(
                'body' => json_encode( $data ),
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ) );
            
            // 检查响应
            if ( is_wp_error( $response ) ) {
                error_log( 'Ding Pusher Error: ' . $response->get_error_message() );
                sleep( $retry_interval * pow( 2, $i ) ); // 指数退避
                continue;
            }
            
            $body = wp_remote_retrieve_body( $response );
            $result = json_decode( $body, true );
            
            if ( isset( $result['errcode'] ) && $result['errcode'] == 0 ) {
                return true;
            }
            
            error_log( 'Ding Pusher Error: ' . $result['errmsg'] );
            sleep( $retry_interval * pow( 2, $i ) ); // 指数退避
        }
        
        return false;
    }
    
    /**
     * 添加安全验证
     */
    private function add_security_validation( &$data, &$webhook_url, $settings ) {
        $security_type = $settings['security_type'];
        
        switch ( $security_type ) {
            case 'secret':
                // 生成签名
                $timestamp = time() * 1000;
                $secret = $settings['security_secret'];
                $string_to_sign = $timestamp . "\n" . $secret;
                $sign = base64_encode( hash_hmac( 'sha256', $string_to_sign, $secret, true ) );
                $sign = urlencode( $sign );
                
                // 更新Webhook URL（直接修改引用参数）
                if ( strpos( $webhook_url, '?' ) !== false ) {
                    $webhook_url .= '&timestamp=' . $timestamp . '&sign=' . $sign;
                } else {
                    $webhook_url .= '?timestamp=' . $timestamp . '&sign=' . $sign;
                }
                break;
                
            case 'keyword':
                // 关键词验证由钉钉服务器处理
                break;
                
            case 'ip_whitelist':
                // IP白名单由钉钉服务器处理
                break;
        }
    }
    
    /**
     * 检查文章是否已推送
     */
    private function is_post_sent( $post_id ) {
        // 检查元数据
        $sent = get_post_meta( $post_id, DTPWP_SENT_META_KEY, true );
        if ( $sent ) {
            return true;
        }
        
        // 检查标题+时间双重去重
        $post = get_post( $post_id );
        $title_hash = md5( $post->post_title . $post->post_date );
        $sent_hashes = get_option( 'dtpwp_sent_title_hashes', array() );
        
        return in_array( $title_hash, $sent_hashes );
    }
    
    /**
     * 标记文章为已推送
     */
    private function mark_post_as_sent( $post_id, $message = '' ) {
        // 更新元数据
        update_post_meta( $post_id, DTPWP_SENT_META_KEY, 1 );
        update_post_meta( $post_id, '_dtpwp_sent_time', current_time( 'mysql' ) );
        
        // 存储发送的消息内容
        if ( ! empty( $message ) ) {
            update_post_meta( $post_id, '_dtpwp_sent_message', $message );
        }
        
        // 更新标题+时间去重哈希
        $post = get_post( $post_id );
        $title_hash = md5( $post->post_title . $post->post_date );
        $sent_hashes = get_option( 'dtpwp_sent_title_hashes', array() );
        $sent_hashes[] = $title_hash;
        update_option( 'dtpwp_sent_title_hashes', array_unique( $sent_hashes ) );
    }
    
    /**
     * 清理过期去重记录
     */
    private function cleanup_deduplicate_records() {
        $deduplicate_days = isset( $this->settings['deduplicate_days'] ) ? $this->settings['deduplicate_days'] : 30;
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$deduplicate_days} days" ) );
        
        // 清理元数据记录
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "
            DELETE FROM $wpdb->postmeta 
            WHERE meta_key = %s 
            AND post_id IN ( 
                SELECT ID FROM $wpdb->posts 
                WHERE post_date < %s
            )
        ", DTPWP_SENT_META_KEY, $cutoff_date ) );
        
        // 清理标题哈希记录（保留最近30天）
        $sent_hashes = get_option( 'dtpwp_sent_title_hashes', array() );
        if ( count( $sent_hashes ) > 1000 ) {
            // 只保留最近1000条
            $sent_hashes = array_slice( $sent_hashes, -1000 );
            update_option( 'dtpwp_sent_title_hashes', $sent_hashes );
        }
    }
}
