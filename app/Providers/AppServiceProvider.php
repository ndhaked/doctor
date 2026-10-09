<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\BookingSlide;
use App\Models\Review;
use App\Models\Service;
use App\Support\AppointmentSection;
use App\Support\ReviewsSection;
use App\Support\SiteSettings;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\ClinicContext::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Authenticate::redirectUsing(fn ($request) => $request->is('admin/*')
            ? route('admin.login')
            : route('home'));

        RedirectIfAuthenticated::redirectUsing(fn ($request) => $request->is('admin/*')
            ? route('admin.dashboard')
            : route('home'));

        // Contact details, opening hours, social links and footer texts are shared by every public page
        View::composer(
            ['layouts.site', 'welcome', 'about', 'contact', 'faq', 'health-tips', 'health-tips-show', 'image-gallery', 'video-gallery'],
            fn ($view) => $view->with([
                'site' => SiteSettings::content(),
                'appt' => AppointmentSection::content(),
                'apptSlides' => BookingSlide::active()->ordered()->get(),
            ])
        );

        // The reviews section appears on the home, About Doctor and Contact Us pages
        View::composer(['welcome', 'about', 'contact'], fn ($view) => $view->with([
            'reviewsSection' => ReviewsSection::content(),
            'reviews' => Review::active()->ordered()->get(),
        ]));

        // The header's Expertise Services dropdown lists the same services as the home page section
        View::composer('layouts.site', fn ($view) => $view->with('navServices', Service::active()->ordered()->get()));

        Gate::before(fn ($user, string $ability) => $user instanceof Admin && $user->hasRole('Super Admin')
            ? true
            : null);
    }
}
