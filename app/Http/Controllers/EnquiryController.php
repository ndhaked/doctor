<?php

namespace App\Http\Controllers;

use App\Mail\NewEnquiryMail;
use App\Models\Enquiry;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    /** Receives the website forms (sent in the background by resources/js/enquiry-forms.js). */
    public function store(Request $request): JsonResponse
    {
        // Hidden "website" field: real visitors never fill it, simple bots do. Pretend it worked.
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Thank you! We will contact you shortly.'], 201);
        }

        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(Enquiry::SOURCES))],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-().]{5,28}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'name.required' => 'Please enter your name.',
            'phone.required' => 'Please enter your phone number.',
            'phone.regex' => 'Please enter a valid phone number.',
            'email.email' => 'Please enter a valid email address.',
            'consent.accepted' => 'Please tick the box to allow us to contact you.',
            'terms.accepted' => 'Please accept the Terms & Conditions and Privacy Policy.',
        ]);

        $enquiry = Enquiry::create([
            'source' => $data['source'],
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'email' => $data['email'] ?? null,
            'message' => $data['message'] ?? null,
            'consent' => true,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        // Email the clinic when a notification address is set in Site Settings. A mail problem must never lose the enquiry.
        $notify = SiteSettings::content()['notify_email'];

        if ($notify !== '') {
            try {
                Mail::to($notify)->send(new NewEnquiryMail($enquiry));
            } catch (\Throwable $e) {
                Log::warning('Could not email the new enquiry: '.$e->getMessage());
            }
        }

        return response()->json(['message' => 'Thank you! We have received your request and will contact you shortly.'], 201);
    }
}
