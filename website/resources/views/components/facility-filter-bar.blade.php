@props(['districts', 'communes'])

<div class="facility-toolbar" role="search">
    <div class="facility-toolbar-search">
        <label class="sr-only" for="facility-search">ស្វែងរកឈ្មោះមូលដ្ឋានសុខាភិបាល</label>
        <input type="search" id="facility-search" x-model.debounce.250ms="search" placeholder="ស្វែងរកឈ្មោះមូលដ្ឋានសុខាភិបាល...">
    </div>

    <div class="facility-toolbar-field">
        <label class="sr-only" for="facility-district">ស្រុក/ក្រុង</label>
        <select id="facility-district" x-model="district">
            <option value="">ស្រុក/ក្រុង (ទាំងអស់)</option>
            @foreach ($districts as $d)
                <option value="{{ $d }}">{{ $d }}</option>
            @endforeach
        </select>
    </div>

    <div class="facility-toolbar-field">
        <label class="sr-only" for="facility-commune">ឃុំ/សង្កាត់</label>
        <select id="facility-commune" x-model="commune">
            <option value="">ឃុំ/សង្កាត់ (ទាំងអស់)</option>
            @foreach ($communes as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
    </div>

    <div class="facility-toolbar-field">
        <label class="sr-only" for="facility-type">ប្រភេទមូលដ្ឋាន</label>
        <select id="facility-type" x-model="type">
            <option value="">ប្រភេទមូលដ្ឋាន (ទាំងអស់)</option>
            <option value="referral_hospital">មន្ទីរពេទ្យបង្អែក</option>
            <option value="health_center">មណ្ឌលសុខភាព</option>
            <option value="health_post">ប៉ុស្តិ៍សុខភាព</option>
            <option value="operational_district_office">ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ</option>
        </select>
    </div>

    <div class="facility-toolbar-views" role="group" aria-label="របៀបបង្ហាញ">
        <button type="button" class="facility-view-btn" :class="view === 'map' && 'is-active'" @click="setView('map')" aria-pressed="view === 'map'">🗺️ ផែនទី</button>
        <button type="button" class="facility-view-btn" :class="view === 'list' && 'is-active'" @click="setView('list')" aria-pressed="view === 'list'">☰ បញ្ជី</button>
    </div>

    <button type="button" class="facility-reset-btn" @click="resetFilters()">↻ សម្អាត</button>
</div>
