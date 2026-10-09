A new enquiry was sent from your website.

Form:    {{ $enquiry->sourceLabel() }}
Name:    {{ $enquiry->name }}
Phone:   {{ $enquiry->phone }}
Email:   {{ $enquiry->email ?: '-' }}
Sent at: {{ $enquiry->created_at->format('d M Y, h:i A') }}

@if ($enquiry->message)
Message:
{{ $enquiry->message }}

@endif
Read it in the admin panel: {{ route('admin.enquiries.index') }}
