@props(['facilities'])

<div class="facility-list-panel">
    <div class="facility-list-header">
        <h2>មូលដ្ឋានសុខាភិបាល (<span x-text="visibleCount()"></span>)</h2>
        <select x-model="sortBy" @change="applySort()" aria-label="តម្រៀបតាម">
            <option value="name_asc">តម្រៀប៖ A-Z</option>
            <option value="name_desc">តម្រៀប៖ Z-A</option>
            <option value="latest">ថ្មីៗបំផុត</option>
        </select>
    </div>
    <div class="facility-list-scroll" id="facility-list-scroll">
        @forelse ($facilities as $facility)
            <x-facility-list-item :facility="$facility" />
        @empty
        @endforelse
        <p class="facility-empty" x-show="visibleCount() === 0" x-cloak>រកមិនឃើញមូលដ្ឋានសុខាភិបាលដែលត្រូវនឹងលក្ខខណ្ឌស្វែងរកនេះទេ។</p>
    </div>
</div>
