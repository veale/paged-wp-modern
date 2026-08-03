<?php
/**
 * Paged Preview Template
 *
 * Minimal HTML document that loads post content + Paged.js for paginated output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! have_posts() ) {
	wp_die( 'No content found.', 'Paged Preview' );
}

the_post();

$post_id       = get_the_ID();
$post_title    = get_the_title();
$footnote_sel  = \PagedWPM\Preview::get_footnote_selector();
$paged_css     = \PagedWPM\Preview::get_paged_css();
$pagedjs_ver   = PAGEDWPM_PAGEDJS_VERSION;
$plugin_url    = PAGEDWPM_PLUGIN_URL;

// Resolve template-tag-based settings
$show_author    = get_option( 'pagedwpm_show_author', '1' ) === '1';
$author_text    = '';
if ( $show_author ) {
	$author_tpl  = get_option( 'pagedwpm_author_template', '{author}' );
	if ( empty( $author_tpl ) ) { $author_tpl = '{author}'; }
	$author_text = \PagedWPM\Preview::resolve_template( $author_tpl, $post_id );
}

$show_date      = get_option( 'pagedwpm_show_date', '0' ) === '1';
$date_text      = '';
if ( $show_date ) {
	$date_tpl   = get_option( 'pagedwpm_date_template', '{date}' );
	if ( empty( $date_tpl ) ) { $date_tpl = '{date}'; }
	$date_text  = \PagedWPM\Preview::resolve_template( $date_tpl, $post_id );
}

$show_subtitle  = get_option( 'pagedwpm_show_subtitle', '1' ) === '1';
$subtitle_text  = '';
$subtitle_style = get_option( 'pagedwpm_subtitle_style', 'abstract' );
if ( $show_subtitle ) {
	$subtitle_tpl = get_option( 'pagedwpm_subtitle_template', '{excerpt}' );
	if ( empty( $subtitle_tpl ) ) { $subtitle_tpl = '{excerpt}'; }
	$subtitle_text = \PagedWPM\Preview::resolve_template( $subtitle_tpl, $post_id );
}

$extra_lines_raw = get_option( 'pagedwpm_extra_lines', '' );
$extra_lines     = [];
if ( ! empty( $extra_lines_raw ) ) {
	$lines = explode( "\n", $extra_lines_raw );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line !== '' ) {
			$extra_lines[] = \PagedWPM\Preview::resolve_template( $line, $post_id );
		}
	}
}

// Elements to hide
$hide_selectors = \PagedWPM\Preview::get_hide_selectors();
$microtype_mode = \PagedWPM\Preview::get_microtype_mode();
$abstract_style = \PagedWPM\Preview::get_abstract_settings();
$show_running_heads = get_option( 'pagedwpm_show_running_heads', '1' ) === '1';
$journal_head_tpl   = get_option( 'pagedwpm_journal_head_template', '{site_title}{volume_suffix}' );
$article_head_tpl   = get_option( 'pagedwpm_article_head_template', '{title}' );
$journal_head       = $show_running_heads ? \PagedWPM\Preview::resolve_template( $journal_head_tpl, $post_id ) : '';
$article_head       = $show_running_heads ? \PagedWPM\Preview::resolve_template( $article_head_tpl, $post_id ) : '';
$paged_css          = str_replace(
	[ '%%PAGEDWPM_JOURNAL_HEAD%%', '%%PAGEDWPM_ARTICLE_HEAD%%' ],
	[ \PagedWPM\Preview::quote_css_string( $journal_head ), \PagedWPM\Preview::quote_css_string( $article_head ) ],
	$paged_css
);
$asset_version  = rawurlencode( PAGEDWPM_VERSION );
$preview_config = [
	'hideSelectors'       => array_values( $hide_selectors ),
	'imageTimeoutMs'      => \PagedWPM\Preview::get_timeout_ms( 'pagedwpm_image_timeout', 15 ),
	'fontTimeoutMs'       => 5000,
	'paginationTimeoutMs' => \PagedWPM\Preview::get_timeout_ms( 'pagedwpm_pagination_timeout', 120 ),
	'messages'            => [
		'preparing'          => __( 'Preparing document…', 'paged-wp-modern' ),
		'loadingImages'      => __( 'Loading images…', 'paged-wp-modern' ),
		'imageUnavailable'   => __( 'Image unavailable', 'paged-wp-modern' ),
		'paginating'         => __( 'Laying out pages…', 'paged-wp-modern' ),
		'paginationTimeout'  => __( 'Pagination timed out.', 'paged-wp-modern' ),
		'incomplete'         => __( 'Pagination ended before all article content was rendered.', 'paged-wp-modern' ),
		'libraryMissing'     => __( 'The local Paged.js library could not be loaded.', 'paged-wp-modern' ),
		'failed'             => __( 'The document could not be fully paginated.', 'paged-wp-modern' ),
		'ready'              => __( 'Document ready.', 'paged-wp-modern' ),
		'pages'              => __( 'pages', 'paged-wp-modern' ),
		'imageWarnings'      => __( 'image(s) could not be loaded and were replaced.', 'paged-wp-modern' ),
		'print'              => __( 'Print / Save PDF', 'paged-wp-modern' ),
		'dismiss'            => __( 'Dismiss', 'paged-wp-modern' ),
	],
];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" class="pagedwpm-microtype-<?php echo esc_attr( $microtype_mode ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $post_title ); ?> — PDF</title>

	<style id="pagedwpm-css">
<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $paged_css;
?>
	</style>

	<script>
		var endNoteCalloutsQuery = <?php echo wp_json_encode( $footnote_sel ); ?>;
	</script>
</head>
<body>

	<article class="pagedwpm-content">

		<header class="pagedwpm-header">
			<h1><?php echo esc_html( $post_title ); ?></h1>

			<?php if ( $show_author && ! empty( $author_text ) ) : ?>
				<p class="author"><?php echo esc_html( $author_text ); ?></p>
			<?php endif; ?>

			<?php if ( $show_date && ! empty( $date_text ) ) : ?>
				<p class="date"><?php echo esc_html( $date_text ); ?></p>
			<?php endif; ?>

			<?php foreach ( $extra_lines as $line ) : ?>
				<?php if ( ! empty( $line ) ) : ?>
					<p class="extra-line"><?php echo esc_html( $line ); ?></p>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $show_subtitle && ! empty( $subtitle_text ) ) : ?>
				<?php if ( $subtitle_style === 'abstract' ) : ?>
					<aside
						class="abstract pagedwpm-abstract pagedwpm-abstract--<?php echo esc_attr( $abstract_style['style'] ); ?> pagedwpm-abstract-gap--<?php echo esc_attr( $abstract_style['gap'] ); ?>"
						style="--pagedwpm-abstract-accent: <?php echo esc_attr( $abstract_style['color'] ); ?>; --pagedwpm-abstract-label-font: <?php echo esc_attr( $abstract_style['font'] ); ?>;"
						aria-label="<?php echo esc_attr( $abstract_style['label'] ); ?>"
					>
						<p class="abstract-copy"><span class="abstract-heading"><?php echo esc_html( $abstract_style['label'] ); ?></span><span class="abstract-text"><?php echo esc_html( $subtitle_text ); ?></span></p>
					</aside>
				<?php elseif ( $subtitle_style === 'subtitle' ) : ?>
					<p class="subtitle"><?php echo esc_html( $subtitle_text ); ?></p>
				<?php else : ?>
					<p class="subtitle-plain"><?php echo esc_html( $subtitle_text ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

		</header>

		<div class="pagedwpm-body">
			<?php the_content(); ?>
			<span data-pagedwpm-end-marker aria-hidden="true"></span>
		</div>

	</article>

	<script id="pagedwpm-config" type="application/json"><?php echo wp_json_encode( $preview_config, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
	<script src="<?php echo esc_url( $plugin_url . 'assets/js/footnote-converter.js?ver=' . $asset_version ); ?>"></script>
	<script src="<?php echo esc_url( $plugin_url . 'assets/js/paged-preview.js?ver=' . $asset_version ); ?>"></script>
	<script src="<?php echo esc_url( $plugin_url . 'assets/vendor/pagedjs/paged.polyfill.min.js?ver=' . rawurlencode( $pagedjs_ver ) ); ?>"></script>

</body>
</html>
