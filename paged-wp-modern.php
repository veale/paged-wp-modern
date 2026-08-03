<?php
/**
 * Plugin Name: Paged WP Modern
 * Plugin URI:  https://github.com/example/paged-wp-modern
 * Description: Generate beautiful paginated PDFs from WordPress posts and pages using Paged.js, with proper footnote support for Mammoth-imported Word documents.
 * Version:     2.0.0
 * Author:      Built with Claude
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: paged-wp-modern
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAGEDWPM_VERSION', '2.0.0' );
define( 'PAGEDWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAGEDWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// Pin to a known good version of Paged.js with footnote support
define( 'PAGEDWPM_PAGEDJS_VERSION', '0.4.3' );

require_once PAGEDWPM_PLUGIN_DIR . 'includes/class-preview.php';
require_once PAGEDWPM_PLUGIN_DIR . 'includes/class-admin.php';
require_once PAGEDWPM_PLUGIN_DIR . 'includes/class-editor.php';
require_once PAGEDWPM_PLUGIN_DIR . 'includes/class-shortcode.php';

// Initialize
add_action( 'plugins_loaded', function() {
	new PagedWPM\Preview();
	new PagedWPM\Admin();
	new PagedWPM\Editor();
	new PagedWPM\Shortcode();
});
