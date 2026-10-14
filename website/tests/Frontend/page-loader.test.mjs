import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/views/components/page-loader.blade.php', import.meta.url), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];

function mount({ readyState = 'loading', reducedMotion = false } = {}) {
    const events = {};
    const transitions = {};
    const classes = new Set();
    const attributes = new Map();
    const loader = {
        hidden: true,
        removed: false,
        setAttribute: (key, value) => attributes.set(key, value),
        classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name), contains: (name) => classes.has(name) },
        addEventListener: (name, callback) => { transitions[name] = callback; },
        remove() { this.removed = true; },
    };
    const document = {
        readyState,
        baseURI: 'http://localhost/PHDHRM/website/public/news',
        getElementById: () => loader,
        body: {
            setAttribute: (key, value) => attributes.set(key, value),
            removeAttribute: (key) => attributes.delete(key),
        },
    };
    const window = {
        location: new URL(document.baseURI),
        matchMedia: () => ({ matches: reducedMotion }),
        addEventListener: (name, callback) => { events[name] = callback; },
    };
    // No timers are provided: dismissal must not depend on an artificial delay.
    runInNewContext(script, { document, window, URL });
    return { loader, events, transitions, classes, attributes };
}

test('starts fade immediately at load and retains the hidden loader for navigation', () => {
    const state = mount();
    assert.equal(state.loader.hidden, false);
    state.events.load();
    assert.ok(state.classes.has('is-leaving'));
    assert.equal(state.attributes.has('aria-busy'), false);
    assert.equal(state.attributes.get('aria-hidden'), 'true');
    state.transitions.transitionend({ target: state.loader, propertyName: 'opacity' });
    assert.equal(state.loader.hidden, true);
    assert.equal(state.loader.removed, false);
});

test('an already-loaded page never shows the loader', () => {
    const { loader } = mount({ readyState: 'complete' });
    assert.equal(loader.hidden, true);
    assert.equal(loader.removed, false);
});

test('reduced motion dismisses without waiting for a transition', () => {
    const state = mount({ reducedMotion: true });
    state.events.load();
    assert.equal(state.loader.hidden, true);
});

test('restoring from browser back/forward cache cannot leave an overlay', () => {
    const state = mount();
    state.events.pageshow({ persisted: true });
    assert.equal(state.loader.hidden, true);
    assert.equal(state.attributes.has('aria-busy'), false);
});

function element(tagName, attributes = {}, optedOut = false) {
    return {
        tagName,
        getAttribute: (key) => attributes[key] ?? null,
        hasAttribute: (key) => Object.hasOwn(attributes, key),
        closest(selector) {
            if (selector === '[data-no-page-loader]') return optedOut ? this : null;
            return selector === 'a[href]' && tagName === 'A' ? this : null;
        },
    };
}

function click(state, href, attributes = {}, event = {}, optedOut = false) {
    state.events.click({ button: 0, target: element('A', { href, ...attributes }, optedOut), ...event });
}

test('internal page links show the loader during the outgoing server-response wait', () => {
    const state = mount({ readyState: 'complete' });
    click(state, '/PHDHRM/website/public/legal-documents');
    assert.equal(state.loader.hidden, false);
    assert.equal(state.attributes.get('aria-busy'), 'true');
    assert.equal(state.attributes.get('aria-hidden'), 'false');
});

test('category and pagination links show the loader without interception or delay', () => {
    for (const href of ['?category=training', '?page=2#articles']) {
        const state = mount({ readyState: 'complete' });
        click(state, href);
        assert.equal(state.loader.hidden, false);
    }
});

test('non-navigation links do not strand the current page behind an overlay', () => {
    for (const href of ['#articles', '#', '/PHDHRM/website/public/news#articles', 'mailto:test@example.com', 'tel:119', 'javascript:void(0)', 'https://example.com/news', '/PHDHRM/website/public/legal-documents/1/download', '/storage/report.pdf', '/storage/image.webp']) {
        const state = mount({ readyState: 'complete' });
        click(state, href);
        assert.equal(state.loader.hidden, true, href);
    }
});

test('new tabs, downloads, modified clicks, handled events and opt-outs are ignored', () => {
    for (const [attributes, event, optedOut] of [
        [{ target: '_blank' }, {}, false], [{ download: '' }, {}, false],
        [{}, { ctrlKey: true }, false], [{}, { metaKey: true }, false],
        [{}, { shiftKey: true }, false], [{}, { altKey: true }, false],
        [{}, { button: 1 }, false], [{}, { defaultPrevented: true }, false],
        [{}, {}, true],
    ]) {
        const state = mount({ readyState: 'complete' });
        click(state, '/PHDHRM/website/public/about', attributes, event, optedOut);
        assert.equal(state.loader.hidden, true);
    }
});

test('native search GET and contact POST forms show the loader', () => {
    for (const method of ['get', 'post']) {
        const state = mount({ readyState: 'complete' });
        state.events.submit({ target: element('FORM', { action: '/PHDHRM/website/public/contact', method }) });
        assert.equal(state.loader.hidden, false);
    }
});

test('form overrides, prevented AJAX forms, dialogs and new targets are respected', () => {
    for (const event of [
        { defaultPrevented: true },
        { submitter: element('BUTTON', { formtarget: '_blank' }) },
        { submitter: element('BUTTON', { formaction: 'https://example.com/submit' }) },
        { submitter: element('BUTTON', { formmethod: 'dialog' }) },
    ]) {
        const state = mount({ readyState: 'complete' });
        state.events.submit({ target: element('FORM', { action: '/PHDHRM/website/public/contact' }), ...event });
        assert.equal(state.loader.hidden, true);
    }
});

test('a new navigation during fade-out is not hidden by the previous transition', () => {
    const state = mount();
    state.events.load();
    click(state, '?page=2');
    state.transitions.transitionend({ target: state.loader, propertyName: 'opacity' });
    assert.equal(state.loader.hidden, false);
    assert.equal(state.classes.has('is-leaving'), false);
});

test('Escape and back/forward restoration recover a pending navigation', () => {
    const state = mount({ readyState: 'complete' });
    click(state, '?page=2');
    state.events.keydown({ key: 'Escape' });
    assert.equal(state.loader.hidden, true);
    click(state, '?page=3');
    state.events.pageshow({ persisted: true });
    assert.equal(state.loader.hidden, true);
    assert.equal(state.attributes.has('aria-busy'), false);
});

test('failed logo images stay invisible and try the default only once', () => {
    const logo = readFileSync(new URL('../../resources/views/components/organization-logo.blade.php', import.meta.url), 'utf8');
    const onerror = logo.match(/onerror="([^"]+)"/)[1];
    const onload = logo.match(/onload="([^"]+)"/)[1];
    const image = { style: { opacity: '0' }, dataset: { fallback: '/default.png' }, previousElementSibling: { hidden: false } };
    const error = new Function(onerror);
    const loaded = new Function(onload);
    error.call(image);
    assert.equal(image.style.opacity, '0');
    assert.equal(image.src, '/default.png');
    assert.equal(image.dataset.fallback, undefined);
    error.call(image);
    assert.equal(image.previousElementSibling.hidden, false);
    loaded.call(image);
    assert.equal(image.style.opacity, '1');
    assert.equal(image.previousElementSibling.hidden, true);
});
