<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\ParticipantUpdateData;
use App\Models\ParticipantPdfVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;

#[Backoff(10)]
#[Tries(3)]
final class IntakeFormMailable extends Mailable implements ShouldBeEncrypted
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        private readonly ParticipantUpdateData $participant,
        private readonly ?ParticipantPdfVersion $version = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Intake Form for '.$this->participant->fullName().($this->version instanceof ParticipantPdfVersion ? ' (v'.$this->version->version.')' : '')
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
                'version' => $this->version,
            ]
        );
    }
}
