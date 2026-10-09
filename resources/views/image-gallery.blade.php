@extends('layouts.site')

@section('title', $galleryPage['title'] . ' - ' . $site['brand_full'])

@section('content')
    {{-- Banner --}}
    <section class="bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/contact/contact-banner.jpg') }}')">
        <div class="mx-auto max-w-4xl px-4 py-16 sm:py-20 text-center min-h-[300px] flex flex-col items-center justify-center">
            <h1 class="text-3xl font-bold text-gray-950">{{ $galleryPage['title'] }}</h1>
            @if ($galleryPage['intro'] !== '')
                <p class="mt-5 text-sm leading-7 text-gray-700">{{ $galleryPage['intro'] }}</p>
            @endif
        </div>
    </section>

    <section class="py-12 bg-white" id="gallery">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            @if ($groups->isEmpty())
                <p class="text-center text-sm text-gray-500">Photos will be added soon.</p>
            @else
                {{-- Group filter --}}
                @if ($groups->count() > 1)
                    <div class="mb-10 flex flex-wrap justify-center gap-3" role="tablist" data-gallery-filters>
                        <button type="button" role="tab" data-group="all" aria-selected="true"
                            class="gallery-filter rounded-sm border px-5 py-2 text-sm font-medium transition-colors border-emerald-950 bg-emerald-950 text-white">All</button>
                        @foreach ($groups as $group)
                            <button type="button" role="tab" data-group="{{ $group->id }}" aria-selected="false"
                                class="gallery-filter rounded-sm border px-5 py-2 text-sm font-medium transition-colors border-gray-300 bg-gray-50 text-gray-800 hover:bg-white">{{ $group->title }}</button>
                        @endforeach
                    </div>
                @endif

                <ul class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5" data-gallery-grid>
                    @foreach ($groups as $group)
                        @foreach ($group->activeImages as $image)
                            <li data-group="{{ $group->id }}">
                                <a href="{{ $image->url() }}" data-gallery-item data-caption="{{ $image->caption ?: $group->title }}" data-group-title="{{ $group->title }}"
                                    class="group block aspect-[6/7] overflow-hidden rounded-xl bg-gray-100 shadow-sm transition hover:shadow-md">
                                    <img src="{{ $image->url() }}" alt="{{ $image->caption ?: $group->title }}" loading="lazy"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                </a>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Large preview --}}
    <div id="gallery-lightbox" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Image preview">
        <button type="button" data-lb-close class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-3xl leading-none text-white hover:bg-white/20" aria-label="Close">&times;</button>
        <button type="button" data-lb-prev class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20" aria-label="Previous image">&#8249;</button>
        <button type="button" data-lb-next class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20" aria-label="Next image">&#8250;</button>
        <figure class="flex max-h-full max-w-5xl flex-col items-center">
            <img data-lb-img src="" alt="" class="max-h-[80vh] max-w-full object-contain shadow-2xl">
            <figcaption class="mt-3 text-center text-sm text-white">
                <span data-lb-caption></span>
                <span data-lb-count class="ml-2 text-white/60"></span>
            </figcaption>
        </figure>
    </div>

    <script>
        (function () {
            var grid = document.querySelector('[data-gallery-grid]');
            if (!grid) { return; }

            var filters = document.querySelectorAll('.gallery-filter');
            var box = document.getElementById('gallery-lightbox');
            var img = box.querySelector('[data-lb-img]');
            var caption = box.querySelector('[data-lb-caption]');
            var count = box.querySelector('[data-lb-count]');
            var current = 0;
            var visible = [];
            var lastFocus = null;

            function visibleLinks() {
                return Array.prototype.filter.call(grid.querySelectorAll('[data-gallery-item]'), function (a) {
                    return !a.parentElement.hidden;
                });
            }

            function applyFilter(group) {
                grid.querySelectorAll('li').forEach(function (li) {
                    li.hidden = group !== 'all' && li.getAttribute('data-group') !== group;
                });

                filters.forEach(function (btn) {
                    var on = btn.getAttribute('data-group') === group;
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                    btn.classList.toggle('border-emerald-950', on);
                    btn.classList.toggle('bg-emerald-950', on);
                    btn.classList.toggle('text-white', on);
                    btn.classList.toggle('border-gray-300', !on);
                    btn.classList.toggle('bg-gray-50', !on);
                    btn.classList.toggle('text-gray-800', !on);
                });
            }

            function show(i) {
                current = (i + visible.length) % visible.length;
                var a = visible[current];
                img.src = a.href;
                img.alt = a.getAttribute('data-caption');
                caption.textContent = a.getAttribute('data-caption');
                count.textContent = (current + 1) + ' / ' + visible.length;
                box.querySelector('[data-lb-prev]').style.display = box.querySelector('[data-lb-next]').style.display = visible.length > 1 ? '' : 'none';
            }

            function open(a) {
                visible = visibleLinks();
                lastFocus = document.activeElement;
                show(visible.indexOf(a));
                box.classList.remove('hidden');
                box.classList.add('flex');
                document.body.style.overflow = 'hidden';
                box.querySelector('[data-lb-close]').focus();
            }

            function close() {
                box.classList.add('hidden');
                box.classList.remove('flex');
                document.body.style.overflow = '';
                img.src = '';
                if (lastFocus) { lastFocus.focus(); }
            }

            filters.forEach(function (btn) {
                btn.addEventListener('click', function () { applyFilter(btn.getAttribute('data-group')); });
            });

            grid.addEventListener('click', function (e) {
                var a = e.target.closest('[data-gallery-item]');
                if (a) { e.preventDefault(); open(a); }
            });

            box.addEventListener('click', function (e) {
                if (e.target.closest('[data-lb-close]') || e.target === box) { close(); }
                else if (e.target.closest('[data-lb-prev]')) { show(current - 1); }
                else if (e.target.closest('[data-lb-next]')) { show(current + 1); }
            });

            document.addEventListener('keydown', function (e) {
                if (box.classList.contains('hidden')) { return; }
                if (e.key === 'Escape') { close(); }
                else if (e.key === 'ArrowLeft') { show(current - 1); }
                else if (e.key === 'ArrowRight') { show(current + 1); }
            });

            // swipe on touch screens
            var startX = null;
            box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
            box.addEventListener('touchend', function (e) {
                if (startX === null) { return; }
                var dx = e.changedTouches[0].clientX - startX;
                startX = null;
                if (Math.abs(dx) > 50) { show(dx < 0 ? current + 1 : current - 1); }
            });
        })();
    </script>
@endsection
