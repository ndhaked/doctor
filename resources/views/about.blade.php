@extends('layouts.site')

@section('title', 'About Doctor - ' . $site['brand_full'])

@section('content')
    {{-- Banner --}}
    <section class="bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/about/about-slider-bg.jpg') }}')">
        <div class="mx-auto max-w-7xl px-4 lg:px-8 py-12 lg:py-14 grid md:grid-cols-[7fr_5fr] gap-8 items-center min-h-[360px] lg:min-h-[450px]">
            <div>
                @if ($about['banner_label'] !== '')
                    <h6 class="text-xs text-gray-700">{{ $about['banner_label'] }}</h6>
                @endif
                <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-emerald-950">{{ $about['banner_name'] }}</h1>
                @if ($about['banner_qualifications'] !== '')
                    <p class="mt-4 text-xs font-bold text-gray-900 leading-6">{!! nl2br(e($about['banner_qualifications'])) !!}</p>
                @endif
                @if ($about['banner_text'] !== '')
                    <p class="mt-3 text-xs text-gray-700 leading-6 max-w-xl">{{ $about['banner_text'] }}</p>
                @endif
                <div class="mt-6 flex flex-wrap gap-4">
                    @if ($about['banner_button_text'] !== '')
                        <a href="{{ $about['banner_button_url'] }}" class="inline-flex items-center rounded-full border border-emerald-950 px-14 py-2.5 text-xs text-gray-900 hover:bg-emerald-950 hover:text-white transition">{{ $about['banner_button_text'] }}</a>
                    @endif
                    @if ($about['banner_show_phone'] && $site['phone'] !== '')
                    <a href="{{ $site['phone_href'] }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-950 px-10 py-2.5 text-xs text-gray-900 hover:bg-emerald-950 hover:text-white transition">
                        <x-icon name="phone" class="h-4 w-4" /> {{ $site['phone'] }}
                    </a>
                    @endif
                </div>
            </div>
            @if ($about['banner_photo'] !== '')
                <div class="flex justify-center md:justify-end">
                    <img src="{{ asset($about['banner_photo']) }}" alt="{{ $about['banner_name'] }}" class="h-64 w-64 sm:h-80 sm:w-80 lg:h-[22rem] lg:w-[22rem] rounded-full object-cover">
                </div>
            @endif
        </div>
    </section>

    {{-- Doctor introduction tabs --}}
    @php
        $tabs = [
            ['key' => 'overview', 'label' => 'Overview', 'img' => 'Director-img.png', 'photo' => true],
            ['key' => 'education', 'label' => 'Education & Qualification', 'img' => 'e-icon-2.png'],
            ['key' => 'experience', 'label' => 'Past Experience', 'img' => 'e-icon-3.png'],
            ['key' => 'responsibilities', 'label' => 'Current Responsibilities', 'img' => 'social-responsibility.png'],
            ['key' => 'expertise', 'label' => 'Expertise', 'img' => 'e-icon-5.png'],
            ['key' => 'research', 'label' => 'Research and publications', 'img' => 'search.png'],
            ['key' => 'awards', 'label' => 'Awards and Recognition', 'img' => 'e-icon-4.png'],
        ];
        $expertise = $aboutExpertise->map(fn ($e) => ['img' => $e->imageUrl(), 'title' => $e->title])->all();
        $lists = [
            'education' => [\App\Support\AboutPageSection::LIST_TABS['education'], $about['lists']['education']],
            'experience' => [\App\Support\AboutPageSection::LIST_TABS['experience'], $about['lists']['experience']],
            'responsibilities' => [\App\Support\AboutPageSection::LIST_TABS['responsibilities'], $about['lists']['responsibilities']],
            'expertise' => ['Expertise', array_column($expertise, 'title')],
            'research' => [\App\Support\AboutPageSection::LIST_TABS['research'], $about['lists']['research']],
            'awards' => [\App\Support\AboutPageSection::LIST_TABS['awards'], $about['lists']['awards']],
        ];
        // a tab with nothing in it is left out (the Overview tab always stays)
        $tabs = array_values(array_filter($tabs, fn ($tab) => $tab['key'] === 'overview' || ! empty($lists[$tab['key']][1])));
        $lists = array_filter($lists, fn ($list) => ! empty($list[1]));    @endphp

    <section class="py-14 bg-[#FBFBFB]" id="doctor-tabs">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <div data-tab-track class="overflow-hidden cursor-grab select-none [touch-action:pan-y]" role="tablist">
                <div class="flex gap-4 will-change-transform">
                    @foreach ($tabs as $i => $tab)
                        <button type="button" role="tab" data-tab="{{ $tab['key'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                            class="tab-btn flex items-center gap-3 rounded-md px-3 py-3.5 text-left text-sm transition-colors duration-300 shrink-0 basis-[75%] sm:basis-[calc((100%-2rem)/3)] lg:basis-[calc((100%-4rem)/5)] {{ $i === 0 ? 'bg-emerald-950 text-white' : 'bg-white text-gray-800 hover:bg-gray-50' }}">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $i === 0 ? 'bg-white' : 'bg-gray-100' }}">
                                <img src="{{ asset('images/about/' . $tab['img']) }}" alt="" class="{{ ! empty($tab['photo']) ? 'h-12 w-12 rounded-full object-cover' : 'h-7 w-7 object-contain' }}">
                            </span>
                            <span class="leading-5">{{ $tab['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
            <div data-tab-dots class="mt-4 flex gap-2.5"></div>

            <div class="mt-10">
                {{-- Overview --}}
                <div data-panel="overview" class="tab-panel">
                    <div class="grid {{ $about['overview_photo'] !== '' ? 'lg:grid-cols-[2fr_1fr]' : '' }} gap-10 items-start">
                        <div class="min-w-0">
                            <h3 class="text-2xl font-bold text-emerald-950">{{ $about['overview_title'] }}</h3>
                            @if ($about['overview_subtitle'] !== '')
                                <p class="mt-3 text-sm font-bold text-gray-900">{{ $about['overview_subtitle'] }}</p>
                            @endif
                            @foreach ($about['overview_paragraphs'] as $paragraph)
                                <p class="mt-4 text-sm leading-7 text-gray-700">{{ $paragraph }}</p>
                            @endforeach

                            @if (count($expertise))
                            @if ($about['expertise_heading'] !== '')<h4 class="mt-6 text-sm font-bold text-gray-900">{{ $about['expertise_heading'] }}</h4>@endif
                            <div data-expertise-track class="mt-4 overflow-hidden cursor-grab select-none [touch-action:pan-y]">
                                <ul class="flex gap-4 will-change-transform">
                                    @foreach ($expertise as $e)
                                        <li class="shrink-0 w-36 rounded-md bg-white px-3 py-5 text-center shadow-[0_0_8px_rgba(0,0,0,0.06)]">
                                            @if ($e['img'])<img src="{{ $e['img'] }}" alt="{{ $e['title'] }}" class="mx-auto h-10 w-10 object-contain">@endif
                                            <h6 class="mt-3 text-[11px] font-medium uppercase text-gray-900">{{ $e['title'] }}</h6>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div data-expertise-dots class="mt-4 flex gap-2.5"></div>
                            <script>
                                window.addEventListener('load', function () {
                                    var track = document.querySelector('[data-expertise-track]');
                                    if (track && window.loopSlider) {
                                        window.loopSlider(track, { dotsBox: document.querySelector('[data-expertise-dots]') });
                                    }
                                });
                            </script>
                            @endif
                        </div>

                        @if ($about['overview_photo'] !== '')
                            <img src="{{ asset($about['overview_photo']) }}" alt="{{ $about['banner_name'] }}" class="w-full max-w-sm mx-auto lg:max-w-none rounded-md object-cover shadow-lg">
                        @endif
                    </div>
                </div>

                {{-- Other tabs --}}
                @foreach ($lists as $key => [$heading, $items])
                    <div data-panel="{{ $key }}" class="tab-panel hidden">
                        <h3 class="text-2xl font-bold text-emerald-950">{{ $heading }}</h3>
                        <ul class="mt-5 space-y-3 max-w-4xl">
                            @foreach ($items as $item)
                                <li class="flex items-start gap-3 text-sm leading-7 text-gray-700">
                                    <span class="mt-2.5 h-0 w-0 shrink-0 border-y-[5px] border-l-[7px] border-y-transparent border-l-amber-400"></span>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <script>
            (function () {
                var root = document.getElementById('doctor-tabs');
                var track = root.querySelector('[data-tab-track]');
                var inner = track.firstElementChild;
                var dotsBox = root.querySelector('[data-tab-dots]');
                var panels = root.querySelectorAll('.tab-panel');
                var originals = Array.prototype.slice.call(inner.querySelectorAll('.tab-btn'));
                var N = originals.length;
                var on = ['bg-emerald-950', 'text-white'], off = ['bg-white', 'text-gray-800', 'hover:bg-gray-50'];
                var EASE = 'transform 0.4s cubic-bezier(0.22, 0.61, 0.36, 1)';

                // Infinite loop: [copy][originals][copy]. Copies are inert for assistive tech; clicks are handled by delegation.
                function makeCopies() {
                    return originals.map(function (b) {
                        var c = b.cloneNode(true);
                        c.setAttribute('aria-hidden', 'true');
                        c.tabIndex = -1;
                        return c;
                    });
                }
                var before = makeCopies(), after = makeCopies();
                before.forEach(function (c) { inner.insertBefore(c, originals[0]); });
                after.forEach(function (c) { inner.appendChild(c); });
                var all = Array.prototype.slice.call(inner.querySelectorAll('.tab-btn'));

                var x = 0, dots = [], page = 0, idleTimer = null;
                function perView() { return window.innerWidth >= 1024 ? 5 : (window.innerWidth >= 640 ? 3 : 1); }
                function pages() { return Math.ceil(N / perView()); }
                function step() { return all[1].offsetLeft - all[0].offsetLeft; }
                function setW() { return step() * N; }

                function setX(v, animate) {
                    x = v;
                    inner.style.transition = animate ? EASE : 'none';
                    inner.style.transform = 'translate3d(' + v + 'px, 0, 0)';
                }
                // Keep x inside the middle copy ([-2W, -W]) by jumping a whole set; the sets look identical so it is seamless
                function normalize() {
                    var W = setW(), shift = 0;
                    while (x + shift > -W) shift -= W;
                    while (x + shift < -2 * W) shift += W;
                    x += shift;
                    return shift;
                }
                function logicalIndex() { return ((Math.round((-x - setW()) / step()) % N) + N) % N; }

                function paint() {
                    dots.forEach(function (d, i) {
                        d.className = 'h-[3px] w-8 transition-colors ' + (i === page ? 'bg-emerald-950' : 'bg-gray-300');
                    });
                }
                function syncPage() {
                    var p = Math.min(Math.floor(logicalIndex() / perView()), pages() - 1);
                    if (p !== page) { page = p; paint(); }
                }
                function settle() {
                    var s = normalize();
                    if (s) { inner.style.transition = 'none'; inner.style.transform = 'translate3d(' + x + 'px, 0, 0)'; }
                    syncPage();
                }
                function glideTo(v) {
                    setX(v, true);
                    clearTimeout(idleTimer);
                    idleTimer = setTimeout(settle, 450);
                }
                function snapTo(v) { glideTo(-Math.round(-v / step()) * step()); }

                function goTo(p) {
                    var cur = logicalIndex(), target = Math.min(p * perView(), N - 1);
                    var diff = target - cur;
                    if (diff > N / 2) diff -= N; else if (diff < -N / 2) diff += N;
                    page = p; paint();
                    glideTo(x - diff * step());
                }
                function buildDots() {
                    dotsBox.innerHTML = ''; dots = [];
                    for (var i = 0; i < pages(); i++) {
                        (function (i) {
                            var d = document.createElement('button');
                            d.type = 'button';
                            d.setAttribute('aria-label', 'Show tabs page ' + (i + 1));
                            d.addEventListener('click', function () { goTo(i); });
                            dotsBox.appendChild(d); dots.push(d);
                        })(i);
                    }
                    setX(-setW(), false);
                    page = 0; paint();
                }
                window.addEventListener('resize', buildDots);
                buildDots();

                // Drag (mouse, touch, pen)
                var drag = { down: false, moved: false, startX: 0, startPos: 0, lastX: 0, lastT: 0, vel: 0, id: null };
                track.addEventListener('pointerdown', function (e) {
                    if (e.pointerType === 'mouse' && e.button !== 0) return;
                    clearTimeout(idleTimer); settle();
                    drag.down = true; drag.moved = false; drag.id = e.pointerId;
                    drag.startX = drag.lastX = e.clientX; drag.startPos = x;
                    drag.lastT = performance.now(); drag.vel = 0;
                });
                window.addEventListener('pointermove', function (e) {
                    if (!drag.down || e.pointerId !== drag.id) return;
                    var dx = e.clientX - drag.startX;
                    if (!drag.moved) {
                        if (Math.abs(dx) < 6) return;
                        drag.moved = true;
                        track.setPointerCapture(e.pointerId);
                        track.classList.add('cursor-grabbing');
                    }
                    var now = performance.now();
                    drag.vel = (e.clientX - drag.lastX) / Math.max(1, now - drag.lastT);
                    drag.lastX = e.clientX; drag.lastT = now;

                    setX(drag.startPos + dx, false);
                    var shift = normalize();
                    if (shift) { drag.startPos += shift; setX(x, false); }
                });
                function endDrag(e) {
                    if (!drag.down || (e && e.pointerId !== drag.id)) return;
                    drag.down = false;
                    track.classList.remove('cursor-grabbing');
                    if (drag.moved) snapTo(x + drag.vel * 180);
                }
                window.addEventListener('pointerup', endDrag);
                window.addEventListener('pointercancel', endDrag);
                track.addEventListener('click', function (e) {
                    if (drag.moved) { e.stopPropagation(); e.preventDefault(); drag.moved = false; }
                }, true);
                track.addEventListener('dragstart', function (e) { e.preventDefault(); });

                // Tab selection (originals and copies share the same data-tab key)
                function select(key) {
                    all.forEach(function (b) {
                        var active = b.dataset.tab === key;
                        on.forEach(function (c) { b.classList.toggle(c, active); });
                        off.forEach(function (c) { b.classList.toggle(c, !active); });
                        b.setAttribute('aria-selected', active);
                        var circle = b.firstElementChild;
                        circle.classList.toggle('bg-white', active);
                        circle.classList.toggle('bg-gray-100', !active);
                    });
                    panels.forEach(function (p) {
                        var show = p.dataset.panel === key;
                        p.classList.toggle('hidden', !show);
                        if (show) { p.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 300 }); }
                    });
                }
                // Glide so the clicked tab's previous and next neighbours come into view
                function reveal(btn) {
                    var view = track.getBoundingClientRect();
                    var next = btn.nextElementSibling && btn.nextElementSibling.getBoundingClientRect();
                    var prev = btn.previousElementSibling && btn.previousElementSibling.getBoundingClientRect();
                    var target = x;
                    if (next && next.right > view.right) target = x - (next.right - view.right);
                    else if (prev && prev.left < view.left) target = x + (view.left - prev.left);
                    if (target !== x) glideTo(target);
                }
                inner.addEventListener('click', function (e) {
                    var btn = e.target.closest('.tab-btn');
                    if (!btn) return;
                    reveal(btn);
                    select(btn.dataset.tab);
                });
            })();
        </script>
    </section>

    {{-- Call to action (managed in Admin > About Page) --}}
    @if ($about['cta_visible'])
    <section class="py-16 bg-[#202020] text-white text-center">
        <div class="mx-auto max-w-4xl px-4">
            <h3 class="text-2xl sm:text-3xl font-bold">{{ $about['cta_heading'] }}</h3>
            @if ($about['cta_text'] !== '')
                <p class="mt-4 text-sm">{{ $about['cta_text'] }}</p>
            @endif
            <div class="mt-8 flex flex-wrap items-center justify-center gap-5">
                @if ($about['cta_button_text'] !== '')
                    <a href="#appointment" data-open-appointment class="inline-flex items-center rounded-full bg-white px-12 py-3 text-sm text-gray-900 hover:bg-emerald-50 transition">{{ $about['cta_button_text'] }}</a>
                @endif
                @if ($about['cta_show_phone'] && $site['phone'] !== '')
                    <a href="{{ $site['phone_href'] }}" class="inline-flex items-center gap-3 text-sm">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-gray-900"><x-icon name="phone" class="h-4 w-4" /></span>
                        {{ $site['phone'] }}
                    </a>
                @endif
            </div>
        </div>
    </section>
    @endif
    @include('partials.book-appointment')

    {{-- Main treatments (managed in Admin > About Page) --}}
    @if ($about['treat_visible'] && $aboutTreatments->isNotEmpty())
    <section class="relative py-14 bg-[#F9F9F9] overflow-hidden">
        <img src="{{ asset('images/about/bg-doctor-icon.png') }}" alt="" class="pointer-events-none absolute right-0 top-0 w-40 sm:w-52 lg:w-64 select-none" aria-hidden="true">
        <div class="relative mx-auto max-w-7xl px-4 lg:px-8">
            <div class="text-center">
                @if ($about['treat_eyebrow'] !== '')
                    <h6 class="text-xs uppercase text-gray-800">{{ $about['treat_eyebrow'] }}</h6>
                @endif
                <h2 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">{{ $about['treat_heading'] }}</h2>
                @if ($about['treat_intro'] !== '')
                    <p class="mt-2 text-sm text-gray-700">{{ $about['treat_intro'] }}</p>
                @endif
            </div>

            <ul class="mt-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-4 gap-y-10">
                @foreach ($aboutTreatments as $treatment)
                    <li class="text-center">
                        <div class="relative mx-auto h-[175px] w-[175px]">
                            <span class="absolute -top-2 left-0 h-[175px] w-[175px] rounded-full bg-amber-400"></span>
                            @if ($treatment->imageUrl())
                                <img src="{{ $treatment->imageUrl() }}" alt="{{ $treatment->title }}" class="relative h-[170px] w-[170px] rounded-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <h4 class="mt-4 text-base font-bold text-gray-950">{{ $treatment->title }}</h4>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    @endif
    @include('partials.patient-reviews')
@endsection
