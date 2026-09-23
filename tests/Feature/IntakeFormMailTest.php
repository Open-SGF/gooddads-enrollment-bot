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
use Symfony\Component\Mime\Address;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.from.address', 'sender@example.org');

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

    $this->mailable = new IntakeFormMailable($participant);
});

it('sends the form without a PDF attachment to the given recipient', function (string $recipient): void {
    $sent = Mail::to($recipient)->send($this->mailable);
    $message = $sent->getOriginalMessage();

    expect(array_map(fn (Address $address): string => $address->getAddress(), $message->getTo()))->toBe([$recipient])
        ->and($message->getFrom()[0]->getAddress())->toBe('sender@example.org')
        ->and($message->getSubject())->toBe('Intake Form for Test Participant')
        ->and($message->getHtmlBody())->toContain('Test Participant')
        ->and($message->getAttachments())->toBe([]);
})->with(['intake@example.org', 'enrollment@example.net']);
