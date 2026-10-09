        {{-- Book appointment --}}
        @if ($appt['visible'])
        <section id="appointment" class="py-14 bg-sky-50">
            <div class="mx-auto max-w-7xl px-4 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    @if ($appt['eyebrow'] !== '')
                        <span class="text-sm uppercase text-gray-800">{{ $appt['eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-2 text-2xl sm:text-3xl font-bold text-gray-900">{{ $appt['heading'] }}</h2>
                    @if ($appt['intro'] !== '')
                        <p class="mt-3 text-sm text-gray-700 leading-6">{{ $appt['intro'] }}</p>
                    @endif
                </div>

                <div class="mt-10 mx-auto max-w-5xl grid lg:grid-cols-[1.4fr_1fr_1.2fr] gap-6 items-stretch">
                    <div class="bg-white rounded-md border-l-4 border-amber-200 p-5 shadow-sm">
                        @if ($appt['clinic_name'] !== '')<h3 class="text-base font-medium text-gray-900">{{ $appt['clinic_name'] }}</h3>@endif
                        <ul class="mt-3 space-y-3 text-sm text-gray-800">
                            @if ($site['phone'] !== '')
                                <li class="flex items-center gap-2"><x-icon name="phone" class="h-4 w-4 shrink-0" /><span><strong>Phone :</strong> {{ $site['phone'] }}</span></li>
                            @endif
                            @if ($site['email'] !== '')
                                <li class="flex items-center gap-2"><x-icon name="mail" class="h-4 w-4 shrink-0" /><span><strong>Email :</strong> {{ $site['email'] }}</span></li>
                            @endif
                            <li class="flex items-start gap-2">
                                <x-icon name="calendar" class="h-4 w-4 shrink-0 mt-0.5" />
                                <div class="space-y-1.5">
                                    <p class="font-bold">Clinic Timing :</p>
                                    @if ($site['timing_title'] !== '')<p class="font-bold">{{ $site['timing_title'] }}</p>@endif
                                    @if ($site['timing_days'] !== '')<p>{{ $site['timing_days'] }}</p>@endif
                                    @if ($site['timing_hours'] !== '')<p>{{ $site['timing_hours'] }}</p>@endif
                                    @if ($site['timing_note'] !== '')<p class="font-bold">{{ $site['timing_note'] }}</p>@endif
                                </div>
                            </li>
                        </ul>
                    </div>

                    @if ($apptSlides->isNotEmpty())
                    <div class="relative mx-auto w-full max-w-xs">
                        <div data-poster-track class="overflow-hidden cursor-grab select-none [touch-action:pan-y]">
                            <div class="flex will-change-transform">
                                @foreach ($apptSlides as $slide)
                                    <div class="w-full shrink-0 aspect-[500/749] overflow-hidden rounded-md">
                                        <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->alt ?: 'Clinic information' }}" class="h-full w-full object-cover" draggable="false">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div data-poster-dots class="absolute left-0 right-0 top-full mt-4 flex justify-center gap-2"></div>
                    </div>
                    <script>
                        window.addEventListener('load', function () {
                            var track = document.querySelector('[data-poster-track]');
                            if (!track || !window.loopSlider) return;
                            window.loopSlider(track, {
                                dotsBox: document.querySelector('[data-poster-dots]'),
                                autoplay: 4000,
                                dotClass: function (active) {
                                    return 'h-1.5 rounded-full transition-all duration-300 ' + (active ? 'w-7 bg-gray-500' : 'w-1.5 bg-gray-300');
                                }
                            });
                        });
                    </script>
                    @endif

                    <div class="bg-white rounded-md border-l-4 border-emerald-950 p-5 shadow-sm">
                        <h3 class="text-center text-lg font-medium text-gray-900">{{ $appt['form_title'] }}</h3>
                        <form method="POST" action="{{ route('enquiries.store') }}" data-enquiry-form  class="mt-4 space-y-4 text-sm">
                            <input type="hidden" name="source" value="appointment">
                            <div class="absolute left-[-9999px] top-auto h-0 w-0 overflow-hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                            <div>
                                <label class="block text-gray-900">Full Name</label>
                                <div class="mt-1 flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3">
                                    <x-icon name="user-doctor" class="h-4 w-4 text-gray-700 shrink-0" />
                                    <input type="text" name="name" autocomplete="name" placeholder="Your Name" class="w-full border-0 bg-transparent py-2.5 text-sm focus:ring-0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-gray-900">Email Address</label>
                                <div class="mt-1 flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3">
                                    <x-icon name="mail" class="h-4 w-4 text-gray-700 shrink-0" />
                                    <input type="email" name="email" autocomplete="email" placeholder="Your Email" class="w-full border-0 bg-transparent py-2.5 text-sm focus:ring-0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-gray-900">Phone Number</label>
                                <div class="mt-1 flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3">
                                    <x-icon name="phone" class="h-4 w-4 text-gray-700 shrink-0" />
                                    <input type="tel" name="phone" autocomplete="tel" placeholder="Phone No." class="w-full border-0 bg-transparent py-2.5 text-sm focus:ring-0">
                                </div>
                            </div>
                            <label class="flex items-start gap-2 text-xs text-gray-700">
                                <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-gray-300">
                                <span>{{ $appt['consent'] }}</span>
                            </label>
                            <label class="flex items-start gap-2 text-xs text-gray-700">
                                <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-gray-300">
                                <span>By submitting this form, you agree to the following <a href="#" class="underline">Terms &amp; Conditions</a> and <a href="#" class="underline">Privacy Policy</a>.</span>
                            </label>
                            <div class="text-center">
                                <button type="submit" class="rounded-full border border-emerald-950 px-12 py-2.5 text-sm hover:bg-emerald-950 hover:text-white transition">{{ $appt['submit_text'] }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        @endif
