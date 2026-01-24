<?php
/**
 * Ding Pusher 卸载文件
 */

// 防止直接访问
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 删除选项
delete_option( 'dtpwp_dingtalk_settings' );
delete_option( 'dtpwp_last_post_id' );
delete_option( 'dtpwp_last_user_id' );
delete_option( 'dtpwp_sent_title_hashes' );

// 删除所有推送记录
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '_dtpwp_dingtalk_sent' OR meta_key = '_dtpwp_sent_time'" );

// 清理定时任务
wp_clear_scheduled_hook( 'dtpwp_check_new_content' );
