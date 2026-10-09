@extends('layouts.site')

@section('title', $blogSection['page_title'] . ' - ' . $site['brand_full'])

@section('content')
    {{-- Banner --}}
    <section class="bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/contact/contact-banner.jpg') }}')">
        <div class="mx-auto max-w-4xl px-4 py-16 sm:py-20 text-center min-h-[300px] flex flex-col items-center justify-center">
            <h1 class="text-3xl font-bold text-gray-950">{{ $blogSection['page_title'] }}</h1>
            @if ($blogSection['page_intro'] !== '')
                <p class="mt-5 text-sm leading-7 text-gray-700">{{ $blogSection['page_intro'] }}</p>
            @endif
        </div>
    </section>

    {{-- Blog grid --}}
    <section class="py-14 bg-white">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            @if ($posts->isEmpty())
                <p class="py-10 text-center text-gray-500">No blog posts have been published yet. Please check back soon.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 items-start">
                    @foreach ($posts as $post)
                        <article class="bg-white shadow-[0_0_8px_rgba(0,0,0,0.1)] overflow-hidden">
                            <a href="{{ $post->url() }}" class="block">
                                @if ($post->imageUrl())
                                    <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="w-full aspect-[1200/628] object-cover" loading="lazy">
                                @else
                                    <div class="w-full aspect-[1200/628] bg-emerald-950"></div>
                                @endif
                            </a>
                            <div class="p-4">
                                <h3 class="text-base font-bold leading-snug text-gray-900">{{ \Illuminate\Support\Str::limit($post->title, 38) }}</h3>
                                <p class="mt-2 text-xs leading-6 text-gray-600 line-clamp-4">{{ $post->excerpt }}</p>
                                <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 text-xs">
                                    <a href="{{ $post->url() }}" class="text-gray-900 hover:underline">Read more &rsaquo;</a>
                                    <span class="text-amber-500">{{ $post->published_at->format('M j, Y') }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
