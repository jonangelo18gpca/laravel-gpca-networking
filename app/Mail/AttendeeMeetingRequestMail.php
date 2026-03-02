<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeMeetingRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $details, $isReceiver;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details, $isReceiver = true)
    {
        $this->isReceiver = $isReceiver;
        $this->details = $details;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        if ($this->isReceiver) {
            $subject = "New Meeting Request from " . $this->details['requesterName'] . " - " . $this->details['eventName'];
        } else {
            $subject = "Meeting Request Sent to " . $this->details['receiverName'] . " - " . $this->details['eventName'];
        }

        return new Envelope(
            subject: $subject ?? 'Meeting Request Notification',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    // public function content()
    // {
    //     if ($this->isReceiver) {
    //         if ($this->details['eventYear'] == "2025") {
    //             if ($this->details['eventCategory'] == "RCC") {
    //                 return new Content(
    //                     markdown: 'emails.2025.rcc.meeting.request.receiver-mail',
    //                 );
    //             } else if ($this->details['eventCategory'] == "AF") {
    //                 return new Content(
    //                     markdown: 'emails.2025.af.meeting.request.receiver-mail',
    //                 );
    //             } else {
    //                 return new Content(
    //                     markdown: 'emails.meeting.request.receiver-mail',
    //                 );
    //             }
    //         } else {
    //             return new Content(
    //                 markdown: 'emails.meeting.request.receiver-mail',
    //             );
    //         }
    //     } else {
    //         if ($this->details['eventYear'] == "2025") {
    //             if ($this->details['eventCategory'] == "RCC") {
    //                 return new Content(
    //                     markdown: 'emails.2025.rcc.meeting.request.requester-mail',
    //                 );
    //             } else if ($this->details['eventCategory'] == "AF") {
    //                 return new Content(
    //                     markdown: 'emails.2025.af.meeting.request.requester-mail',
    //                 );
    //             } else {
    //                 return new Content(
    //                     markdown: 'emails.meeting.request.requester-mail',
    //                 );
    //             }
    //         } else {
    //             return new Content(
    //                 markdown: 'emails.meeting.request.requester-mail',
    //             );
    //         }
    //     }
    // }


    public function content()
{
    $year = (string) ($this->details['eventYear'] ?? '');
    $category = strtoupper($this->details['eventCategory'] ?? '');
    $type = $this->isReceiver ? 'receiver-mail' : 'requester-mail';

    $allowedYears = ['2025', '2026'];
    $allowedCategories = ['RCC', 'RIC', 'AF'];

    if (in_array($year, $allowedYears) && in_array($category, $allowedCategories)) {
        return new Content(
            markdown: "emails.$year." . strtolower($category) . ".meeting.request.$type",
        );
    }

    return new Content(
        markdown: "emails.meeting.request.$type",
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
