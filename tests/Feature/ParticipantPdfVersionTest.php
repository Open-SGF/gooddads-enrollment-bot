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
use App\Models\Participant;
use App\Services\ParticipantPdfVersionService;
use App\Services\PdfIntakeFormService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use mikehaertl\tmp\File;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
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

    public function test_versions_store_the_source_hash_and_skip_unchanged_data(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $hash = hash('sha256', 'Neon record');

        $first = $service->generate($this->participant(), $pdf, $hash)->version;
        $this->assertNull($service->generate($this->participant(), $pdf, $hash));
        $other = $service->generate($this->participant(id: '456'), $pdf, $hash)->version;

        $this->assertSame([1, 1], [$first->version, $other->version]);
        $this->assertSame($hash, $first->neon_data_hash);
        $this->assertSame([], $first->changed_fields);
        $this->assertStringContainsString('_Enrollment_v1_', $first->filename);
        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseCount('participant_pdf_versions', 2);
        $this->assertFalse(Schema::hasTable('neon_participant_hashes'));
        $this->assertFalse(Schema::hasColumn('participant_pdf_versions', 'field_hashes'));

        $participant = Participant::query()->findOrFail('123');
        $storedHashes = DB::table('participants')->where('id', '123')->value('field_hashes');
        $expected = hash_hmac('sha256', json_encode(['123', 'email', 'first@example.org'], JSON_THROW_ON_ERROR), config()->string('participant_pdf.hash_key'));
        $this->assertSame($expected, $participant->fieldHashes()['email']);
        $this->assertSame($participant->fieldHashes(), json_decode($storedHashes, true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('first@example.org', $storedHashes);
        $this->assertStringNotContainsString(config()->string('participant_pdf.hash_key'), $storedHashes);
        $this->assertNotSame($expected, Participant::query()->findOrFail('456')->fieldHashes()['email']);
    }

    public function test_changes_and_reversions_create_versions_and_replace_only_the_current_field_hashes(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $original = $this->participant();
        $originalHash = hash('sha256', 'original');
        $first = $service->generate($original, $pdf, $originalHash)->version;
        $originalFieldHashes = Participant::query()->findOrFail('123')->fieldHashes();

        $participant = $this->participant(email: 'updated@example.org', children: [new ChildDTO('Alex', '7', '2018-01-01')]);
        $second = $service->generate($participant, $pdf, hash('sha256', 'updated'))->version;
        $this->assertNotSame($originalFieldHashes, Participant::query()->findOrFail('123')->fieldHashes());
        $third = $service->generate($original, $pdf, $originalHash)->version;

        $changedFields = ['child_age_1', 'child_dob_1', 'child_name_1', 'email'];
        $this->assertSame([1, 2, 3], [$first->version, $second->version, $third->version]);
        $this->assertSame($changedFields, $second->fresh()->changed_fields);
        $this->assertSame($changedFields, $third->changed_fields);
        $this->assertSame($first->neon_data_hash, $third->neon_data_hash);
        $this->assertSame([], $first->fresh()->changed_fields);
        $this->assertSame($originalFieldHashes, Participant::query()->findOrFail('123')->fieldHashes());
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('participant_pdf_versions', 3);

        $mail = new IntakeFormMailable($participant, $second);
        $mail->assertHasSubject('Intake Form for Test Participant (v2)');
        $mail->assertSeeInHtml('PDF version: 2');
        $mail->assertSeeInHtml($second->filename);
        $mail->assertSeeInHtml('<li>email</li>', escape: false);
    }

    public function test_failed_generation_preserves_the_previous_version_and_field_hashes(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $service->generate($this->participant(), $pdf, hash('sha256', 'original'));
        $fieldHashes = Participant::query()->findOrFail('123')->fieldHashes();
        $updated = $this->participant(email: 'updated@example.org');
        $hash = hash('sha256', 'updated');
        $this->pdfShouldFail = true;

        try {
            $service->generate($updated, $pdf, $hash);
            $this->fail('Expected PDF generation to fail.');
        } catch (Exception $exception) {
            $this->assertSame('PDF generation failed: Test PDF tool failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('participant_pdf_versions', 1);
        $this->assertSame($fieldHashes, Participant::query()->findOrFail('123')->fieldHashes());
        $this->pdfShouldFail = false;
        $retry = $service->generate($updated, $pdf, $hash)->version;
        $this->assertSame(2, $retry->version);
        $this->assertSame(['email'], $retry->changed_fields);
    }

    public function test_resetting_fingerprints_allows_a_new_key_and_baseline_without_losing_history(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $service->generate($this->participant(), $pdf, hash('sha256', 'original'));
        $participant = Participant::query()->findOrFail('123');
        $originalFieldHashes = $participant->fieldHashes();

        $participant->update(['field_hashes' => null]);
        config()->set('participant_pdf.hash_key', str_repeat('b', 64));
        $second = $service->generate($this->participant(), $pdf, hash('sha256', 'updated'))->version;

        $this->assertSame(2, $second->version);
        $this->assertSame([], $second->changed_fields);
        $this->assertNotSame($originalFieldHashes['email'], $participant->fresh()->fieldHashes()['email']);
        $this->assertDatabaseCount('participant_pdf_versions', 2);
    }

    public function test_generation_requires_a_dedicated_fingerprint_key(): void
    {
        config()->set('participant_pdf.hash_key', '');

        try {
            new ParticipantPdfVersionService()->generate($this->participant(), new PdfIntakeFormService, hash('sha256', 'original'));
            $this->fail('Expected generation to reject the missing fingerprint key.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('PARTICIPANT_PDF_HASH_KEY must be set to a secret of at least 32 characters.', $runtimeException->getMessage());
        }

        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('participant_pdf_versions', 0);
    }

    public function test_a_delivery_retry_reuses_its_version_without_replacing_newer_fingerprints(): void
    {
        $service = new ParticipantPdfVersionService;
        $pdf = new PdfIntakeFormService;
        $service->generate($this->participant(), $pdf, hash('sha256', 'original'));
        $updated = $this->participant(email: 'updated@example.org');
        $hash = hash('sha256', 'updated');
        $jobUuid = '00000000-0000-0000-0000-000000000123';
        $firstAttempt = $service->generate($updated, $pdf, $hash, jobUuid: $jobUuid);

        $newer = $this->participant(email: 'newest@example.org');
        $newerHash = hash('sha256', 'newest');
        $service->generate($newer, $pdf, $newerHash);
        $latestFieldHashes = Participant::query()->findOrFail('123')->fieldHashes();
        $retry = $service->generate($updated, $pdf, $hash, jobUuid: $jobUuid);

        $this->assertSame($firstAttempt->version->id, $retry->version->id);
        $this->assertSame(2, $retry->version->version);
        $this->assertSame(['email'], $retry->version->changed_fields);
        $this->assertSame($firstAttempt->filename, $retry->filename);
        $this->assertSame($latestFieldHashes, Participant::query()->findOrFail('123')->fieldHashes());
        $this->assertDatabaseCount('participant_pdf_versions', 3);
        $this->assertNull($service->generate($newer, $pdf, $newerHash));
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
