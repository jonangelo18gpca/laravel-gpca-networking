<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UsernameChanged extends Mailable
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
    // public function content()
    // {
    //     if ($this->details['eventYear'] == "2025") {
    //         if ($this->details['eventCategory'] == "ANC") {
    //             return new Content(
    //                 markdown: 'emails.2025.anc.username-changed-mail',
    //             );
    //         } else if ($this->details['eventCategory'] == "RCC") {
    //             return new Content(
    //                 markdown: 'emails.2025.rcc.username-changed-mail',
    //             );
    //         } else if ($this->details['eventCategory'] == "AF") {
    //             return new Content(
    //                 markdown: 'emails.2025.af.username-changed-mail',
    //             );
    //         } else {
    //             return new Content(
    //                 markdown: 'emails.username-changed-mail',
    //             );
    //         }
    //     } else {
    //         return new Content(
    //             markdown: 'emails.username-changed-mail',
    //         );
    //     }
    // }


    public function content()
{
    $year = (string) ($this->details['eventYear'] ?? '');
    $category = strtoupper($this->details['eventCategory'] ?? '');

    $allowedYears = ['2025', '2026'];
    $allowedCategories = ['ANC', 'RCC', 'RIC', 'AF'];

    if (in_array($year, $allowedYears) && in_array($category, $allowedCategories)) {
        return new Content(
            markdown: "emails.$year." . strtolower($category) . ".username-changed-mail",
        );
    }

    return new Content(
        markdown: 'emails.username-changed-mail',
    );
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
