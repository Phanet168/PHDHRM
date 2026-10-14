// CodeMirror is only needed on the content edit page, and only for users who
// can see an HTML+CSS block's editor fields, so it is fetched on demand as a
// separate chunk instead of bundling it into the site-wide entrypoint.
let loading = null;

export function initContentEditors() {
    if (!document.querySelector('textarea[data-codemirror]')) return;

    loading ??= import('./content-editor-cm.js');
    loading.then((mod) => mod.mountContentEditors());
}

document.addEventListener('DOMContentLoaded', initContentEditors);
document.addEventListener('click', () => {
    requestAnimationFrame(() => requestAnimationFrame(initContentEditors));
});
