<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\ParticipantUpdateData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class IntakeFormMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(private readonly ParticipantUpdateData $participant) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Intake Form for '.$this->participant->fullName()
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.intake-form',
            with: [
                'participant' => $this->participant,
            ]
        );
    }
}
