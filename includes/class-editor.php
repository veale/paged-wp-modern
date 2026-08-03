<?php
/**
 * Editor integration: adds "Paged Preview" button to both
 * the Classic Editor (via meta box) and Gutenberg (via plugin sidebar).
 */
namespace PagedWPM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor {

	public function __construct() {
		// Classic Editor: meta box
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );

		// Gutenberg: enqueue sidebar script
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_gutenberg_assets' ] );
	}

	/**
	 * Register the Paged Preview meta box for Classic Editor.
	 */
	public function add_meta_box() {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		add_meta_box(
			'pagedwpm-preview',
			__( 'Paged Preview', 'paged-wp-modern' ),
			[ $this, 'render_meta_box' ],
			$post_types,
			'side',
			'high'
		);
	}

	/**
	 * Render the Classic Editor meta box content.
	 */
	public function render_meta_box( $post ) {
		$preview_url = Preview::get_preview_url( $post->ID );
		if ( ! $preview_url ) {
			echo '<p>' . esc_html__( 'Save the post first to enable preview.', 'paged-wp-modern' ) . '</p>';
			return;
		}
		?>
		<p class="description" style="margin-bottom: 8px;">
			<?php esc_html_e( 'Open a paginated, print-ready preview of this post. Use Ctrl+P / ⌘P to save as PDF.', 'paged-wp-modern' ); ?>
		</p>
		<a class="button button-primary"
		   href="<?php echo esc_url( $preview_url ); ?>"
		   target="_blank"
		   rel="noopener noreferrer">
			<?php esc_html_e( '📄 Paged Preview', 'paged-wp-modern' ); ?>
		</a>
		<?php
	}

	/**
	 * Enqueue Gutenberg sidebar assets.
	 *
	 * Uses the wp.plugins and wp.editPost packages without requiring JSX.
	 */
	public function enqueue_gutenberg_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor ) {
			return;
		}

		// We need these WP packages
		$deps = [
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-data',
			'wp-i18n',
		];

		// Register the editor integration with WordPress package dependencies.
		wp_register_script(
			'pagedwpm-gutenberg',
			PAGEDWPM_PLUGIN_URL . 'assets/js/editor.js',
			$deps,
			PAGEDWPM_VERSION,
			true
		);

		wp_localize_script( 'pagedwpm-gutenberg', 'pagedwpmData', [
			'settingsUrl' => admin_url( 'options-general.php?page=pagedwpm-settings' ),
		] );

		wp_enqueue_script( 'pagedwpm-gutenberg' );
	}
}
