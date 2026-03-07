<?php
/**
 * Plugin Name: Ding Pusher
 * Plugin URI: https://github.com/Lexo0522/Ding-Pusher
 * Description: 自动检测WordPress新文章并通过钉钉机器人推送，包含去重机制和新用户提示功能
 * Version: 1.0.0
 * Author: Kate522
 * Author URI: https://www.rutua.cn/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ding-pusher
 * Domain Path: /languages
 */

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 定义插件常量
define( 'DTPWP_VERSION', '1.0.0' );
define( 'DTPWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DTPWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DTPWP_OPTION_NAME', 'dtpwp_dingtalk_settings' );
define( 'DTPWP_LAST_POST_KEY', 'dtpwp_last_post_id' );
define( 'DTPWP_LAST_USER_KEY', 'dtpwp_last_user_id' );
define( 'DTPWP_SENT_META_KEY', '_dtpwp_dingtalk_sent' );

// 检查PHP版本
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', 'dtpwp_php_version_notice' );
    return;
}

/**
 * PHP版本过低提示
 */
function dtpwp_php_version_notice() {
    echo '<div class="notice notice-error">';
    echo '<p>' . esc_html__( 'Ding Pusher 插件需要 PHP 7.4 或更高版本，请升级您的 PHP 版本。', 'ding-pusher' ) . '</p>';
    echo '</div>';
}

// 加载核心类
if ( ! class_exists( 'Ding_Pusher_Core' ) ) {
    require_once DTPWP_PLUGIN_DIR . 'includes/class-ding-pusher-core.php';
}

// 加载管理类
if ( is_admin() ) {
    require_once DTPWP_PLUGIN_DIR . 'admin/class-ding-pusher-admin.php';
}

// 初始化插件
function dtpwp_init() {
    // 加载文本域
    load_plugin_textdomain( 'ding-pusher', false, basename( dirname( __FILE__ ) ) . '/languages' );
    
    // 实例化核心类
    Ding_Pusher_Core::get_instance();
    
    // 如果是后台，实例化管理类
    if ( is_admin() ) {
        Ding_Pusher_Admin::get_instance();
    }
}
// 激活钩子
register_activation_hook( __FILE__, array( 'Ding_Pusher_Core', 'activate' ) );

// 停用钩子
register_deactivation_hook( __FILE__, array( 'Ding_Pusher_Core', 'deactivate' ) );

// 初始化插件钩子
add_action( 'plugins_loaded', 'dtpwp_init' );

// WordPress原生自动更新功能
/**
 * 为插件添加自动更新支持
 */
function dtpwp_plugin_updater( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    
    // 检查是否启用自动更新
    $settings = get_option( DTPWP_OPTION_NAME );
    if ( ! isset( $settings['enable_auto_update'] ) || ! $settings['enable_auto_update'] ) {
        return $transient;
    }
    
    // 检查更新的远程JSON文件URL
    $update_url = 'https://raw.githubusercontent.com/Lexo0522/Ding-Pusher/master/update.json';
    
    // 获取当前插件版本
    $current_version = DTPWP_VERSION;
    
    // 获取插件信息
    $plugin_slug = plugin_basename( __FILE__ );
    
    // 尝试获取更新信息
    $response = wp_remote_get( $update_url, array( 'timeout' => 10, 'sslverify' => true ) );
    
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
        return $transient;
    }
    
    $update_data = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( ! is_array( $update_data ) ) {
        return $transient;
    }
    
    // 检查版本号是否更新
    if ( version_compare( $update_data['version'], $current_version, '>' ) ) {
        $transient->response[$plugin_slug] = array(
            'slug' => dirname( plugin_basename( __FILE__ ) ),
            'new_version' => $update_data['version'],
            'url' => $update_data['homepage'],
            'package' => $update_data['download_url']
        );
    }
    
    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'dtpwp_plugin_updater' );

/**
 * 插件信息API支持
 */
function dtpwp_plugin_info( $res, $action, $args ) {
    if ( $action !== 'plugin_information' ) {
        return $res;
    }
    
    $plugin_slug = dirname( plugin_basename( __FILE__ ) );
    
    if ( $args->slug !== $plugin_slug ) {
        return $res;
    }
    
    // 远程JSON文件URL
    $update_url = 'https://raw.githubusercontent.com/Lexo0522/Ding-Pusher/master/update.json';
    
    // 获取更新信息
    $response = wp_remote_get( $update_url, array( 'timeout' => 10, 'sslverify' => true ) );
    
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
        return $res;
    }
    
    $update_data = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( ! is_array( $update_data ) ) {
        return $res;
    }
    
    // 构建插件信息
    $res = new stdClass();
    $res->name = $update_data['name'];
    $res->slug = $plugin_slug;
    $res->version = $update_data['version'];
    $res->author = $update_data['author'];
    $res->author_profile = $update_data['author_profile'];
    $res->requires = $update_data['requires'];
    $res->tested = $update_data['tested'];
    $res->requires_php = $update_data['requires_php'];
    $res->download_link = $update_data['download_url'];
    $res->trunk = $update_data['download_url'];
    $res->homepage = $update_data['homepage'];
    $res->sections = array(
        'description' => $update_data['description'],
        'installation' => $update_data['installation'],
        'changelog' => $update_data['changelog']
    );
    
    if ( ! empty( $update_data['banners'] ) ) {
        $res->banners = $update_data['banners'];
    }
    
    return $res;
}
add_filter( 'plugins_api', 'dtpwp_plugin_info', 20, 3 );

/**
 * 处理插件更新包
 */
function dtpwp_upgrader_source_selection( $source, $remote_source, $upgrader ) {
    // 检查是否启用自动更新
    $settings = get_option( DTPWP_OPTION_NAME );
    if ( ! isset( $settings['enable_auto_update'] ) || ! $settings['enable_auto_update'] ) {
        return $source;
    }
    
    $plugin_slug = dirname( plugin_basename( __FILE__ ) );
    $upgrader->skin->feedback( __( '正在更新 Ding Pusher 插件...', 'ding-pusher' ) );
    return $source;
}
add_filter( 'upgrader_source_selection', 'dtpwp_upgrader_source_selection', 10, 3 );

/**
 * 支持WordPress插件列表中的自动更新开关
 */
function dtpwp_auto_update_plugin( $update, $item ) {
    // 只处理当前插件
    if ( $item->slug === dirname( plugin_basename( __FILE__ ) ) ) {
        $settings = get_option( DTPWP_OPTION_NAME );
        return isset( $settings['enable_auto_update'] ) && $settings['enable_auto_update'];
    }
    return $update;
}
add_filter( 'auto_update_plugin', 'dtpwp_auto_update_plugin', 10, 2 );
