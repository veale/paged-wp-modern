=== Paged WP Modern ===

Contributors: veale
Tags: pdf, print, paged, footnotes, mammoth, typography
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate beautiful paginated PDFs from WordPress posts and pages with proper page-bottom footnotes, powered by Paged.js.

== Description ==

Paged WP Modern turns your WordPress posts and pages into paginated, print-ready documents with proper page-bottom footnotes — directly in your browser.

**The problem it solves:** If you import Word documents into WordPress using Mammoth, your footnotes become endnotes (a list at the bottom of the HTML). Every existing WordPress PDF plugin renders those as endnotes too. This plugin converts them back into real page-bottom footnotes using Paged.js and the CSS Paged Media specification.

**How it works:**

1. Click "Paged Preview" in the post/page editor (works with both Classic Editor and Gutenberg)
2. A new tab opens showing your content laid out as paginated A4/Letter pages
3. Footnotes from Mammoth-imported documents are automatically detected and moved to the bottom of the correct page
4. Press Ctrl+P / ⌘P and choose "Save as PDF"

**Features:**

* Proper page-bottom footnotes (not endnotes) from Mammoth and Pandoc HTML
* Works with both Gutenberg and Classic Editor
* Customizable page CSS (page size, margins, fonts, etc.)
* Running headers and page numbers
* [paged_download] shortcode for front-end "View as PDF" button
* Clean, professional default typography
* Enhanced academic microtypography by default, with progressive browser fallbacks
* Resilient image loading: damaged or stalled images cannot truncate the article
* Local bundled Paged.js — no runtime CDN dependency
* Locally bundled Source Serif 4 variable font with optical sizing and system-serif fallback
* Updates from tagged GitHub releases through the normal WordPress updater
* Configurable footnote detection (works out of the box with Mammoth and Pandoc patterns)
* No server-side dependencies — runs entirely in the browser using Paged.js

**Supported footnote formats:**

* Mammoth .docx converter (WordPress plugin) — footnotes and endnotes
* Pandoc-generated HTML (.footnote-ref links)
* Any tool that produces superscript links pointing to an endnote list

== Installation ==

1. Download the plugin zip file
2. Go to Plugins → Add New → Upload Plugin
3. Upload the zip and activate
4. Open any post or page in the editor and click "Paged Preview"

== Configuration ==

Go to Settings → Paged WP Modern to:

* Toggle author name and date display
* Customize the CSS selector used to detect footnote callouts (default works with Mammoth and Pandoc)
* Add custom Paged Media CSS (change page size, margins, fonts, etc.)
* Choose enhanced or standard browser typography
* Configure image and pagination timeouts
* Opt into unattended installation of tagged GitHub releases

**Common CSS customizations:**

    /* US Letter instead of A4 */
    @page { size: letter; }

    /* 1-inch margins */
    @page { margin: 1in; }

    /* Sans-serif font */
    body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }

    /* No running headers */
    @page { @top-center { content: none; } }

== Shortcode ==

Add a "View as PDF" button to any post or page:

    [paged_download]
    [paged_download text="Download PDF version"]
    [paged_download text="Print version" class="my-custom-button" style=""]

== How Footnotes Work ==

When Mammoth converts a Word .docx file to HTML, footnotes become:

* Inline: `<sup><a href="#post-123-footnote-1">[1]</a></sup>`
* At bottom: `<ol><li id="post-123-footnote-1">Footnote text</li></ol>`

This plugin's JavaScript detects these callout links, extracts the footnote text, creates inline `<span>` elements with `float: footnote` CSS, and removes the original endnote list. Paged.js then lays out these spans at the bottom of the correct page during pagination.

== Requirements ==

* **Chrome or Chromium-based browser** (Edge, Brave, etc.) for best results. Firefox has partial support. Safari is not recommended.
* Posts must be **published** (or at least saved with a permalink) for the preview to work.

== Frequently Asked Questions ==

= Why does it only work well in Chrome? =

Paged.js relies on the browser's rendering engine to lay out pages. Chrome/Chromium provides the most consistent results. Firefox works but may have minor layout differences. Safari has known issues with Paged.js.

= Can visitors download PDFs without Ctrl+P? =

The current version requires the browser print dialog. Fully automated PDF generation would require a server-side headless browser (Puppeteer/Chrome), which is a planned future feature.

= Does it work with footnotes from other sources? =

Yes — you can configure the CSS selector in Settings to match any footnote HTML pattern. The default selector works with Mammoth and Pandoc output.

== Changelog ==

= 2.1.1 =
* Fixed the unpaginated source article remaining visible above the paginated output
* Paginate a detached source clone while retaining the original only as an error fallback
* Added regression coverage preventing duplicate visible article content

= 2.1.0 =
* Prevented stalled image requests from silently truncating articles
* Added resource preflight, timeouts, visible diagnostics, and end-of-document verification
* Added professional academic microtypography as the progressive default
* Constrained oversized figures to the printable page area
* Hardened footnote detection, ID handling, repeated references, and error isolation
* Bundled Paged.js locally and removed the runtime CDN dependency
* Added GitHub Actions validation, release ZIP builds, and GitHub release updates
* Added browser regression tests for valid and permanently pending images

= 2.0.0 =
* Complete rewrite based on Paged WP by Electric Book Works
* Added proper footnote support via Paged.js footnotes module
* Added Mammoth-specific endnote-to-footnote conversion
* Added Gutenberg editor support
* Added [paged_download] shortcode
* Added settings page with custom CSS editor
* Updated to Paged.js 0.4.3
* Fixed PHP 8.1+ deprecation warnings
* Modernized code structure

== Credits ==

* [Paged.js](https://pagedjs.org/) by the Coko Foundation — the engine that makes this possible
* [Source Serif 4](https://github.com/adobe-fonts/source-serif) by Adobe — the default publication serif (SIL Open Font License)
* [Electric Book Works](https://electricbookworks.com/) — original Paged WP plugin (v1.0.3)
* [Mammoth](https://github.com/mwilliamson/mammoth.js) by Michael Williamson — the Word-to-HTML converter
