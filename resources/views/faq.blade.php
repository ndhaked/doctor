@extends('layouts.site')

@section('title', 'FAQs - ' . $site['brand_full'])

@section('content')
    {{-- Banner --}}
    <section class="bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/contact/contact-banner.jpg') }}')">
        <div class="mx-auto max-w-4xl px-4 py-16 sm:py-20 text-center min-h-[300px] flex flex-col items-center justify-center">
            <h1 class="text-3xl font-bold text-gray-950">FAQs</h1>
            <p class="mt-5 text-sm leading-7 text-gray-700">
                Read all the FAQs related to viral fever, asthma, anemia, joint pain and headache and know all the answers of your queries.
            </p>
        </div>
    </section>

    {{-- FAQ tabs + accordion --}}
    <section class="py-14 bg-[#f7f7f7]" id="faq-page">
        @if ($groups->isEmpty())
            <p class="mx-auto max-w-7xl px-4 py-10 text-center text-gray-500">No FAQs have been published yet. Please check back soon.</p>
        @else
        <div class="mx-auto max-w-7xl px-4 lg:px-8 grid lg:grid-cols-[4fr_8fr] gap-8 items-start">
            {{-- Condition tabs --}}
            <ul class="space-y-3" role="tablist">
                @foreach ($groups as $g => $group)
                    <li>
                        <button type="button" role="tab" data-faq-tab="{{ $g }}" aria-selected="{{ $g === 0 ? 'true' : 'false' }}"
                            class="faq-tab flex w-full items-center gap-4 rounded-sm border px-4 py-3.5 text-left text-base font-medium transition-colors duration-300 {{ $g === 0 ? 'border-emerald-950 bg-emerald-950 text-white' : 'border-gray-300 bg-gray-50 text-gray-800 hover:bg-white' }}">
                            @if ($group->iconUrl())
                                <img src="{{ $group->iconUrl() }}" alt="" class="faq-tab-icon h-9 w-9 object-contain transition {{ $g === 0 ? 'brightness-0 invert' : '' }}">
                            @else
                                <span class="faq-tab-icon h-9 w-9"></span>
                            @endif
                            <span>{{ $group->title }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- Questions for the selected condition --}}
            <div>
                @foreach ($groups as $g => $group)
                    <div data-faq-group="{{ $g }}" class="space-y-3 {{ $g === 0 ? '' : 'hidden' }}">
                        @foreach ($group->activeItems as $i => $item)
                            <div data-faq-item data-open="{{ $i === 0 ? 'true' : 'false' }}" class="overflow-hidden rounded-md bg-white shadow-[0_0_5px_#ededed]">
                                <button type="button" data-faq-btn aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-sm transition-colors duration-300 {{ $i === 0 ? 'bg-emerald-950 text-white' : 'bg-white text-gray-900' }}">
                                    <span>{{ $item->question }}</span>
                                    <span data-faq-icon class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all duration-300 {{ $i === 0 ? 'bg-white text-emerald-950 rotate-180' : 'bg-amber-100 text-gray-700' }}">
                                        <x-icon name="chevron-down" class="h-4 w-4" />
                                    </span>
                                </button>
                                <div data-faq-panel class="grid transition-[grid-template-rows] duration-500 ease-in-out {{ $i === 0 ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]' }}">
                                    <div class="overflow-hidden">
                                        <p class="px-4 py-4 text-sm leading-7 text-gray-700">{{ $item->answer }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <script>
            (function () {
                var root = document.getElementById('faq-page');
                var tabs = root.querySelectorAll('.faq-tab');
                var groups = root.querySelectorAll('[data-faq-group]');
                var tabOn = ['border-emerald-950', 'bg-emerald-950', 'text-white'];
                var tabOff = ['border-gray-300', 'bg-gray-50', 'text-gray-800', 'hover:bg-white'];
                var qOn = ['bg-emerald-950', 'text-white'], qOff = ['bg-white', 'text-gray-900'];
                var iOn = ['bg-white', 'text-emerald-950', 'rotate-180'], iOff = ['bg-amber-100', 'text-gray-700'];

                function setItem(item, open) {
                    var btn = item.querySelector('[data-faq-btn]');
                    var icon = item.querySelector('[data-faq-icon]');
                    var panel = item.querySelector('[data-faq-panel]');
                    panel.classList.toggle('grid-rows-[1fr]', open);
                    panel.classList.toggle('grid-rows-[0fr]', !open);
                    qOn.forEach(function (c) { btn.classList.toggle(c, open); });
                    qOff.forEach(function (c) { btn.classList.toggle(c, !open); });
                    iOn.forEach(function (c) { icon.classList.toggle(c, open); });
                    iOff.forEach(function (c) { icon.classList.toggle(c, !open); });
                    btn.setAttribute('aria-expanded', open);
                    item.dataset.open = open;
                }

                // One question open at a time inside each condition; clicking the open one closes it
                groups.forEach(function (group) {
                    var items = group.querySelectorAll('[data-faq-item]');
                    items.forEach(function (item) {
                        item.querySelector('[data-faq-btn]').addEventListener('click', function () {
                            var willOpen = item.dataset.open !== 'true';
                            items.forEach(function (other) { setItem(other, other === item && willOpen); });
                        });
                    });
                });

                // Switching condition shows its questions, with the first one open
                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        var key = tab.dataset.faqTab;
                        tabs.forEach(function (t) {
                            var active = t === tab;
                            tabOn.forEach(function (c) { t.classList.toggle(c, active); });
                            tabOff.forEach(function (c) { t.classList.toggle(c, !active); });
                            t.querySelector('.faq-tab-icon').classList.toggle('brightness-0', active);
                            t.querySelector('.faq-tab-icon').classList.toggle('invert', active);
                            t.setAttribute('aria-selected', active);
                        });
                        groups.forEach(function (group) {
                            var show = group.dataset.faqGroup === key;
                            group.classList.toggle('hidden', !show);
                            if (show) {
                                var items = group.querySelectorAll('[data-faq-item]');
                                items.forEach(function (item, i) { setItem(item, i === 0); });
                                group.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 300, easing: 'ease-out' });
                            }
                        });
                    });
                });
            })();
        </script>
    </section>

    @include('partials.book-appointment')
@endsection
