<?php
/**
 * Updates the plugin from tagged GitHub releases.
 */
namespace PagedWPM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {
	private const REPOSITORY = 'veale/paged-wp-modern';
	private const ASSET_NAME = 'paged-wp-modern.zip';
	private $plugin_basename;

	public function __construct( $plugin_file ) {
		$this->plugin_basename = plugin_basename( $plugin_file );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_information' ], 20, 3 );
		add_filter( 'auto_update_plugin', [ $this, 'maybe_auto_update' ], 10, 2 );
	}

	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release || version_compare( PAGEDWPM_VERSION, $release['version'], '>=' ) ) {
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) [
			'id'           => 'github.com/' . self::REPOSITORY,
			'slug'         => 'paged-wp-modern',
			'plugin'       => $this->plugin_basename,
			'new_version'  => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'icons'        => [],
			'banners'      => [],
			'banners_rtl'  => [],
			'tested'       => '',
			'requires_php' => '7.4',
		];

		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'paged-wp-modern' !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Paged WP Modern',
			'slug'          => 'paged-wp-modern',
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/veale">Michael Veale</a>',
			'homepage'      => 'https://github.com/' . self::REPOSITORY,
			'download_link' => $release['package'],
			'last_updated'  => $release['published_at'],
			'sections'      => [
				'description' => __( 'Professional paged academic articles and PDFs with resilient images and page-bottom footnotes.', 'paged-wp-modern' ),
				'changelog'   => wp_kses_post( nl2br( esc_html( $release['notes'] ) ) ),
			],
		];
	}

	public function maybe_auto_update( $update, $item ) {
		if ( ! is_object( $item ) || empty( $item->plugin ) || $this->plugin_basename !== $item->plugin ) {
			return $update;
		}
		return '1' === get_option( 'pagedwpm_auto_updates', '0' ) ? true : $update;
	}

	private function get_latest_release() {
		$cache_key = 'pagedwpm_github_release';
		$cached    = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Paged-WP-Modern/' . PAGEDWPM_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( $cache_key, [], HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || empty( $data['assets'] ) ) {
			return null;
		}

		$package = '';
		foreach ( $data['assets'] as $asset ) {
			if ( self::ASSET_NAME === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( $asset['browser_download_url'] ?? '' );
				break;
			}
		}
		if ( ! $package ) {
			return null;
		}

		$release = [
			'version'      => ltrim( sanitize_text_field( $data['tag_name'] ), 'vV' ),
			'html_url'     => esc_url_raw( $data['html_url'] ?? '' ),
			'package'      => $package,
			'published_at' => sanitize_text_field( $data['published_at'] ?? '' ),
			'notes'        => sanitize_textarea_field( $data['body'] ?? '' ),
		];
		set_site_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}
}
