@extends('layouts.site')

@section('title', $post->title . ' - ' . $site['brand_full'])

@section('content')
    <section class="py-10 bg-white">
        <div class="mx-auto max-w-6xl px-4 lg:px-8 grid gap-10 lg:grid-cols-[1fr_280px] items-start">
            {{-- Article --}}
            <article class="min-w-0">
                @if ($post->imageUrl())
                    <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="w-full object-cover">
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-[11px] font-medium uppercase text-gray-700">
                    <span class="inline-flex items-center gap-2">
                        <x-icon name="user-doctor" class="h-4 w-4 text-emerald-700" />
                        By {{ $post->author ?: 'Admin' }}
                    </span>
                    <span class="inline-flex items-center gap-2 normal-case">
                        <x-icon name="calendar" class="h-4 w-4 text-emerald-700" />
                        {{ $post->published_at->format('F j, Y') }}
                    </span>
                </div>

                <h1 class="mt-4 text-2xl font-bold leading-snug text-gray-950">{{ $post->title }}</h1>

                @if ($post->content)
                    <div class="blog-body mt-3 text-[12.5px] leading-7 text-gray-600
                        [&_h2]:mt-7 [&_h2]:mb-2 [&_h2]:text-[22px] [&_h2]:font-bold [&_h2]:leading-snug [&_h2]:text-gray-950
                        [&_h3]:mt-4 [&_h3]:mb-1 [&_h3]:text-[13px] [&_h3]:font-bold [&_h3]:text-gray-900
                        [&_p]:mt-2
                        [&_ul]:mt-2 [&_ul]:space-y-2.5
                        [&_li]:flex [&_li]:items-start [&_li]:gap-2.5
                        [&_strong]:font-semibold [&_strong]:text-gray-900">
                        {{ $post->formattedContent() }}
                    </div>
                @elseif ($post->excerpt)
                    <p class="mt-3 text-[13px] leading-7 text-gray-600">{{ $post->excerpt }}</p>
                @endif

                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('health-tips') }}" class="inline-flex items-center rounded-full border border-emerald-950 px-8 py-2.5 text-sm hover:bg-emerald-950 hover:text-white transition">&lsaquo; All blogs</a>
                    <a href="#appointment" data-open-appointment class="inline-flex items-center rounded-full bg-emerald-950 px-8 py-2.5 text-sm text-white hover:bg-emerald-900 transition">Book an Appointment</a>
                </div>
            </article>

            {{-- Recent news --}}
            <aside class="lg:sticky lg:top-24">
                <h2 class="text-lg font-bold text-gray-950">{{ $blogSection['recent_heading'] }}</h2>
                <span class="mt-1 mb-4 flex h-[3px] w-24"><span class="w-1/2 bg-amber-400"></span><span class="w-1/2 bg-emerald-950"></span></span>

                <ul class="space-y-3">
                    @forelse ($recent as $item)
                        <li>
                            <a href="{{ $item->url() }}" class="flex items-center gap-3 rounded-md bg-white p-2 shadow-[0_0_8px_rgba(0,0,0,0.1)] transition hover:shadow-md">
                                @if ($item->imageUrl())
                                    <img src="{{ $item->imageUrl() }}" alt="" class="h-14 w-16 shrink-0 object-cover" loading="lazy">
                                @else
                                    <span class="h-14 w-16 shrink-0 bg-emerald-950"></span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-medium leading-snug text-gray-900 line-clamp-2">{{ $item->title }}</span>
                                    <span class="mt-1 block text-[11px] text-amber-500">{{ $item->published_at->format('j F') }}</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">No other posts yet.</li>
                    @endforelse
                </ul>
            </aside>
        </div>
    </section>
@endsection
