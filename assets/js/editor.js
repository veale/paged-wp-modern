(function () {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerPlugin = wp.plugins.registerPlugin;
	var editPost = wp.editPost || {};
	var Button = wp.components.Button;
	var PanelBody = wp.components.PanelBody;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;

	function PagedPreviewButton() {
		var permalink = useSelect(function (select) {
			return select('core/editor').getPermalink();
		}, []);

		if (!permalink) {
			return el('p', { style: { color: '#666', fontStyle: 'italic' } },
				__('Save the post to enable paged preview.', 'paged-wp-modern'));
		}

		var previewUrl = new URL(permalink, window.location.href);
		previewUrl.searchParams.set('pagedwpm', 'true');

		return el(Fragment, null,
			el(Button, {
				variant: 'primary',
				href: previewUrl.toString(),
				target: '_blank',
				rel: 'noopener noreferrer',
				style: { width: '100%', justifyContent: 'center', marginBottom: '8px' }
			}, '📄 ' + __('Paged Preview', 'paged-wp-modern')),
			el('p', { style: { color: '#666', fontSize: '12px' } },
				__('Opens a paginated preview. Use Ctrl+P / ⌘P to save as PDF.', 'paged-wp-modern')),
			el('p', null, el('a', {
				href: pagedwpmData.settingsUrl,
				target: '_blank',
				rel: 'noopener noreferrer',
				style: { fontSize: '12px' }
			}, __('Plugin settings →', 'paged-wp-modern')))
		);
	}

	if (editPost.PluginPostStatusInfo) {
		registerPlugin('pagedwpm-status-button', {
			render: function () {
				return el(editPost.PluginPostStatusInfo, { className: 'pagedwpm-status' },
					el('div', { style: { width: '100%' } }, el(PagedPreviewButton)));
			}
		});
	}

	if (editPost.PluginSidebar && editPost.PluginSidebarMoreMenuItem) {
		registerPlugin('pagedwpm-sidebar', {
			icon: 'media-document',
			render: function () {
				return el(Fragment, null,
					el(editPost.PluginSidebarMoreMenuItem, { target: 'pagedwpm-sidebar' },
						__('Paged WP Modern', 'paged-wp-modern')),
					el(editPost.PluginSidebar, {
						name: 'pagedwpm-sidebar',
						title: __('Paged WP Modern', 'paged-wp-modern'),
						icon: 'media-document'
					},
					el(PanelBody, { title: __('PDF Preview', 'paged-wp-modern'), initialOpen: true },
						el(PagedPreviewButton)),
					el(PanelBody, { title: __('About', 'paged-wp-modern'), initialOpen: false },
						el('p', { style: { fontSize: '12px', color: '#666' } },
							__('Creates publication-quality paged articles with resilient images and page-bottom footnotes.', 'paged-wp-modern'))))
				);
			}
		});
	}
})();
