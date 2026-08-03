<?php
/**
 * Shortcode: [paged_download]
 *
 * Adds a "View as PDF" link on the front end of any post or page.
 * When clicked, it opens the paged preview in a new tab where the
 * visitor can print/save as PDF.
 *
 * Usage:
 *   [paged_download]
 *   [paged_download text="Download PDF version"]
 *   [paged_download text="Print version" class="my-button"]
 */
namespace PagedWPM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode {

	public function __construct() {
		add_shortcode( 'paged_download', [ $this, 'render' ] );

		// Also register as a Gutenberg block (simple server-side rendered)
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Render the shortcode.
	 */
	public function render( $atts = [] ) {
		$atts = shortcode_atts( [
			'text'  => __( '📄 View as PDF', 'paged-wp-modern' ),
			'class' => 'pagedwpm-download-link',
			'style' => '', // Allow inline styles
		], $atts, 'paged_download' );

		// Don't render inside the paged preview itself
		if ( Preview::is_paged_preview() ) {
			return '';
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$url = Preview::get_preview_url( $post_id );
		if ( ! $url ) {
			return '';
		}

		$style = '';
		if ( empty( $atts['style'] ) ) {
			// Default styling if none provided
			$style = 'display:inline-block; padding:8px 16px; background:#2563eb; color:#fff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;';
		} else {
			$style = esc_attr( $atts['style'] );
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="%s" style="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $atts['class'] ),
			$style,
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Register a simple server-rendered Gutenberg block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type( 'pagedwpm/download-button', [
			'render_callback' => [ $this, 'render_block' ],
			'attributes'      => [
				'text' => [
					'type'    => 'string',
					'default' => __( '📄 View as PDF', 'paged-wp-modern' ),
				],
			],
		] );
	}

	/**
	 * Block render callback.
	 */
	public function render_block( $attributes ) {
		return $this->render( [
			'text' => $attributes['text'] ?? __( '📄 View as PDF', 'paged-wp-modern' ),
		] );
	}
}
