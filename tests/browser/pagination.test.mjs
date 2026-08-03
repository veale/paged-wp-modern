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
	const css = assets.css.replaceAll('%%PAGEDWPM_ASSET_URL%%', origin + '/');
	return `<!doctype html><html lang="en-GB" class="pagedwpm-microtype-enhanced"><head>
		<style>${css}</style><script>var endNoteCalloutsQuery='a[href*="footnote"], .footnote-ref';</script></head><body>
		<article class="pagedwpm-content"><div class="pagedwpm-body">
		${paragraphs('Before', 35)}
		<figure><img src="${imagePath}" alt="Evidence chart"><figcaption>Figure 1. Evidence chart.</figcaption></figure>
		<p>Text with a note<sup><a class="footnote-ref" href="#footnote%3A1.2">[1]</a></sup>.</p>
		${paragraphs('After', 55)}
		<ol><li id="footnote:1.2"><p>A representative legal footnote. <a href="#footnote-ref-1">↑</a></p></li></ol>
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
		fontFamily: getComputedStyle(document.querySelector('.pagedjs_pages p')).fontFamily,
		textIndent: getComputedStyle(document.querySelector('.pagedjs_pages p')).textIndent,
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
	await page.close();
	return result;
}

test('renders all content and converts footnotes with a valid image', async () => {
	const result = await render('/normal');
	assert.equal(result.error, '');
	assert.equal(result.complete, true);
	assert.equal(result.failedImages, 0);
	assert.equal(result.footnoteCalls, 1);
	assert.equal(result.sourceHidden, true);
	assert.equal(result.screenHintDisplay, 'block');
	assert.equal(result.printHintDisplay, 'none');
	assert.match(result.fontFamily, /PagedWPM Source Serif/);
	assert.notEqual(result.textIndent, '0px');
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
