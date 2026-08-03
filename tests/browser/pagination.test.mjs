import assert from 'node:assert/strict';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import test, { after, before } from 'node:test';
import puppeteer from 'puppeteer-core';

const root = path.resolve(import.meta.dirname, '../..');
const assets = {
	controller: fs.readFileSync(path.join(root, 'assets/js/paged-preview.js')),
	css: fs.readFileSync(path.join(root, 'assets/css/paged-default.css'), 'utf8'),
	footnotes: fs.readFileSync(path.join(root, 'assets/js/footnote-converter.js')),
	paged: fs.readFileSync(path.join(root, 'assets/vendor/pagedjs/paged.polyfill.min.js')),
	fonts: Object.fromEntries(fs.readdirSync(path.join(root, 'assets/vendor/source-serif-4'))
		.filter((file) => file.endsWith('.woff2'))
		.map((file) => [file, fs.readFileSync(path.join(root, 'assets/vendor/source-serif-4', file))]))
};
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6WQAAAABJRU5ErkJggg==', 'base64');
let browser;
let server;
let origin;

function chromePath() {
	const candidates = [
		process.env.CHROME_PATH,
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
		'/usr/bin/google-chrome',
		'/usr/bin/google-chrome-stable',
		'/usr/bin/chromium'
	].filter(Boolean);
	return candidates.find((candidate) => fs.existsSync(candidate));
}

function paragraphs(prefix, count) {
	return Array.from({ length: count }, (_, index) =>
		`<p>${prefix} ${index}. Academic legal prose exercises line breaking, justification, hyphenation, and multi-page fragmentation.</p>`
	).join('');
}

function documentHtml(imagePath) {
	const config = JSON.stringify({
		hideSelectors: [],
		imageTimeoutMs: 300,
		fontTimeoutMs: 100,
		paginationTimeoutMs: 15000,
		messages: {}
	});
	const css = assets.css
		.replaceAll('%%PAGEDWPM_ASSET_URL%%', origin + '/')
		.replaceAll('%%PAGEDWPM_JOURNAL_HEAD%%', '"Current Legal Problems, Vol 79"')
		.replaceAll('%%PAGEDWPM_ARTICLE_HEAD%%', '"Demanding Inheritance: A Typeset Legal Article"')
		.replaceAll('%%PAGEDWPM_ACCENT%%', '#163c73')
		.replaceAll('%%PAGEDWPM_LABEL_FONT%%', "-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif");
	return `<!doctype html><html lang="en-GB" class="pagedwpm-microtype-enhanced" style="--pagedwpm-accent: #163c73; --pagedwpm-label-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;"><head>
		<style>${css}</style><script>var endNoteCalloutsQuery='a[href*="footnote"], .footnote-ref';</script></head><body>
		<article class="pagedwpm-content">
		<header class="pagedwpm-header">
			<h1>Demanding Inheritance: A Typeset Legal Article</h1>
			<p class="author">A. N. Author</p>
			<p class="extra-line">Recommended citation: (2026) 79 Current Legal Problems</p>
			<aside class="abstract pagedwpm-abstract pagedwpm-abstract--plain pagedwpm-abstract-gap--triple" aria-label="Abstract">
				<p class="abstract-copy"><span class="abstract-heading">Abstract</span><span class="abstract-text">This article examines the legal construction of family and inheritance through public inquiry. It demonstrates a publication-quality abstract with a deliberately restrained measure, justified serif text, and a compact inline label.</span></p>
			</aside>
		</header>
		<div class="pagedwpm-body">
		${paragraphs('Before', 35)}
		<p data-test-indent="pixels" style="padding-left: 40px;">An imported quotation whose original left padding should become a balanced print indent without an additional first-line indent.</p>
		<figure><img src="${imagePath}" alt="Evidence chart"><figcaption>Figure 1. Evidence chart.</figcaption></figure>
		<p>Text with a note<sup><a class="footnote-ref" href="#footnote%3A1.2">[1]</a></sup>, an empty imported note<sup><a class="footnote-ref" data-test-empty-note href="#footnote%3A3">[3]</a></sup>, and malformed imported notes<sup><a class="footnote-ref" href="#footnote%3A76">[76]</a></sup><sup><a class="footnote-ref" href="#footnote%3A78">[78]</a></sup>.</p>
		${paragraphs('After', 55)}
		<ol>
			<li id="footnote:1.2"><p>A representative legal footnote. <a href="#footnote-ref-1">↑</a></p></li>
			<li id="footnote:3"><p><a href="#footnote-ref-3">↑</a></p></li>
			<li id="footnote:76">[1983] QB 1053 <a href="#footnote-ref-76">↑</a></p></li>
			<li id="footnote:78">[2004] UKHL 21, para 52. <a href="#footnote-ref-78">↑</a></p></li>
		</ol>
		<span data-pagedwpm-end-marker></span></div></article>
		<script id="pagedwpm-config" type="application/json">${config}</script>
		<script src="/footnotes.js"></script><script src="/controller.js"></script><script src="/paged.js"></script>
		</body></html>`;
}

before(async () => {
	const executablePath = chromePath();
	assert.ok(executablePath, 'Chrome/Chromium is required; set CHROME_PATH when it is not in a standard location.');
	server = http.createServer((request, response) => {
		if (request.url === '/hang.png') {
			return;
		}
		if (request.url === '/ok.png') {
			response.writeHead(200, { 'Content-Type': 'image/png', 'Content-Length': png.length });
			response.end(png);
			return;
		}
		if (request.url.includes('/source-serif-4/')) {
			const font = assets.fonts[path.basename(request.url)];
			response.writeHead(font ? 200 : 404, { 'Content-Type': 'font/woff2' });
			response.end(font || '');
			return;
		}
		const files = {
			'/controller.js': ['application/javascript', assets.controller],
			'/footnotes.js': ['application/javascript', assets.footnotes],
			'/paged.js': ['application/javascript', assets.paged]
		};
		if (files[request.url]) {
			response.writeHead(200, { 'Content-Type': files[request.url][0] });
			response.end(files[request.url][1]);
			return;
		}
		response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		response.end(documentHtml(request.url === '/stalled' ? '/hang.png' : '/ok.png'));
	});
	await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
	origin = `http://127.0.0.1:${server.address().port}`;
	browser = await puppeteer.launch({ executablePath, headless: true, args: ['--no-sandbox'] });
});

after(async () => {
	await browser?.close();
	server?.closeAllConnections();
	await new Promise((resolve) => server?.close(resolve));
});

async function render(route) {
	const page = await browser.newPage();
	await page.goto(origin + route, { waitUntil: 'domcontentloaded' });
	try {
		await page.waitForFunction(() => document.querySelector('#pagedwpm-print-hint, #pagedwpm-status[data-state="error"]'), { timeout: 20000 });
	} catch (error) {
		const diagnostic = await page.evaluate(() => ({
			url: location.href,
			body: document.body?.innerHTML.slice(0, 300),
			status: document.querySelector('#pagedwpm-status')?.outerHTML,
			images: Array.from(document.images).map((image) => ({ src: image.src, complete: image.complete })),
			pages: document.querySelectorAll('.pagedjs_page').length
		}));
		throw new Error(`${error.message}: ${JSON.stringify(diagnostic)}`);
	}
	const result = await page.evaluate(() => ({
		complete: Boolean(document.querySelector('.pagedjs_pages [data-pagedwpm-end-marker]')),
		error: document.querySelector('#pagedwpm-status[data-state="error"]')?.textContent || '',
		failedImages: document.querySelectorAll('.pagedjs_pages .pagedwpm-image-error').length,
		footnoteCalls: document.querySelectorAll('.pagedjs_pages .pagedwpm-fn-call').length,
		footnoteTexts: Array.from(document.querySelectorAll('.pagedjs_pages .pagedwpm-footnote'))
			.map((footnote) => footnote.textContent.trim()).filter(Boolean),
		footnoteMarkers: Array.from(document.querySelectorAll('.pagedjs_pages .pagedwpm-fn-marker'))
			.map((marker) => marker.textContent.trim()),
		emptyNoteCalloutPresent: Boolean(document.querySelector('.pagedjs_pages [data-test-empty-note]')),
		remainingEndnotes: document.querySelectorAll('.pagedjs_pages li[id*="footnote"], .pagedjs_pages li[id*="endnote"]').length,
		fontFamily: getComputedStyle(document.querySelector('.pagedjs_pages .pagedwpm-body > p')).fontFamily,
		textIndent: getComputedStyle(document.querySelector('.pagedjs_pages .pagedwpm-body > p')).textIndent,
		frontMatter: (() => {
			const title = document.querySelector('.pagedjs_pages .pagedwpm-header h1');
			const author = document.querySelector('.pagedjs_pages .pagedwpm-header .author');
			const extra = document.querySelector('.pagedjs_pages .pagedwpm-header .extra-line');
			return {
				titleColor: getComputedStyle(title).color,
				titleFont: getComputedStyle(title).fontFamily,
				titleOpacity: getComputedStyle(title).opacity,
				authorColor: getComputedStyle(author).color,
				authorFont: getComputedStyle(author).fontFamily,
				authorOpacity: getComputedStyle(author).opacity,
				extraColor: getComputedStyle(extra).color,
				extraFont: getComputedStyle(extra).fontFamily,
				extraOpacity: getComputedStyle(extra).opacity
			};
		})(),
		abstract: (() => {
			const block = document.querySelector('.pagedjs_pages .pagedwpm-abstract');
			const copy = block?.querySelector('.abstract-copy');
			const label = block?.querySelector('.abstract-heading');
			const text = block?.querySelector('.abstract-text');
			return block && copy && label && text ? {
				accent: getComputedStyle(label).color,
				borderLeftWidth: getComputedStyle(block).borderLeftWidth,
				copyTextAlign: getComputedStyle(copy).textAlign,
				copyTextIndent: getComputedStyle(copy).textIndent,
				fontFamily: getComputedStyle(label).fontFamily,
				fontWeight: getComputedStyle(label).fontWeight,
				gap: getComputedStyle(label).marginInlineEnd,
				inlineLabel: getComputedStyle(label).display,
				inlineText: getComputedStyle(text).display,
				textColor: getComputedStyle(text).color,
				maxWidth: getComputedStyle(block).maxWidth,
				opacity: getComputedStyle(block).opacity,
				oneParagraph: label.parentElement === text.parentElement && label.parentElement === copy
			} : null;
		})(),
		runningHeads: Array.from(document.querySelectorAll('.pagedjs_page')).slice(0, 3).map((pagedPage) => {
			const head = pagedPage.querySelector('.pagedjs_margin-top-center .pagedjs_margin-content');
			const generated = head ? getComputedStyle(head, '::after').content : 'none';
			if (generated === 'none' || generated === 'normal') return '';
			try { return JSON.parse(generated); } catch { return generated.replace(/^['"]|['"]$/g, ''); }
		}),
		folios: Array.from(document.querySelectorAll('.pagedjs_page')).slice(0, 3).map((pagedPage) => {
			const folio = pagedPage.querySelector('.pagedjs_margin-bottom-center .pagedjs_margin-content');
			if (!folio) return null;
			const pseudo = getComputedStyle(folio, '::after');
			const generated = pseudo.content;
			return {
				text: pagedPage.dataset.pageNumber || '',
				content: generated,
				fontFamily: pseudo.fontFamily,
				fontWeight: pseudo.fontWeight
			};
		}),
		importedIndents: Array.from(document.querySelectorAll('.pagedjs_pages .pagedwpm-imported-indent')).map((paragraph) => ({
			kind: paragraph.dataset.testIndent,
			paddingLeft: getComputedStyle(paragraph).paddingLeft,
			paddingRight: getComputedStyle(paragraph).paddingRight,
			textIndent: getComputedStyle(paragraph).textIndent
		})),
		footnoteStyle: (() => {
			const call = document.querySelector('.pagedjs_pages .pagedwpm-fn-call');
			const marker = document.querySelector('.pagedjs_pages .pagedwpm-fn-marker');
			const footnote = marker?.closest('.pagedwpm-footnote');
			return call && footnote && marker ? {
				callColor: getComputedStyle(call).color,
				callFont: getComputedStyle(call).fontFamily,
				callFontSize: getComputedStyle(call).fontSize,
				callOpacity: getComputedStyle(call).opacity,
				footnoteFontSize: getComputedStyle(footnote).fontSize,
				footnoteOpacity: getComputedStyle(footnote).opacity,
				markerColor: getComputedStyle(marker).color,
				markerFont: getComputedStyle(marker).fontFamily,
				markerFontSize: getComputedStyle(marker).fontSize
			} : null;
		})(),
		sourceHidden: document.querySelector('body > .pagedwpm-content')?.hidden,
		pages: document.querySelectorAll('.pagedjs_page').length
	}));
	result.screenHintDisplay = await page.$eval('#pagedwpm-print-hint', (hint) => getComputedStyle(hint).display);
	await page.emulateMediaType('print');
	result.printHintDisplay = await page.$eval('#pagedwpm-print-hint', (hint) => getComputedStyle(hint).display);
	await page.emulateMediaType('screen');
	if (process.env.PAGEDWPM_SCREENSHOT && route === '/normal') {
		await page.screenshot({ path: process.env.PAGEDWPM_SCREENSHOT, fullPage: true });
	}
	if (process.env.PAGEDWPM_PDF && route === '/normal') {
		await page.pdf({ path: process.env.PAGEDWPM_PDF, printBackground: true, preferCSSPageSize: true });
	}
	await page.close();
	return result;
}

test('renders all content and converts footnotes with a valid image', async () => {
	const result = await render('/normal');
	assert.equal(result.error, '');
	assert.equal(result.complete, true);
	assert.equal(result.failedImages, 0);
	assert.equal(result.footnoteCalls, 3);
	assert.ok(result.footnoteTexts.some((text) => text.includes('[1983] QB 1053')));
	assert.ok(result.footnoteTexts.some((text) => text.includes('[2004] UKHL 21, para 52.')));
	assert.deepEqual(result.footnoteMarkers, ['1.', '3.', '4.']);
	assert.equal(result.emptyNoteCalloutPresent, false);
	assert.equal(result.remainingEndnotes, 0);
	assert.equal(result.sourceHidden, true);
	assert.equal(result.screenHintDisplay, 'block');
	assert.equal(result.printHintDisplay, 'none');
	assert.match(result.fontFamily, /PagedWPM Source Serif/);
	assert.notEqual(result.textIndent, '0px');
	assert.equal(result.frontMatter.titleColor, 'rgb(22, 60, 115)');
	assert.equal(result.frontMatter.authorColor, 'rgb(22, 60, 115)');
	assert.equal(result.frontMatter.extraColor, 'rgb(22, 60, 115)');
	assert.match(result.frontMatter.titleFont, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.match(result.frontMatter.authorFont, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.match(result.frontMatter.extraFont, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.ok(Number.parseFloat(result.frontMatter.titleOpacity) < 1);
	assert.ok(Number.parseFloat(result.frontMatter.authorOpacity) < 1);
	assert.ok(Number.parseFloat(result.frontMatter.extraOpacity) < 1);
	assert.ok(result.abstract);
	assert.equal(result.abstract.inlineLabel, 'inline');
	assert.equal(result.abstract.inlineText, 'inline');
	assert.equal(result.abstract.oneParagraph, true);
	assert.equal(result.abstract.copyTextAlign, 'justify');
	assert.equal(result.abstract.copyTextIndent, '0px');
	assert.match(result.abstract.fontFamily, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.equal(result.abstract.fontWeight, '700');
	assert.ok(Number.parseFloat(result.abstract.gap) > 8);
	assert.equal(result.abstract.borderLeftWidth, '0px');
	assert.notEqual(result.abstract.accent, 'rgb(0, 0, 0)');
	assert.equal(result.abstract.textColor, 'rgb(26, 26, 26)');
	assert.ok(Number.parseFloat(result.abstract.maxWidth) > 400);
	assert.equal(result.abstract.opacity, '0.8');
	assert.deepEqual(result.runningHeads, [
		'',
		'Current Legal Problems, Vol 79',
		'Demanding Inheritance: A Typeset Legal Article'
	]);
	assert.deepEqual(result.folios.map((folio) => folio?.text), ['1', '2', '3']);
	assert.ok(result.folios.every((folio) => folio?.content === 'counter(page)'));
	assert.match(result.folios[0].fontFamily, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.ok(Number.parseInt(result.folios[0].fontWeight, 10) >= 600);
	assert.deepEqual(result.importedIndents[0], {
		kind: 'pixels',
		paddingLeft: '40px',
		paddingRight: '40px',
		textIndent: '0px'
	});
	assert.equal(result.importedIndents.length, 1);
	assert.ok(result.footnoteStyle);
	assert.equal(result.footnoteStyle.callColor, 'rgb(22, 60, 115)');
	assert.equal(result.footnoteStyle.markerColor, 'rgb(22, 60, 115)');
	assert.match(result.footnoteStyle.callFont, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.match(result.footnoteStyle.markerFont, /-apple-system|BlinkMacSystemFont|Segoe UI|Arial/);
	assert.equal(result.footnoteStyle.callFontSize, result.footnoteStyle.footnoteFontSize);
	assert.equal(result.footnoteStyle.markerFontSize, result.footnoteStyle.footnoteFontSize);
	assert.ok(Number.parseFloat(result.footnoteStyle.callOpacity) < 1);
	assert.ok(Number.parseFloat(result.footnoteStyle.footnoteOpacity) < 1);
	assert.ok(result.pages > 1);
});

test('replaces a permanently pending image and still renders the article end', async () => {
	const result = await render('/stalled');
	assert.equal(result.error, '');
	assert.equal(result.complete, true);
	assert.ok(result.failedImages >= 1);
	assert.equal(result.sourceHidden, true);
	assert.equal(result.printHintDisplay, 'none');
	assert.ok(result.pages > 1);
});
