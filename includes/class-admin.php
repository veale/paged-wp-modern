<?php
/**
 * Admin settings page.
 */
namespace PagedWPM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( PAGEDWPM_PLUGIN_DIR . 'paged-wp-modern.php' ), [ $this, 'add_settings_link' ] );
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Paged WP Modern', 'paged-wp-modern' ),
			__( 'Paged WP Modern', 'paged-wp-modern' ),
			'manage_options',
			'pagedwpm-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function add_settings_link( $links ) {
		$link = '<a href="' . esc_url( admin_url( 'options-general.php?page=pagedwpm-settings' ) ) . '">' . esc_html__( 'Settings', 'paged-wp-modern' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	public function register_settings() {

		// =====================================================================
		// SECTION: Title Block
		// =====================================================================
		add_settings_section(
			'pagedwpm_title_block',
			__( 'Title Block', 'paged-wp-modern' ),
			function() {
				echo '<p>' . esc_html__( 'Configure what appears at the top of the PDF, below the post title.', 'paged-wp-modern' ) . '</p>';
				echo '<div class="notice notice-info inline" style="padding:8px 12px; margin:8px 0 16px;">';
				echo '<p style="margin:0 0 4px;"><strong>Template tags you can use in any text field below:</strong></p>';
				echo '<code>{author}</code> post author &nbsp; ';
				echo '<code>{date}</code> publication date &nbsp; ';
				echo '<code>{modified_date}</code> last modified date &nbsp; ';
				echo '<code>{excerpt}</code> post excerpt / abstract &nbsp; ';
				echo '<code>{title}</code> post title<br>';
				echo '<code>{acf:field_name}</code> any ACF field (replace <em>field_name</em> with the field name) &nbsp; ';
				echo '<code>{meta:key}</code> any post meta field';
				echo '</div>';
			},
			'pagedwpm-settings'
		);

		// -- Subtitle / abstract --
		register_setting( 'pagedwpm-settings', 'pagedwpm_show_subtitle', [
			'type' => 'string', 'default' => '1',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_show_subtitle', __( 'Show subtitle / abstract', 'paged-wp-modern' ),
			[ $this, 'render_checkbox' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[ 'option' => 'pagedwpm_show_subtitle', 'label' => 'Display a subtitle or abstract block below the title' ]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_subtitle_template', [
			'type' => 'string', 'default' => '{excerpt}',
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		add_settings_field( 'pagedwpm_subtitle_template', __( 'Subtitle / abstract template', 'paged-wp-modern' ),
			[ $this, 'render_text_field' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[
				'option'      => 'pagedwpm_subtitle_template',
				'placeholder' => '{excerpt}',
				'description' => 'Default: {excerpt}. Examples: {acf:abstract} or "Abstract: {excerpt}"',
				'wide'        => true,
			]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_subtitle_style', [
			'type' => 'string', 'default' => 'abstract',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_subtitle_style', __( 'Subtitle display style', 'paged-wp-modern' ),
			[ $this, 'render_select' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[
				'option'  => 'pagedwpm_subtitle_style',
				'options' => [
					'subtitle' => 'Subtitle (italic, larger text)',
					'abstract' => 'Abstract (indented block with "Abstract" heading)',
					'plain'    => 'Plain paragraph',
				],
			]
		);

		// -- Author --
		register_setting( 'pagedwpm-settings', 'pagedwpm_show_author', [
			'type' => 'string', 'default' => '1',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_show_author', __( 'Show author line', 'paged-wp-modern' ),
			[ $this, 'render_checkbox' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[ 'option' => 'pagedwpm_show_author', 'label' => 'Display the author line' ]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_author_template', [
			'type' => 'string', 'default' => '{author}',
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		add_settings_field( 'pagedwpm_author_template', __( 'Author line template', 'paged-wp-modern' ),
			[ $this, 'render_text_field' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[
				'option'      => 'pagedwpm_author_template',
				'placeholder' => '{author}',
				'description' => 'Default: {author}. Examples: "{author}, {acf:author_affiliation}" or "{author} — {acf:institution}"',
				'wide'        => true,
			]
		);

		// -- Date --
		register_setting( 'pagedwpm-settings', 'pagedwpm_show_date', [
			'type' => 'string', 'default' => '0',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_show_date', __( 'Show date line', 'paged-wp-modern' ),
			[ $this, 'render_checkbox' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[ 'option' => 'pagedwpm_show_date', 'label' => 'Display a date line' ]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_date_template', [
			'type' => 'string', 'default' => '{date}',
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		add_settings_field( 'pagedwpm_date_template', __( 'Date line template', 'paged-wp-modern' ),
			[ $this, 'render_text_field' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[
				'option'      => 'pagedwpm_date_template',
				'placeholder' => '{date}',
				'description' => 'Default: {date}. Examples: "Published: {date}" or "Published {date}, last updated {modified_date}"',
				'wide'        => true,
			]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_date_format', [
			'type' => 'string', 'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_date_format', __( 'Date format', 'paged-wp-modern' ),
			[ $this, 'render_text_field' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[
				'option'      => 'pagedwpm_date_format',
				'placeholder' => get_option( 'date_format', 'F j, Y' ),
				'description' => 'PHP date format. Leave blank for WordPress default (' . esc_html( date_i18n( get_option( 'date_format', 'F j, Y' ) ) ) . '). Examples: "F j, Y" → January 1, 2026 · "j F Y" → 1 January 2026 · "Y-m-d" → 2026-01-01',
			]
		);

		// -- Extra lines --
		register_setting( 'pagedwpm-settings', 'pagedwpm_extra_lines', [
			'type' => 'string', 'default' => '',
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		add_settings_field( 'pagedwpm_extra_lines', __( 'Additional header lines', 'paged-wp-modern' ),
			[ $this, 'render_textarea' ], 'pagedwpm-settings', 'pagedwpm_title_block',
			[
				'option'      => 'pagedwpm_extra_lines',
				'rows'        => 3,
				'placeholder' => "{acf:journal_name}, Vol. {acf:volume}\nDOI: {acf:doi}",
				'description' => 'Extra lines in the title block, one per line. Each can use template tags. Leave blank for none.',
			]
		);

		// =====================================================================
		// SECTION: Content Options
		// =====================================================================
		add_settings_section(
			'pagedwpm_content',
			__( 'Content Options', 'paged-wp-modern' ),
			function() {
				echo '<p>' . esc_html__( 'Control what content appears in the PDF.', 'paged-wp-modern' ) . '</p>';
			},
			'pagedwpm-settings'
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_hide_author_box', [
			'type' => 'string', 'default' => '1',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_hide_author_box', __( 'Hide author box', 'paged-wp-modern' ),
			[ $this, 'render_checkbox' ], 'pagedwpm-settings', 'pagedwpm_content',
			[ 'option' => 'pagedwpm_hide_author_box', 'label' => 'Remove author bio box, avatar, and related-posts sections from PDF (recommended — on by default)' ]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_hide_selectors', [
			'type' => 'string', 'default' => '',
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		add_settings_field( 'pagedwpm_hide_selectors', __( 'Hide additional elements', 'paged-wp-modern' ),
			[ $this, 'render_text_field' ], 'pagedwpm-settings', 'pagedwpm_content',
			[
				'option'      => 'pagedwpm_hide_selectors',
				'placeholder' => '.sharedaddy, .jp-relatedposts, .comments-area',
				'description' => 'Comma-separated CSS selectors for elements to remove from PDF. Useful for share buttons, related posts, comments, etc.',
				'wide'        => true,
			]
		);

		// =====================================================================
		// SECTION: Footnotes
		// =====================================================================
		add_settings_section(
			'pagedwpm_footnotes',
			__( 'Footnotes', 'paged-wp-modern' ),
			function() {
				echo '<p>' . esc_html__( 'Configure how footnotes are detected.', 'paged-wp-modern' ) . '</p>';
			},
			'pagedwpm-settings'
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_footnote_selector', [
			'type' => 'string', 'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_footnote_selector', __( 'Footnote callout selector', 'paged-wp-modern' ),
			[ $this, 'render_text_field' ], 'pagedwpm-settings', 'pagedwpm_footnotes',
			[
				'option'      => 'pagedwpm_footnote_selector',
				'placeholder' => 'a[href*="footnote"], a[href*="endnote"], .footnote-ref',
				'description' => 'CSS selector for footnote callout links. Leave blank to use the default (Mammoth + Pandoc).',
				'wide'        => true,
			]
		);

		// =====================================================================
		// SECTION: Typesetting and reliability
		// =====================================================================
		add_settings_section(
			'pagedwpm_typesetting',
			__( 'Typesetting and Reliability', 'paged-wp-modern' ),
			function() {
				echo '<p>' . esc_html__( 'Professional browser typography is enabled by default. Resource timeouts prevent a damaged image from truncating the article.', 'paged-wp-modern' ) . '</p>';
			},
			'pagedwpm-settings'
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_microtypography', [
			'type'              => 'string',
			'default'           => 'enhanced',
			'sanitize_callback' => [ $this, 'sanitize_microtype' ],
		] );
		add_settings_field( 'pagedwpm_microtypography', __( 'Academic microtypography', 'paged-wp-modern' ),
			[ $this, 'render_select' ], 'pagedwpm-settings', 'pagedwpm_typesetting',
			[
				'option'  => 'pagedwpm_microtypography',
				'options' => [
					'enhanced' => __( 'Enhanced academic typesetting (default)', 'paged-wp-modern' ),
					'standard' => __( 'Standard browser typography', 'paged-wp-modern' ),
				],
			]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_image_timeout', [
			'type'              => 'integer',
			'default'           => 15,
			'sanitize_callback' => [ $this, 'sanitize_timeout' ],
		] );
		add_settings_field( 'pagedwpm_image_timeout', __( 'Image timeout', 'paged-wp-modern' ),
			[ $this, 'render_number_field' ], 'pagedwpm-settings', 'pagedwpm_typesetting',
			[
				'option'      => 'pagedwpm_image_timeout',
				'default'     => 15,
				'min'         => 1,
				'max'         => 300,
				'suffix'      => __( 'seconds', 'paged-wp-modern' ),
				'description' => __( 'A stalled image is replaced by a labelled placeholder so the article can finish.', 'paged-wp-modern' ),
			]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_pagination_timeout', [
			'type'              => 'integer',
			'default'           => 120,
			'sanitize_callback' => [ $this, 'sanitize_timeout' ],
		] );
		add_settings_field( 'pagedwpm_pagination_timeout', __( 'Pagination timeout', 'paged-wp-modern' ),
			[ $this, 'render_number_field' ], 'pagedwpm-settings', 'pagedwpm_typesetting',
			[
				'option'      => 'pagedwpm_pagination_timeout',
				'default'     => 120,
				'min'         => 5,
				'max'         => 300,
				'suffix'      => __( 'seconds', 'paged-wp-modern' ),
				'description' => __( 'An explicit diagnostic is shown if layout cannot finish.', 'paged-wp-modern' ),
			]
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_auto_updates', [
			'type'              => 'string',
			'default'           => '0',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field( 'pagedwpm_auto_updates', __( 'Automatic GitHub updates', 'paged-wp-modern' ),
			[ $this, 'render_checkbox' ], 'pagedwpm-settings', 'pagedwpm_typesetting',
			[
				'option' => 'pagedwpm_auto_updates',
				'label'  => __( 'Install new tagged GitHub releases automatically using WordPress updates', 'paged-wp-modern' ),
			]
		);

		// =====================================================================
		// SECTION: Custom CSS
		// =====================================================================
		add_settings_section(
			'pagedwpm_styling',
			__( 'Custom CSS', 'paged-wp-modern' ),
			function() {
				echo '<p>' . esc_html__( 'Custom Paged Media CSS appended after the default stylesheet.', 'paged-wp-modern' ) . '</p>';
			},
			'pagedwpm-settings'
		);

		register_setting( 'pagedwpm-settings', 'pagedwpm_custom_css', [
			'type' => 'string', 'default' => '',
			'sanitize_callback' => [ $this, 'sanitize_css' ],
		] );
		add_settings_field( 'pagedwpm_custom_css', __( 'Custom CSS', 'paged-wp-modern' ),
			[ $this, 'render_textarea' ], 'pagedwpm-settings', 'pagedwpm_styling',
			[
				'option'      => 'pagedwpm_custom_css',
				'rows'        => 15,
				'placeholder' => "/* Your custom paged media CSS here */",
			]
		);
	}

	public function sanitize_css( $input ) {
		$input = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $input );
		$input = wp_strip_all_tags( $input );
		$input = str_ireplace( [ '</style', '<style' ], '', $input );
		$input = str_replace( "\0", '', $input );
		return $input;
	}

	public function sanitize_microtype( $input ) {
		return in_array( $input, [ 'enhanced', 'standard' ], true ) ? $input : 'enhanced';
	}

	public function sanitize_timeout( $input ) {
		return max( 1, min( 300, absint( $input ) ) );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="notice notice-info" style="padding: 12px;">
				<p><strong><?php esc_html_e( 'How it works:', 'paged-wp-modern' ); ?></strong>
				<?php esc_html_e( 'Open any post/page in the editor → click "Paged Preview" → paginated view opens in new tab → Ctrl+P / ⌘P to save as PDF.', 'paged-wp-modern' ); ?></p>
				<p><?php esc_html_e( 'Shortcode: [paged_download] adds a "View as PDF" link for visitors.', 'paged-wp-modern' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'pagedwpm-settings' );
				do_settings_sections( 'pagedwpm-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/* -- Field renderers -- */

	public function render_checkbox( $args ) {
		$value = get_option( $args['option'], '0' );
		?>
		<label>
			<input type="hidden" name="<?php echo esc_attr( $args['option'] ); ?>" value="0">
			<input type="checkbox" name="<?php echo esc_attr( $args['option'] ); ?>" value="1" <?php checked( '1', $value ); ?>>
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php
	}

	public function render_text_field( $args ) {
		$value = get_option( $args['option'], '' );
		$wide  = ! empty( $args['wide'] );
		?>
		<input type="text" name="<?php echo esc_attr( $args['option'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo esc_attr( $args['placeholder'] ?? '' ); ?>"
			class="<?php echo $wide ? 'large-text' : 'regular-text'; ?>"
			style="font-family: monospace;">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function render_textarea( $args ) {
		$value = get_option( $args['option'], '' );
		?>
		<textarea name="<?php echo esc_attr( $args['option'] ); ?>"
			rows="<?php echo esc_attr( $args['rows'] ?? 10 ); ?>"
			class="large-text code"
			placeholder="<?php echo esc_attr( $args['placeholder'] ?? '' ); ?>"
			style="font-family: monospace;"><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function render_select( $args ) {
		$value = get_option( $args['option'], '' );
		?>
		<select name="<?php echo esc_attr( $args['option'] ); ?>">
			<?php foreach ( $args['options'] as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_number_field( $args ) {
		$value = get_option( $args['option'], $args['default'] ?? 1 );
		?>
		<input type="number"
			name="<?php echo esc_attr( $args['option'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			min="<?php echo esc_attr( $args['min'] ?? 1 ); ?>"
			max="<?php echo esc_attr( $args['max'] ?? 300 ); ?>"
			step="1">
		<?php echo esc_html( $args['suffix'] ?? '' ); ?>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}
}
