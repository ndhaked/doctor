@extends('layouts.site')

@section('title', $contactPage['title'] . ' - ' . $site['brand_full'])

@section('content')
    {{-- Banner --}}
    <section class="bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/contact/contact-banner.jpg') }}')">
        <div class="mx-auto max-w-4xl px-4 py-16 sm:py-20 text-center min-h-[300px] flex flex-col items-center justify-center">
            <h1 class="text-3xl font-bold text-gray-950">{{ $contactPage['title'] }}</h1>
            @if ($contactPage['intro'] !== '')
                <p class="mt-5 text-sm leading-7 text-gray-700">{{ $contactPage['intro'] }}</p>
            @endif
        </div>
    </section>

    {{-- Contact info cards --}}
    <section class="py-14 bg-white">
        <div class="mx-auto max-w-5xl px-4 lg:px-8">
            <div class="text-center max-w-xl mx-auto">
                @if ($contactPage['info_eyebrow'] !== '')
                    <h6 class="text-sm text-emerald-950">{{ $contactPage['info_eyebrow'] }}</h6>
                @endif
                <h2 class="mt-2 text-2xl sm:text-3xl font-bold text-gray-950">{{ $contactPage['info_heading'] }}</h2>
                @if ($contactPage['info_text'] !== '')
                    <p class="mt-3 text-sm leading-6 text-gray-700">{{ $contactPage['info_text'] }}</p>
                @endif
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <div class="border border-gray-200 bg-white px-6 py-8 text-center">
                    <img src="{{ asset('images/contact/call-icon.svg') }}" alt="" class="mx-auto h-14 w-14">
                    <h4 class="mt-5 text-sm font-bold text-gray-950">{{ $contactPage['phone_title'] }}</h4>
                    @if ($site['phone'] !== '')
                        <a href="{{ $site['phone_href'] }}" class="mt-3 block text-xs text-gray-800 hover:text-emerald-700">{{ $site['phone'] }}</a>
                    @endif
                    @if ($site['email'] !== '')
                        <a href="mailto:{{ $site['email'] }}" class="mt-3 block text-xs text-gray-800 hover:text-emerald-700">{{ $site['email'] }}</a>
                    @endif
                </div>
                <div class="border border-gray-200 bg-white px-6 py-8 text-center">
                    <img src="{{ asset('images/contact/watch-icon.svg') }}" alt="" class="mx-auto h-14 w-14">
                    <h4 class="mt-5 text-sm font-bold text-gray-950">{{ $contactPage['hours_title'] }}</h4>
                    <div class="mt-3 space-y-2 text-xs text-gray-800">
                        @if ($site['timing_title'] !== '')<p class="font-bold">{{ $site['timing_title'] }}</p>@endif
                        @if ($site['timing_days'] !== '')<p>{{ $site['timing_days'] }}</p>@endif
                        @if ($site['timing_hours'] !== '')<p>{{ $site['timing_hours'] }}</p>@endif
                        @if ($site['timing_note'] !== '')<p class="font-bold">{{ $site['timing_note'] }}</p>@endif
                    </div>
                </div>
                <div class="border border-gray-200 bg-white px-6 py-8 text-center">
                    <img src="{{ asset('images/contact/location-icon.svg') }}" alt="" class="mx-auto h-14 w-14">
                    <h4 class="mt-5 text-sm font-bold text-gray-950">{{ $contactPage['address_title'] }}</h4>
                    <p class="mt-3 text-xs leading-6 text-gray-800">{{ $site['address'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Query form (managed in Admin > Contact Page) --}}
    @if ($contactPage['form_visible'])
    <section class="py-14 bg-white border-t border-gray-200">
        <div class="mx-auto max-w-6xl px-4 lg:px-8 grid {{ $contactPage['photo'] !== '' ? 'lg:grid-cols-[5fr_7fr]' : '' }} gap-10 lg:gap-14 items-center">
            @if ($contactPage['photo'] !== '')
            <div class="relative mx-auto w-full max-w-md lg:max-w-none pl-12 pb-12">
                {{-- Cream block peeking out behind the photo (left and bottom) --}}
                <div class="absolute left-0 right-12 top-12 bottom-0 bg-[#fdf1dc]"></div>

                <div class="relative">
                    <img src="{{ asset($contactPage['photo']) }}" alt="{{ $contactPage['name'] }}" class="block w-full rounded-tr-[3.5rem] rounded-bl-[3.5rem] object-cover">
                    @if ($contactPage['name'] !== '' || $contactPage['qualifications'] !== '')
                        <div class="absolute left-[17%] right-3 bottom-9 rounded-md bg-white px-3 py-4 text-center">
                            @if ($contactPage['name'] !== '')
                                <h5 class="text-xl font-medium text-gray-950">{{ $contactPage['name'] }}</h5>
                            @endif
                            @if ($contactPage['qualifications'] !== '')
                                <p class="mt-1 text-xs leading-6 text-gray-900">{!! nl2br(e($contactPage['qualifications'])) !!}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <div>
                <h3 class="text-2xl font-bold text-gray-950">{{ $contactPage['form_heading'] }}</h3>
                @if ($contactPage['form_text'] !== '')<p class="mt-3 text-xs leading-6 text-gray-700 max-w-lg">{{ $contactPage['form_text'] }}</p>@endif

                <form method="POST" action="{{ route('enquiries.store') }}" data-enquiry-form  class="mt-6 space-y-4 text-sm">
                    <input type="hidden" name="source" value="contact">
                    <div class="absolute left-[-9999px] top-auto h-0 w-0 overflow-hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3">
                            <x-icon name="user-doctor" class="h-4 w-4 text-gray-700 shrink-0" />
                            <input type="text" name="name" autocomplete="name" placeholder="Your Name" class="w-full border-0 bg-transparent py-3 text-sm focus:ring-0">
                        </label>
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3">
                            <x-icon name="phone" class="h-4 w-4 text-gray-700 shrink-0" />
                            <input type="tel" name="phone" autocomplete="tel" placeholder="Phone No." class="w-full border-0 bg-transparent py-3 text-sm focus:ring-0">
                        </label>
                    </div>
                    <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3">
                        <x-icon name="mail" class="h-4 w-4 text-gray-700 shrink-0" />
                        <input type="email" name="email" autocomplete="email" placeholder="Your Email" class="w-full border-0 bg-transparent py-3 text-sm focus:ring-0">
                    </label>
                    <label class="flex items-start gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 pt-3">
                        <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0 fill-none stroke-current text-gray-700" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
                        <textarea rows="5" name="message" placeholder="Write Your Message Here....." class="w-full resize-y border-0 bg-transparent pb-3 text-sm focus:ring-0"></textarea>
                    </label>

                    <label class="flex items-start gap-2 text-xs text-gray-800">
                        <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-gray-300">
                        <span>{{ $appt['consent'] }}</span>
                    </label>
                    <label class="flex items-start gap-2 text-xs text-gray-800">
                        <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-gray-300">
                        <span>By submitting this form, you agree to the following <a href="#" class="underline text-emerald-900">Terms &amp; Conditions</a> and <a href="#" class="underline text-emerald-900">Privacy Policy</a>.</span>
                    </label>

                    <button type="submit" class="rounded-full border border-emerald-950 px-10 py-2.5 text-sm hover:bg-emerald-950 hover:text-white transition">{{ $contactPage['form_button'] }}</button>
                </form>
            </div>
        </div>
    </section>
    @endif

    {{-- Map (the embed link is set in Admin > Site Settings) --}}
    @if ($contactPage['map_visible'] && $site['map_url'] !== '')
    <section>
        <iframe title="Clinic location" class="block h-[420px] w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            src="{{ $site['map_url'] }}"></iframe>
    </section>
    @endif

    @include('partials.patient-reviews')
@endsection
