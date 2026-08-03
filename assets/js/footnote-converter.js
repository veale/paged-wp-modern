/**
 * Paged WP Modern — Footnote Converter
 *
 * Transforms endnote-style HTML (as produced by Mammoth, Pandoc, or similar tools)
 * into inline <span> elements that Paged.js can lay out as true page-bottom footnotes.
 *
 * Mammoth produces footnotes like:
 *   Inline:  <sup><a href="#post-123-footnote-1" id="post-123-footnote-ref-1">[1]</a></sup>
 *   Bottom:  <ol><li id="post-123-footnote-1"><p>Text <a href="#post-123-footnote-ref-1">↑</a></p></li></ol>
 *
 * Pandoc produces:
 *   Inline:  <a class="footnote-ref" href="#fn1" id="fnref1"><sup>1</sup></a>
 *   Bottom:  <section class="footnotes"><ol><li id="fn1"><p>Text<a href="#fnref1">↩</a></p></li></ol></section>
 *
 * This script finds all callout links, locates their target note elements,
 * extracts the note text, and creates inline spans with class "pagedwpm-footnote"
 * that Paged.js will pick up via `float: footnote` in CSS.
 *
 * The variable `endNoteCalloutsQuery` must be set before this script loads.
 * It should be a CSS selector string matching the footnote callout elements.
 */

(function () {
	'use strict';

	if (typeof endNoteCalloutsQuery === 'undefined' || !endNoteCalloutsQuery) {
		console.warn('[PagedWPM] endNoteCalloutsQuery not defined, skipping footnote conversion.');
		return;
	}

	/**
	 * Register as a Paged.js handler so conversion happens at the right time
	 * (after DOM is ready but before Paged.js chunks the content into pages).
	 */
	if (typeof Paged !== 'undefined' && Paged.registerHandlers) {
		// Paged.js is already loaded — register directly
		registerHandler();
	} else {
		// Paged.js hasn't loaded yet — store our handler for later registration.
		// We'll also run a fallback DOM-based conversion just in case.
		window._pagedwpmFootnoteHandler = registerHandler;
		document.addEventListener('DOMContentLoaded', function () {
			// If Paged.js is available by now, register
			if (typeof Paged !== 'undefined' && Paged.registerHandlers) {
				registerHandler();
			} else {
				// Wait a tick for Paged.js to load, then try again
				setTimeout(function () {
					if (typeof Paged !== 'undefined' && Paged.registerHandlers) {
						registerHandler();
					} else {
						// Last resort: run conversion directly on the DOM
						console.warn('[PagedWPM] Paged.js not found, running footnote conversion directly on DOM.');
						convertFootnotes(document.body);
					}
				}, 100);
			}
		});
	}

	function registerHandler() {
		class EndToFootNotes extends Paged.Handler {
			constructor(chunker, polisher, caller) {
				super(chunker, polisher, caller);
			}

			beforeParsed(content) {
				convertFootnotes(content);
			}
		}

		Paged.registerHandlers(EndToFootNotes);
	}

	/**
	 * Core conversion logic.
	 *
	 * @param {HTMLElement} content - The content root element.
	 */
	function convertFootnotes(content) {
		// Split the selector by comma to handle multiple patterns
		var selectors = endNoteCalloutsQuery.split(',').map(function (s) { return s.trim(); });
		var callouts = [];

		selectors.forEach(function (sel) {
			try {
				var found = content.querySelectorAll(sel);
				found.forEach(function (el) { callouts.push(el); });
			} catch (e) {
				console.warn('[PagedWPM] Invalid selector "' + sel + '":', e);
			}
		});

		if (callouts.length === 0) {
			console.log('[PagedWPM] No footnote callouts found. Checked selectors:', endNoteCalloutsQuery);
			return;
		}

		console.log('[PagedWPM] Found', callouts.length, 'footnote callout(s). Converting to page footnotes.');

		// Track which note elements we've processed so we can remove them after
		var processedNotes = new Set();
		var footnoteCounter = 0;

		callouts.forEach(function (callout) {
			// Get the href hash to find the target note
			var href = callout.getAttribute('href');
			if (!href || href.indexOf('#') === -1) {
				console.warn('[PagedWPM] Callout has no hash href:', callout);
				return;
			}

			var hash = href.substring(href.indexOf('#'));
			var noteId = hash.substring(1); // Remove the #

			// Find the target note element
			var note = null;
			try {
				note = content.querySelector(hash);
			} catch (e) {
				// ID might have special characters; try getElementById-style
				note = content.querySelector('[id="' + CSS.escape(noteId) + '"]');
			}

			if (!note) {
				// Try a more relaxed match — Mammoth sometimes uses id prefixes
				note = content.querySelector('[id$="' + noteId.replace(/^.*?(?=footnote|endnote|fn)/, '') + '"]');
			}

			if (!note) {
				console.warn('[PagedWPM] Could not find note element for', hash);
				return;
			}

			footnoteCounter++;

			// Extract the note content, excluding any back-reference links
			var noteClone = note.cloneNode(true);

			// Remove back-reference links (↑, ↩, ↩︎, etc.)
			var backLinks = noteClone.querySelectorAll('a[href*="ref"], a[href*="backref"]');
			backLinks.forEach(function (bl) { bl.remove(); });

			// Also remove links that just contain ↑ or ↩ characters
			noteClone.querySelectorAll('a').forEach(function (a) {
				var text = a.textContent.trim();
				if (text === '↑' || text === '↩' || text === '↩︎' || text === '↩\uFE0E') {
					a.remove();
				}
			});

			// Get the clean note HTML
			var noteHTML = noteClone.innerHTML;

			// If the note is an <li>, unwrap it — we just want the inner content
			// Also strip wrapping <p> tags if there's only one
			var tempDiv = document.createElement('div');
			tempDiv.innerHTML = noteHTML;

			// If content is a single <p>, unwrap it for cleaner footnote text
			if (tempDiv.children.length === 1 && tempDiv.children[0].tagName === 'P') {
				noteHTML = tempDiv.children[0].innerHTML;
			} else {
				noteHTML = tempDiv.innerHTML;
			}

			// Trim trailing whitespace
			noteHTML = noteHTML.trim();

			// Create the footnote span that Paged.js will pick up via
			// float:footnote.
			//
			// Paged.js moves footnote elements into isolated page-margin
			// boxes, which breaks CSS counters AND attr() on the
			// ::footnote-call / ::footnote-marker pseudo-elements it
			// generates.  To get continuous 1-to-N numbering we bake
			// the numbers directly into the DOM as visible elements and
			// suppress Paged.js's own numbering via CSS.
			var footnoteSpan = document.createElement('span');
			footnoteSpan.className = 'pagedwpm-footnote';
			footnoteSpan.innerHTML = '<span class="pagedwpm-fn-marker">'
				+ footnoteCounter + '. </span>' + noteHTML;

			// Create the visible inline call (superscript number in body text).
			// This <sup> sits just before the footnote span in the DOM so it
			// stays in the text flow when Paged.js pulls the span to the bottom.
			var callSup = document.createElement('sup');
			callSup.className = 'pagedwpm-fn-call';
			callSup.textContent = String(footnoteCounter);

			// Find the insertion point — we want to insert right after the callout
			// If the callout is inside a <sup>, we replace the <sup> entirely
			var insertionTarget = callout;
			if (callout.parentNode && callout.parentNode.tagName === 'SUP') {
				insertionTarget = callout.parentNode;
			}

			// Insert: callSup first (stays inline), then footnoteSpan (floats to bottom)
			var parent = insertionTarget.parentNode;
			parent.insertBefore(callSup, insertionTarget.nextSibling);
			callSup.parentNode.insertBefore(footnoteSpan, callSup.nextSibling);

			// Remove the original callout link/sup
			insertionTarget.remove();

			// Mark the original note for removal
			processedNotes.add(note);
		});

		// Remove the original note elements (the endnote list items)
		processedNotes.forEach(function (note) {
			var parent = note.parentNode;
			note.remove();

			// If the parent <ol> or <section> is now empty, remove it too
			if (parent && parent.children.length === 0) {
				var grandparent = parent.parentNode;
				parent.remove();

				// And if that was inside a <section class="footnotes">, clean up
				if (grandparent && grandparent.children.length === 0 &&
					grandparent.tagName === 'SECTION') {
					grandparent.remove();
				}
			}
		});

		console.log('[PagedWPM] Converted', footnoteCounter, 'footnote(s) to page footnotes.');
	}

})();
