{{-- Shared tab nav for News create/edit. Requires an ancestor with x-data="{ tab: '...' }". --}}
<div class="flex items-center gap-1 border-b border-line overflow-x-auto mb-5" role="tablist">
    <button type="button" role="tab" @click="tab = 'content'" :aria-selected="tab === 'content'"
            class="shrink-0 px-4 h-11 text-[13px] font-bold border-b-2 -mb-px transition-colors"
            :class="tab === 'content' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-muted hover:text-ink'">
        មាតិកា
    </button>
    <button type="button" role="tab" @click="tab = 'media'" :aria-selected="tab === 'media'"
            class="shrink-0 px-4 h-11 text-[13px] font-bold border-b-2 -mb-px transition-colors"
            :class="tab === 'media' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-muted hover:text-ink'">
        រូបភាព និងឯកសារ
    </button>
    <button type="button" role="tab" @click="tab = 'settings'" :aria-selected="tab === 'settings'"
            class="shrink-0 px-4 h-11 text-[13px] font-bold border-b-2 -mb-px transition-colors"
            :class="tab === 'settings' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-muted hover:text-ink'">
        ការកំណត់ផ្សេងៗ
    </button>
    <button type="button" role="tab" @click="tab = 'seo'" :aria-selected="tab === 'seo'"
            class="shrink-0 px-4 h-11 text-[13px] font-bold border-b-2 -mb-px transition-colors"
            :class="tab === 'seo' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-muted hover:text-ink'">
        SEO និងការបោះផ្សាយ
    </button>
</div>
