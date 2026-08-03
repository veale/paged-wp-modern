/**
 * Coordinates resource preparation and Paged.js rendering.
 */
(function () {
	'use strict';

	var started = false;
	var failedImages = [];
	var config = readConfig();

	// This must exist before paged.polyfill.js is evaluated.
	window.PagedConfig = Object.assign({}, window.PagedConfig, { auto: false });

	function readConfig() {
		var node = document.getElementById('pagedwpm-config');
		if (!node) {
			return {};
		}

		try {
			return JSON.parse(node.textContent || '{}');
		} catch (error) {
			console.error('[PagedWPM] Invalid preview configuration.', error);
			return {};
		}
	}

	function message(key, fallback) {
		return config.messages && config.messages[key] ? config.messages[key] : fallback;
	}

	function numberSetting(key, fallback, minimum) {
		var value = Number(config[key]);
		return Number.isFinite(value) && value >= minimum ? value : fallback;
	}

	function createStatus() {
		var status = document.createElement('div');
		status.id = 'pagedwpm-status';
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		status.textContent = message('preparing', 'Preparing document…');
		document.body.insertBefore(status, document.body.firstChild);
		return status;
	}

	function setStatus(status, text, state) {
		status.textContent = text;
		status.dataset.state = state || 'working';
	}

	function normalizeLazyImage(image) {
		var lazySource = image.getAttribute('data-src') ||
			image.getAttribute('data-lazy-src') ||
			image.getAttribute('data-original');
		var lazySrcset = image.getAttribute('data-srcset') ||
			image.getAttribute('data-lazy-srcset');

		if ((!image.getAttribute('src') || image.getAttribute('src') === 'about:blank') && lazySource) {
			image.setAttribute('src', lazySource);
		}
		if (!image.getAttribute('srcset') && lazySrcset) {
			image.setAttribute('srcset', lazySrcset);
		}

		image.loading = 'eager';
		image.decoding = 'async';
		image.removeAttribute('data-src');
		image.removeAttribute('data-lazy-src');
		image.removeAttribute('data-original');
		image.removeAttribute('data-srcset');
		image.removeAttribute('data-lazy-srcset');
	}

	function imageUrl(image) {
		return image.currentSrc || image.getAttribute('src') || '(missing image URL)';
	}

	function waitForImage(image, timeoutMs) {
		normalizeLazyImage(image);

		return new Promise(function (resolve) {
			var settled = false;
			var timer;

			function finish(ok, reason) {
				if (settled) {
					return;
				}
				settled = true;
				window.clearTimeout(timer);
				image.removeEventListener('load', loaded);
				image.removeEventListener('error', errored);
				resolve({ ok: ok, reason: reason, url: imageUrl(image), image: image });
			}

			function loaded() {
				finish(image.naturalWidth > 0, image.naturalWidth > 0 ? 'loaded' : 'decode');
			}

			function errored() {
				finish(false, 'error');
			}

			if (!image.getAttribute('src') && !image.getAttribute('srcset')) {
				finish(false, 'missing');
				return;
			}

			if (image.complete) {
				finish(image.naturalWidth > 0, image.naturalWidth > 0 ? 'loaded' : 'error');
				return;
			}

			image.addEventListener('load', loaded, { once: true });
			image.addEventListener('error', errored, { once: true });
			timer = window.setTimeout(function () {
				finish(false, 'timeout');
			}, timeoutMs);
		});
	}

	function replaceFailedImage(result) {
		var image = result.image;
		var replacement = document.createElement('span');
		var alt = image.getAttribute('alt') || '';
		var target = image.parentElement && image.parentElement.tagName === 'PICTURE' ? image.parentElement : image;
		var transparentPixel = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

		// Cancel a request that is still pending before removing the node. A held
		// request can otherwise prevent Chromium from scheduling Paged.js work.
		if (target.tagName === 'PICTURE') {
			target.querySelectorAll('source').forEach(function (source) {
				source.removeAttribute('srcset');
			});
		}
		image.removeAttribute('srcset');
		image.src = transparentPixel;

		replacement.className = 'pagedwpm-image-error';
		replacement.setAttribute('role', 'img');
		replacement.setAttribute('aria-label', alt || message('imageUnavailable', 'Image unavailable'));
		replacement.textContent = alt ?
			message('imageUnavailable', 'Image unavailable') + ': ' + alt :
			message('imageUnavailable', 'Image unavailable');
		replacement.dataset.imageUrl = result.url;
		target.replaceWith(replacement);
	}

	async function prepareImages(status) {
		var images = Array.from(document.querySelectorAll('.pagedwpm-content img'));
		var timeoutMs = numberSetting('imageTimeoutMs', 15000, 1000);
		var completed = 0;

		if (!images.length) {
			return;
		}

		var results = await Promise.all(images.map(function (image) {
			return waitForImage(image, timeoutMs).then(function (result) {
				completed += 1;
				setStatus(
					status,
					message('loadingImages', 'Loading images…') + ' ' + completed + '/' + images.length,
					'working'
				);
				return result;
			});
		}));

		if (results.some(function (result) { return result.reason === 'timeout'; })) {
			// All plugin scripts are local and loaded by this point. Stop any image
			// transfers that ignored node removal before beginning pagination.
			window.stop();
		}

		results.forEach(function (result) {
			if (!result.ok) {
				failedImages.push({ url: result.url, reason: result.reason });
				console.warn('[PagedWPM] Replacing unavailable image (' + result.reason + '):', result.url);
				replaceFailedImage(result);
			}
		});
	}

	async function prepareFonts() {
		if (!document.fonts || !document.fonts.ready) {
			return;
		}

		var timeoutMs = numberSetting('fontTimeoutMs', 5000, 500);
		await Promise.race([
			document.fonts.ready,
			new Promise(function (resolve) { window.setTimeout(resolve, timeoutMs); })
		]);
	}

	function removeHiddenElements() {
		var selectors = Array.isArray(config.hideSelectors) ? config.hideSelectors : [];
		selectors.forEach(function (selector) {
			try {
				document.querySelectorAll(selector).forEach(function (element) {
					element.remove();
				});
			} catch (error) {
				console.warn('[PagedWPM] Ignoring invalid hide selector:', selector, error);
			}
		});
	}

	function paginationWithTimeout(timeoutMs) {
		var source = document.querySelector('.pagedwpm-content');
		var content = source ? source.cloneNode(true) : undefined;

		// Paged.js renders the detached clone. Keep the source available for an
		// accessible error fallback, but do not leave it above the generated pages.
		if (source) {
			source.hidden = true;
		}

		var previewPromise = window.PagedPolyfill.preview(content);
		var timer;
		var timeoutPromise = new Promise(function (_, reject) {
			timer = window.setTimeout(function () {
				if (window.PagedPolyfill.chunker && typeof window.PagedPolyfill.chunker.stop === 'function') {
					window.PagedPolyfill.chunker.stop();
				}
				reject(new Error(message('paginationTimeout', 'Pagination timed out.')));
			}, timeoutMs);
		});

		return Promise.race([previewPromise, timeoutPromise]).finally(function () {
			window.clearTimeout(timer);
		});
	}

	function assertComplete(flow) {
		var pages = document.querySelector('.pagedjs_pages');
		var endMarker = pages && pages.querySelector('[data-pagedwpm-end-marker]');
		if (!pages || !endMarker) {
			throw new Error(message('incomplete', 'Pagination ended before all article content was rendered.'));
		}
		return flow;
	}

	function installPrintExclusion() {
		if (document.getElementById('pagedwpm-print-exclusion')) {
			return;
		}

		var style = document.createElement('style');
		style.id = 'pagedwpm-print-exclusion';
		style.media = 'print';
		style.dataset.pagedjsIgnore = 'true';
		style.textContent = '#pagedwpm-print-hint, #pagedwpm-status {' +
			'display: none !important; visibility: hidden !important;' +
			'}';
		document.head.append(style);
	}

	function hideControlsForPrint() {
		document.querySelectorAll('#pagedwpm-print-hint, #pagedwpm-status').forEach(function (control) {
			control.dataset.pagedwpmDisplay = control.style.display || '';
			control.style.setProperty('display', 'none', 'important');
			control.setAttribute('aria-hidden', 'true');
		});
	}

	function restoreControlsAfterPrint() {
		document.querySelectorAll('#pagedwpm-print-hint, #pagedwpm-status').forEach(function (control) {
			var display = control.dataset.pagedwpmDisplay || '';
			control.style.removeProperty('display');
			if (display) {
				control.style.display = display;
			}
			delete control.dataset.pagedwpmDisplay;
			control.removeAttribute('aria-hidden');
		});
	}

	function addPrintHint(flow) {
		var hint = document.createElement('div');
		var summary = document.createElement('p');
		var printButton = document.createElement('button');
		var dismissButton = document.createElement('button');

		installPrintExclusion();
		hint.id = 'pagedwpm-print-hint';
		summary.append(document.createTextNode(message('ready', 'Document ready.') + ' '));
		if (flow && flow.total) {
			summary.append(document.createTextNode(flow.total + ' ' + message('pages', 'pages') + '. '));
		}
		if (failedImages.length) {
			summary.append(document.createTextNode(
				failedImages.length + ' ' + message('imageWarnings', 'image(s) could not be loaded and were replaced. ')
			));
			hint.title = failedImages.map(function (failure) {
				return failure.reason + ': ' + failure.url;
			}).join('\n');
		}

		printButton.type = 'button';
		printButton.textContent = message('print', 'Print / Save PDF');
		printButton.addEventListener('click', function () { window.print(); });
		dismissButton.type = 'button';
		dismissButton.textContent = message('dismiss', 'Dismiss');
		dismissButton.addEventListener('click', function () { hint.remove(); });
		summary.append(printButton, document.createTextNode(' '), dismissButton);
		hint.append(summary);
		document.body.insertBefore(hint, document.body.firstChild);
	}

	function showError(status, error) {
		var detail = document.createElement('pre');
		var source = document.querySelector('.pagedwpm-content');
		if (source) {
			source.hidden = false;
		}
		setStatus(status, message('failed', 'The document could not be fully paginated.'), 'error');
		detail.className = 'pagedwpm-error-detail';
		detail.textContent = error && error.message ? error.message : String(error);
		status.append(detail);
		console.error('[PagedWPM] Pagination failed.', error);
	}

	async function start() {
		if (started) {
			return;
		}
		started = true;

		var status = createStatus();
		try {
			removeHiddenElements();
			await prepareImages(status);
			await prepareFonts();

			if (!window.PagedPolyfill || typeof window.PagedPolyfill.preview !== 'function') {
				throw new Error(message('libraryMissing', 'The local Paged.js library could not be loaded.'));
			}

			setStatus(status, message('paginating', 'Laying out pages…'), 'working');
			var flow = await paginationWithTimeout(numberSetting('paginationTimeoutMs', 120000, 5000));
			assertComplete(flow);
			status.remove();
			addPrintHint(flow);
			console.log('[PagedWPM] Paged.js rendered ' + flow.total + ' pages.');

			if (new URLSearchParams(window.location.search).get('print') === 'true') {
				window.print();
			}
		} catch (error) {
			showError(status, error);
		}
	}

	window.PagedWPM = { start: start };
	window.addEventListener('beforeprint', hideControlsForPrint);
	window.addEventListener('afterprint', restoreControlsAfterPrint);
	window.addEventListener('unhandledrejection', function (event) {
		console.error('[PagedWPM] Unhandled promise rejection.', event.reason);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		// The local Paged.js script may be the next script in the document.
		window.setTimeout(start, 0);
	}
})();
