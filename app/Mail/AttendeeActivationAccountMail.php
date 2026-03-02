<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeActivationAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: $this->details['subject'],
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        if ($this->details['eventYear'] == "2025") {
            if ($this->details['eventCategory'] == "ANC") {
                return new Content(
                    markdown: 'emails.2025.anc.attendee-activation-account-otp-mail',
                );
            } else if ($this->details['eventCategory'] == "RCC") {
                return new Content(
                    markdown: 'emails.2025.rcc.attendee-activation-account-otp-mail',
                );
            } else if ($this->details['eventCategory'] == "AF") {
                return new Content(
                    markdown: 'emails.2025.af.attendee-activation-account-otp-mail',
                );
            } else {
                return new Content(
                    markdown: 'emails.attendee-activation-account-otp-mail',
                );
            }
        } elseif ($this->details['eventYear'] == "2026") {
            if ($this->details['eventCategory'] == "RIC") {
                return new Content(
                    markdown: 'emails.2026.ric.attendee-activation-account-otp-mail',
                );
            } else if ($this->details['eventCategory'] == "RCC") {
                return new Content(
                    markdown: 'emails.2026.rcc.attendee-activation-account-otp-mail',
                );
            } else if ($this->details['eventCategory'] == "AF") {
                return new Content(
                    markdown: 'emails.2026.af.attendee-activation-account-otp-mail',
                );
            } else {
                return new Content(
                    markdown: 'emails.attendee-activation-account-otp-mail',
                );
            }
        } else {
            return new Content(
                markdown: 'emails.attendee-activation-account-otp-mail',
            );
        }
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
