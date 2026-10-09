<?php

use App\Http\Controllers\ClinicPrintController;
use App\Http\Controllers\EnquiryController;
use App\Models\GalleryGroup;
use App\Models\GalleryVideo;
use App\Support\VideoPageSection;
use App\Support\GalleryPageSection;
use App\Models\AboutExpertise;
use App\Models\AboutTreatment;
use App\Models\BlogPost;
use App\Models\ClinicTreatment;
use App\Models\FaqGroup;
use App\Models\HeroSlide;
use App\Models\ProfileTreatment;
use App\Models\Service;
use App\Models\Symptom;
use App\Models\WhyChooseItem;
use App\Support\BlogSection;
use App\Support\AboutPageSection;
use App\Support\AssociateSection;
use App\Support\ClinicSection;
use App\Support\ContactPageSection;
use App\Support\DoctorCardSection;
use App\Support\DoctorSection;
use App\Support\FaqSection;
use App\Support\InfoStripSection;
use App\Support\IntroSection;
use App\Support\WhyChooseSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'slides' => HeroSlide::active()->ordered()->get(),
        'symptoms' => Symptom::active()->ordered()->get(),
        'services' => Service::active()->ordered()->get(),
        'clinic' => ClinicSection::content(),
        'intro' => IntroSection::content(),
        'doctor' => DoctorSection::content(),
        'card' => DoctorCardSection::content(),
        'strip' => InfoStripSection::content(),
        'assoc' => AssociateSection::content(),
        'cardTreatments' => ProfileTreatment::active()->ordered()->get(),
        'whyChoose' => WhyChooseSection::content(),
        'whyChooseItems' => WhyChooseItem::active()->ordered()->get(),
        'clinicTreatments' => ClinicTreatment::active()->ordered()->get(),
        'serviceText' => Service::sectionText(),
        'symptomText' => Symptom::sectionText(),
        'blogSection' => $blogSection = BlogSection::content(),
        'latestPosts' => $blogSection['home_visible'] ? BlogPost::active()->latestFirst()->take($blogSection['home_count'])->get() : collect(),
        'faqSection' => $faqSection = FaqSection::content(),
        'homeFaqs' => FaqGroup::active()->placedOn('home')->ordered()->with('activeItems')->get()->flatMap->activeItems->values(),
    ]);
})->name('home');

Route::get('/about-doctor', function () {
    return view('about', [
        'about' => AboutPageSection::content(),
        'aboutExpertise' => AboutExpertise::active()->ordered()->get(),
        'aboutTreatments' => AboutTreatment::active()->ordered()->get(),
    ]);
})->name('about');
Route::get('/contact-us', function () {
    return view('contact', ['contactPage' => ContactPageSection::content()]);
})->name('contact');
Route::get('/health-tips', function () {
    return view('health-tips', [
        'blogSection' => BlogSection::content(),
        'posts' => BlogPost::active()->latestFirst()->get(),
    ]);
})->name('health-tips');

Route::get('/health-tips/{post:slug}', function (BlogPost $post) {
    abort_unless($post->is_active, 404);

    $blogSection = BlogSection::content();

    return view('health-tips-show', [
        'post' => $post,
        'blogSection' => $blogSection,
        'recent' => BlogPost::active()->latestFirst()->whereKeyNot($post->id)->take($blogSection['recent_count'])->get(),
    ]);
})->name('health-tips.show');
// Website forms are sent here in the background (see resources/js/enquiry-forms.js)
Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:6,1')->name('enquiries.store');

Route::get('/faq', function () {
    return view('faq', [
        'groups' => FaqGroup::active()->placedOn('faq_page')->ordered()->with('activeItems')->get()->filter(fn ($g) => $g->activeItems->isNotEmpty())->values(),
    ]);
})->name('faq');

Route::get('/image-gallery', function () {
    return view('image-gallery', [
        'galleryPage' => GalleryPageSection::content(),
        'groups' => GalleryGroup::active()->ordered()->with('activeImages')->get()->filter(fn ($g) => $g->activeImages->isNotEmpty())->values(),
    ]);
})->name('gallery');

Route::get('/video-gallery', function () {
    return view('video-gallery', [
        'videoPage' => VideoPageSection::content(),
        'videos' => GalleryVideo::active()->ordered()->get(),
    ]);
})->name('video-gallery');

Route::middleware('guest:admin')->group(function () {
    Route::livewire('/admin/login', 'pages::admin.login')->name('admin.login');
});

Route::middleware('auth:admin')->group(function () {
    Route::livewire('/admin/dashboard', 'pages::admin.dashboard')->name('admin.dashboard');

    Route::livewire('/admin/profile', 'pages::admin.profile')->name('admin.profile');

    // ---- Clinic management ----
    Route::middleware('permission:manage clinic profile,admin')->group(function () {
        Route::livewire('/admin/clinic/profile', 'pages::admin.clinic.profile.index')->name('admin.clinic.profile');
    });

    Route::middleware('permission:manage clinic settings,admin')->group(function () {
        Route::livewire('/admin/clinic/settings', 'pages::admin.clinic.settings.index')->name('admin.clinic.settings');
    });

    Route::middleware('permission:manage staff,admin')->group(function () {
        Route::livewire('/admin/clinic/staff', 'pages::admin.clinic.staff.index')->name('admin.clinic.staff.index');
        Route::livewire('/admin/clinic/staff/{staff}', 'pages::admin.clinic.staff.show')->name('admin.clinic.staff.show');
    });

    Route::middleware('permission:view reports,admin')->group(function () {
        Route::livewire('/admin/clinic/reports', 'pages::admin.clinic.reports.index')->name('admin.clinic.reports');
    });
    Route::middleware('permission:manage patients,admin')->group(function () {
        Route::livewire('/admin/clinic/patients', 'pages::admin.clinic.patients.index')->name('admin.clinic.patients.index');
        Route::livewire('/admin/clinic/patients/{patient}', 'pages::admin.clinic.patients.show')->name('admin.clinic.patients.show');
    });

    Route::middleware('permission:manage appointments,admin')->group(function () {
        Route::livewire('/admin/clinic/appointments', 'pages::admin.clinic.appointments.index')->name('admin.clinic.appointments.index');
    });

    Route::middleware('permission:view medical records,admin')->group(function () {
        Route::livewire('/admin/clinic/consultations', 'pages::admin.clinic.consultations.index')->name('admin.clinic.consultations.index');
        Route::livewire('/admin/clinic/consultations/{consultation}', 'pages::admin.clinic.consultations.edit')->name('admin.clinic.consultations.edit');
        Route::get('/admin/clinic/consultations/{consultation}/prescription', [ClinicPrintController::class, 'prescription'])->name('admin.clinic.consultations.prescription');
        Route::get('/admin/clinic/documents/{document}', [ClinicPrintController::class, 'document'])->name('admin.clinic.documents.show');
    });

    Route::middleware('permission:manage billing,admin')->group(function () {
        Route::livewire('/admin/clinic/billing', 'pages::admin.clinic.billing.index')->name('admin.clinic.billing.index');
        Route::livewire('/admin/clinic/billing/new', 'pages::admin.clinic.billing.show')->name('admin.clinic.billing.create');
        Route::livewire('/admin/clinic/billing/{invoice}', 'pages::admin.clinic.billing.show')->name('admin.clinic.billing.show');
        Route::get('/admin/clinic/billing/{invoice}/print', [ClinicPrintController::class, 'invoice'])->name('admin.clinic.billing.print');
        Route::get('/admin/clinic/payments/{payment}/receipt', [ClinicPrintController::class, 'receipt'])->name('admin.clinic.payments.receipt');
    });
    Route::middleware('permission:manage admins,admin')->group(function () {
        Route::livewire('/admin/admins', 'pages::admin.admins.index')->name('admin.admins.index');
    });

    Route::middleware('permission:manage slider,admin')->group(function () {
        Route::livewire('/admin/slides', 'pages::admin.slides.index')->name('admin.slides.index');
    });

    Route::middleware('permission:manage videos,admin')->group(function () {
        Route::livewire('/admin/videos', 'pages::admin.videos.index')->name('admin.videos.index');
    });

    Route::middleware('permission:manage gallery,admin')->group(function () {
        Route::livewire('/admin/gallery', 'pages::admin.gallery.index')->name('admin.gallery.index');
    });

    Route::middleware('permission:manage enquiries,admin')->group(function () {
        Route::livewire('/admin/enquiries', 'pages::admin.enquiries.index')->name('admin.enquiries.index');
    });

    Route::middleware('permission:manage contact page,admin')->group(function () {
        Route::livewire('/admin/contact-page', 'pages::admin.contact-page.index')->name('admin.contact-page.index');
    });

    Route::middleware('permission:manage about page,admin')->group(function () {
        Route::livewire('/admin/about-page', 'pages::admin.about-page.index')->name('admin.about-page.index');
    });

    Route::middleware('permission:manage strip,admin')->group(function () {
        Route::livewire('/admin/strip', 'pages::admin.strip.index')->name('admin.strip.index');
    });

    Route::middleware('permission:manage associate,admin')->group(function () {
        Route::livewire('/admin/associate', 'pages::admin.associate.index')->name('admin.associate.index');
    });

    Route::middleware('permission:manage reviews,admin')->group(function () {
        Route::livewire('/admin/reviews', 'pages::admin.reviews.index')->name('admin.reviews.index');
    });

    Route::middleware('permission:manage appointment,admin')->group(function () {
        Route::livewire('/admin/appointment', 'pages::admin.appointment.index')->name('admin.appointment.index');
    });

    Route::middleware('permission:manage site,admin')->group(function () {
        Route::livewire('/admin/site-settings', 'pages::admin.site.index')->name('admin.site.index');
    });

    Route::middleware('permission:manage doctor card,admin')->group(function () {
        Route::livewire('/admin/doctor-card', 'pages::admin.doctor-card.index')->name('admin.doctor-card.index');
    });

    Route::middleware('permission:manage doctor,admin')->group(function () {
        Route::livewire('/admin/doctor', 'pages::admin.doctor.index')->name('admin.doctor.index');
    });

    Route::middleware('permission:manage why choose,admin')->group(function () {
        Route::livewire('/admin/why-choose', 'pages::admin.why-choose.index')->name('admin.why-choose.index');
    });

    Route::middleware('permission:manage intro,admin')->group(function () {
        Route::livewire('/admin/intro', 'pages::admin.intro.index')->name('admin.intro.index');
    });

    Route::middleware('permission:manage clinic,admin')->group(function () {
        Route::livewire('/admin/clinic', 'pages::admin.clinic.index')->name('admin.clinic.index');
    });

    Route::middleware('permission:manage services,admin')->group(function () {
        Route::livewire('/admin/services', 'pages::admin.services.index')->name('admin.services.index');
    });

    Route::middleware('permission:manage symptoms,admin')->group(function () {
        Route::livewire('/admin/symptoms', 'pages::admin.symptoms.index')->name('admin.symptoms.index');
    });

    Route::middleware('permission:manage blog,admin')->group(function () {
        Route::livewire('/admin/blog', 'pages::admin.blog.index')->name('admin.blog.index');
    });

    Route::middleware('permission:manage faqs,admin')->group(function () {
        Route::livewire('/admin/faqs', 'pages::admin.faqs.index')->name('admin.faqs.index');
    });

    Route::middleware('permission:manage roles,admin')->group(function () {
        Route::livewire('/admin/roles', 'pages::admin.roles.index')->name('admin.roles.index');
    });

    Route::post('/admin/logout', function (Request $request) {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('admin.logout');
});
