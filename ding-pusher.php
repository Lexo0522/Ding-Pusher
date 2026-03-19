<?php
/**
 * Plugin Name: Kate522 Notifier for DingTalk
 * Plugin URI: https://github.com/Lexo0522/Ding-Pusher
 * Description: Automatically push WordPress new posts and new user registration messages to DingTalk bots.
 * Version: 1.0.4
 * Author: Kate522
 * Author URI: https://github.com/Lexo0522
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DTPWP_VERSION', '1.0.4' );
define( 'DTPWP_OPTION_NAME', 'dtpwp_dingtalk_settings' );
define( 'DTPWP_SENT_META_KEY', '_dtpwp_sent' );
define( 'DTPWP_SENT_TIME_META_KEY', '_dtpwp_sent_time' );
define( 'DTPWP_SENT_MESSAGE_META_KEY', '_dtpwp_sent_message' );
define( 'DTPWP_SENT_MODIFIED_META_KEY', '_dtpwp_last_sent_modified_gmt' );
define( 'DTPWP_USER_SENT_META_KEY', '_dtpwp_user_notice_sent' );
define( 'DTPWP_ACTIVATION_TIME_OPTION', 'dtpwp_activation_time' );
define( 'DTPWP_TITLE_HASH_OPTION', 'dtpwp_sent_title_hashes' );
define( 'DTPWP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DTPWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DTPWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function dtpwp_defaults() {
	return array(
		'webhook_url' => '',
		'security_type' => 'keyword',
		'security_keyword' => array(),
		'security_secret' => '',
		'security_ip_whitelist' => array(),
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
}

function dtpwp_settings() {
	$value = get_option( DTPWP_OPTION_NAME, array() );
	return wp_parse_args( is_array( $value ) ? $value : array(), dtpwp_defaults() );
}

function dtpwp_slug() {
	$dir = dirname( DTPWP_PLUGIN_BASENAME );
	return '.' !== $dir ? $dir : basename( DTPWP_PLUGIN_BASENAME, '.php' );
}

function dtpwp_add_schedules( $schedules ) {
	for ( $i = 1; $i <= 60; $i++ ) {
		$key = $i . 'm';
		if ( isset( $schedules[ $key ] ) ) {
			continue;
		}
		$schedules[ $key ] = array(
			'interval' => $i * MINUTE_IN_SECONDS,
			'display'  => sprintf( 'Every %d minutes', $i ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'dtpwp_add_schedules' );

function dtpwp_schedule_name( $minutes ) {
	$minutes   = max( 1, min( 60, absint( $minutes ) ) );
	$schedule  = $minutes . 'm';
	$schedules = wp_get_schedules();
	if ( isset( $schedules[ $schedule ] ) ) {
		return $schedule;
	}
	return isset( $schedules['hourly'] ) ? 'hourly' : '';
}

function dtpwp_unschedule_all( $hook ) {
	if ( ! function_exists( '_get_cron_array' ) ) {
		wp_clear_scheduled_hook( $hook );
		return;
	}
	$crons = _get_cron_array();
	if ( empty( $crons ) || ! is_array( $crons ) ) {
		return;
	}
	foreach ( $crons as $timestamp => $cron ) {
		if ( empty( $cron[ $hook ] ) || ! is_array( $cron[ $hook ] ) ) {
			continue;
		}
		foreach ( $cron[ $hook ] as $event ) {
			wp_unschedule_event( $timestamp, $hook, isset( $event['args'] ) ? $event['args'] : array() );
		}
	}
}

function dtpwp_schedule_main_event( $force = false ) {
	if ( $force ) {
		dtpwp_unschedule_all( 'dtpwp_check_new_content' );
	}
	if ( wp_next_scheduled( 'dtpwp_check_new_content' ) ) {
		return;
	}
	$schedule = dtpwp_schedule_name( dtpwp_settings()['push_interval'] );
	if ( $schedule ) {
		wp_schedule_event( time(), $schedule, 'dtpwp_check_new_content' );
	}
}

function dtpwp_activate() {
	update_option( DTPWP_OPTION_NAME, wp_parse_args( dtpwp_settings(), dtpwp_defaults() ) );
	update_option( DTPWP_ACTIVATION_TIME_OPTION, time() );
	dtpwp_schedule_main_event( true );
}

function dtpwp_deactivate() {
	dtpwp_unschedule_all( 'dtpwp_check_new_content' );
	dtpwp_unschedule_all( 'dtpwp_retry_post_push' );
	dtpwp_unschedule_all( 'dtpwp_retry_user_push' );
}

register_activation_hook( __FILE__, 'dtpwp_activate' );
register_deactivation_hook( __FILE__, 'dtpwp_deactivate' );

$dtpwp_core_file = DTPWP_PLUGIN_DIR . 'includes/class-ding-pusher-core.php';
if ( file_exists( $dtpwp_core_file ) ) {
	require_once $dtpwp_core_file;
}

$dtpwp_admin_file = DTPWP_PLUGIN_DIR . 'admin/class-ding-pusher-admin.php';
if ( file_exists( $dtpwp_admin_file ) ) {
	require_once $dtpwp_admin_file;
}

function dtpwp_bootstrap() {
	if ( class_exists( 'Ding_Pusher_Core' ) ) {
		Ding_Pusher_Core::get_instance();
	}
	if ( is_admin() && class_exists( 'Ding_Pusher_Admin' ) ) {
		Ding_Pusher_Admin::get_instance();
	}
	dtpwp_schedule_main_event();
}
add_action( 'plugins_loaded', 'dtpwp_bootstrap' );
