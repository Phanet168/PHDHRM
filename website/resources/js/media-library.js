export function initMediaLibrary() {
    const dropzone = document.getElementById('media-file-pane');
    const input = document.getElementById('media-upload-input');
    const overlay = document.getElementById('media-dropzone-overlay');
    if (!dropzone || !input) return;

    const uploadUrl = dropzone.dataset.uploadUrl;
    const folderId = dropzone.dataset.folderId || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const isFileDrag = (event) => Array.from(event.dataTransfer?.types || []).includes('Files');

    const uploadFiles = (files) => {
        const list = Array.from(files);
        if (!list.length) return;

        let done = 0;
        const failures = [];
        if (overlay) overlay.textContent = `កំពុងបញ្ចូល... (0/${list.length})`;

        const uploadOne = (file) => new Promise((resolve) => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder_id', folderId);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable || !overlay) return;
                const percent = Math.round((event.loaded / event.total) * 100);
                overlay.textContent = `កំពុងបញ្ចូល "${file.name}"... ${percent}% (${done}/${list.length})`;
            });

            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) failures.push(file.name);
                done++;
                resolve();
            };
            xhr.onerror = () => {
                failures.push(file.name);
                done++;
                resolve();
            };
            xhr.send(formData);
        });

        if (overlay) overlay.style.display = 'flex';

        list.reduce((chain, file) => chain.then(() => uploadOne(file)), Promise.resolve())
            .then(() => {
                if (failures.length) {
                    if (overlay) overlay.textContent = `មិនអាចបញ្ចូលឯកសារខ្លះបានទេ៖ ${failures.join(', ')}`;
                    window.setTimeout(() => window.location.reload(), 2500);
                } else {
                    try { sessionStorage.setItem('media-toast', 'បានបញ្ចូលឯកសារដោយជោគជ័យ។'); } catch (e) { /* ignore */ }
                    window.location.reload();
                }
            });
    };

    dropzone.addEventListener('dragover', (event) => {
        if (!isFileDrag(event)) return;
        event.preventDefault();
        if (overlay) overlay.style.display = 'flex';
    });

    dropzone.addEventListener('dragleave', (event) => {
        if (!isFileDrag(event)) return;
        if (event.target === dropzone && overlay) overlay.style.display = 'none';
    });

    dropzone.addEventListener('drop', (event) => {
        if (!isFileDrag(event)) return;
        event.preventDefault();
        if (overlay) overlay.style.display = 'none';
        uploadFiles(event.dataTransfer.files);
    });

    input.addEventListener('change', () => {
        uploadFiles(input.files);
        input.value = '';
    });
}
