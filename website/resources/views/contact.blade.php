@php
    // Facility type -> icon for the directory rows (badge colours live in contact.css).
    $typeIcons = ['referral_hospital' => 'hospital', 'health_center' => 'stethoscope', 'health_post' => 'heart-pulse', 'operational_district_office' => 'users'];
    $activeFilters = array_filter(['q' => $filters['q'], 'facility_type' => $filters['facility_type'], 'district' => $filters['district']]);
    $groupBase = array_filter(['q' => $filters['q'], 'facility_type' => $filters['facility_type']]);
    $directoryUrl = route('contact').'#hotline-directory';
@endphp
<x-layout title="ទំនាក់ទំនង">
    <x-page-banner page="contact" title="ទំនាក់ទំនង" />
    <x-page-header title="ទំនាក់ទំនង" />

    <main class="contact-page public-container">
        {{-- 1. Summary cards (site settings) --}}
        <section class="contact-summary" aria-label="ព័ត៌មានទំនាក់ទំនងសង្ខេប">
            <div class="contact-summary-card">
                <span class="contact-summary-icon is-phone"><x-news-icon name="phone" width="24" height="24" /></span>
                <div>
                    <h2>ទូរស័ព្ទទំនាក់ទំនង</h2>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('site.phone')) }}" class="contact-summary-value">{{ config('site.phone') }}</a>
                    <p>ហៅមកកាន់មន្ទីរក្នុងម៉ោងធ្វើការ</p>
                </div>
            </div>
            <div class="contact-summary-card">
                <span class="contact-summary-icon is-mail"><x-news-icon name="mail" width="24" height="24" /></span>
                <div>
                    <h2>អ៊ីមែល</h2>
                    <a href="mailto:{{ config('site.email') }}" class="contact-summary-value is-link">{{ config('site.email') }}</a>
                    <p>ផ្ញើសំណួរ ឬសំណើមកកាន់អ៊ីមែលនេះ</p>
                </div>
            </div>
            <div class="contact-summary-card">
                <span class="contact-summary-icon is-pin"><x-news-icon name="pin" width="24" height="24" /></span>
                <div>
                    <h2>អាសយដ្ឋាន</h2>
                    <p class="contact-summary-text">{{ config('site.address') }}</p>
                </div>
            </div>
            <div class="contact-summary-card">
                <span class="contact-summary-icon is-clock"><x-news-icon name="clock" width="24" height="24" /></span>
                <div>
                    <h2>ម៉ោងធ្វើការ</h2>
                    <p class="contact-summary-text">{{ config('site.hours') }}</p>
                </div>
            </div>
        </section>

        {{-- 2. Hotline directory: search + filters + compact list + pagination --}}
        <section id="hotline-directory" class="contact-directory" aria-labelledby="hotline-heading">
            <div class="contact-directory-top">
            <header class="contact-directory-head">
                <span class="contact-directory-icon"><x-news-icon name="hospital" width="26" height="26" /></span>
                <div>
                    <h2 id="hotline-heading">លេខទំនាក់ទំនងបន្ទាន់ និង Hotline</h2>
                    <p>បញ្ជីលេខទំនាក់ទំនងសម្រាប់មន្ទីរពេទ្យ មណ្ឌលសុខភាព និងមូលដ្ឋានសុខាភិបាលក្នុងខេត្តស្ទឹងត្រែង</p>
                </div>
            </header>

            <form method="GET" action="{{ $directoryUrl }}" class="contact-filter" role="search" x-data>
                <label class="contact-filter-search">
                    <span class="sr-only">ស្វែងរកមូលដ្ឋានសុខាភិបាល</span>
                    <x-news-icon name="search" width="18" height="18" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="ស្វែងរកឈ្មោះមន្ទីរពេទ្យ មណ្ឌលសុខភាព...">
                </label>
                <label class="contact-filter-field">ប្រភេទមូលដ្ឋាន
                    <select name="facility_type" @change="$el.form.requestSubmit()">
                        <option value="">ទាំងអស់</option>
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['facility_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="contact-filter-field">ស្រុក/ក្រុង (OD)
                    <select name="district" @change="$el.form.requestSubmit()">
                        <option value="">ទាំងអស់</option>
                        @foreach ($districts as $name)
                            <option value="{{ $name }}" @selected($filters['district'] === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="contact-filter-actions">
                    <button type="submit"><x-news-icon name="search" width="18" height="18" />ស្វែងរក</button>
                    <a href="{{ $directoryUrl }}"><x-news-icon name="refresh" width="18" height="18" />សម្អាត</a>
                </div>
            </form>
            </div>

            <div class="contact-directory-body">
                <nav class="contact-groups" aria-label="ជ្រើសរើសតាមស្រុក/ក្រុង">
                    <a href="{{ route('contact', $groupBase) }}#hotline-directory" class="contact-group {{ $filters['district'] ? '' : 'is-active' }}" @if (! $filters['district']) aria-current="true" @endif>
                        <x-news-icon name="hospital" width="18" height="18" />
                        <span class="contact-group-name">ទាំងអស់</span>
                        <span class="contact-group-count">{{ $matchingTotal }}</span>
                    </a>
                    @foreach ($districts as $name)
                        <a href="{{ route('contact', [...$groupBase, 'district' => $name]) }}#hotline-directory" class="contact-group {{ $filters['district'] === $name ? 'is-active' : '' }}" @if ($filters['district'] === $name) aria-current="true" @endif>
                            <x-news-icon name="pin" width="18" height="18" />
                            <span class="contact-group-name">{{ $name }}</span>
                            <span class="contact-group-count">{{ $districtCounts[$name] ?? 0 }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="contact-directory-main">
                    @if ($facilities->isEmpty())
                        <div class="contact-empty">
                            <x-news-icon name="search" width="40" height="40" />
                            <p>មិនមានមូលដ្ឋានសុខាភិបាលដែលត្រូវនឹងការស្វែងរកទេ</p>
                            @if ($activeFilters)
                                <a href="{{ $directoryUrl }}">សម្អាតការស្វែងរក</a>
                            @endif
                        </div>
                    @else
                        <div class="contact-dir-table">
                            <div class="contact-dir-header" aria-hidden="true">
                                <span class="contact-dir-no">ល.រ</span>
                                <span class="contact-dir-name">ឈ្មោះមូលដ្ឋានសុខាភិបាល</span>
                                <span class="contact-dir-type">ប្រភេទមូលដ្ឋាន</span>
                                <span class="contact-dir-district">ស្រុក/OD</span>
                                <span class="contact-dir-phone">លេខទូរស័ព្ទ Hotline</span>
                                <span class="contact-dir-action">សកម្មភាព</span>
                            </div>
                            <ul class="contact-dir-list" aria-label="បញ្ជីលេខទំនាក់ទំនងមូលដ្ឋានសុខាភិបាល">
                                @foreach ($facilities as $facility)
                                    @php
                                        $phone = $facility->publicPhone();
                                        $href = $facility->phoneHref();
                                        $place = filled($facility->commune) ? $facility->commune : $facility->address;
                                    @endphp
                                    <li class="contact-dir-row">
                                        <span class="contact-dir-no">{{ $facilities->firstItem() + $loop->index }}</span>
                                        <div class="contact-dir-name">
                                            <span class="contact-dir-icon is-{{ $facility->facility_type }}" aria-hidden="true"><x-news-icon :name="$typeIcons[$facility->facility_type] ?? 'hospital'" width="20" height="20" /></span>
                                            <div>
                                                <a href="{{ route('facilities.show', $facility) }}">{{ $facility->name_km }}</a>
                                                @if (filled($place))
                                                    <small>{{ $place }}</small>
                                                @endif
                                                @if (filled($facility->district))
                                                    <small class="contact-dir-sub-district">{{ $facility->district }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="contact-dir-type"><span class="contact-badge is-{{ $facility->facility_type }}">{{ \App\Models\Facility::typeLabel($facility->facility_type) }}</span></span>
                                        <span class="contact-dir-district">{{ $facility->district }}</span>
                                        <span class="contact-dir-phone">
                                            @if ($href)
                                                <a href="{{ $href }}"><x-news-icon name="phone" width="17" height="17" />{{ $phone }}</a>
                                            @else
                                                {{ $facility->phone }}
                                            @endif
                                        </span>
                                        <span class="contact-dir-action">
                                            @if ($href)
                                                <a href="{{ $href }}" class="contact-call" aria-label="ហៅទូរស័ព្ទ៖ {{ $facility->name_km }}"><x-news-icon name="phone" width="17" height="17" />ហៅទូរស័ព្ទ</a>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="contact-pagination">
                            <p class="contact-pagination-summary">បង្ហាញ {{ $facilities->firstItem() }}-{{ $facilities->lastItem() }} នៃ {{ $facilities->total() }} មូលដ្ឋានសុខាភិបាល</p>
                            {{ $facilities->onEachSide(1)->fragment('hotline-directory')->links('contact.pagination') }}
                            <p class="contact-pagination-pages">ទំព័រ {{ $facilities->currentPage() }} នៃ {{ $facilities->lastPage() }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Department contact details + message form + map (unchanged logic) --}}
        <div class="contact-lower">
            <section class="contact-card" aria-labelledby="contact-info-heading">
                <h2 id="contact-info-heading" class="contact-card-title"><x-news-icon name="pin" width="22" height="22" />ព័ត៌មានទំនាក់ទំនងមន្ទីរ</h2>
                <ul class="contact-info-list">
                    <li><x-news-icon name="pin" width="20" height="20" /><div><span>អាសយដ្ឋាន</span><p>{{ config('site.address') }}</p></div></li>
                    <li><x-news-icon name="phone" width="20" height="20" /><div><span>ទូរស័ព្ទ</span><p>{{ config('site.phone') }}</p></div></li>
                    <li><x-news-icon name="mail" width="20" height="20" /><div><span>អ៊ីមែល</span><p>{{ config('site.email') }}</p></div></li>
                    <li><x-news-icon name="clock" width="20" height="20" /><div><span>ម៉ោងធ្វើការ</span><p>{{ config('site.hours') }}</p></div></li>
                </ul>
                @if (! empty(config('site.emergency_contacts')))
                    <div class="contact-emergency">
                        <h3>លេខទំនាក់ទំនងសំខាន់</h3>
                        <ul>
                            @foreach (config('site.emergency_contacts') as $contact)
                                <li class="{{ $loop->first ? 'is-first' : '' }}">
                                    <span>{{ $contact['label'] }}</span>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section class="contact-card" aria-labelledby="contact-form-heading">
                <h2 id="contact-form-heading" class="contact-card-title"><x-news-icon name="send" width="22" height="22" />ផ្ញើសារមកកាន់យើង</h2>

                @if (session('status'))
                    <p class="contact-status" role="status">{{ session('status') }}</p>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" class="contact-form">
                    @csrf
                    <label><span>ឈ្មោះ <span class="contact-required" aria-hidden="true">*</span></span>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="បញ្ចូលឈ្មោះរបស់អ្នក" required>
                    </label>
                    <label>លេខទូរស័ព្ទ
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="បញ្ចូលលេខទូរស័ព្ទ">
                    </label>
                    <label class="is-wide">អ៊ីមែល
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="បញ្ចូលអ៊ីមែលរបស់អ្នក">
                    </label>
                    <label class="is-wide">ប្រធានបទ
                        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="បញ្ចូលប្រធានបទ">
                    </label>
                    <label class="is-wide"><span>សារ <span class="contact-required" aria-hidden="true">*</span></span>
                        <textarea name="message" rows="4" placeholder="បញ្ចូលសាររបស់អ្នក..." required>{{ old('message') }}</textarea>
                    </label>
                    @error('name')<p class="contact-error is-wide">{{ $message }}</p>@enderror
                    @error('email')<p class="contact-error is-wide">{{ $message }}</p>@enderror
                    @error('message')<p class="contact-error is-wide">{{ $message }}</p>@enderror
                    <button type="submit" class="is-wide"><x-news-icon name="send" width="18" height="18" />ផ្ញើសារ</button>
                </form>
            </section>

            {{-- Same Google Maps search link as before, presented as a map card --}}
            <a class="contact-map" href="https://www.google.com/maps/search/?api=1&query={{ urlencode(config('site.name_en')) }}" target="_blank" rel="noopener noreferrer">
                <span class="contact-map-pin"><x-news-icon name="pin" width="30" height="30" /></span>
                <span class="contact-map-card">
                    <strong>ផែនទីទីតាំងមន្ទីរសុខាភិបាល</strong>
                    <span>{{ config('site.name_en') }}</span>
                    <span class="contact-map-cta">មើលលើ Google Maps <x-news-icon name="external" width="16" height="16" /></span>
                </span>
            </a>
        </div>
    </main>
</x-layout>
