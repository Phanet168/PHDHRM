// A real WYSIWYG editor for News' "content" field. Mounts over a plain <textarea data-tiptap>,
// same progressive-enhancement pattern as content-editor-cm.js (CodeMirror over a textarea):
// the textarea is the actual form field and source of truth, so the page still works without
// JS; Tiptap only keeps the hidden textarea's value in sync on every edit.
//
// Output HTML is only ever a client-side convenience — the server re-sanitizes every save with
// HtmlSanitizer::clean() (HTMLPurifier's 'cms' allow-list), so this never has to be trusted.
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TiptapImage from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';

function mountOne(textarea) {
    if (textarea.dataset.tiptapMounted) return;
    textarea.dataset.tiptapMounted = '1';

    const root = document.createElement('div');
    root.className = 'news-editor';
    textarea.insertAdjacentElement('beforebegin', root);
    textarea.style.display = 'none';

    const toolbar = document.createElement('div');
    toolbar.className = 'news-editor-toolbar';
    root.appendChild(toolbar);

    const content = document.createElement('div');
    content.className = 'news-editor-content';
    root.appendChild(content);

    const imagePicker = buildImagePicker(textarea.dataset.mediaPickerUrl);
    // JS-created toolbar/content are outside Blade's reach, so labelling for screen readers happens here.
    content.setAttribute('role', 'textbox');
    content.setAttribute('aria-multiline', 'true');
    content.setAttribute('aria-label', textarea.labels?.[0]?.textContent?.trim() || 'ខ្លឹមសារ');
    root.appendChild(imagePicker.el);

    const editor = new Editor({
        element: content,
        extensions: [
            StarterKit,
            Underline,
            Link.configure({ openOnClick: false, autolink: true }),
            TiptapImage,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
        ],
        content: textarea.value,
        onUpdate: ({ editor }) => { textarea.value = editor.getHTML(); },
    });

    buildToolbar(toolbar, editor, imagePicker);
}

function buildToolbar(toolbar, editor, imagePicker) {
    const buttons = [
        { label: 'B', title: 'ដិត', cmd: () => editor.chain().focus().toggleBold().run(), active: 'bold', style: 'font-weight:700' },
        { label: 'I', title: 'ទ្រេត', cmd: () => editor.chain().focus().toggleItalic().run(), active: 'italic', style: 'font-style:italic' },
        { label: 'U', title: 'គូសបន្ទាត់ក្រោម', cmd: () => editor.chain().focus().toggleUnderline().run(), active: 'underline', style: 'text-decoration:underline' },
        { label: 'S', title: 'គូសបន្ទាត់ឆូត', cmd: () => editor.chain().focus().toggleStrike().run(), active: 'strike', style: 'text-decoration:line-through' },
        { sep: true },
        { label: 'H2', title: 'ចំណងជើងធំ', cmd: () => editor.chain().focus().toggleHeading({ level: 2 }).run(), active: () => editor.isActive('heading', { level: 2 }) },
        { label: 'H3', title: 'ចំណងជើងរង', cmd: () => editor.chain().focus().toggleHeading({ level: 3 }).run(), active: () => editor.isActive('heading', { level: 3 }) },
        { sep: true },
        { label: '≡', title: 'តម្រឹមឆ្វេង', cmd: () => editor.chain().focus().setTextAlign('left').run(), active: () => editor.isActive({ textAlign: 'left' }) },
        { label: '☰', title: 'តម្រឹមកណ្តាល', cmd: () => editor.chain().focus().setTextAlign('center').run(), active: () => editor.isActive({ textAlign: 'center' }) },
        { label: '≢', title: 'តម្រឹមស្តាំ', cmd: () => editor.chain().focus().setTextAlign('right').run(), active: () => editor.isActive({ textAlign: 'right' }) },
        { sep: true },
        { label: '•', title: 'បញ្ជីចំណុច', cmd: () => editor.chain().focus().toggleBulletList().run(), active: 'bulletList' },
        { label: '1.', title: 'បញ្ជីលេខរៀង', cmd: () => editor.chain().focus().toggleOrderedList().run(), active: 'orderedList' },
        { label: '❝', title: 'សម្រង់', cmd: () => editor.chain().focus().toggleBlockquote().run(), active: 'blockquote' },
        { sep: true },
        {
            label: '🔗', title: 'តំណភ្ជាប់ (Link)', cmd: () => {
                const url = editor.isActive('link') ? '' : window.prompt('URL តំណភ្ជាប់៖');
                if (editor.isActive('link')) editor.chain().focus().unsetLink().run();
                else if (url) editor.chain().focus().setLink({ href: url, target: '_blank' }).run();
            }, active: 'link',
        },
        { label: '🖼', title: 'បញ្ចូលរូបភាពពី Media Library', cmd: () => imagePicker.open((url) => editor.chain().focus().setImage({ src: url }).run()) },
        {
            label: '▦', title: 'បញ្ចូលតារាង', cmd: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
        },
        { sep: true },
        { label: '↶', title: 'ត្រឡប់វិញ (Undo)', cmd: () => editor.chain().focus().undo().run() },
        { label: '↷', title: 'ធ្វើវិញ (Redo)', cmd: () => editor.chain().focus().redo().run() },
    ];

    buttons.forEach((spec) => {
        if (spec.sep) {
            const sep = document.createElement('span');
            sep.className = 'news-editor-sep';
            toolbar.appendChild(sep);
            return;
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'news-editor-btn';
        button.title = spec.title;
        button.setAttribute('aria-label', spec.title);
        button.textContent = spec.label;
        if (spec.style) button.style.cssText = spec.style;
        button.addEventListener('click', spec.cmd);
        toolbar.appendChild(button);

        if (spec.active) {
            const isActive = typeof spec.active === 'function' ? spec.active : () => editor.isActive(spec.active);
            editor.on('transaction', () => button.classList.toggle('is-active', !!isActive()));
        }
    });
}

// A small "insert image" picker: recent Media Library images only (this is for inserting an
// image into the body text — the featured image / gallery pickers elsewhere cover those roles).
function buildImagePicker(mediaPickerUrl) {
    const el = document.createElement('div');
    el.className = 'news-editor-image-picker';
    el.hidden = true;
    el.innerHTML = '<div class="news-editor-image-picker-backdrop"></div><div class="news-editor-image-picker-panel"><div class="news-editor-image-picker-head"><span>ជ្រើសរើសរូបភាពពី Media Library</span><button type="button" class="news-editor-image-picker-close" aria-label="បិទ">✕</button></div><div class="news-editor-image-picker-grid"></div></div>';

    const grid = el.querySelector('.news-editor-image-picker-grid');
    const close = () => { el.hidden = true; };
    el.querySelector('.news-editor-image-picker-close').addEventListener('click', close);
    el.querySelector('.news-editor-image-picker-backdrop').addEventListener('click', close);

    let loaded = false;
    let onPick = null;

    async function load() {
        if (loaded || !mediaPickerUrl) return;
        loaded = true;
        try {
            const response = await fetch(mediaPickerUrl, { headers: { Accept: 'application/json' } });
            const items = await response.json();
            grid.innerHTML = '';
            items.forEach((media) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'news-editor-image-picker-item';
                button.innerHTML = `<img src="${media.url}" alt="" loading="lazy">`;
                button.addEventListener('click', () => { onPick?.(media.url); close(); });
                grid.appendChild(button);
            });
        } catch {
            grid.innerHTML = '<p class="news-editor-image-picker-empty">មិនអាចផ្ទុករូបភាពបានទេ។</p>';
        }
    }

    return {
        el,
        open(callback) { onPick = callback; el.hidden = false; load(); },
    };
}

export function initNewsEditor() {
    document.querySelectorAll('textarea[data-tiptap]').forEach(mountOne);
}

document.addEventListener('DOMContentLoaded', initNewsEditor);
