import { EditorView, basicSetup } from 'codemirror';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';

function syncPreview(form) {
    const iframe = form.querySelector('[data-html-css-preview]');
    if (!iframe) return;

    const htmlValue = form.querySelector('textarea[data-codemirror="html"]')?.value ?? '';
    const cssValue = form.querySelector('textarea[data-codemirror="css"]')?.value ?? '';

    iframe.srcdoc = `<!doctype html><html><head><style>body{font-family:sans-serif;margin:8px;}${cssValue}</style></head><body>${htmlValue}</body></html>`;
}

export function mountContentEditors() {
    document.querySelectorAll('textarea[data-codemirror]').forEach((textarea) => {
        if (textarea.dataset.cmInitialized) return;
        if (textarea.offsetParent === null) return; // still hidden; try again later
        textarea.dataset.cmInitialized = '1';

        const form = textarea.closest('form');
        const language = textarea.dataset.codemirror === 'css' ? css() : html();

        const wrapper = document.createElement('div');
        wrapper.className = 'cm-wrapper border border-line rounded-[8px] overflow-hidden text-[13px]';
        textarea.insertAdjacentElement('afterend', wrapper);
        textarea.style.display = 'none';

        const view = new EditorView({
            doc: textarea.value,
            extensions: [
                basicSetup,
                language,
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        textarea.value = view.state.doc.toString();
                        if (form) syncPreview(form);
                    }
                }),
            ],
            parent: wrapper,
        });

        if (form) syncPreview(form);
    });
}
