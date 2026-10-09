<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewEnquiryMail extends Mailable
{
    public function __construct(public Enquiry $enquiry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New '.strtolower($this->enquiry->sourceLabel()).' enquiry from '.$this->enquiry->name,
            replyTo: $this->enquiry->email ? [$this->enquiry->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.new-enquiry');
    }
}
