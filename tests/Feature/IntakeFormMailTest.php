<?php

declare(strict_types=1);

use App\DTOs\AssessmentDTO;
use App\DTOs\ContactInfoDTO;
use App\DTOs\DisclosureDTO;
use App\DTOs\ParticipantUpdateData;
use App\DTOs\ServicePlanDTO;
use App\DTOs\SurveyDTO;
use App\Mail\IntakeFormMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.from.address', 'sender@example.org');
    Storage::fake();
    Storage::put('participant-forms/123/intake.pdf', '%PDF-1.4 test attachment');

    $participant = new ParticipantUpdateData(
        id: '123',
        firstName: 'Test',
        lastName: 'Participant',
        contactInfo: new ContactInfoDTO,
        children: [],
        disclosure: new DisclosureDTO,
        assessment: new AssessmentDTO(
            fullName: 'Test Participant',
            dob: '',
            eligibilityMissouriResident: '',
            eligibilityChildUnder18: '',
            financialEligibility: '',
            financialDriversLicence: '',
            financialUtilityBill: '',
            financialWrittenEmployerStatement: '',
            financialSsBenefitsStatement: '',
            financialNoEmploymentIncome: '',
            financialUnemploymentCompensation: '',
            financialOther: '',
        ),
        survey: new SurveyDTO,
        servicePlan: new ServicePlanDTO,
    );

    $this->mailable = new IntakeFormMailable($participant, 'participant-forms/123/intake.pdf');
});

it('sends the form and PDF attachment to the configured recipient', function (string $recipient): void {
    config()->set('mail.intake_form_recipient', $recipient);

    $sent = Mail::send($this->mailable);
    $message = $sent->getOriginalMessage();

    expect($message->getTo())->toHaveCount(1)
        ->and($message->getTo()[0]->getAddress())->toBe($recipient)
        ->and($message->getFrom()[0]->getAddress())->toBe('sender@example.org')
        ->and($message->getSubject())->toBe('Intake Form for Test Participant')
        ->and($message->getHtmlBody())->toContain('Test Participant')
        ->and($message->getAttachments())->toHaveCount(1)
        ->and($message->getAttachments()[0]->getFilename())->toBe('intake-form.pdf')
        ->and($message->getAttachments()[0]->getBody())->toBe('%PDF-1.4 test attachment');
})->with(['intake@example.org', 'enrollment@example.net']);

it('rejects missing or invalid recipients without sending a form', function (mixed $recipient): void {
    config()->set('mail.intake_form_recipient', $recipient);

    expect(fn () => Mail::send($this->mailable))
        ->toThrow(RuntimeException::class, 'MAIL_INTAKE_FORM_RECIPIENT must be configured with a valid email address.');

    expect(Mail::getSymfonyTransport()->messages())->toBeEmpty();
})->with([
    'unset' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'invalid address' => ['not-an-email'],
    'multiple addresses' => ['one@example.org,two@example.org'],
    'non-string' => [false],
]);
