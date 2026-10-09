@extends('layouts.site')

@section('content')
        {{-- Hero: slides are managed from the admin panel (Admin > Hero Slider) --}}
        @if ($slides->isNotEmpty())
        <section id="home" class="relative overflow-hidden min-h-[620px] lg:min-h-[680px]" data-hero>
            @foreach ($slides as $slide)
                <div class="hero-slide absolute inset-0 {{ $loop->first ? 'is-active' : '' }}" data-hero-slide>
                    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $slide->imageUrl() }}')"></div>

                    <div class="absolute inset-0 flex items-center">
                        {{-- Fade to white so the overlaid text stays readable --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/60 to-white lg:via-white/40 pointer-events-none"></div>

                        <div class="relative mx-auto max-w-7xl px-4 lg:px-8 w-full">
                            <div class="lg:ml-auto lg:w-1/2 text-center">
                                <span class="inline-block h-24 w-24 rounded-full bg-white ring-4 ring-white shadow-lg overflow-hidden text-emerald-200">
                                    @if ($slide->doctorImageUrl())
                                        <img src="{{ $slide->doctorImageUrl() }}" alt="{{ $slide->doctor_name }}" class="h-full w-full object-cover">
                                    @elseif (file_exists(public_path('images/doctor.webp')))
                                        <img src="{{ asset('images/doctor.webp') }}" alt="{{ $slide->doctor_name }}" class="h-full w-full object-cover">
                                    @else
                                        <x-icon name="user-doctor" class="w-full h-full p-4" />
                                    @endif
                                </span>
                                @if ($slide->doctor_name)
                                    <p class="mt-2 text-sm font-semibold text-gray-800">{{ $slide->doctor_name }}</p>
                                @endif

                                @if ($slide->badge)
                                    <span class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/80 text-emerald-700 text-xs font-semibold px-3 py-1 shadow-sm">
                                        {{ $slide->badge }}
                                    </span>
                                @endif

                                <h1 class="mt-4 text-3xl lg:text-4xl font-bold text-emerald-950">{{ $slide->title }}</h1>
                                @if ($slide->credentials)
                                    <p class="mt-2 text-gray-700">{{ $slide->credentials }}</p>
                                @endif
                                @if ($slide->specialty)
                                    <p class="text-emerald-700 font-medium">{{ $slide->specialty }}</p>
                                @endif

                                @if ($slide->description)
                                    <p class="mt-4 text-sm text-gray-600 leading-relaxed max-w-lg mx-auto">{{ $slide->description }}</p>
                                @endif

                                @if (! empty($slide->cards))
                                    <div class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-lg mx-auto">
                                        @foreach ($slide->cards as $quick)
                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 py-4 px-2 flex flex-col items-center gap-2">
                                                <x-icon :name="$quick['icon'] ?? 'home'" class="w-7 h-7 text-emerald-600" />
                                                <span class="text-xs font-medium text-gray-700">{{ $quick['title'] ?? '' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($slide->cta_text)
                                    <a href="{{ $slide->ctaHref() }}" @if ($slide->ctaOpensPopup()) data-open-appointment @endif class="mt-8 inline-flex items-center gap-2 rounded-full border-2 border-emerald-950 px-7 py-2.5 text-sm font-semibold text-emerald-950 hover:bg-emerald-950 hover:text-white transition">
                                        {{ $slide->cta_text }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($slides->count() > 1)
                <div class="absolute inset-x-0 bottom-6 z-10 flex items-center justify-center gap-2">
                    @foreach ($slides as $slide)
                        <button type="button" data-hero-dot aria-label="Show slide {{ $loop->iteration }}" class="hero-dot h-1.5 rounded-full {{ $loop->first ? 'is-active' : '' }}"></button>
                    @endforeach
                </div>

                <script>
                    (function () {
                        var root = document.querySelector('[data-hero]');
                        var slides = root.querySelectorAll('[data-hero-slide]');
                        var dots = root.querySelectorAll('[data-hero-dot]');
                        var current = 0, timer = null;

                        function show(i) {
                            current = (i + slides.length) % slides.length;
                            slides.forEach(function (s, n) { s.classList.toggle('is-active', n === current); });
                            dots.forEach(function (d, n) { d.classList.toggle('is-active', n === current); });
                        }
                        function start() { stop(); timer = setInterval(function () { show(current + 1); }, 4000); }
                        function stop() { clearInterval(timer); }

                        dots.forEach(function (d, n) { d.addEventListener('click', function () { show(n); start(); }); });
                        root.addEventListener('mouseenter', stop);
                        root.addEventListener('mouseleave', start);
                        start();
                    })();
                </script>
            @endif
        </section>
        @endif

        {{-- Info strip: managed from the admin panel (Admin > Info Strip); can be hidden there --}}
        @if ($strip['visible'])
        <section class="bg-white border-b border-gray-100">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 py-12 grid sm:grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-dashed lg:divide-gray-300">
                {{-- Doctor info --}}
                <div class="lg:pr-8">
                    <x-icon name="user-doctor" class="w-10 h-10 text-emerald-700" />
                    <h3 class="mt-4 font-bold text-gray-900">{{ $strip['doctor_name'] }}</h3>
                    @if ($strip['doctor_text'] !== '')
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $strip['doctor_text'] }}</p>
                    @endif
                    @if ($strip['doctor_button_text'] !== '')
                        <a href="{{ $strip['doctor_button_url'] }}" class="mt-4 inline-flex items-center rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                            {{ $strip['doctor_button_text'] }}
                        </a>
                    @endif
                </div>

                {{-- Google reviews (rating, count and link come from the Reviews section) --}}
                <div class="mt-10 sm:mt-0 lg:px-8">
                    <svg viewBox="0 0 48 48" class="w-10 h-10">
                        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.4 29.3 35 24 35c-6.1 0-11-4.9-11-11s4.9-11 11-11c2.8 0 5.3 1 7.3 2.7l5.7-5.7C33.5 6.5 29 4.5 24 4.5 12.7 4.5 3.5 13.7 3.5 25S12.7 45.5 24 45.5 44.5 36.3 44.5 25c0-1.6-.2-3.1-.4-4.5z"/>
                        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 16 19 13 24 13c2.8 0 5.3 1 7.3 2.7l5.7-5.7C33.5 6.5 29 4.5 24 4.5c-7.5 0-14 4.2-17.7 10.2z"/>
                        <path fill="#4CAF50" d="M24 45.5c5.2 0 9.9-2 13.4-5.2l-6.2-5.2c-2 1.4-4.5 2.2-7.2 2.2-5.3 0-9.7-3.6-11.3-8.4l-6.6 5.1C9.9 41.3 16.4 45.5 24 45.5z"/>
                        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.7l6.2 5.2C40.4 36.3 44.5 31 44.5 25c0-1.6-.2-3.1-.4-4.5z"/>
                    </svg>
                    @if ($strip['reviews_title'] !== '')
                        <h3 class="mt-4 font-bold text-gray-900">{{ $strip['reviews_title'] }}</h3>
                    @endif
                    @if ($reviewsSection['rating'] !== '')
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-xl font-bold text-gray-900">{{ $reviewsSection['rating'] }}</span>
                            <div class="flex gap-0.5 text-amber-400">
                                @for ($i = 0; $i < $reviewsSection['stars']; $i++)
                                    <x-icon name="star" class="w-4 h-4 fill-current" />
                                @endfor
                            </div>
                        </div>
                    @endif
                    @if ($reviewsSection['count_text'] !== '')
                        <a href="{{ $reviewsSection['button_url'] }}" class="mt-3 inline-flex items-center rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                            {{ $reviewsSection['count_text'] }}
                        </a>
                    @endif
                    @if ($reviewsSection['intro'] !== '')
                        <p class="mt-4 text-sm text-gray-600 leading-relaxed">{{ $reviewsSection['intro'] }}</p>
                    @endif
                </div>

                {{-- Contact (phone and email come from Site Settings) --}}
                <div class="mt-10 lg:mt-0 lg:px-8">
                    <x-icon name="phone" class="w-10 h-10 text-emerald-700" />
                    <h3 class="mt-4 font-bold text-gray-900">{{ $strip['contact_title'] }}</h3>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        @if ($strip['contact_link_text'] !== '')
                            <div class="flex items-center gap-2">
                                <x-icon name="building" class="w-4 h-4 text-gray-400 shrink-0" />
                                <dt class="font-semibold text-gray-900">{{ $strip['contact_clinic_label'] }}</dt>
                                <dd>
                                    <a href="{{ $strip['contact_link_url'] }}" class="inline-flex items-center gap-1 text-emerald-700 hover:text-emerald-800">
                                        {{ $strip['contact_link_text'] }} <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if ($site['phone'] !== '')
                            <div class="flex items-center gap-2">
                                <x-icon name="phone" class="w-4 h-4 text-gray-400 shrink-0" />
                                <dt class="font-semibold text-gray-900">Phone</dt>
                                <dd class="text-gray-600">{{ $site['phone'] }}</dd>
                            </div>
                        @endif
                        @if ($site['email'] !== '')
                            <div class="flex items-center gap-2">
                                <x-icon name="mail" class="w-4 h-4 text-gray-400 shrink-0" />
                                <dt class="font-semibold text-gray-900">Email</dt>
                                <dd class="text-gray-600">{{ $site['email'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Quick booking form (static preview) --}}
                <div class="mt-10 lg:mt-0 lg:pl-8">
                    <x-icon name="calendar" class="w-10 h-10 text-emerald-700" />
                    <h3 class="mt-4 font-bold text-gray-900">{{ $strip['book_title'] }}</h3>

                    <form method="POST" action="{{ route('enquiries.store') }}" data-enquiry-form data-errors="summary" class="mt-4 space-y-3">
                        <input type="hidden" name="source" value="strip">
                        <div class="absolute left-[-9999px] top-auto h-0 w-0 overflow-hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                                <x-icon name="user-group" class="w-4 h-4 text-gray-400 shrink-0" />
                                <input type="text" name="name" autocomplete="name" placeholder="Your Name" class="w-full border-0 p-0 text-sm placeholder-gray-400 focus:ring-0">
                            </div>
                            <div class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                                <x-icon name="phone" class="w-4 h-4 text-gray-400 shrink-0" />
                                <input type="text" name="phone" autocomplete="tel" placeholder="Phone No." class="w-full border-0 p-0 text-sm placeholder-gray-400 focus:ring-0">
                            </div>
                        </div>

                        <div class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                            <x-icon name="mail" class="w-4 h-4 text-gray-400 shrink-0" />
                            <input type="email" name="email" autocomplete="email" placeholder="Your Email" class="w-full border-0 p-0 text-sm placeholder-gray-400 focus:ring-0">
                        </div>

                        <label class="flex items-start gap-2 text-xs text-gray-500 leading-relaxed">
                            <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span>{{ $appt['consent'] }}</span>
                        </label>

                        <label class="flex items-start gap-2 text-xs text-gray-500 leading-relaxed">
                            <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span>By submitting this form, you agree to the following <a href="#" class="underline hover:text-gray-700">Terms &amp; Conditions</a> and <a href="#" class="underline hover:text-gray-700">Privacy Policy</a>.</span>
                        </label>

                        @if ($strip['book_button_text'] !== '')
                            <button type="submit" class="inline-flex items-center rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                                {{ $strip['book_button_text'] }}
                            </button>
                        @endif
                    </form>
                </div>
            </div>
        </section>
        @endif

        {{-- Symptoms: heading text and cards are managed from the admin panel (Admin > Symptoms) --}}
        @if ($symptoms->isNotEmpty())
        <section id="symptoms" class="py-20 bg-sky-50">
            <div class="mx-auto max-w-7xl px-4 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    @if ($symptomText['eyebrow'] !== '')
                        <span class="text-sm uppercase tracking-wide text-gray-800">{{ $symptomText['eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-2 text-3xl font-bold text-gray-900">{{ $symptomText['heading'] }}</h2>
                    @if ($symptomText['intro'] !== '')
                        <p class="mt-3 text-sm text-gray-700">{{ $symptomText['intro'] }}</p>
                    @endif
                </div>

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($symptoms as $symptom)
                        <div class="bg-white rounded-md px-6 py-5 text-center">
                            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-gray-100">
                                @if ($symptom->iconUrl())
                                    <img src="{{ $symptom->iconUrl() }}" alt="{{ $symptom->title }}" class="h-12 w-12 object-contain">
                                @endif
                            </div>
                            <h3 class="mt-5 text-sm font-bold text-emerald-950">{{ $symptom->title }}</h3>
                            @if ($symptom->description)
                                <p class="mt-3 text-sm leading-6 text-gray-700">{{ $symptom->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- About Doctor: managed from the admin panel (Admin > Doctor Profile); can be hidden there --}}
        @if ($doctor['visible'])
        <section id="about" class="relative py-16 bg-white overflow-hidden">
            @if ($doctor['photo'] !== '')
                <div class="absolute inset-y-0 left-0 w-full lg:w-[31%] bg-emerald-950 hidden lg:block rounded-r-lg overflow-hidden">
                    <div class="about-dots absolute bottom-4 left-[22%] right-4 h-44"></div>
                </div>
            @endif

            <div class="relative mx-auto max-w-7xl px-4 lg:px-8 grid {{ $doctor['photo'] !== '' ? 'lg:grid-cols-[2fr_3fr]' : '' }} gap-10 lg:gap-14 items-center">
                @if ($doctor['photo'] !== '')
                    <div class="relative mx-auto w-full max-w-md lg:max-w-none py-6">
                        <img src="{{ asset($doctor['photo']) }}" alt="{{ $doctor['name'] }}" class="relative w-full rounded-md object-cover shadow-lg" loading="lazy">
                        @if ($doctor['badge_number'] !== '' || $doctor['badge_label'] !== '')
                            <div class="absolute bottom-10 left-1/2 -translate-x-1/2 w-[75%] flex items-center justify-center gap-3 rounded-sm bg-amber-50 px-4 py-3 shadow">
                                <span class="text-4xl font-semibold text-amber-600">{{ $doctor['badge_number'] }}</span>
                                <span class="text-base leading-tight text-gray-800">{!! nl2br(e($doctor['badge_label'])) !!}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="text-gray-900">
                    @if ($doctor['eyebrow'] !== '')
                        <span class="text-sm uppercase">{{ $doctor['eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-1 text-3xl font-bold">{{ $doctor['name'] }}</h2>
                    @if ($doctor['qualifications'] !== '')
                        <p class="mt-3 text-sm">{!! nl2br(e($doctor['qualifications'])) !!}</p>
                    @endif

                    @foreach ($doctor['paragraphs'] as $paragraph)
                        <p class="mt-{{ $loop->first ? '4' : '3' }} text-sm text-gray-600 leading-7">{!! nl2br(e($paragraph)) !!}</p>
                    @endforeach

                    @if ($doctor['diseases_intro'] !== '' || ! empty($doctor['disease_list']))
                        <hr class="my-5 border-gray-200">
                    @endif
                    @if ($doctor['diseases_intro'] !== '')
                        <p class="text-sm font-bold">{{ $doctor['diseases_intro'] }}</p>
                    @endif
                    @if (! empty($doctor['disease_list']))
                        <ul class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                            @foreach ($doctor['disease_list'] as $disease)
                                <li class="flex items-center gap-2 text-sm">
                                    <x-icon name="badge-check" class="w-5 h-5 text-amber-500 shrink-0" />
                                    {{ $disease }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($doctor['button_text'] !== '')
                        <a href="{{ $doctor['button_url'] !== '' ? $doctor['button_url'] : route('about') }}" class="mt-8 inline-flex items-center rounded-full border border-emerald-950 px-10 py-3 text-sm hover:bg-emerald-950 hover:text-white transition">
                            {{ $doctor['button_text'] }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
        @endif

        {{-- Expertise / Services: heading text and cards are managed from the admin panel (Admin > Services) --}}
        @if ($services->isNotEmpty())
        <section id="services" class="relative py-20 bg-gray-50 overflow-hidden">
            <div class="services-dots absolute top-0 right-0 w-64 h-64 pointer-events-none"></div>

            <div class="relative mx-auto max-w-7xl px-4 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    @if ($serviceText['eyebrow'] !== '')
                        <span class="text-sm font-semibold text-emerald-600 uppercase tracking-wide">{{ $serviceText['eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-2 text-3xl font-bold text-gray-900">{{ $serviceText['heading'] }}</h2>
                    @if ($serviceText['intro'] !== '')
                        <p class="mt-3 text-gray-600">{!! nl2br(e($serviceText['intro'])) !!}</p>
                    @endif
                </div>

                <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-10">
                    @foreach ($services as $i => $service)
                        <div class="group bg-white rounded-lg shadow-sm">
                            <div class="main-services-img">
                                @if ($service->imageUrl())
                                    <img src="{{ $service->imageUrl() }}" alt="{{ $service->title }}" class="group-hover:scale-105 transition duration-300">
                                @else
                                    <div class="rounded-[5px] w-full h-[150px] bg-emerald-50 flex items-center justify-center">
                                        <x-icon :name="$service->icon" class="w-10 h-10 text-emerald-300" />
                                    </div>
                                @endif
                            </div>

                            <div class="p-5 pt-3">
                                <h3 class="text-lg font-bold text-gray-900">{{ $service->title }}</h3>
                                <hr class="mt-2 border-gray-200">
                                @if ($service->description)
                                    <p class="mt-3 text-sm text-gray-500 leading-relaxed">{{ $service->description }}</p>
                                @endif

                                <div class="services-card-footer mt-4 flex items-center gap-2">
                                    <a href="{{ $service->href() }}" class="shrink-0 text-sm font-medium text-emerald-600 hover:text-emerald-700">View Service</a>
                                    <span class="flex-1 border-b-2 border-dotted border-gray-200"></span>
                                    <span class="shrink-0 font-bold">{{ sprintf('%02d', $i + 1) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- Doctor card: managed from the admin panel (Admin > Doctor Card); can be hidden there --}}
        @if ($card['visible'])
        <section class="relative py-16 bg-white overflow-hidden">
            @if ($card['poster'] !== '')
                <div class="absolute top-0 bottom-16 right-0 w-[22%] bg-emerald-950 rounded-bl-lg hidden lg:block"></div>
            @endif

            <div class="relative mx-auto max-w-7xl px-4 lg:px-8 grid {{ $card['poster'] !== '' ? 'lg:grid-cols-[1fr_auto]' : '' }} gap-10 items-start">
                <div>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                        @if ($card['photo'] !== '')
                            <img src="{{ asset($card['photo']) }}" alt="{{ $card['name'] }}" class="h-40 w-40 sm:h-56 sm:w-56 shrink-0 rounded-full object-cover" loading="lazy">
                        @endif
                        <div>
                            <h2 class="text-3xl font-bold text-emerald-950">{{ $card['name'] }}</h2>
                            @if ($card['qualifications'] !== '')
                                <p class="mt-3 text-gray-700">{!! nl2br(e($card['qualifications'])) !!}</p>
                            @endif
                            @if ($card['address'] !== '')
                                <p class="mt-3 font-semibold text-gray-900 leading-7">{{ $card['address'] }}</p>
                            @endif
                            @if ($card['experience'] !== '')
                                <p class="mt-2 text-sm text-emerald-900">{{ $card['experience'] }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($card['button_text'] !== '' || $card['phone'] !== '')
                        <div class="mt-8 flex flex-wrap gap-4">
                            @if ($card['button_text'] !== '')
                                <a href="{{ \App\Support\DoctorCardSection::buttonHref($card) }}" @if ($card['button_action'] === 'appointment') data-open-appointment @endif class="inline-flex items-center rounded-full border border-emerald-950 px-14 py-3 text-sm hover:bg-emerald-950 hover:text-white transition">{{ $card['button_text'] }}</a>
                            @endif
                            @if ($card['phone'] !== '')
                                <a href="{{ $card['phone_href'] }}" class="inline-flex items-center rounded-full border border-emerald-950 px-14 py-3 text-sm hover:bg-emerald-950 hover:text-white transition">{{ $card['phone'] }}</a>
                            @endif
                        </div>
                    @endif

                    @if ($cardTreatments->isNotEmpty())
                        <div class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-y-6">
                            @foreach ($cardTreatments as $t)
                                <div class="flex items-center gap-3 pr-3 sm:border-r border-gray-100 last:border-r-0">
                                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gray-100">
                                        @if ($t->imageUrl())
                                            <img src="{{ $t->imageUrl() }}" alt="{{ $t->title }}" class="h-10 w-10 object-contain">
                                        @elseif ($t->icon)
                                            <x-icon :name="$t->icon" class="h-8 w-8 text-gray-600" />
                                        @endif
                                    </span>
                                    <span class="text-sm font-semibold text-gray-900">{{ $t->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($card['poster'] !== '')
                    <div class="mx-auto lg:mx-0 w-72 shrink-0 overflow-hidden rounded-sm bg-white shadow-lg">
                        <img src="{{ asset($card['poster']) }}" alt="Clinic information" class="w-full" loading="lazy">
                    </div>
                @endif
            </div>
        </section>
        @endif

        {{-- Associate hospital: managed from the admin panel (Admin > Associate Band); can be hidden there --}}
        @if ($assoc['visible'])
        <section class="py-12 bg-emerald-950 text-white">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 grid md:grid-cols-[5fr_2fr_5fr] gap-10 items-center">
                <div class="text-center">
                    @if ($assoc['photo'] !== '')
                        <img src="{{ asset($assoc['photo']) }}" alt="{{ $assoc['name'] }}" class="mx-auto h-40 w-40 rounded-full object-cover" loading="lazy">
                    @endif
                    <h3 class="mt-5 text-3xl font-semibold">{{ $assoc['name'] }}</h3>
                    @if ($assoc['qualifications'] !== '')
                        <p class="mt-2 text-sm font-semibold leading-6">{!! nl2br(e($assoc['qualifications'])) !!}</p>
                    @endif
                    @if ($assoc['tagline'] !== '')
                        <p class="mt-1 text-sm font-medium uppercase text-orange-400">{{ $assoc['tagline'] }}</p>
                    @endif
                    @if ($assoc['button_text'] !== '')
                        <a href="{{ $assoc['button_url'] }}" class="mt-6 inline-flex items-center rounded-full bg-white px-10 py-3 text-sm text-gray-900 hover:bg-emerald-50">{{ $assoc['button_text'] }}</a>
                    @endif
                </div>

                <div class="mx-auto flex h-40 w-40 flex-col items-center justify-center rounded-full bg-white text-center text-sm text-gray-700 {{ ($assoc['badge_image'] === '' && $assoc['badge_text'] === '') ? 'invisible' : '' }}">
                    @if ($assoc['badge_image'] !== '')
                        <img src="{{ asset($assoc['badge_image']) }}" alt="{{ $assoc['badge_text'] }}" class="h-16 w-16 object-contain">
                    @endif
                    @if ($assoc['badge_text'] !== '')
                        <p class="mt-2">{{ $assoc['badge_text'] }}</p>
                    @endif
                </div>

                <div class="text-center">
                    @if ($assoc['hospital_image'] !== '')
                        <a href="{{ $assoc['hospital_link'] }}" class="block mx-auto w-64 overflow-hidden rounded-md">
                            <img src="{{ asset($assoc['hospital_image']) }}" alt="{{ $assoc['hospital_name'] }}" class="w-full" loading="lazy">
                        </a>
                    @endif
                    @if ($assoc['hospital_name'] !== '')
                        <h4 class="mt-4 text-xl font-medium">{{ $assoc['hospital_name'] }}</h4>
                    @endif
                    @if ($assoc['hospital_role'] !== '')
                        <p class="mt-2 text-lg font-medium text-orange-400">{{ $assoc['hospital_role'] }}</p>
                    @endif
                    @if ($assoc['hospital_text'] !== '')
                        <p class="mt-2 text-sm leading-6">{{ $assoc['hospital_text'] }}</p>
                    @endif
                </div>
            </div>
        </section>
        @endif

        {{-- About the clinic: texts, photos, counter and cards are managed from the admin panel (Admin > About the Clinic) --}}
        <section class="py-14 bg-white">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 grid lg:grid-cols-[1fr_1.4fr] gap-10 items-center">
                @if (! empty($clinic['photos']))
                    <div class="relative mx-auto w-full max-w-xl">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($clinic['photos'] as $photo)
                                <img src="{{ asset($photo) }}" alt="Clinic photo" class="w-full aspect-[4/3] object-cover" loading="lazy">
                            @endforeach
                        </div>
                        @if ($clinic['stat_number'] !== '' || $clinic['stat_label'] !== '')
                            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex h-44 w-44 flex-col items-center justify-center rounded-full bg-amber-50 border-2 border-dashed border-amber-400 text-center">
                                <span class="text-3xl font-bold text-emerald-950">{{ $clinic['stat_number'] }}</span>
                                <span class="mt-1 text-sm font-medium text-gray-900">{{ $clinic['stat_label'] }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="{{ empty($clinic['photos']) ? 'lg:col-span-2' : '' }}">
                    @if ($clinic['eyebrow'] !== '')
                        <span class="text-sm uppercase text-gray-800">{{ $clinic['eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-1 text-2xl sm:text-3xl font-bold text-emerald-950">{{ $clinic['heading'] }}</h2>
                    @if ($clinic['description'] !== '')
                        <p class="mt-3 text-sm text-gray-600 leading-7">{{ $clinic['description'] }}</p>
                    @endif

                    @if ($clinicTreatments->isNotEmpty())
                        <div class="mt-5 grid grid-cols-2 sm:grid-cols-5 gap-3">
                            @foreach ($clinicTreatments as $card)
                                <div class="flex flex-col items-center justify-center gap-2 rounded-md bg-white px-2 py-4 text-center shadow-[0_0_8px_rgba(0,0,0,0.08)]">
                                    @if ($card->imageUrl())
                                        <img src="{{ $card->imageUrl() }}" alt="{{ $card->title }}" class="h-10 w-10 object-contain">
                                    @endif
                                    <span class="text-xs font-semibold text-gray-900">{{ $card->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($clinic['button_text'] !== '')
                        <a href="{{ $clinic['button_url'] !== '' ? $clinic['button_url'] : route('contact') }}" class="mt-8 inline-flex items-center rounded-full border border-emerald-950 px-12 py-3 text-sm hover:bg-emerald-950 hover:text-white transition">{{ $clinic['button_text'] }}</a>
                    @endif
                </div>
            </div>
        </section>

        @include('partials.book-appointment')

        @include('partials.patient-reviews')

        {{-- Book an appointment intro: managed from the admin panel (Admin > Appointment Intro); can be hidden there --}}
        @if ($intro['visible'])
        <section class="py-16 bg-white">
            <div class="mx-auto max-w-6xl px-4 lg:px-8 grid {{ $intro['image'] !== '' ? 'md:grid-cols-[5fr_6fr]' : '' }} gap-10 items-center">
                @if ($intro['image'] !== '')
                    <img src="{{ asset($intro['image']) }}" alt="{{ $intro['heading'] }}" class="w-full object-cover" loading="lazy">
                @endif

                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $intro['heading'] }}</h2>
                    @foreach ($intro['paragraphs'] as $paragraph)
                        <p class="mt-4 text-sm text-gray-700 leading-7">{!! nl2br(e($paragraph)) !!}</p>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- Why choose (accordion): managed from the admin panel (Admin > Why Choose); can be hidden there --}}
        @if ($whyChoose['visible'])
        <section class="py-14 bg-sky-50">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 grid {{ $whyChooseItems->isNotEmpty() ? 'lg:grid-cols-[1fr_1fr]' : '' }} gap-10 items-start">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">{{ $whyChoose['heading'] }}</h2>
                    @if ($whyChoose['subheading'] !== '')
                        <h3 class="mt-4 text-base text-gray-900">{{ $whyChoose['subheading'] }}</h3>
                    @endif
                    @if ($whyChoose['content'] !== '')
                        <p class="mt-5 text-sm text-gray-700 leading-7">{!! nl2br(e($whyChoose['content'])) !!}</p>
                    @endif
                </div>

                @if ($whyChooseItems->isNotEmpty())
                    <div class="space-y-4">
                        @foreach ($whyChooseItems as $item)
                            @php $open = $loop->first; @endphp
                            <div data-acc-item class="rounded-md border border-gray-200 bg-white overflow-hidden" data-open="{{ $open ? 'true' : 'false' }}">
                                <button type="button" data-acc-btn aria-expanded="{{ $open ? 'true' : 'false' }}" class="flex w-full items-center justify-between px-5 py-4 text-left text-sm text-gray-900">
                                    <span>&gt; {{ $item->title }}</span>
                                    <span data-acc-icon class="text-xl leading-none">{{ $open ? '−' : '+' }}</span>
                                </button>
                                <div data-acc-panel class="grid transition-[grid-template-rows] duration-500 ease-in-out {{ $open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]' }}">
                                    <div class="overflow-hidden">
                                        <p class="bg-gray-50 px-5 py-4 text-sm text-gray-800 leading-6">{!! nl2br(e($item->content)) !!}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($whyChooseItems->isNotEmpty())
            <script>
                (function () {
                    var items = document.querySelectorAll('[data-acc-item]');
                    function set(item, open) {
                        var panel = item.querySelector('[data-acc-panel]');
                        panel.classList.toggle('grid-rows-[1fr]', open);
                        panel.classList.toggle('grid-rows-[0fr]', !open);
                        item.querySelector('[data-acc-btn]').setAttribute('aria-expanded', open);
                        item.querySelector('[data-acc-icon]').textContent = open ? '\u2212' : '+';
                        item.dataset.open = open;
                    }
                    items.forEach(function (item) {
                        item.querySelector('[data-acc-btn]').addEventListener('click', function () {
                            var willOpen = item.dataset.open !== 'true';
                            items.forEach(function (other) { set(other, other === item && willOpen); });
                        });
                    });
                })();
            </script>
            @endif
        </section>
        @endif

        {{-- Blog slider --}}
        @if ($latestPosts->isNotEmpty())
        <section class="py-14 bg-white" id="blog">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <div class="text-center max-w-4xl mx-auto">
                    @if ($blogSection['home_eyebrow'] !== '')
                        <span class="text-sm uppercase text-gray-800">{{ $blogSection['home_eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">{{ $blogSection['home_heading'] }}</h2>
                </div>

                <div class="mt-8" data-blog-slider>
                    <div data-track class="flex gap-0 overflow-x-auto scroll-smooth snap-x snap-mandatory items-start [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($latestPosts as $post)
                            <article class="snap-start shrink-0 basis-full sm:basis-1/2 lg:basis-1/4 px-0 sm:px-0">
                                <div class="mx-1.5 bg-white shadow-[0_0_8px_rgba(0,0,0,0.1)] overflow-hidden">
                                    <a href="{{ $post->url() }}" class="block">
                                        @if ($post->imageUrl())
                                            <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="w-full aspect-[1200/628] object-cover" loading="lazy">
                                        @else
                                            <div class="w-full aspect-[1200/628] bg-emerald-950"></div>
                                        @endif
                                    </a>
                                    <div class="p-3">
                                        <h3 class="text-base font-bold text-gray-900 leading-snug">{{ \Illuminate\Support\Str::limit($post->title, 38) }}</h3>
                                        <p class="mt-2 text-xs leading-6 text-gray-600 line-clamp-4">{{ $post->excerpt }}</p>
                                        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 text-xs">
                                            <a href="{{ $post->url() }}" class="text-gray-900 hover:underline">Explore &rsaquo;</a>
                                            <span class="text-amber-500">{{ $post->published_at->format('M j, Y') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div data-dots class="mt-10 flex justify-center gap-2"></div>
                </div>

                @if ($blogSection['home_button_text'] !== '')
                    <div class="mt-8 text-center">
                        <a href="{{ route('health-tips') }}" class="inline-flex items-center rounded-full border border-emerald-950 px-12 py-3 text-sm hover:bg-emerald-950 hover:text-white transition">{{ $blogSection['home_button_text'] }}</a>
                    </div>
                @endif
            </div>

            <script>
                (function () {
                    var root = document.querySelector('[data-blog-slider]');
                    if (!root) return;
                    var track = root.querySelector('[data-track]');
                    var cards = track.children;
                    var dotsBox = root.querySelector('[data-dots]');
                    var dots = [], current = 0, timer;

                    function perView() { return window.innerWidth >= 1024 ? 4 : (window.innerWidth >= 640 ? 2 : 1); }
                    function pages() { return Math.ceil(cards.length / perView()); }

                    function paint() {
                        dots.forEach(function (d, i) {
                            d.className = 'h-1.5 rounded-full transition-all ' + (i === current ? 'w-7 bg-gray-500' : 'w-1.5 bg-gray-300');
                        });
                    }
                    function go(i) {
                        current = (i + pages()) % pages();
                        var target = cards[Math.min(current * perView(), cards.length - 1)];
                        track.scrollTo({ left: target.offsetLeft - cards[0].offsetLeft, behavior: 'smooth' });
                        paint();
                    }
                    function build() {
                        dotsBox.innerHTML = ''; dots = []; current = 0;
                        for (var i = 0; i < pages(); i++) {
                            (function (i) {
                                var d = document.createElement('button');
                                d.type = 'button';
                                d.setAttribute('aria-label', 'Go to page ' + (i + 1));
                                d.addEventListener('click', function () { go(i); restart(); });
                                dotsBox.appendChild(d); dots.push(d);
                            })(i);
                        }
                        paint();
                    }
                    function restart() { clearInterval(timer); timer = setInterval(function () { go(current + 1); }, 5000); }

                    track.addEventListener('scroll', function () {
                        var w = cards[0].offsetWidth * perView();
                        var i = Math.round(track.scrollLeft / w);
                        if (i !== current && i < pages()) { current = i; paint(); }
                    });
                    root.addEventListener('mouseenter', function () { clearInterval(timer); });
                    root.addEventListener('mouseleave', restart);
                    window.addEventListener('resize', build);

                    build(); restart();
                })();
            </script>
        </section>
        @endif

        {{-- FAQ (two-column accordion) --}}
        @if ($faqSection['home_visible'])
        @if ($homeFaqs->isNotEmpty())
        <section class="py-14 bg-cover bg-center bg-no-repeat" @if ($faqSection['home_background'] !== '') style="background-image: url('{{ asset($faqSection['home_background']) }}')" @endif id="faq">
            <div class="mx-auto max-w-7xl px-4 lg:px-8">
                @if ($faqSection['home_eyebrow'] !== '')
                    <span class="text-sm uppercase text-gray-800">{{ $faqSection['home_eyebrow'] }}</span>
                @endif
                <h2 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">{{ $faqSection['home_heading'] }}</h2>

                <div class="mt-8 grid md:grid-cols-2 gap-x-6 gap-y-3 items-start" data-faq>
                    @foreach ($homeFaqs as $i => $faq)
                        <div data-faq-item data-open="{{ $i === 0 ? 'true' : 'false' }}" class="rounded-md bg-white shadow-[0_0_5px_#ededed] overflow-hidden transition-colors">
                            <button type="button" data-faq-btn aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-xs sm:text-[13px] transition-colors duration-300 {{ $i === 0 ? 'bg-emerald-950 text-white' : 'bg-white text-gray-900' }}">
                                <span>{{ $i + 1 }}. {{ $faq->question }}</span>
                                <span data-faq-icon class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all duration-300 {{ $i === 0 ? 'bg-white text-emerald-950 rotate-180' : 'bg-amber-100 text-gray-700' }}">
                                    <x-icon name="chevron-down" class="h-4 w-4" />
                                </span>
                            </button>
                            <div data-faq-panel class="grid transition-[grid-template-rows] duration-500 ease-in-out {{ $i === 0 ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]' }}">
                                <div class="overflow-hidden">
                                    <p class="px-4 py-3 text-xs sm:text-[13px] leading-6 text-gray-700">{{ $faq->answer }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <script>
                (function () {
                    var items = document.querySelectorAll('[data-faq-item]');
                    var openCls = ['bg-emerald-950', 'text-white'], closedCls = ['bg-white', 'text-gray-900'];
                    var iOpen = ['bg-white', 'text-emerald-950', 'rotate-180'], iClosed = ['bg-amber-100', 'text-gray-700'];

                    function set(item, open) {
                        var btn = item.querySelector('[data-faq-btn]');
                        var icon = item.querySelector('[data-faq-icon]');
                        var panel = item.querySelector('[data-faq-panel]');
                        panel.classList.toggle('grid-rows-[1fr]', open);
                        panel.classList.toggle('grid-rows-[0fr]', !open);
                        openCls.forEach(function (c) { btn.classList.toggle(c, open); });
                        closedCls.forEach(function (c) { btn.classList.toggle(c, !open); });
                        iOpen.forEach(function (c) { icon.classList.toggle(c, open); });
                        iClosed.forEach(function (c) { icon.classList.toggle(c, !open); });
                        btn.setAttribute('aria-expanded', open);
                        item.dataset.open = open;
                    }
                    items.forEach(function (item) {
                        item.querySelector('[data-faq-btn]').addEventListener('click', function () {
                            var willOpen = item.dataset.open !== 'true';
                            items.forEach(function (other) { set(other, other === item && willOpen); });
                        });
                    });
                })();
            </script>
        </section>
        @endif
        @endif

@endsection
