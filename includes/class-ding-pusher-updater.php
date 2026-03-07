<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ding_Pusher_Updater' ) ) {
	class Ding_Pusher_Updater {
		private static $instance = null;
		private $cache_key = 'dtpwp_remote_update_payload';

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
			add_filter( 'auto_update_plugin', array( $this, 'auto_update' ), 10, 2 );
			add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
		}

		public function inject( $transient ) {
			if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
				return $transient;
			}
			$remote = $this->remote();
			if ( empty( $remote['version'] ) || empty( $remote['download_url'] ) || version_compare( $remote['version'], DTPWP_VERSION, '<=' ) ) {
				return $transient;
			}
			$item = (object) array(
				'slug'         => dtpwp_slug(),
				'plugin'       => DTPWP_PLUGIN_BASENAME,
				'new_version'  => $remote['version'],
				'url'          => ! empty( $remote['homepage'] ) ? $remote['homepage'] : 'https://github.com/Lexo0522/Ding-Pusher',
				'package'      => $remote['download_url'],
				'tested'       => ! empty( $remote['tested'] ) ? $remote['tested'] : '',
				'requires'     => ! empty( $remote['requires'] ) ? $remote['requires'] : '',
				'requires_php' => ! empty( $remote['requires_php'] ) ? $remote['requires_php'] : '',
				'banners'      => ! empty( $remote['banners'] ) && is_array( $remote['banners'] ) ? $remote['banners'] : array(),
			);
			$transient->response[ DTPWP_PLUGIN_BASENAME ] = $item;
			return $transient;
		}

		public function plugin_information( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
				return $result;
			}
			$slug = (string) $args->slug;
			if ( $slug !== dtpwp_slug() && $slug !== basename( DTPWP_PLUGIN_BASENAME, '.php' ) ) {
				return $result;
			}
			$remote = $this->remote();
			if ( empty( $remote['version'] ) ) {
				return $result;
			}
			return (object) array(
				'name'           => 'Ding Pusher',
				'slug'           => dtpwp_slug(),
				'version'        => $remote['version'],
				'author'         => ! empty( $remote['author'] ) ? $remote['author'] : 'Kate522',
				'author_profile' => ! empty( $remote['author_profile'] ) ? $remote['author_profile'] : 'https://github.com/Lexo0522',
				'homepage'       => ! empty( $remote['homepage'] ) ? $remote['homepage'] : 'https://github.com/Lexo0522/Ding-Pusher',
				'requires'       => ! empty( $remote['requires'] ) ? $remote['requires'] : '',
				'tested'         => ! empty( $remote['tested'] ) ? $remote['tested'] : '',
				'requires_php'   => ! empty( $remote['requires_php'] ) ? $remote['requires_php'] : '',
				'download_link'  => ! empty( $remote['download_url'] ) ? $remote['download_url'] : '',
				'sections'       => array(
					'description'  => ! empty( $remote['description'] ) ? $remote['description'] : '',
					'installation' => ! empty( $remote['installation'] ) ? nl2br( $remote['installation'] ) : '',
					'changelog'    => ! empty( $remote['changelog'] ) ? nl2br( $remote['changelog'] ) : '',
				),
				'banners'        => ! empty( $remote['banners'] ) && is_array( $remote['banners'] ) ? $remote['banners'] : array(),
			);
		}

		public function auto_update( $update, $item ) {
			if ( empty( $item->plugin ) || DTPWP_PLUGIN_BASENAME !== $item->plugin ) {
				return $update;
			}
			return ! empty( dtpwp_settings()['enable_auto_update'] );
		}

		public function clear_cache( $upgrader, $options ) {
			if ( ! empty( $options['action'] ) && 'update' === $options['action'] && ! empty( $options['type'] ) && 'plugin' === $options['type'] && ! empty( $options['plugins'] ) && in_array( DTPWP_PLUGIN_BASENAME, (array) $options['plugins'], true ) ) {
				delete_site_transient( $this->cache_key );
			}
		}

		private function remote() {
			$cached = get_site_transient( $this->cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
			$response = wp_remote_get( DTPWP_UPDATE_URL, array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( is_wp_error( $response ) ) {
				return array();
			}
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return array();
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['version'] ) ) {
				return array();
			}
			set_site_transient( $this->cache_key, $data, 6 * HOUR_IN_SECONDS );
			return $data;
		}
	}
}
