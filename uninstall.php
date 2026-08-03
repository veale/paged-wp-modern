<?php
/**
 * Remove plugin-owned settings when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$pagedwpm_options = [
	'pagedwpm_show_subtitle',
	'pagedwpm_subtitle_template',
	'pagedwpm_subtitle_style',
	'pagedwpm_show_author',
	'pagedwpm_author_template',
	'pagedwpm_show_date',
	'pagedwpm_date_template',
	'pagedwpm_date_format',
	'pagedwpm_extra_lines',
	'pagedwpm_hide_author_box',
	'pagedwpm_hide_selectors',
	'pagedwpm_footnote_selector',
	'pagedwpm_microtypography',
	'pagedwpm_image_timeout',
	'pagedwpm_pagination_timeout',
	'pagedwpm_auto_updates',
	'pagedwpm_custom_css',
];

foreach ( $pagedwpm_options as $pagedwpm_option ) {
	delete_option( $pagedwpm_option );
}

delete_site_transient( 'pagedwpm_github_release' );
