<x-layout :title="$category->name_km">
    <x-page-banner page="content-categories" :title="$category->name_km" />
    <x-page-header :title="$category->name_km" />

    <section class="flex flex-col gap-2 public-container pt-8">
        @if ($category->description)
            <p class="text-muted text-[15px] max-w-[750px]">{{ $category->description }}</p>
        @endif
    </section>

    <section class="flex flex-col gap-[18px] public-container pt-5 pb-[45px]">
        @if ($contents->isEmpty())
            <p class="text-muted text-[15px] py-10 text-center">មិនមានមាតិកាក្នុងប្រភេទនេះទេ។</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-[18px]">
                @foreach ($contents as $article)
                    <x-content-card :article="$article" />
                @endforeach
            </div>
            <div>{{ $contents->links() }}</div>
        @endif
    </section>
</x-layout>
