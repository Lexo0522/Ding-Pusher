<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ding_Pusher_Core' ) ) {
	class Ding_Pusher_Core {
		private static $instance = null;
		private $last_error = '';

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
			add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
			add_action( 'user_register', array( $this, 'on_user_register' ) );
			add_action( 'dtpwp_check_new_content', array( $this, 'check_new_content' ) );
			add_action( 'dtpwp_retry_post_push', array( $this, 'retry_post' ), 10, 3 );
			add_action( 'dtpwp_retry_user_push', array( $this, 'retry_user' ), 10, 2 );
		}

		public function on_transition( $new_status, $old_status, $post ) {
			if ( $post instanceof WP_Post && 'publish' === $new_status && 'publish' !== $old_status ) {
				$this->send_post_notification( $post->ID, 'publish', 1 );
			}
		}

		public function on_post_updated( $post_id, $after, $before ) {
			if ( ! $after instanceof WP_Post || ! $before instanceof WP_Post ) {
				return;
			}
			if ( 'publish' !== $after->post_status || 'publish' !== $before->post_status ) {
				return;
			}
			if ( $after->post_modified_gmt === $before->post_modified_gmt ) {
				return;
			}
			$context = get_post_meta( $post_id, DTPWP_SENT_META_KEY, true ) ? 'update' : 'publish';
			$this->send_post_notification( $post_id, $context, 1 );
		}

		public function on_user_register( $user_id ) {
			$this->send_user_notification( $user_id, 1 );
		}

		public function retry_post( $post_id, $attempt = 1, $context = 'publish' ) {
			$this->send_post_notification( $post_id, $context, max( 1, absint( $attempt ) ) );
		}

		public function retry_user( $user_id, $attempt = 1 ) {
			$this->send_user_notification( $user_id, max( 1, absint( $attempt ) ) );
		}

		public function check_new_content() {
			$settings = $this->settings();
			if ( empty( $settings['enable_new_post'] ) ) {
				return;
			}
			$args = array(
				'post_type'           => $this->allowed_post_types( $settings ),
				'post_status'         => 'publish',
				'posts_per_page'      => 20,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'meta_query'          => array(
					array(
						'key'     => DTPWP_SENT_META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			);
			$activation_time = (int) get_option( DTPWP_ACTIVATION_TIME_OPTION, 0 );
			if ( $activation_time > 0 ) {
				$args['date_query'] = array(
					array(
						'column' => 'post_date_gmt',
						'after'  => gmdate( 'Y-m-d H:i:s', $activation_time ),
					),
				);
			}
			$query = new WP_Query( $args );
			foreach ( (array) $query->posts as $post_id ) {
				$this->send_post_notification( $post_id, 'cron', 1 );
			}
		}

		public function send_dingtalk_message( $message, $override = array() ) {
			$settings = $this->settings( $override );
			if ( ! $this->has_webhook( $settings ) ) {
				return false;
			}
			$title = ! empty( $settings['custom_message'] ) ? $settings['custom_message'] : '站点通知';
			$data  = $this->build_payload( $title, (string) $message, home_url( '/' ), $settings );
			return ! empty( $data['payload'] ) ? $this->send_payload( $data['payload'], $settings ) : false;
		}

		private function send_post_notification( $post_id, $context, $attempt ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || ! $this->is_supported_post( $post ) ) {
				return false;
			}
			$settings = $this->settings();
			if ( ! $this->has_webhook( $settings ) || ! $this->should_send_post( $post, $context, $settings ) ) {
				return false;
			}
			if ( ! $this->lock( 'post_' . $post_id ) ) {
				return false;
			}
			$data = $this->post_message_data( $post, $settings, $context );
			$ok   = ! empty( $data['payload'] ) && $this->send_payload( $data['payload'], $settings );
			if ( $ok ) {
				update_post_meta( $post->ID, DTPWP_SENT_META_KEY, 1 );
				update_post_meta( $post->ID, DTPWP_SENT_TIME_META_KEY, current_time( 'mysql' ) );
				update_post_meta( $post->ID, DTPWP_SENT_MESSAGE_META_KEY, $data['rendered_message'] );
				update_post_meta( $post->ID, DTPWP_SENT_MODIFIED_META_KEY, $post->post_modified_gmt );
				$this->record_hash( $post, $settings );
			} else {
				$this->schedule_post_retry( $post_id, $attempt, $context, $settings );
			}
			$this->unlock( 'post_' . $post_id );
			return $ok;
		}

		private function send_user_notification( $user_id, $attempt ) {
			$settings = $this->settings();
			if ( empty( $settings['enable_new_user'] ) || ! $this->has_webhook( $settings ) || get_user_meta( $user_id, DTPWP_USER_SENT_META_KEY, true ) ) {
				return false;
			}
			$user = get_userdata( $user_id );
			if ( ! $user instanceof WP_User || ! $this->lock( 'user_' . $user_id ) ) {
				return false;
			}
			$data = $this->user_message_data( $user, $settings );
			$ok   = ! empty( $data['payload'] ) && $this->send_payload( $data['payload'], $settings );
			if ( $ok ) {
				update_user_meta( $user_id, DTPWP_USER_SENT_META_KEY, current_time( 'mysql' ) );
			} else {
				$this->schedule_user_retry( $user_id, $attempt, $settings );
			}
			$this->unlock( 'user_' . $user_id );
			return $ok;
		}

		private function should_send_post( WP_Post $post, $context, $settings ) {
			$is_update = 'update' === $context;
			if ( $is_update && empty( $settings['enable_post_update'] ) ) {
				return false;
			}
			if ( ! $is_update && empty( $settings['enable_new_post'] ) ) {
				return false;
			}
			if ( $is_update ) {
				return (string) get_post_meta( $post->ID, DTPWP_SENT_MODIFIED_META_KEY, true ) !== (string) $post->post_modified_gmt;
			}
			if ( get_post_meta( $post->ID, DTPWP_SENT_META_KEY, true ) ) {
				return false;
			}
			return ! $this->is_duplicate_hash( $post, $settings );
		}

		private function is_supported_post( WP_Post $post ) {
			if ( 'publish' !== $post->post_status || wp_is_post_revision( $post ) || post_password_required( $post ) ) {
				return false;
			}
			return in_array( $post->post_type, $this->allowed_post_types( $this->settings() ), true );
		}

		private function allowed_post_types( $settings ) {
			$types = array( 'post' );
			foreach ( ! empty( $settings['enable_custom_post_type'] ) && is_array( $settings['enable_custom_post_type'] ) ? $settings['enable_custom_post_type'] : array() as $type ) {
				$type = sanitize_key( $type );
				if ( $type && post_type_exists( $type ) ) {
					$types[] = $type;
				}
			}
			return array_values( array_unique( $types ) );
		}

		private function post_message_data( WP_Post $post, $settings, $context ) {
			$title    = html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
			$link     = get_permalink( $post );
			$excerpt  = has_excerpt( $post ) ? wp_strip_all_tags( $post->post_excerpt ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '...' );
			$template = ! empty( $settings['post_template'] ) ? $settings['post_template'] : dtpwp_defaults()['post_template'];
			$body     = strtr(
				$template,
				array(
					'{title}'        => $title,
					'{author}'       => get_the_author_meta( 'display_name', (int) $post->post_author ),
					'{link}'         => $link,
					'{excerpt}'      => $excerpt,
					'{publish_time}' => get_date_from_gmt( $post->post_date_gmt, 'Y-m-d H:i:s' ),
					'{post_type}'    => $post->post_type,
				)
			);
			if ( 'update' === $context ) {
				$body = "【文章更新】\n" . $body;
			}
			$subject = ! empty( $settings['custom_message'] ) ? $settings['custom_message'] : ( 'update' === $context ? '文章已更新：' : '新文章：' ) . $title;
			return $this->build_payload( $subject, $body, $link, $settings, get_the_post_thumbnail_url( $post, 'medium' ) );
		}

		private function user_message_data( WP_User $user, $settings ) {
			$template = ! empty( $settings['user_template'] ) ? $settings['user_template'] : dtpwp_defaults()['user_template'];
			$body     = strtr(
				$template,
				array(
					'{username}'      => $user->user_login,
					'{email}'         => $user->user_email,
					'{display_name}'  => $user->display_name,
					'{role}'          => ! empty( $user->roles ) ? reset( $user->roles ) : '',
					'{register_time}' => ! empty( $user->user_registered ) ? mysql2date( 'Y-m-d H:i:s', $user->user_registered, false ) : current_time( 'mysql' ),
				)
			);
			$subject = ! empty( $settings['custom_message'] ) ? $settings['custom_message'] : '新用户注册：' . $user->user_login;
			return $this->build_payload( $subject, $body, admin_url( 'user-edit.php?user_id=' . $user->ID ), $settings );
		}

		private function build_payload( $title, $body, $url, $settings, $pic_url = '' ) {
			$type    = sanitize_key( isset( $settings['message_type'] ) ? $settings['message_type'] : 'text' );
			$type    = in_array( $type, array( 'text', 'link', 'markdown' ), true ) ? $type : 'text';
			$title   = trim( wp_strip_all_tags( (string) $title ) );
			$body    = trim( (string) $body );
			$url     = esc_url_raw( $url );
			$keyword = $this->primary_keyword( $settings );
			if ( $keyword ) {
				$title = trim( $keyword . ' ' . $title );
				$body  = trim( $keyword . "\n" . $body );
			}
			$title = $title ? $title : '站点通知';
			$body  = $body ? $body : $title;
			if ( 'link' === $type ) {
				return array(
					'payload' => array(
						'msgtype' => 'link',
						'link'    => array(
							'title'      => $title,
							'text'       => wp_trim_words( $body, 80, '...' ),
							'messageUrl' => $url ? $url : home_url( '/' ),
							'picUrl'     => esc_url_raw( $pic_url ),
						),
					),
					'rendered_message' => trim( $title . "\n" . $body . ( $url ? "\n" . $url : '' ) ),
				);
			}
			if ( 'markdown' === $type ) {
				$text = '## ' . $title . "\n\n" . str_replace( "\r\n", "\n", $body ) . ( $url ? "\n\n[查看链接](" . $url . ')' : '' );
				return array(
					'payload' => array(
						'msgtype'  => 'markdown',
						'markdown' => array( 'title' => $title, 'text' => $text ),
					),
					'rendered_message' => trim( $text . ( $url ? "\n\n" . $url : '' ) ),
				);
			}
			$text = $title . "\n" . $body . ( $url ? "\n" . $url : '' );
			return array(
				'payload' => array(
					'msgtype' => 'text',
					'text'    => array( 'content' => trim( $text ) ),
				),
				'rendered_message' => trim( $text ),
			);
		}

		private function send_payload( $payload, $settings ) {
			$url = $this->webhook_url( $settings );
			if ( ! $url ) {
				return false;
			}
			$response = wp_remote_post(
				$url,
				array(
					'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'timeout' => 15,
					'body'    => wp_json_encode( $payload ),
				)
			);
			if ( is_wp_error( $response ) ) {
				$this->log_error( 'Transport error: ' . $response->get_error_message() );
				return false;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $code < 200 || $code >= 300 ) {
				$this->log_error( 'Unexpected HTTP status: ' . $code );
				return false;
			}
			if ( is_array( $body ) && isset( $body['errcode'] ) && 0 !== (int) $body['errcode'] ) {
				$this->last_error = ! empty( $body['errmsg'] ) ? $body['errmsg'] : 'Unknown DingTalk error';
				$this->log_error( 'DingTalk rejected the payload: ' . $this->last_error );
				return false;
			}
			$this->last_error = '';
			return true;
		}

		private function webhook_url( $settings ) {
			$url = ! empty( $settings['webhook_url'] ) ? esc_url_raw( $settings['webhook_url'] ) : '';
			if ( ! $url || false === strpos( $url, 'https://oapi.dingtalk.com/robot/send' ) ) {
				return '';
			}
			if ( 'secret' !== sanitize_key( isset( $settings['security_type'] ) ? $settings['security_type'] : 'keyword' ) || empty( $settings['security_secret'] ) ) {
				return $url;
			}
			$timestamp = (string) round( microtime( true ) * 1000 );
			$secret    = (string) $settings['security_secret'];
			$sign      = base64_encode( hash_hmac( 'sha256', $timestamp . "\n" . $secret, $secret, true ) );
			return add_query_arg(
				array(
					'timestamp' => $timestamp,
					'sign'      => $sign,
				),
				$url
			);
		}

		private function has_webhook( $settings ) {
			return ! empty( $settings['webhook_url'] ) && false !== strpos( $settings['webhook_url'], 'https://oapi.dingtalk.com/robot/send' );
		}

		private function primary_keyword( $settings ) {
			foreach ( ! empty( $settings['security_keyword'] ) && is_array( $settings['security_keyword'] ) ? $settings['security_keyword'] : array() as $keyword ) {
				$keyword = trim( sanitize_text_field( $keyword ) );
				if ( '' !== $keyword ) {
					return $keyword;
				}
			}
			return '';
		}

		private function hash_for_post( WP_Post $post ) {
			return md5( strtolower( trim( wp_strip_all_tags( get_the_title( $post ) ) ) ) . '|' . ( ! empty( $post->post_date_gmt ) ? $post->post_date_gmt : $post->post_date ) );
		}

		private function is_duplicate_hash( WP_Post $post, $settings ) {
			$hashes = $this->cleanup_hashes( get_option( DTPWP_TITLE_HASH_OPTION, array() ), $settings );
			$hash   = $this->hash_for_post( $post );
			$entry  = isset( $hashes[ $hash ] ) ? $hashes[ $hash ] : null;
			update_option( DTPWP_TITLE_HASH_OPTION, $hashes, false );
			return ! empty( $entry ) && ( empty( $entry['post_id'] ) || (int) $entry['post_id'] !== (int) $post->ID );
		}

		private function record_hash( WP_Post $post, $settings ) {
			$hashes                     = $this->cleanup_hashes( get_option( DTPWP_TITLE_HASH_OPTION, array() ), $settings );
			$hashes[ $this->hash_for_post( $post ) ] = array( 'post_id' => (int) $post->ID, 'timestamp' => time() );
			update_option( DTPWP_TITLE_HASH_OPTION, $hashes, false );
		}

		private function cleanup_hashes( $hashes, $settings ) {
			$hashes = is_array( $hashes ) ? $hashes : array();
			$expire = time() - max( 1, absint( $settings['deduplicate_days'] ) ) * DAY_IN_SECONDS;
			foreach ( $hashes as $hash => $entry ) {
				if ( empty( $entry['timestamp'] ) || (int) $entry['timestamp'] < $expire ) {
					unset( $hashes[ $hash ] );
				}
			}
			return $hashes;
		}

		private function schedule_post_retry( $post_id, $attempt, $context, $settings ) {
			if ( $attempt >= max( 1, absint( $settings['retry_count'] ) ) ) {
				$this->log_error( 'Post push failed after max retries for post ' . $post_id . '.' );
				return;
			}
			$next  = $attempt + 1;
			$delay = max( 1, absint( $settings['retry_interval'] ) ) * (int) pow( 2, max( 0, $attempt - 1 ) );
			$args  = array( (int) $post_id, (int) $next, (string) $context );
			if ( ! wp_next_scheduled( 'dtpwp_retry_post_push', $args ) ) {
				wp_schedule_single_event( time() + $delay, 'dtpwp_retry_post_push', $args );
			}
		}

		private function schedule_user_retry( $user_id, $attempt, $settings ) {
			if ( $attempt >= max( 1, absint( $settings['retry_count'] ) ) ) {
				$this->log_error( 'User push failed after max retries for user ' . $user_id . '.' );
				return;
			}
			$next  = $attempt + 1;
			$delay = max( 1, absint( $settings['retry_interval'] ) ) * (int) pow( 2, max( 0, $attempt - 1 ) );
			$args  = array( (int) $user_id, (int) $next );
			if ( ! wp_next_scheduled( 'dtpwp_retry_user_push', $args ) ) {
				wp_schedule_single_event( time() + $delay, 'dtpwp_retry_user_push', $args );
			}
		}

		private function settings( $override = array() ) {
			return is_array( $override ) && ! empty( $override ) ? wp_parse_args( $override, dtpwp_settings() ) : dtpwp_settings();
		}

		private function lock( $key ) {
			$key = 'dtpwp_lock_' . md5( $key );
			if ( get_transient( $key ) ) {
				return false;
			}
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		private function unlock( $key ) {
			delete_transient( 'dtpwp_lock_' . md5( $key ) );
		}

		private function log_error( $message ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Ding Pusher] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
