<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', trim($site['brand_name'].' - '.$site['meta_title'], ' -'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Hero slider: any number of slides, rotated by a small script (see welcome.blade.php) */
            .hero-slide {
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.9s ease-in-out;
            }
            .hero-slide.is-active {
                opacity: 1;
                pointer-events: auto;
            }
            .hero-dot {
                width: 0.375rem;
                background-color: #d1d5db;
                transition: width 0.4s ease, background-color 0.4s ease;
            }
            .hero-dot.is-active {
                width: 1.5rem;
                background-color: #022c22;
            }

            /* Header only gets its bottom border once the page is scrolled (i.e. once it's stuck to the top) */
            #site-header.is-stuck { border-bottom-color: #111827; }

            /* Hollow/outlined service card number */
            .services-card-footer span {
                color: #07362a;
                -webkit-text-fill-color: white;
                -webkit-text-stroke-width: 1px;
                -webkit-text-stroke-color: #07362a;
                font-size: 40px;
                line-height: 1;
            }

            /* Subtle decorative dot grid, like the corner pattern on the services section */
            .about-dots {
                background-image: radial-gradient(rgba(255, 255, 255, 0.55) 1.5px, transparent 1.5px);
                background-size: 22px 22px;
            }

            .services-dots {
                background-image: radial-gradient(#d1d5db 1.5px, transparent 1.5px);
                background-size: 14px 14px;
                mask-image: radial-gradient(circle at top right, black 0%, transparent 75%);
                -webkit-mask-image: radial-gradient(circle at top right, black 0%, transparent 75%);
            }

            .main-services-img {
                padding: 0 20px;
                transform: translateY(-25px);
                margin-bottom: -25px;
            }
            .main-services-img img {
                border-radius: 5px;
                display: block;
                width: 100%;
                height: 150px;
                object-fit: cover;
                box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.25);
            }
        </style>
    </head>
    <body class="bg-white text-gray-900 antialiased">
        @php $h = request()->routeIs('home') ? '' : url('/'); @endphp

        {{-- Top bar --}}
        <div class="text-sm" style="background-color: {{ $site['topbar_bg'] }}; color: {{ $site['topbar_text'] }}">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 py-2.5 flex items-center justify-between">
                <div class="flex items-center gap-4 sm:gap-6">
                    @if ($site['phone'] !== '')
                        <a href="{{ $site['phone_href'] }}" class="flex items-center gap-1.5">
                            <x-icon name="phone" class="w-4 h-4" />
                            <span class="hidden sm:inline">Phone :</span> {{ $site['phone'] }}
                        </a>
                    @endif
                    @if ($site['email'] !== '')
                        <a href="mailto:{{ $site['email'] }}" class="hidden sm:flex items-center gap-1.5">
                            <x-icon name="mail" class="w-4 h-4" />
                            Email : {{ $site['email'] }}
                        </a>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if ($site['facebook'] !== '')
<a href="{{ $site['facebook'] }}" target="_blank" rel="noopener" title="Facebook" aria-label="Facebook" class="h-8 w-8 rounded-full border border-current flex items-center justify-center hover:opacity-70 transition">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 fill-current"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.5 1.6-1.5h1.7V4.4c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.2v2.3H7.5V14h2.8v8h3.2z"/></svg>
                    </a>
@endif
                    @if ($site['instagram'] !== '')
<a href="{{ $site['instagram'] }}" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram" class="h-8 w-8 rounded-full border border-current flex items-center justify-center hover:opacity-70 transition">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 fill-none stroke-current" stroke-width="2.4"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" class="fill-current stroke-0"/></svg>
                    </a>
@endif
                    @if ($site['youtube'] !== '')
<a href="{{ $site['youtube'] }}" target="_blank" rel="noopener" title="YouTube" aria-label="YouTube" class="h-8 w-8 rounded-full border border-current flex items-center justify-center hover:opacity-70 transition">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 fill-current"><path d="M21.6 7.2a2.5 2.5 0 00-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 002.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 001.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 001.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15V9l5.2 3L10 15z"/></svg>
                    </a>
@endif
                </div>
            </div>
        </div>

        {{-- Header / Nav --}}
        <header id="site-header" class="sticky top-0 z-30 border-b-2 border-transparent transition-colors duration-200" style="background-color: {{ $site['header_bg'] }}">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 h-16 flex items-center justify-between">
                <div class="logo-box">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        @if ($site['logo_url'])
                            <img src="{{ $site['logo_url'] }}" alt="{{ $site['brand_name'] }}" class="h-11 w-auto max-w-[220px] object-contain">
                        @else
                        <span class="h-9 w-9 rounded-full bg-white ring-1 ring-gray-100 shadow-sm flex items-center justify-center">
                            <svg viewBox="0 0 24 24" class="w-5 h-5">
                                <path fill="#0f766e" d="M12 21s-6.716-4.24-9.428-8.28C.6 9.936 1.02 6.6 3.6 4.8c2.1-1.47 4.86-1.05 6.51.75L12 7.5l1.89-1.95c1.65-1.8 4.41-2.22 6.51-.75 2.58 1.8 3 5.136 1.028 7.92C18.716 16.76 12 21 12 21z"/>
                                <path stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M4.5 12h3l1.5-3 2 5 1.5-3h3.5"/>
                            </svg>
                        </span>
                        <span class="leading-none">
                            <span class="block text-lg font-bold text-gray-900">{{ $site['brand_name'] }}</span>
                            @if ($site['brand_tagline'] !== '')<span class="block text-base font-bold text-teal-500 -mt-0.5">{{ $site['brand_tagline'] }}</span>@endif
                        </span>
                    @endif
                    </a>
                </div>

                <div class="nav-outer flex items-center gap-6">
                <nav class="nav main-menu hidden lg:flex items-center gap-6 text-sm font-medium text-gray-700">
                    <a href="{{ $h }}#home" class="{{ request()->routeIs('home') ? 'text-gray-900 font-semibold' : 'hover:text-emerald-600' }}">Home</a>
                    <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'text-gray-900 font-semibold' : 'hover:text-emerald-600' }}">About Doctor</a>

                    <div class="relative group py-6 -my-6">
                        <a href="{{ $h }}#services" class="flex items-center gap-1 hover:text-emerald-600">
                            Expertise Services
                            <svg viewBox="0 0 12 8" class="w-2.5 h-2.5 fill-current text-gray-400 transition group-hover:rotate-180"><path d="M0 0l6 8 6-8z"/></svg>
                        </a>

                        <div class="absolute left-1/2 -translate-x-1/2 top-full pt-2 w-64 opacity-0 invisible translate-y-1 group-hover:opacity-100 group-hover:visible group-hover:translate-y-0 transition duration-150 z-40">
                            <div class="bg-white rounded-xl shadow-lg border border-gray-100 py-2">
                                @forelse ($navServices as $item)
                                    <a href="{{ $h }}#services" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-600">
                                        <x-icon :name="$item->icon" class="w-4 h-4 text-emerald-600 shrink-0" />
                                        {{ $item->title }}
                                    </a>
                                @empty
                                    <span class="block px-4 py-2 text-sm text-gray-400">No services yet</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'text-gray-900 font-semibold' : 'hover:text-emerald-600' }}">Contact Us</a>

                    <div class="relative group py-6 -my-6">
                        <span class="flex items-center gap-1 hover:text-emerald-600 cursor-pointer">
                            More
                            <svg viewBox="0 0 12 8" class="w-2.5 h-2.5 fill-current text-gray-400 transition group-hover:rotate-180"><path d="M0 0l6 8 6-8z"/></svg>
                        </span>

                        <div class="absolute right-0 top-full pt-2 w-48 opacity-0 invisible translate-y-1 group-hover:opacity-100 group-hover:visible group-hover:translate-y-0 transition duration-150 z-40">
                            <div class="bg-white rounded-xl shadow-lg border border-gray-100 py-2">
                                <a href="{{ route('health-tips') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-600">Health Tips</a>
                                <a href="{{ $h }}#testimonials" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-600">Testimonials</a>
                                <a href="{{ route('faq') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-600">FAQs</a>
                                <a href="{{ route('gallery') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-600">Galleries</a>
                                <a href="{{ route('video-gallery') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-600">Video Gallery</a>
                            </div>
                        </div>
                    </div>
                </nav>

                    <div class="btn-header">
                        <a href="{{ $h }}#appointment" data-open-appointment class="hidden sm:inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-900 hover:bg-emerald-950 hover:border-emerald-950 hover:text-white transition">
                            Get Appointment
                        </a>
                    </div>
                </div>
            </div>
        </header>

        @yield('content')

        {{-- Footer --}}
        <footer id="contact" class="bg-gray-50 border-t border-gray-300">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 pt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]">
                <div>
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                    @if ($site['logo_url'])
                        <img src="{{ $site['logo_url'] }}" alt="{{ $site['brand_name'] }}" class="h-16 w-auto max-w-[260px] object-contain">
                    @else
                        <span class="h-14 w-14 rounded-full bg-white ring-1 ring-gray-100 shadow-sm flex items-center justify-center">
                            <svg viewBox="0 0 24 24" class="w-8 h-8">
                                <path fill="#0f766e" d="M12 21s-6.716-4.24-9.428-8.28C.6 9.936 1.02 6.6 3.6 4.8c2.1-1.47 4.86-1.05 6.51.75L12 7.5l1.89-1.95c1.65-1.8 4.41-2.22 6.51-.75 2.58 1.8 3 5.136 1.028 7.92C18.716 16.76 12 21 12 21z"/>
                                <path stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M4.5 12h3l1.5-3 2 5 1.5-3h3.5"/>
                            </svg>
                        </span>
                        <span class="leading-none">
                            <span class="block text-3xl font-bold text-emerald-950">{{ $site['brand_name'] }}</span>
                            @if ($site['brand_tagline'] !== '')<span class="block text-2xl font-bold text-teal-500">{{ $site['brand_tagline'] }}</span>@endif
                        </span>
                    @endif
                    </a>
                    @if ($site['footer_text'] !== '')
                        <p class="mt-6 text-sm leading-7 text-gray-700 max-w-md">{{ $site['footer_text'] }}</p>
                    @endif
                    <div class="mt-6 flex gap-3">
                        @if ($site['facebook'] !== '')
<a href="{{ $site['facebook'] }}" target="_blank" rel="noopener" aria-label="Facebook" class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-700 text-gray-900 hover:bg-emerald-950 hover:text-white transition">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.5 1.6-1.5h1.7V4.4c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.2v2.3H7.5V14h2.8v8h3.2z"/></svg>
                        </a>
@endif
                        @if ($site['instagram'] !== '')
<a href="{{ $site['instagram'] }}" target="_blank" rel="noopener" aria-label="Instagram" class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-700 text-gray-900 hover:bg-emerald-950 hover:text-white transition">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" class="fill-current"/></svg>
                        </a>
@endif
                        @if ($site['youtube'] !== '')
<a href="{{ $site['youtube'] }}" target="_blank" rel="noopener" aria-label="YouTube" class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-700 text-gray-900 hover:bg-emerald-950 hover:text-white transition">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current"><path d="M21.6 7.2a2.5 2.5 0 00-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 002.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 001.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 001.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15V9l5.2 3L10 15z"/></svg>
                        </a>
@endif
                    </div>
                </div>

                <div>
                    <h4 class="text-2xl font-medium text-emerald-950">Quick Links</h4>
                    <span class="mt-2 mb-6 flex h-[3px] w-28"><span class="w-1/2 bg-amber-400"></span><span class="w-1/2 bg-emerald-950"></span></span>
                    <ul class="space-y-3.5 text-sm text-gray-800">
                        @foreach ([['Home', $h . '#home'], ['About Doctor', route('about')], ['Image Gallery', route('gallery')], ['Video Gallery', route('video-gallery')], ['FAQs', route('faq')], ['Contact Us', route('contact')]] as [$label, $href])
                            <li class="flex items-center gap-3">
                                <span class="h-0 w-0 border-y-[5px] border-l-[7px] border-y-transparent border-l-amber-400"></span>
                                <a href="{{ $href }}" class="hover:text-emerald-700">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-2xl font-medium text-emerald-950">Appointment Timing</h4>
                    <span class="mt-2 mb-6 flex h-[3px] w-28"><span class="w-1/2 bg-amber-400"></span><span class="w-1/2 bg-emerald-950"></span></span>
                    <div class="space-y-3 text-sm text-gray-800">
                        <p class="uppercase">Clinic Time</p>
                        @if ($site['timing_title'] !== '')
                            <p class="flex items-center gap-3 font-bold">
                                <span class="h-0 w-0 border-y-[5px] border-l-[7px] border-y-transparent border-l-amber-400"></span>
                                {{ $site['timing_title'] }}
                            </p>
                        @endif
                        @if ($site['timing_days'] !== '')<p>{{ $site['timing_days'] }}</p>@endif
                        @if ($site['timing_hours'] !== '')<p>{{ $site['timing_hours'] }}</p>@endif
                        @if ($site['timing_note'] !== '')<p class="font-bold">{{ $site['timing_note'] }}</p>@endif
                    </div>
                </div>

                <div>
                    <h4 class="text-2xl font-medium text-emerald-950">Contact Info</h4>
                    <span class="mt-2 mb-6 flex h-[3px] w-28"><span class="w-1/2 bg-amber-400"></span><span class="w-1/2 bg-emerald-950"></span></span>
                    <ul class="space-y-3 text-sm text-gray-800">
                        @if ($site['phone'] !== '')
                            <li class="flex items-start gap-3"><x-icon name="phone" class="h-4 w-4 mt-0.5 shrink-0" /><a href="{{ $site['phone_href'] }}">{{ $site['phone'] }}</a></li>
                        @endif
                        @if ($site['email'] !== '')
                            <li class="flex items-start gap-3"><x-icon name="mail" class="h-4 w-4 mt-0.5 shrink-0" /><a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a></li>
                        @endif
                        @if ($site['address'] !== '')
                            <li class="flex items-start gap-3"><x-icon name="building" class="h-4 w-4 mt-0.5 shrink-0" /><span>{{ $site['address'] }}</span></li>
                        @endif
                    </ul>
                </div>
            </div>

            @if (count($site['footer_links']))
            <div class="mx-auto max-w-7xl px-4 lg:px-8 mt-8 pb-8" data-footer-links>
                <div class="rounded-md border border-gray-200 bg-white">
                    <button type="button" data-fl-btn aria-expanded="true" class="flex w-full items-center justify-between rounded-t-md bg-sky-50 px-5 py-4 text-left text-sm text-gray-900 transition-colors duration-300">
                        <span>Quick Links</span>
                        <x-icon name="chevron-down" data-fl-icon class="h-5 w-5 transition-transform duration-300 rotate-180" />
                    </button>
                    <div data-fl-panel class="grid transition-[grid-template-rows] duration-500 ease-in-out grid-rows-[1fr]">
                        <div class="overflow-hidden">
                            <ul class="flex flex-wrap gap-y-3 px-5 pb-5 pt-3 text-sm text-gray-900">
                                @foreach ($site['footer_links'] as $link)
                                    <li class="border-r border-gray-300 px-3 first:pl-3 last:border-r-0"><a href="{{ $link['url'] }}" class="hover:text-emerald-700">{{ $link['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="bg-emerald-950 py-5 text-center text-sm text-white">
                Copyright &copy; {{ date('Y') }} @if ($site['copyright_name'] !== '')<span class="text-amber-400">{{ $site['copyright_name'] }}</span>. @endif All Rights Reserved @if ($site['credit_name'] !== '') Design &amp; Developed by <span class="text-amber-400">{{ $site['credit_name'] }}</span>@endif
            </div>

            @if (count($site['footer_links']))
            <script>
                (function () {
                    var root = document.querySelector('[data-footer-links]');
                    if (!root) return;
                    var btn = root.querySelector('[data-fl-btn]'), panel = root.querySelector('[data-fl-panel]'), icon = root.querySelector('[data-fl-icon]');
                    btn.addEventListener('click', function () {
                        var open = btn.getAttribute('aria-expanded') !== 'true';
                        btn.setAttribute('aria-expanded', open);
                        panel.classList.toggle('grid-rows-[1fr]', open);
                        panel.classList.toggle('grid-rows-[0fr]', !open);
                        icon.classList.toggle('rotate-180', open);
                        btn.classList.toggle('bg-sky-50', open);
                        btn.classList.toggle('bg-white', !open);
                    });
                })();
            </script>
            @endif
        </footer>

        <script>
            (function () {
                var header = document.getElementById('site-header');
                if (!header) return;

                function onScroll() {
                    header.classList.toggle('is-stuck', window.scrollY > 4);
                }

                onScroll();
                window.addEventListener('scroll', onScroll, { passive: true });
            })();
        </script>

        {{-- Appointment popup --}}
        <div id="appointment-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-black/50 p-4 opacity-0 transition-opacity duration-150 ease-linear" role="dialog" aria-modal="true" aria-labelledby="appointment-modal-title">
            <div class="relative my-auto w-full max-w-4xl -translate-y-[50px] transition-transform duration-300 ease-out" data-modal-card>
                <button type="button" data-close-appointment aria-label="Close" class="absolute -top-0 right-0 z-10 flex h-12 w-12 items-center justify-center bg-white text-gray-950 shadow hover:bg-gray-100 sm:-right-[3.25rem] sm:top-0">
                    <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="2.5" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
                </button>

                <div class="grid overflow-hidden bg-white md:grid-cols-[1.3fr_1fr]">
                    <div class="bg-[#f2f3f5] p-6 sm:p-8">
                        <h3 id="appointment-modal-title" class="text-2xl font-medium text-gray-950">{{ $appt['popup_title'] }}</h3>
                        @if ($appt['popup_subtitle'] !== '')<p class="mt-1 text-sm text-gray-800">{{ $appt['popup_subtitle'] }}</p>@endif

                        <form method="POST" action="{{ route('enquiries.store') }}" data-enquiry-form data-close-after="3500" class="mt-5 space-y-4 text-sm">
                            <input type="hidden" name="source" value="popup">
                            <div class="absolute left-[-9999px] top-auto h-0 w-0 overflow-hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                            <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3">
                                <x-icon name="user-doctor" class="h-4 w-4 shrink-0 text-gray-800" />
                                <input type="text" name="name" autocomplete="name" placeholder="Your Name" class="w-full border-0 bg-transparent py-3 text-sm focus:ring-0">
                            </label>
                            <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3">
                                <x-icon name="phone" class="h-4 w-4 shrink-0 text-gray-800" />
                                <input type="tel" name="phone" autocomplete="tel" placeholder="Phone No." class="w-full border-0 bg-transparent py-3 text-sm focus:ring-0">
                            </label>
                            <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3">
                                <x-icon name="mail" class="h-4 w-4 shrink-0 text-gray-800" />
                                <input type="email" name="email" autocomplete="email" placeholder="Your Email" class="w-full border-0 bg-transparent py-3 text-sm focus:ring-0">
                            </label>
                            <label class="flex items-start gap-2 rounded-md border border-gray-200 bg-white px-3 pt-3">
                                <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0 fill-none stroke-current text-gray-800" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
                                <textarea rows="4" name="message" placeholder="Write Your Message Here....." class="w-full resize-y border-0 bg-transparent pb-3 text-sm focus:ring-0"></textarea>
                            </label>

                            <label class="flex items-start gap-2 text-xs text-gray-800">
                                <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-gray-300">
                                <span>{{ $appt['consent'] }}</span>
                            </label>
                            <label class="flex items-start gap-2 text-xs text-gray-800">
                                <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-gray-300">
                                <span>By submitting this form, you agree to the following <a href="#" class="underline">Terms &amp; Conditions</a> and <a href="#" class="underline">Privacy Policy</a>.</span>
                            </label>

                            <div class="pt-1 text-center">
                                <button type="submit" class="rounded-full border border-emerald-950 px-14 py-2.5 text-sm text-gray-950 hover:bg-emerald-950 hover:text-white transition">{{ $appt['popup_submit'] }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="flex flex-col items-center bg-emerald-950 px-6 py-8 text-center text-white">
                        @php $popupLogo = $appt['popup_logo'] !== '' ? asset($appt['popup_logo']) : $site['logo_url']; @endphp
                        <div class="rounded-md bg-white px-6 py-4">
                            @if ($popupLogo)
                                <img src="{{ $popupLogo }}" alt="{{ $site['brand_full'] }}" class="h-20 w-auto max-w-[240px] object-contain">
                            @else
                                <span class="flex items-center gap-3">
                                    <svg viewBox="0 0 24 24" class="h-12 w-12"><path fill="#0f766e" d="M12 21s-6.716-4.24-9.428-8.28C.6 9.936 1.02 6.6 3.6 4.8c2.1-1.47 4.86-1.05 6.51.75L12 7.5l1.89-1.95c1.65-1.8 4.41-2.22 6.51-.75 2.58 1.8 3 5.136 1.028 7.92C18.716 16.76 12 21 12 21z"/><path stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M4.5 12h3l1.5-3 2 5 1.5-3h3.5"/></svg>
                                    <span class="text-left leading-none"><span class="block text-2xl font-bold text-gray-900">{{ $site['brand_name'] }}</span>@if ($site['brand_tagline'] !== '')<span class="block text-xl font-bold text-teal-500">{{ $site['brand_tagline'] }}</span>@endif</span>
                                </span>
                            @endif
                        </div>

                        <svg viewBox="0 0 24 24" class="mt-12 h-11 w-11 fill-white"><path d="M7 2a1 1 0 011 1v1h8V3a1 1 0 112 0v1h1a3 3 0 013 3v12a3 3 0 01-3 3H5a3 3 0 01-3-3V7a3 3 0 013-3h1V3a1 1 0 011-1zm-2 8v9a1 1 0 001 1h12a1 1 0 001-1v-9H5zm3 2h2v2H8v-2zm3 0h2v2h-2v-2zm3 0h2v2h-2v-2zm-6 3h2v2H8v-2zm3 0h2v2h-2v-2zm3 0h2v2h-2v-2z"/></svg>
                        @if ($appt['popup_side_title'] !== '')<h4 class="mt-3 text-xl font-medium">{{ $appt['popup_side_title'] }}</h4>@endif
                        @if ($appt['popup_side_text'] !== '')<p class="mt-4 text-xs leading-6">{{ $appt['popup_side_text'] }}</p>@endif

                        <div class="mt-auto space-y-2 pt-10 text-sm">
                            @if ($site['phone'] !== '')
                                <a href="{{ $site['phone_href'] }}" class="flex items-center justify-center gap-2"><x-icon name="phone" class="h-4 w-4" /> {{ $site['phone'] }}</a>
                            @endif
                            @if ($site['email'] !== '')
                                <a href="mailto:{{ $site['email'] }}" class="flex items-center justify-center gap-2"><x-icon name="mail" class="h-4 w-4" /> {{ $site['email'] }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var modal = document.getElementById('appointment-modal');
                if (!modal) return;
                var card = modal.querySelector('[data-modal-card]');
                var lastFocus = null;

                function open(e) {
                    if (e) e.preventDefault();
                    clearTimeout(closing);
                    lastFocus = document.activeElement;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                    // Force a reflow so the browser registers the starting state, then fade in and slide down (like a Bootstrap modal)
                    void modal.offsetWidth;
                    modal.classList.remove('opacity-0');
                    card.classList.remove('-translate-y-[50px]');
                    setTimeout(function () {
                        var first = modal.querySelector('input');
                        if (first) first.focus({ preventScroll: true });
                    }, 300);
                }
                var closing = null;
                function close() {
                    modal.classList.add('opacity-0');
                    card.classList.add('-translate-y-[50px]');
                    document.body.style.overflow = '';
                    clearTimeout(closing);
                    closing = setTimeout(function () {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        if (lastFocus) lastFocus.focus({ preventScroll: true });
                    }, 300);
                }

                document.querySelectorAll('[data-open-appointment]').forEach(function (el) { el.addEventListener('click', open); });
                modal.querySelectorAll('[data-close-appointment]').forEach(function (el) { el.addEventListener('click', close); });
                modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
            })();
        </script>
    </body>
</html>
