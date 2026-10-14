<x-admin.layout title="នាំចូលវីដេអូពី YouTube">
    <div class="flex items-center justify-between mb-5">
        <p class="font-bold text-brand-navy text-[18px]">នាំចូលវីដេអូពី YouTube</p>
        <a href="{{ route('admin.health-content.index') }}" class="text-muted text-[13px] font-bold">← ត្រឡប់ទៅមាតិកាអប់រំសុខភាព</a>
    </div>

    <div x-data="{ tab: 'url' }" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[820px]">
        <div class="flex gap-2 border-b border-line mb-5">
            <button type="button" @click="tab = 'url'" class="px-4 py-2 text-[13px] font-bold border-b-2"
                :class="tab === 'url' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-muted'">នាំចូលតាម Link</button>
            <button type="button" id="youtube-tab-channel-btn" @click="tab = 'channel'" class="px-4 py-2 text-[13px] font-bold border-b-2"
                :class="tab === 'channel' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-muted'">ទាញពី Channel</button>
        </div>

        {{-- Tab 1: single video URL — plain form post, server creates the
             draft and redirects straight to its edit page. --}}
        <div x-show="tab === 'url'" x-cloak>
            @error('url_or_id')
                <p class="text-brand-red text-[13px] font-bold mb-3">{{ $message }}</p>
            @enderror
            <form method="POST" action="{{ route('admin.health-content.youtube-import.import') }}" class="flex flex-col gap-3">
                @csrf
                <x-admin.form-input name="url_or_id" label="តំណភ្ជាប់ YouTube" placeholder="https://www.youtube.com/watch?v=..." required />
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5 self-start">ទាញព័ត៌មាន</button>
            </form>
        </div>

        {{-- Tab 2: channel — search first (preview only), then select which
             videos to import; the actual import loop is client-side JS
             calling the same import endpoint once per selected video. --}}
        <div x-show="tab === 'channel'" x-cloak
             id="youtube-channel-import"
             data-search-url="{{ route('admin.health-content.youtube-import.search') }}"
             data-import-url="{{ route('admin.health-content.youtube-import.import') }}"
             class="flex flex-col gap-3">
            <div class="grid grid-cols-1 md:grid-cols-[1fr_140px_auto] gap-2 items-end">
                <x-admin.form-input name="channel_url" label="Channel" value="https://www.youtube.com/@MOHCambodia" />
                <x-admin.select name="limit" label="ចំនួន" :options="[10 => 10, 20 => 20, 30 => 30, 50 => 50]" :value="10" :placeholder="null" />
                <button type="button" id="youtube-channel-search-btn" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-11 px-5">ស្វែងរកវីដេអូ</button>
            </div>

            <p id="youtube-channel-error" class="text-brand-red text-[13px] font-bold hidden"></p>

            <div id="youtube-channel-results" class="hidden flex-col gap-3">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
                        <input type="checkbox" id="youtube-select-all">
                        ជ្រើសទាំងអស់
                    </label>
                    <button type="button" id="youtube-import-selected-btn" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-9 px-4">នាំចូលដែលបានជ្រើស</button>
                </div>

                <div id="youtube-video-list" class="flex flex-col gap-2 max-h-[420px] overflow-y-auto"></div>

                <p id="youtube-import-progress" class="text-ink text-[13px] font-bold hidden"></p>
                <p id="youtube-import-summary" class="text-ink text-[13px] hidden"></p>
            </div>
        </div>
    </div>
</x-admin.layout>
