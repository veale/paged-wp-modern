/**
 * Converts Mammoth/Pandoc endnotes to Paged.js page-bottom footnotes.
 */
(function () {
	'use strict';

	var registered = false;

	function registerHandler() {
		if (registered || typeof Paged === 'undefined' || !Paged.registerHandlers) {
			return false;
		}

		class EndToFootNotes extends Paged.Handler {
			beforeParsed(content) {
				convertFootnotes(content);
			}
		}

		Paged.registerHandlers(EndToFootNotes);
		registered = true;
		return true;
	}

	function decodeFragment(href) {
		if (!href || href.indexOf('#') === -1) {
			return '';
		}
		var fragment = href.slice(href.indexOf('#') + 1);
		try {
			return decodeURIComponent(fragment);
		} catch (error) {
			return fragment;
		}
	}

	function buildIdMap(content) {
		var map = new Map();
		content.querySelectorAll('[id]').forEach(function (element) {
			if (!map.has(element.id)) {
				map.set(element.id, element);
			}
		});
		return map;
	}

	function findNote(noteId, idMap) {
		if (idMap.has(noteId)) {
			return idMap.get(noteId);
		}

		var relaxed = noteId.replace(/^.*?(?=footnote|endnote|fn)/i, '');
		var match = null;
		idMap.forEach(function (element, id) {
			if (!match && relaxed && id.endsWith(relaxed)) {
				match = element;
			}
		});
		return match;
	}

	function isBackReferenceTarget(element) {
		return !element || ['A', 'SUP'].indexOf(element.tagName) !== -1 ||
			element.classList.contains('pagedwpm-fn-call');
	}

	function cleanNote(note) {
		var clone = note.cloneNode(true);
		clone.removeAttribute('id');
		clone.querySelectorAll('a').forEach(function (anchor) {
			var text = anchor.textContent.trim();
			var href = anchor.getAttribute('href') || '';
			if (/ref|backref/i.test(href) || /^(↑|↩|↩︎)$/.test(text)) {
				anchor.remove();
			}
		});
		clone.querySelectorAll('p, div, span').forEach(function (element) {
			if (!element.textContent.trim() && !element.children.length) {
				element.remove();
			}
		});

		var onlyChild = clone.children.length === 1 ? clone.children[0] : null;
		var hasDirectText = Array.from(clone.childNodes).some(function (node) {
			return node.nodeType === Node.TEXT_NODE && node.textContent.trim();
		});
		if (onlyChild && onlyChild.tagName === 'P' && !hasDirectText) {
			return onlyChild.innerHTML.trim();
		}
		return clone.innerHTML.trim();
	}

	function makeCall(number) {
		var call = document.createElement('sup');
		call.className = 'pagedwpm-fn-call';
		call.textContent = String(number);
		call.setAttribute('aria-label', 'Footnote ' + number);
		return call;
	}

	function makeFootnote(number, html) {
		var footnote = document.createElement('span');
		var marker = document.createElement('span');
		footnote.className = 'pagedwpm-footnote';
		marker.className = 'pagedwpm-fn-marker';
		marker.textContent = number + '. ';
		footnote.append(marker);
		footnote.insertAdjacentHTML('beforeend', html);
		return footnote;
	}

	function cleanEmptyContainers(note) {
		var container = note.parentElement;
		note.remove();
		while (container && ['OL', 'UL', 'SECTION', 'DIV'].indexOf(container.tagName) !== -1) {
			if (container.children.length || container.textContent.trim()) {
				break;
			}
			var parent = container.parentElement;
			container.remove();
			container = parent;
		}
	}

	function convertFootnotes(content) {
		if (typeof endNoteCalloutsQuery === 'undefined' || !endNoteCalloutsQuery) {
			return;
		}

		var callouts = new Set();
		endNoteCalloutsQuery.split(',').map(function (selector) {
			return selector.trim();
		}).filter(Boolean).forEach(function (selector) {
			try {
				content.querySelectorAll(selector).forEach(function (element) {
					callouts.add(element);
				});
			} catch (error) {
				console.warn('[PagedWPM] Ignoring invalid footnote selector:', selector, error);
			}
		});

		var idMap = buildIdMap(content);
		var noteNumbers = new Map();
		var processedNotes = new Set();
		var nextNumber = 1;
		var converted = 0;

		callouts.forEach(function (callout) {
			try {
				if (!callout.isConnected && !callout.parentNode) {
					return;
				}
				var noteId = decodeFragment(callout.getAttribute('href'));
				var note = findNote(noteId, idMap);
				if (isBackReferenceTarget(note)) {
					return;
				}

				var number = noteNumbers.get(noteId);
				if (!number) {
					number = nextNumber++;
					noteNumbers.set(noteId, number);
				}

				var html = cleanNote(note);
				if (!html) {
					return;
				}

				var target = callout.parentElement && callout.parentElement.tagName === 'SUP' ?
					callout.parentElement : callout;
				var call = makeCall(number);
				var footnote = makeFootnote(number, html);
				target.before(call, footnote);
				target.remove();
				processedNotes.add(note);
				converted += 1;
			} catch (error) {
				console.warn('[PagedWPM] Could not convert a footnote callout.', callout, error);
			}
		});

		processedNotes.forEach(cleanEmptyContainers);
		console.log('[PagedWPM] Converted ' + converted + ' footnote callout(s).');
	}

	window.PagedWPMFootnotes = {
		convert: convertFootnotes,
		register: registerHandler
	};

	if (!registerHandler()) {
		document.addEventListener('DOMContentLoaded', registerHandler, { once: true });
	}
})();
