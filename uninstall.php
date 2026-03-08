<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data for the current site.
 */
function dtpwp_uninstall_cleanup_site() {
	global $wpdb;

	delete_option( 'dtpwp_dingtalk_settings' );
	delete_option( 'dtpwp_activation_time' );
	delete_option( 'dtpwp_sent_title_hashes' );

	wp_clear_scheduled_hook( 'dtpwp_check_new_content' );
	wp_clear_scheduled_hook( 'dtpwp_retry_post_push' );
	wp_clear_scheduled_hook( 'dtpwp_retry_user_push' );

	$post_meta_keys = array(
		'_dtpwp_sent',
		'_dtpwp_sent_time',
		'_dtpwp_sent_message',
		'_dtpwp_last_sent_modified_gmt',
		'_dtpwp_record_deleted',
	);

	foreach ( $post_meta_keys as $meta_key ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$meta_key
			)
		);
	}

	$user_meta_keys = array(
		'_dtpwp_user_notice_sent',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$meta_key
			)
		);
	}

	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '\\_transient\\_dtpwp\\_%' ESCAPE '\\'
		OR option_name LIKE '\\_transient\\_timeout\\_dtpwp\\_%' ESCAPE '\\'"
	);

	$uploads = wp_upload_dir();
	if ( empty( $uploads['error'] ) ) {
		$export_dir = trailingslashit( $uploads['basedir'] ) . 'dtpwp-exports';
		if ( is_dir( $export_dir ) ) {
			$files = glob( trailingslashit( $export_dir ) . 'ding-pusher-records-*' );
			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						@unlink( $file );
					}
				}
			}
			@rmdir( $export_dir );
		}
	}
}

if ( is_multisite() ) {
	global $wpdb;

	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
	$current_blog_id = get_current_blog_id();

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		dtpwp_uninstall_cleanup_site();
	}

	switch_to_blog( $current_blog_id );

	if ( ! empty( $wpdb->sitemeta ) ) {
		$wpdb->query(
			"DELETE FROM {$wpdb->sitemeta}
			WHERE meta_key LIKE '\\_site\\_transient\\_dtpwp\\_%' ESCAPE '\\'
			OR meta_key LIKE '\\_site\\_transient\\_timeout\\_dtpwp\\_%' ESCAPE '\\'"
		);
	}
} else {
	dtpwp_uninstall_cleanup_site();
}

