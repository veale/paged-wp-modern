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
	 * We use the wp.plugins and wp.editPost APIs via inline JS
	 * to avoid requiring a build step.
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

		// Register a dummy handle so we can inline our script with proper deps
		wp_register_script(
			'pagedwpm-gutenberg',
			false, // No external file — we'll inline it
			$deps,
			PAGEDWPM_VERSION,
			true
		);

		// Get current post ID — we'll pass it via localization
		global $post;
		$post_id = $post ? $post->ID : 0;

		wp_localize_script( 'pagedwpm-gutenberg', 'pagedwpmData', [
			'previewBaseUrl' => home_url( '/' ),
			'postId'         => $post_id,
			'settingsUrl'    => admin_url( 'options-general.php?page=pagedwpm-settings' ),
		] );

		wp_enqueue_script( 'pagedwpm-gutenberg' );

		// Add the inline script
		wp_add_inline_script( 'pagedwpm-gutenberg', $this->get_gutenberg_inline_script() );
	}

	/**
	 * Returns the inline JavaScript for the Gutenberg sidebar panel.
	 * Uses vanilla JS with wp.* APIs — no JSX or build step needed.
	 */
	private function get_gutenberg_inline_script() {
		return <<<'JS'
( function() {
	var el              = wp.element.createElement;
	var Fragment        = wp.element.Fragment;
	var registerPlugin  = wp.plugins.registerPlugin;
	var PluginSidebar   = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
	var Button          = wp.components.Button;
	var PanelBody       = wp.components.PanelBody;
	var useSelect       = wp.data.useSelect;
	var __              = wp.i18n.__;

	/**
	 * Simple component: a button that opens the paged preview in a new tab.
	 */
	function PagedPreviewButton() {
		var postId = useSelect( function( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );

		var permalink = useSelect( function( select ) {
			return select( 'core/editor' ).getPermalink();
		}, [] );

		if ( ! permalink ) {
			return el( 'p', { style: { color: '#666', fontStyle: 'italic' } },
				__( 'Save the post to enable paged preview.', 'paged-wp-modern' )
			);
		}

		var previewUrl = permalink + ( permalink.indexOf( '?' ) > -1 ? '&' : '?' ) + 'pagedwpm=true';

		return el( Fragment, null,
			el( Button, {
				variant: 'primary',
				href: previewUrl,
				target: '_blank',
				rel: 'noopener noreferrer',
				style: { width: '100%', justifyContent: 'center', marginBottom: '8px' },
			}, '📄 ' + __( 'Paged Preview', 'paged-wp-modern' ) ),
			el( 'p', { style: { color: '#666', fontSize: '12px' } },
				__( 'Opens a paginated preview. Use Ctrl+P / ⌘P to save as PDF.', 'paged-wp-modern' )
			),
			el( 'p', null,
				el( 'a', {
					href: pagedwpmData.settingsUrl,
					target: '_blank',
					style: { fontSize: '12px' },
				}, __( 'Plugin settings →', 'paged-wp-modern' ) )
			)
		);
	}

	/**
	 * Register in the post status area (below the Publish panel) — always visible.
	 */
	registerPlugin( 'pagedwpm-status-button', {
		render: function() {
			return el( PluginPostStatusInfo, { className: 'pagedwpm-status' },
				el( 'div', { style: { width: '100%' } },
					el( PagedPreviewButton )
				)
			);
		},
	} );

	/**
	 * Also register a sidebar for more prominent access.
	 */
	registerPlugin( 'pagedwpm-sidebar', {
		icon: 'media-document',
		render: function() {
			return el( Fragment, null,
				el( PluginSidebarMoreMenuItem, { target: 'pagedwpm-sidebar' },
					__( 'Paged WP Modern', 'paged-wp-modern' )
				),
				el( PluginSidebar, {
					name: 'pagedwpm-sidebar',
					title: __( 'Paged WP Modern', 'paged-wp-modern' ),
					icon: 'media-document',
				},
					el( PanelBody, { title: __( 'PDF Preview', 'paged-wp-modern' ), initialOpen: true },
						el( PagedPreviewButton )
					),
					el( PanelBody, { title: __( 'About', 'paged-wp-modern' ), initialOpen: false },
						el( 'p', { style: { fontSize: '12px', color: '#666' } },
							__( 'Generates paginated PDF-ready previews using Paged.js. Footnotes from Mammoth-imported Word documents are automatically converted to proper page-bottom footnotes.', 'paged-wp-modern' )
						)
					)
				)
			);
		},
	} );

} )();
JS;
	}
}
