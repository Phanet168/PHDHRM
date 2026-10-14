export function initYoutubeImport() {
    const container = document.getElementById('youtube-channel-import');
    if (!container) return;

    const searchUrl = container.dataset.searchUrl;
    const importUrl = container.dataset.importUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const searchBtn = document.getElementById('youtube-channel-search-btn');
    const errorEl = document.getElementById('youtube-channel-error');
    const resultsEl = document.getElementById('youtube-channel-results');
    const listEl = document.getElementById('youtube-video-list');
    const selectAll = document.getElementById('youtube-select-all');
    const importSelectedBtn = document.getElementById('youtube-import-selected-btn');
    const progressEl = document.getElementById('youtube-import-progress');
    const summaryEl = document.getElementById('youtube-import-summary');

    const jsonHeaders = {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };

    const showError = (message) => {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    };

    const hideError = () => errorEl.classList.add('hidden');

    const formatDuration = (seconds) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    const renderVideo = (video) => {
        const disabled = video.already_imported;
        const wrapper = document.createElement('label');
        wrapper.className = `flex items-center gap-3 border border-line rounded-[8px] p-2 ${disabled ? 'opacity-50' : 'cursor-pointer'}`;

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'youtube-video-checkbox';
        checkbox.disabled = disabled;
        checkbox.dataset.videoId = video.video_id;
        wrapper.appendChild(checkbox);

        if (video.thumbnail_url) {
            const img = document.createElement('img');
            img.src = video.thumbnail_url;
            img.alt = video.title;
            img.loading = 'lazy';
            img.className = 'h-[45px] w-[80px] object-cover rounded-[4px] flex-shrink-0';
            wrapper.appendChild(img);
        }

        const body = document.createElement('div');
        body.className = 'flex-1 min-w-0';

        const title = document.createElement('p');
        title.className = 'text-[13px] font-bold text-ink truncate';
        title.textContent = video.title;
        body.appendChild(title);

        const meta = document.createElement('p');
        meta.className = 'text-[11px] text-muted';
        const durationLabel = video.duration_seconds ? formatDuration(video.duration_seconds) : '';
        const dateLabel = video.published_at ? new Date(video.published_at).toLocaleDateString() : '';
        meta.textContent = [durationLabel, dateLabel].filter(Boolean).join(' · ');
        body.appendChild(meta);

        wrapper.appendChild(body);

        if (disabled) {
            const badge = document.createElement('span');
            badge.className = 'text-[11px] font-bold text-brand-green flex-shrink-0';
            badge.textContent = 'មានក្នុងប្រព័ន្ធរួច';
            wrapper.appendChild(badge);
        }

        return wrapper;
    };

    searchBtn.addEventListener('click', () => {
        hideError();
        resultsEl.classList.add('hidden');
        resultsEl.classList.remove('flex');

        const channelUrl = document.getElementById('channel_url').value;
        const limit = document.getElementById('limit').value;

        searchBtn.disabled = true;
        searchBtn.textContent = 'កំពុងស្វែងរក...';

        fetch(searchUrl, {
            method: 'POST',
            headers: jsonHeaders,
            body: JSON.stringify({ channel_url: channelUrl, limit: Number(limit) }),
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    showError(data.message || 'មានបញ្ហាកើតឡើង។');
                    return;
                }

                listEl.innerHTML = '';
                data.videos.forEach((video) => listEl.appendChild(renderVideo(video)));
                selectAll.checked = false;
                resultsEl.classList.remove('hidden');
                resultsEl.classList.add('flex');
            })
            .catch(() => showError('មិនអាចភ្ជាប់ទៅម៉ាស៊ីនមេបានទេ។'))
            .finally(() => {
                searchBtn.disabled = false;
                searchBtn.textContent = 'ស្វែងរកវីដេអូ';
            });
    });

    selectAll.addEventListener('change', () => {
        document.querySelectorAll('.youtube-video-checkbox:not(:disabled)').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });

    const importOne = (videoId) => fetch(importUrl, {
        method: 'POST',
        headers: jsonHeaders,
        body: JSON.stringify({ url_or_id: videoId }),
    }).then((response) => response.json().then((data) => ({ ok: response.ok, data })));

    importSelectedBtn.addEventListener('click', () => {
        const checked = Array.from(document.querySelectorAll('.youtube-video-checkbox:checked'));
        if (!checked.length) return;

        importSelectedBtn.disabled = true;
        summaryEl.classList.add('hidden');
        progressEl.classList.remove('hidden');

        const total = checked.length;
        let done = 0;
        let succeeded = 0;
        let duplicated = 0;
        let failed = 0;

        progressEl.textContent = `កំពុងនាំចូល 0 / ${total}`;

        checked
            .reduce((chain, checkbox) => chain.then(() => importOne(checkbox.dataset.videoId).then(({ ok, data }) => {
                done++;
                if (!ok) {
                    failed++;
                } else if (data.duplicate) {
                    duplicated++;
                } else {
                    succeeded++;
                }
                progressEl.textContent = `កំពុងនាំចូល ${done} / ${total}`;
            })), Promise.resolve())
            .then(() => {
                progressEl.classList.add('hidden');
                summaryEl.classList.remove('hidden');
                summaryEl.textContent = `នាំចូលជោគជ័យ៖ ${succeeded} · មានរួច៖ ${duplicated} · បរាជ័យ៖ ${failed}`;
                importSelectedBtn.disabled = false;
            });
    });
}
