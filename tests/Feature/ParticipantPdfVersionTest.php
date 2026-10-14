<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\AssessmentDTO;
use App\DTOs\ChildDTO;
use App\DTOs\ContactInfoDTO;
use App\DTOs\DisclosureDTO;
use App\DTOs\ParticipantUpdateData;
use App\DTOs\ServicePlanDTO;
use App\DTOs\SurveyDTO;
use App\Mail\IntakeFormMailable;
use App\Services\ParticipantPdfVersionService;
use App\Services\PdfIntakeFormService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use mikehaertl\tmp\File;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ParticipantPdfVersionTest extends TestCase
{
    use RefreshDatabase;

    private bool $pdfShouldFail = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Pest does not set the autoloader path needed by PHPUnit's isolated processes.
        if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
            define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 2).'/vendor/autoload.php');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $pdf = Mockery::mock('overload:mikehaertl\\pdftk\\Pdf');
        $pdf->shouldReceive('fillForm', 'flatten')->andReturnSelf();
        $pdf->shouldReceive('execute')->andReturnUsing(fn (): bool => ! $this->pdfShouldFail);
        $pdf->shouldReceive('getTmpFile')->andReturnUsing(fn (): File => new File('%PDF-1.4 version test', '.pdf'));
        $pdf->shouldReceive('getError')->andReturn('Test PDF tool failure');
    }

    public function test_versions_are_numbered_per_participant_and_store_the_source_hash(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $hash = hash('sha256', 'Neon record');

        $jobUuid = '00000000-0000-0000-0000-000000000123';
        $first = $service->generate($this->participant(), $pdf, $hash, jobUuid: $jobUuid)->version->fresh();
        $this->assertSame($jobUuid, $first->job_uuid);
        $this->assertNull($service->generate($this->participant(), $pdf, $hash));
        $this->assertNull($service->generate($this->participant(), $pdf, $hash, jobUuid: '00000000-0000-0000-0000-000000000456'));
        $second = $service->generate($this->participant(), $pdf, $hash, forceRegeneration: true)->version->fresh();
        $other = $service->generate($this->participant(id: '456'), $pdf, $hash)->version->fresh();

        $this->assertSame([1, 2, 1], [$first->version, $second->version, $other->version]);
        $this->assertSame($hash, $first->neon_data_hash);
        $this->assertSame($hash, $second->neon_data_hash);
        $this->assertSame([], $first->changed_fields);
        $this->assertSame([], $second->changed_fields);
        $this->assertStringContainsString('_Enrollment_v1_', $first->filename);
        $this->assertStringContainsString('_Enrollment_v2_', $second->filename);
        $storedHashes = DB::table('participant_pdf_versions')->where('id', $first->id)->value('field_hashes');
        $this->assertStringNotContainsString($first->field_hashes['email'], $storedHashes);

        // Automatic retries and queue:retry must not skip delivery after generation commits.
        $retry = $service->generate($this->participant(), $pdf, $hash, jobUuid: $jobUuid)->version->fresh();
        $this->assertSame(3, $retry->version);
        $this->assertSame($jobUuid, $retry->job_uuid);
    }

    public function test_changed_fields_are_saved_without_overwriting_history(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $original = $this->participant();
        $originalHash = hash('sha256', 'original');
        $first = $service->generate($original, $pdf, $originalHash)->version;
        $participant = $this->participant(email: 'updated@example.org', children: [new ChildDTO('Alex', '7', '2018-01-01')]);
        $second = $service->generate($participant, $pdf, hash('sha256', 'updated'))->version->fresh();
        $third = $service->generate($original, $pdf, $originalHash)->version->fresh();

        $changedFields = ['child_age_1', 'child_dob_1', 'child_name_1', 'email'];
        $this->assertSame($changedFields, $second->changed_fields);
        $this->assertSame($changedFields, $third->changed_fields);
        $this->assertSame(3, $third->version);
        $this->assertSame($first->neon_data_hash, $third->neon_data_hash);
        $this->assertSame([], $first->fresh()->changed_fields);
        $this->assertSame($first->field_hashes, $first->fresh()->field_hashes);
        $this->assertDatabaseCount('participant_pdf_versions', 3);

        $mail = new IntakeFormMailable($participant, $second);
        $mail->assertHasSubject('Intake Form for Test Participant (v2)');
        $mail->assertSeeInHtml('PDF version: 2');
        $mail->assertSeeInHtml($second->filename);
        $mail->assertSeeInHtml('<li>email</li>', escape: false);
    }

    public function test_failed_generation_does_not_consume_a_version(): void
    {
        $service = new ParticipantPdfVersionService;
        $participant = $this->participant();
        $this->pdfShouldFail = true;

        try {
            $service->generate($participant, new PdfIntakeFormService, hash('sha256', 'Neon record'));
            $this->fail('Expected PDF generation to fail.');
        } catch (Exception $exception) {
            $this->assertSame('PDF generation failed: Test PDF tool failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('participant_pdf_versions', 0);
        $this->pdfShouldFail = false;
        $retry = $service->generate($participant, new PdfIntakeFormService, hash('sha256', 'Neon record'));
        $this->assertSame(1, $retry->version->version);
    }

    /** @param list<ChildDTO> $children */
    private function participant(string $id = '123', ?string $email = 'first@example.org', array $children = []): ParticipantUpdateData
    {
        return new ParticipantUpdateData(
            id: $id,
            firstName: 'Test',
            lastName: 'Participant',
            contactInfo: new ContactInfoDTO(email: $email),
            children: $children,
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
    }
}
