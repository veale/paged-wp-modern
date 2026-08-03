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
		if ( ! is_singular() || ! get_queried_object_id() ) {
			return $template;
		}

		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
		return PAGEDWPM_PLUGIN_DIR . 'templates/preview.php';
	}

	public static function is_paged_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$value = get_query_var( 'pagedwpm', '' );
		if ( '' === $value && isset( $_GET['pagedwpm'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['pagedwpm'] ) );
		}
		return 'true' === $value;
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
		$css_path = PAGEDWPM_PLUGIN_DIR . 'assets/css/paged-default.css';
		$css      = is_readable( $css_path ) ? file_get_contents( $css_path ) : '';
		if ( false === $css ) {
			$css = '';
		}
		$css = str_replace( '%%PAGEDWPM_ASSET_URL%%', esc_url_raw( PAGEDWPM_PLUGIN_URL ), $css );
		$custom_css = get_option( 'pagedwpm_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			$css .= "\n/* === Custom CSS === */\n" . $custom_css . "\n";
		}
		return $css;
	}

	/**
	 * Return the progressive microtypography mode.
	 */
	public static function get_microtype_mode() {
		$mode = get_option( 'pagedwpm_microtypography', 'enhanced' );
		return in_array( $mode, [ 'enhanced', 'standard' ], true ) ? $mode : 'enhanced';
	}

	/**
	 * Get the abstract presentation settings with safe fallbacks.
	 */
	public static function get_abstract_settings() {
		$style = get_option( 'pagedwpm_abstract_style', 'plain' );
		$gap   = get_option( 'pagedwpm_abstract_label_gap', 'triple' );
		$label = trim( (string) get_option( 'pagedwpm_abstract_label', __( 'Abstract', 'paged-wp-modern' ) ) );
		$font  = (string) get_option(
			'pagedwpm_abstract_label_font',
			"-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif"
		);
		$font  = preg_replace( '/[^a-zA-Z0-9\s,\'"-]/', '', $font );
		$font  = substr( trim( $font ), 0, 200 );
		$color = sanitize_hex_color( get_option( 'pagedwpm_abstract_accent_color', '#163c73' ) );

		return [
			'label' => $label ?: __( 'Abstract', 'paged-wp-modern' ),
			'style' => in_array( $style, [ 'rule', 'panel', 'plain' ], true ) ? $style : 'plain',
			'gap'   => in_array( $gap, [ 'double', 'triple' ], true ) ? $gap : 'triple',
			'font'  => $font ?: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
			'color' => $color ?: '#163c73',
		];
	}

	/**
	 * Quote plain text for use as a CSS string value.
	 */
	public static function quote_css_string( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = str_replace(
			[ '\\', '"', "\r\n", "\r", "\n", "\f", '<' ],
			[ '\\\\', '\\"', '\\A ', '\\A ', '\\A ', '\\C ', '\\3C ' ],
			$value
		);
		return '"' . $value . '"';
	}

	/**
	 * Get a seconds-based timeout option as milliseconds with safe limits.
	 */
	public static function get_timeout_ms( $option, $default ) {
		$seconds = absint( get_option( $option, $default ) );
		$seconds = max( 1, min( 300, $seconds ) );
		return $seconds * 1000;
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
	 *   {site_title}     — WordPress site title
	 *   {volume}         — citation_volume post meta
	 *   {volume_suffix}  — optional ", Vol XX" suffix
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
		if ( ! $post ) {
			return '';
		}
		$date_format = self::get_date_format();
		$volume      = self::stringify_template_value( get_post_meta( $post_id, 'citation_volume', true ) );

		// Simple replacements
		$replacements = [
			'{author}'        => get_the_author_meta( 'display_name', $post->post_author ),
			'{date}'          => get_the_date( $date_format, $post_id ),
			'{modified_date}' => get_the_modified_date( $date_format, $post_id ),
			'{excerpt}'       => self::get_clean_excerpt( $post_id ),
			'{title}'         => get_the_title( $post_id ),
			'{site_title}'    => get_bloginfo( 'name' ),
			'{volume}'        => $volume,
			'{volume_suffix}' => '' !== $volume ? sprintf( __( ', Vol %s', 'paged-wp-modern' ), $volume ) : '',
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
				return self::stringify_template_value( $value );
			}
			return ''; // ACF not installed
		}, $result );

		// Post meta: {meta:key}
		$result = preg_replace_callback( '/\{meta:([a-zA-Z0-9_-]+)\}/', function( $matches ) use ( $post_id ) {
			$value = get_post_meta( $post_id, $matches[1], true );
			return self::stringify_template_value( $value );
		}, $result );

		return $result;
	}

	private static function stringify_template_value( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return (string) $value;
		}
		if ( is_array( $value ) ) {
			$flat = [];
			array_walk_recursive( $value, function( $item ) use ( &$flat ) {
				if ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			} );
			return implode( ', ', $flat );
		}
		return '';
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
