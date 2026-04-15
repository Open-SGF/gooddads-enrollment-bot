<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\ParticipantUpdateData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class IncompleteIntakeFormMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly ParticipantUpdateData $participant,
        private readonly array $missingFields,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Incomplete Intake Form for '.$this->participant->fullName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incomplete-intake-form',
            with: [
                'participant'   => $this->participant,
                'missingFields' => $this->missingFields,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}