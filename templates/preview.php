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
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
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

	<?php if ( ! empty( $hide_selectors ) ) : ?>
	<style id="pagedwpm-hide">
		<?php echo esc_html( implode( ",\n", $hide_selectors ) ); ?> {
			display: none !important;
		}
	</style>
	<?php endif; ?>

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
					<div class="abstract">
						<p class="abstract-heading">Abstract</p>
						<p class="abstract-text"><?php echo esc_html( $subtitle_text ); ?></p>
					</div>
				<?php elseif ( $subtitle_style === 'subtitle' ) : ?>
					<p class="subtitle"><?php echo esc_html( $subtitle_text ); ?></p>
				<?php else : ?>
					<p class="subtitle-plain"><?php echo esc_html( $subtitle_text ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

		</header>

		<div class="pagedwpm-body">
			<?php the_content(); ?>
		</div>

	</article>

	<!-- Remove unwanted elements before Paged.js runs -->
	<?php if ( ! empty( $hide_selectors ) ) : ?>
	<script>
	(function() {
		var selectors = <?php echo wp_json_encode( $hide_selectors ); ?>;
		selectors.forEach(function(sel) {
			try {
				document.querySelectorAll(sel).forEach(function(el) {
					el.remove();
				});
			} catch(e) {}
		});
	})();
	</script>
	<?php endif; ?>

	<!-- Footnote converter -->
	<script src="<?php echo esc_url( $plugin_url . 'assets/js/footnote-converter.js' ); ?>"></script>

	<!-- Paged.js -->
	<script>
		window.PagedConfig = {
			auto: false,
			after: function(flow) {
				var hint = document.createElement('div');
				hint.id = 'pagedwpm-print-hint';
				hint.innerHTML = '<p>Press <kbd>Ctrl+P</kbd> / <kbd>⌘P</kbd> to save as PDF &nbsp; <button onclick="window.print()">Print / Save PDF</button> <button onclick="this.parentNode.parentNode.remove()">Dismiss</button></p>';
				document.body.insertBefore(hint, document.body.firstChild);
				console.log('Paged.js: ' + flow.total + ' pages');

				// Auto-trigger print dialog if ?print=true
				if (new URLSearchParams(window.location.search).get('print') === 'true') {
					window.print();
				}
			}
		};
	</script>
	<script src="https://unpkg.com/pagedjs@<?php echo esc_attr( $pagedjs_ver ); ?>/dist/paged.polyfill.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			if (window.PagedPolyfill) {
				window.PagedPolyfill.preview();
			}
		});
	</script>

</body>
</html>
