<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\ParticipantUpdateData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

final class IntakeFormMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(private readonly ParticipantUpdateData $participant, private readonly string $pdfPath) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $recipient = config('mail.intake_form_recipient');

        throw_if(
            ! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false,
            RuntimeException::class,
            'MAIL_INTAKE_FORM_RECIPIENT must be configured with a valid email address.'
        );

        return new Envelope(
            to: [new Address($recipient)],
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

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromStorage($this->pdfPath)
            ->as('intake-form.pdf')
            ->withMime('application/pdf')];
    }
}
