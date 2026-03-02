<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAttendee extends Mailable
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
    // public function envelope()
    // {
    //     if ($this->details['eventYear'] == "2025") {
    //         if ($this->details['eventCategory'] == "PC") {
    //             $subject =  "Maximize your event experience: Download the 14ᵗʰ GPCA Plastics Conference networking app today!";
    //         } else if ($this->details['eventCategory'] == "SCC") {
    //             $subject =  "Maximize your event experience: Download the 16ᵗʰ GPCA Supply Chain Conference networking app today!";
    //         } else if ($this->details['eventCategory'] == "ANC") {
    //             $subject =  "Maximize your experience with the 15ᵗʰ GPCA Agri-Nutrients Conference networking app!";
    //         } else if ($this->details['eventCategory'] == "AF") {
    //             $subject =  "Maximize your experience with the 19ᵗʰ Annual GPCA Forum networking app!";
    //         } else {
    //             $subject =  "Maximize your event experience: Download the networking app today!";
    //         }
    //     } else {
    //         $subject =  "Maximize your event experience: Download the networking app today!";
    //     }

    //     return new Envelope(
    //         subject: $subject,
    //     );
    // }


    public function envelope()
{
    $year = (string) ($this->details['eventYear'] ?? '');
    $category = strtoupper($this->details['eventCategory'] ?? '');

    $defaultSubject = "Maximize your event experience: Download the networking app today!";

    $subjects = [
        'PC'  => "Maximize your event experience: Download the 14ᵗʰ GPCA Plastics Conference networking app today!",
        'SCC' => "Maximize your event experience: Download the 16ᵗʰ GPCA Supply Chain Conference networking app today!",
        'ANC' => "Maximize your experience with the 15ᵗʰ GPCA Agri-Nutrients Conference networking app!",
        'AF'  => "Maximize your experience with the 19ᵗʰ Annual GPCA Forum networking app!",
    ];

    if (in_array($year, ['2025', '2026']) && isset($subjects[$category])) {
        $subject = $subjects[$category];
    } else {
        $subject = $defaultSubject;
    }

    return new Envelope(
        subject: $subject,
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
    //         if ($this->details['eventCategory'] == "PC") {
    //             return new Content(
    //                 markdown: 'emails.2025.pc.new-attendee-mail',
    //             );
    //         } else if ($this->details['eventCategory'] == "SCC") {
    //             return new Content(
    //                 markdown: 'emails.2025.scc.new-attendee-mail',
    //             );
    //         } else if ($this->details['eventCategory'] == "ANC") {
    //             return new Content(
    //                 markdown: 'emails.2025.anc.new-attendee-mail',
    //             );
    //         } else if ($this->details['eventCategory'] == "RCC") {
    //             return new Content(
    //                 markdown: 'emails.2025.rcc.new-attendee-mail',
    //             );
    //         } else if ($this->details['eventCategory'] == "AF") {
    //             return new Content(
    //                 markdown: 'emails.2025.af.new-attendee-mail',
    //             );
    //         } else {
    //             return new Content(
    //                 markdown: 'emails.new-attendee-mail',
    //             );
    //         }
    //     } else {
    //         return new Content(
    //             markdown: 'emails.new-attendee-mail',
    //         );
    //     }
    // }


    public function content()
{
    $year = (string) ($this->details['eventYear'] ?? '');
    $category = strtoupper($this->details['eventCategory'] ?? '');

    $allowedYears = ['2025', '2026'];
    $allowedCategories = ['PC', 'SCC', 'ANC', 'RCC', 'RIC', 'AF'];

    if (in_array($year, $allowedYears) && in_array($category, $allowedCategories)) {
        return new Content(
            markdown: "emails.$year." . strtolower($category) . ".new-attendee-mail",
        );
    }

    return new Content(
        markdown: 'emails.new-attendee-mail',
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
