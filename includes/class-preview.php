<?php
/**
 * Handles the paged preview: template override, asset injection, template tag resolution.
 */
namespace PagedWPM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Preview {

	public function __construct() {
		add_filter( 'template_include', [ $this, 'maybe_override_template' ], 99 );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
	}

	public function register_query_var( $vars ) {
		$vars[] = 'pagedwpm';
		return $vars;
	}

	public function maybe_override_template( $template ) {
		if ( ! $this->is_paged_preview() ) {
			return $template;
		}
		return PAGEDWPM_PLUGIN_DIR . 'templates/preview.php';
	}

	public static function is_paged_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return isset( $_GET['pagedwpm'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['pagedwpm'] ) );
	}

	public static function get_preview_url( $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return '';
		}
		return add_query_arg( 'pagedwpm', 'true', $permalink );
	}

	/**
	 * Get the paged media CSS (default + custom).
	 */
	public static function get_paged_css() {
		$css = file_get_contents( PAGEDWPM_PLUGIN_DIR . 'assets/css/paged-default.css' );
		$custom_css = get_option( 'pagedwpm_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			$css .= "\n/* === Custom CSS === */\n" . $custom_css . "\n";
		}
		return $css;
	}

	/**
	 * Get the footnote query selector.
	 */
	public static function get_footnote_selector() {
		$selector = get_option( 'pagedwpm_footnote_selector', '' );
		if ( empty( $selector ) ) {
			$selector = 'a[href*="footnote"], a[href*="endnote"], .footnote-ref';
		}
		return $selector;
	}

	/**
	 * Get the date format to use.
	 */
	public static function get_date_format() {
		$format = get_option( 'pagedwpm_date_format', '' );
		if ( empty( $format ) ) {
			$format = get_option( 'date_format', 'F j, Y' );
		}
		return $format;
	}

	/**
	 * Get CSS selectors for elements to hide in the preview.
	 * Always includes common author box / bio selectors when that option is on.
	 */
	public static function get_hide_selectors() {
		$selectors = [];

		// Author box removal
		if ( get_option( 'pagedwpm_hide_author_box', '1' ) === '1' ) {
			$selectors = array_merge( $selectors, [
				// Common theme author box classes
				'.author-box',
				'.author-bio',
				'.author-info',
				'.post-author-box',
				'.entry-author',
				'.about-author',
				'.author-details',
				'.author-card',
				'.author-profile',
				'.author-description',
				'.author-avatar',
				'.author-meta',
				// Specific themes / plugins
				'.saboxplugin-wrap',          // Simple Author Box
				'.pp-author-boxes-wrapper',   // PublishPress
				'.awp-author-box',
				'.molongui-author-box',
				'.flavor-suspended',          // flavor theme author
				'.about-the-author',
				'.post-author',
				'[class*="author-box"]',
				'[class*="author-bio"]',
				// Jetpack / WordPress.com
				'.sd-sharing',
				'.sharedaddy',
				// Related posts
				'.jp-relatedposts',
				'.related-posts',
				'.yarpp-related',
			]);
		}

		// User-specified selectors
		$custom = get_option( 'pagedwpm_hide_selectors', '' );
		if ( ! empty( $custom ) ) {
			$custom_selectors = array_map( 'trim', explode( ',', $custom ) );
			$selectors = array_merge( $selectors, array_filter( $custom_selectors ) );
		}

		return array_unique( $selectors );
	}

	/**
	 * Resolve template tags in a string against the current post.
	 *
	 * Supported tags:
	 *   {author}         — post author display name
	 *   {date}           — publication date (formatted)
	 *   {modified_date}  — last modified date (formatted)
	 *   {excerpt}        — post excerpt (the abstract)
	 *   {title}          — post title
	 *   {acf:field_name} — ACF field value
	 *   {meta:key}       — post meta value
	 *
	 * @param string $template  The template string.
	 * @param int    $post_id   The post ID.
	 * @return string Resolved string.
	 */
	public static function resolve_template( $template, $post_id = null ) {
		if ( empty( $template ) ) {
			return '';
		}

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$post        = get_post( $post_id );
		$date_format = self::get_date_format();

		// Simple replacements
		$replacements = [
			'{author}'        => get_the_author_meta( 'display_name', $post->post_author ),
			'{date}'          => get_the_date( $date_format, $post_id ),
			'{modified_date}' => get_the_modified_date( $date_format, $post_id ),
			'{excerpt}'       => self::get_clean_excerpt( $post_id ),
			'{title}'         => get_the_title( $post_id ),
		];

		$result = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$template
		);

		// ACF fields: {acf:field_name}
		$result = preg_replace_callback( '/\{acf:([a-zA-Z0-9_-]+)\}/', function( $matches ) use ( $post_id ) {
			if ( function_exists( 'get_field' ) ) {
				$value = get_field( $matches[1], $post_id );
				if ( is_array( $value ) ) {
					return implode( ', ', $value );
				}
				return (string) $value;
			}
			return ''; // ACF not installed
		}, $result );

		// Post meta: {meta:key}
		$result = preg_replace_callback( '/\{meta:([a-zA-Z0-9_-]+)\}/', function( $matches ) use ( $post_id ) {
			$value = get_post_meta( $post_id, $matches[1], true );
			if ( is_array( $value ) ) {
				return implode( ', ', $value );
			}
			return (string) $value;
		}, $result );

		return $result;
	}

	/**
	 * Get the excerpt without the "read more" link and without the [...] suffix.
	 */
	private static function get_clean_excerpt( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Prefer the manual excerpt if set
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		// Fall back to auto-generated excerpt from content
		$text = strip_shortcodes( $post->post_content );
		$text = wp_strip_all_tags( $text );
		$text = str_replace( ']]>', ']]&gt;', $text );

		$excerpt_length = apply_filters( 'excerpt_length', 55 );
		$words = preg_split( '/\s+/', $text, $excerpt_length + 1 );
		if ( count( $words ) > $excerpt_length ) {
			array_pop( $words );
			$text = implode( ' ', $words ) . '…';
		} else {
			$text = implode( ' ', $words );
		}

		return $text;
	}
}
