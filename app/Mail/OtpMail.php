<?php

namespace App\Mail;

use App\Services\Otp\OtpMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly OtpMessage $otp)
    {
    }

    public function envelope(): Envelope
    {
        // The requester's name goes in the subject so the owner can triage
        // from the notification preview without opening the mail.
        return new Envelope(
            subject: 'Login approval: ' . $this->otp->requesterName() . ' - code ' . $this->otp->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otp' => $this->otp,
                'appName' => config('app.name', 'Home Nursing'),
            ],
        );
    }
}
