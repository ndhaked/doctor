<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Admin' }} - {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen flex">

            <!-- Sidebar -->
            <aside
                class="fixed inset-y-0 left-0 z-30 w-64 bg-white border-r border-gray-200 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto flex flex-col"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="h-16 flex items-center gap-2.5 px-6 border-b border-gray-200 shrink-0">
                    <span class="h-9 w-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm font-bold">
                        {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                    </span>
                    <span class="text-base font-semibold tracking-tight text-gray-900 leading-tight">{{ config('app.name') }}</span>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-sm">
                    <x-panel.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                        <x-slot name="icon"><x-icon name="home" class="w-5 h-5" /></x-slot>
                        Dashboard
                    </x-panel.nav-link>

                    @php
                        $adminUser = auth('admin')->user();
                        $unreadEnquiries = $adminUser->can('manage enquiries') ? \App\Models\Enquiry::unread()->count() : 0;

                        // [label, route, icon, permission, route pattern]
                        $menuGroups = [
                            'Clinic' => [
                                ['Patients', 'admin.clinic.patients.index', 'users', 'manage patients', 'admin.clinic.patients.*'],
                                ['Appointments', 'admin.clinic.appointments.index', 'calendar', 'manage appointments', 'admin.clinic.appointments.*'],
                                ['Consultations', 'admin.clinic.consultations.index', 'clipboard', 'view medical records', 'admin.clinic.consultations.*'],
                                ['Billing & Payments', 'admin.clinic.billing.index', 'banknotes', 'manage billing', 'admin.clinic.billing.*'],
                                ['Reports', 'admin.clinic.reports', 'chart', 'view reports', 'admin.clinic.reports'],
                            ],
                            'Clinic Setup' => [
                                ['Doctor Profile', 'admin.clinic.profile', 'user-doctor', 'manage clinic profile', 'admin.clinic.profile'],
                                ['Staff', 'admin.clinic.staff.index', 'user-group', 'manage staff', 'admin.clinic.staff.*'],
                                ['Clinic Settings', 'admin.clinic.settings', 'cog', 'manage clinic settings', 'admin.clinic.settings'],
                            ],
                            'Inbox & Settings' => [
                                ['Enquiries', 'admin.enquiries.index', 'mail', 'manage enquiries', 'admin.enquiries.*'],
                                ['Site Settings', 'admin.site.index', 'cog', 'manage site', 'admin.site.*'],
                            ],
                            'Home Page' => [
                                ['Hero Slider', 'admin.slides.index', 'sparkles', 'manage slider', 'admin.slides.*'],
                                ['Symptoms', 'admin.symptoms.index', 'thermometer', 'manage symptoms', 'admin.symptoms.*'],
                                ['Services', 'admin.services.index', 'building', 'manage services', 'admin.services.*'],
                                ['About the Clinic', 'admin.clinic.index', 'home', 'manage clinic', 'admin.clinic.*'],
                                ['Appointment Intro', 'admin.intro.index', 'calendar', 'manage intro', 'admin.intro.*'],
                                ['Why Choose', 'admin.why-choose.index', 'star', 'manage why choose', 'admin.why-choose.*'],
                                ['Doctor Profile', 'admin.doctor.index', 'user-doctor', 'manage doctor', 'admin.doctor.*'],
                                ['Doctor Card', 'admin.doctor-card.index', 'badge-check', 'manage doctor card', 'admin.doctor-card.*'],
                                ['Appointment', 'admin.appointment.index', 'calendar', 'manage appointment', 'admin.appointment.*'],
                                ['Reviews', 'admin.reviews.index', 'star', 'manage reviews', 'admin.reviews.*'],
                                ['Info Strip', 'admin.strip.index', 'menu', 'manage strip', 'admin.strip.*'],
                                ['Associate Band', 'admin.associate.index', 'users', 'manage associate', 'admin.associate.*'],
                            ],
                            'Pages' => [
                                ['About Page', 'admin.about-page.index', 'user-doctor', 'manage about page', 'admin.about-page.*'],
                                ['Contact Page', 'admin.contact-page.index', 'mail', 'manage contact page', 'admin.contact-page.*'],
                                ['FAQs', 'admin.faqs.index', 'badge-check', 'manage faqs', 'admin.faqs.*'],
                            ],
                            'Content' => [
                                ['Blog Posts', 'admin.blog.index', 'chart', 'manage blog', 'admin.blog.*'],
                                ['Image Gallery', 'admin.gallery.index', 'image', 'manage gallery', 'admin.gallery.*'],
                                ['Video Gallery', 'admin.videos.index', 'play', 'manage videos', 'admin.videos.*'],
                            ],
                        ];
                    @endphp

                    @foreach ($menuGroups as $groupLabel => $items)
                        @php
                            $items = array_values(array_filter($items, fn ($item) => $adminUser->can($item[3])));
                            $groupActive = collect($items)->contains(fn ($item) => request()->routeIs($item[4]));
                        @endphp

                        @if ($items)
                            <div x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }" class="pt-3">
                                <button type="button" x-on:click="open = ! open" :aria-expanded="open"
                                    class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 hover:text-gray-600">
                                    <span class="flex items-center gap-2">
                                        {{ $groupLabel }}
                                        @if ($groupLabel === 'Inbox & Settings' && $unreadEnquiries > 0 && ! $groupActive)
                                            <span x-show="! open" class="rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white">{{ $unreadEnquiries }}</span>
                                        @endif
                                    </span>
                                    <svg viewBox="0 0 12 8" class="h-2 w-2 fill-current transition" :class="open ? '' : '-rotate-90'"><path d="M0 0l6 8 6-8z"/></svg>
                                </button>

                                <div x-show="open" x-cloak class="mt-1 space-y-1">
                                    @foreach ($items as [$label, $routeName, $icon, $permission, $pattern])
                                        <x-panel.nav-link :href="route($routeName)" :active="request()->routeIs($pattern)">
                                            <x-slot name="icon"><x-icon :name="$icon" class="w-5 h-5" /></x-slot>
                                            {{ $label }}
                                            @if ($routeName === 'admin.enquiries.index' && $unreadEnquiries > 0)
                                                <span class="ml-2 rounded-full bg-indigo-600 px-2 py-0.5 text-[11px] font-semibold leading-none text-white">{{ $unreadEnquiries }}</span>
                                            @endif
                                        </x-panel.nav-link>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    @if (auth('admin')->user()->canAny(['manage admins', 'manage roles']))
                        <p class="px-3 pt-5 pb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">User Management</p>

                        @if (auth('admin')->user()->can('manage admins'))
                            <x-panel.nav-link :href="route('admin.admins.index')" :active="request()->routeIs('admin.admins.*')">
                                <x-slot name="icon"><x-icon name="user-group" class="w-5 h-5" /></x-slot>
                                Admins
                            </x-panel.nav-link>
                        @endif

                        @if (auth('admin')->user()->can('manage roles'))
                            <x-panel.nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')">
                                <x-slot name="icon"><x-icon name="cog" class="w-5 h-5" /></x-slot>
                                Roles &amp; Permissions
                            </x-panel.nav-link>
                        @endif
                    @endif
                </nav>
            </aside>

            <!-- Overlay for mobile -->
            <div
                x-show="sidebarOpen"
                x-cloak
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-black/30 lg:hidden"
            ></div>

            <!-- Main column -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Topbar -->
                <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-10">
                    <div class="flex items-center gap-3">
                        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-500 hover:text-gray-700">
                            <x-icon name="menu" class="h-6 w-6" />
                        </button>

                        @isset($header)
                            {{ $header }}
                        @endisset
                    </div>

                    <div class="flex items-center gap-3" x-data="{ open: false }">
                        <span class="hidden sm:inline-flex text-xs font-medium px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700">
                            {{ auth('admin')->user()->roles->pluck('name')->join(', ') ?: 'Admin' }}
                        </span>

                        <div class="relative">
                            <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                                <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold">
                                    {{ strtoupper(substr(auth('admin')->user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden sm:inline text-sm font-medium text-gray-700">{{ auth('admin')->user()->name }}</span>
                                <x-icon name="chevron-down" class="h-4 w-4 text-gray-400" />
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                @click.outside="open = false"
                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 ring-1 ring-black/5"
                            >
                                <a href="{{ route('admin.profile') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    Profile
                                </a>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 p-4 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
