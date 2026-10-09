@extends('layouts.site')

@section('title', $videoPage['title'] . ' - ' . $site['brand_full'])

@section('content')
    {{-- Banner --}}
    <section class="bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/contact/contact-banner.jpg') }}')">
        <div class="mx-auto max-w-4xl px-4 py-16 sm:py-20 text-center min-h-[300px] flex flex-col items-center justify-center">
            <h1 class="text-3xl font-bold text-gray-950">{{ $videoPage['title'] }}</h1>
            @if ($videoPage['intro'] !== '')
                <p class="mt-5 text-sm leading-7 text-gray-700">{{ $videoPage['intro'] }}</p>
            @endif
        </div>
    </section>

    <section class="py-12 bg-white">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            @if ($videos->isEmpty())
                <p class="text-center text-sm text-gray-500">Videos will be added soon.</p>
            @else
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($videos as $video)
                        <li class="flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                            {{-- Shows the thumbnail only; the video loads and plays when the visitor clicks it --}}
                            <button type="button" data-video-play data-embed="{{ $video->embedUrl() }}" data-title="{{ $video->title }}"
                                class="group relative block aspect-video w-full overflow-hidden bg-gray-900" aria-label="Play video: {{ $video->title }}">
                                <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                <span class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-emerald-950/70 transition group-hover:bg-emerald-950">
                                    <svg viewBox="0 0 24 24" class="ml-0.5 h-6 w-6 fill-white"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </button>
                            <div class="p-5">
                                <h3 class="text-base font-bold leading-snug text-gray-950">{{ $video->title }}</h3>
                                @if ($video->description)
                                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $video->description }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <script>
        document.querySelectorAll('[data-video-play]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var frame = document.createElement('iframe');
                frame.src = btn.getAttribute('data-embed');
                frame.title = btn.getAttribute('data-title');
                frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
                frame.allowFullscreen = true;
                frame.className = 'absolute inset-0 h-full w-full';

                var holder = document.createElement('div');
                holder.className = 'relative aspect-video w-full bg-black';
                holder.appendChild(frame);
                btn.replaceWith(holder);
            });
        });
    </script>
@endsection
