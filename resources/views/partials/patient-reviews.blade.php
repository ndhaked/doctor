        {{-- Patient reviews slider --}}
        @if ($reviewsSection['visible'])
        <section class="py-14 bg-gray-50 border-t border-gray-200" id="testimonials">
            <div class="mx-auto max-w-7xl px-4 lg:px-8 grid {{ $reviews->isNotEmpty() ? 'lg:grid-cols-[5fr_7fr]' : '' }} gap-10 items-start">
                <div>
                    @if ($reviewsSection['eyebrow'] !== '')
                        <span class="text-sm uppercase text-gray-800">{{ $reviewsSection['eyebrow'] }}</span>
                    @endif
                    <h2 class="mt-1 text-3xl font-bold text-gray-900">{{ $reviewsSection['heading'] }}</h2>
                    @if ($reviewsSection['intro'] !== '')
                        <p class="mt-3 text-sm text-gray-700 leading-7 max-w-md">{{ $reviewsSection['intro'] }}</p>
                    @endif

                    @if ($reviewsSection['google_label'] !== '')
                        <h3 class="mt-5 text-lg font-semibold text-gray-900">{{ $reviewsSection['google_label'] }}</h3>
                    @endif
                    @if ($reviewsSection['rating'] !== '' || $reviewsSection['count_text'] !== '')
                        <div class="mt-4 flex items-center gap-3">
                            @if ($reviewsSection['rating'] !== '')
                                <span class="text-4xl font-light text-gray-700">{{ $reviewsSection['rating'] }}</span>
                                <span class="flex gap-0.5 text-amber-400">
                                    @for ($i = 0; $i < $reviewsSection['stars']; $i++)
                                        <x-icon name="star" class="h-5 w-5 fill-current" />
                                    @endfor
                                </span>
                            @endif
                            @if ($reviewsSection['count_text'] !== '')
                                <span class="text-sm font-medium text-gray-900">{{ $reviewsSection['count_text'] }}</span>
                            @endif
                        </div>
                    @endif

                    @if ($reviewsSection['button_text'] !== '')                    <a href="{{ $reviewsSection['button_url'] }}" class="mt-10 inline-flex items-center gap-3 rounded-full border border-emerald-950 px-10 py-3 text-sm hover:bg-emerald-950 hover:text-white transition">
                        <svg viewBox="0 0 48 48" class="h-5 w-5" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"/><path fill="#FBBC05" d="M10.5 28.7a14.5 14.5 0 010-9.4l-7.9-6.1a24 24 0 000 21.6l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                        {{ $reviewsSection['button_text'] }}
                    </a>
                    @endif
                </div>

                @if ($reviews->isNotEmpty())
                <div x-data class="min-w-0" data-review-slider>
                    <div data-track class="flex gap-5 overflow-x-auto scroll-smooth snap-x snap-mandatory [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($reviews as $review)
                            <article class="snap-start shrink-0 basis-full md:basis-[calc(50%-0.625rem)] rounded-3xl bg-white px-5 py-5 self-start">
                                <h4 class="text-lg font-semibold text-gray-900">{{ $review->name }}</h4>
                                <span class="mt-2 flex gap-1 text-amber-400">
                                    @for ($i = 0; $i < $review->rating; $i++)
                                        <x-icon name="star" class="h-4 w-4 fill-current" />
                                    @endfor
                                </span>
                                <p data-text class="mt-4 text-sm leading-7 text-gray-700 line-clamp-2">{{ $review->text }}</p>
                                <button type="button" data-more class="mt-1 text-sm text-gray-900 hover:underline">Show more &rsaquo;</button>
                            </article>
                        @endforeach
                    </div>
                    <div data-dots class="mt-6 flex justify-center gap-2"></div>
                </div>
                @endif
            </div>

            @if ($reviews->isNotEmpty())
            <script>
                (function () {
                    var root = document.querySelector('[data-review-slider]');
                    if (!root) return;
                    var track = root.querySelector('[data-track]');
                    var cards = track.children;
                    var dotsBox = root.querySelector('[data-dots]');
                    var dots = [];
                    var current = 0;

                    function perView() { return window.innerWidth >= 768 ? 2 : 1; }
                    function pages() { return Math.max(1, cards.length - perView() + 1); }

                    function build() {
                        dotsBox.innerHTML = '';
                        dots = [];
                        for (var i = 0; i < pages(); i++) {
                            (function (i) {
                                var d = document.createElement('button');
                                d.type = 'button';
                                d.setAttribute('aria-label', 'Go to review ' + (i + 1));
                                d.addEventListener('click', function () { go(i); restart(); });
                                dotsBox.appendChild(d);
                                dots.push(d);
                            })(i);
                        }
                        paint();
                    }

                    function paint() {
                        dots.forEach(function (d, i) {
                            d.className = 'h-1.5 rounded-full transition-all ' + (i === current ? 'w-7 bg-gray-500' : 'w-1.5 bg-gray-300');
                        });
                    }

                    function go(i) {
                        current = (i + pages()) % pages();
                        track.scrollTo({ left: cards[current].offsetLeft - cards[0].offsetLeft, behavior: 'smooth' });
                        paint();
                    }

                    track.addEventListener('scroll', function () {
                        var step = cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : 1;
                        var i = Math.round(track.scrollLeft / step);
                        if (i !== current && i < pages()) { current = i; paint(); }
                    });

                    var timer;
                    function restart() { clearInterval(timer); timer = setInterval(function () { go(current + 1); }, 5000); }
                    root.addEventListener('mouseenter', function () { clearInterval(timer); });
                    root.addEventListener('mouseleave', restart);
                    window.addEventListener('resize', function () { current = 0; build(); });

                    root.querySelectorAll('[data-more]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var p = btn.previousElementSibling;
                            var open = p.classList.toggle('line-clamp-2');
                            btn.innerHTML = open ? 'Show more &rsaquo;' : 'Show less &lsaquo;';
                        });
                    });

                    build();
                    restart();
                })();
            </script>
            @endif
        </section>
        @endif
