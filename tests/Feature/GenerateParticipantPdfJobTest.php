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
use App\Jobs\GenerateParticipantPdfJob;
use App\Models\ParticipantPdfVersion;
use App\Services\DropboxUploadService;
use App\Services\ParticipantPdfVersionService;
use App\Services\PdfIntakeFormService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use mikehaertl\tmp\File;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use RuntimeException;
use Spatie\Dropbox\Client;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

final class GenerateParticipantPdfJobTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Pest does not set the autoloader path needed by PHPUnit's isolated processes.
        if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
            define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 2).'/vendor/autoload.php');
        }
    }

    public static function recipientLists(): array
    {
        return [
            'empty list' => [[], [], []],
            'valid recipients' => [['intake@example.org', 'enrollment@example.net'], ['intake@example.org', 'enrollment@example.net'], []],
            'mixed recipients' => [['not-an-email', 'intake@example.org', 'also-invalid', 'enrollment@example.net'], ['intake@example.org', 'enrollment@example.net'], ['not-an-email', 'also-invalid']],
            'all invalid' => [['not-an-email', 'also-invalid'], [], ['not-an-email', 'also-invalid']],
            'non-string address' => [[false], [], [false]],
            'empty address' => [[''], [], ['']],
            'null address' => [[null], [], [null]],
            'zero string' => [['0'], [], ['0']],
        ];
    }

    #[DataProvider('recipientLists')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_skips_invalid_recipients_without_failing_the_job(array $recipients, array $valid, array $invalid): void
    {
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'sender@example.org');
        config()->set('mail.intake_form_recipients', $recipients);
        Storage::fake();

        // Stub the PDF tool, not the job, so tests do not need the pdftk executable.
        $pdf = Mockery::mock('overload:mikehaertl\\pdftk\\Pdf');
        $contents = "%PDF-1.4 test document\0\xFF";
        $tmpFile = new File($contents, '.pdf');
        $pdf->shouldReceive('fillForm', 'flatten')->andReturnSelf();
        $pdf->shouldReceive('execute')->andReturnTrue();
        $pdf->shouldReceive('getTmpFile')->andReturn($tmpFile);
        $pdf->shouldReceive('getError')->andReturn('');

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('upload')->once()
            ->withArgs(fn (string $path, string $bytes, string $mode, bool $autorename): bool => str_contains($path, '/participant-forms/123/Participant_Test_Enrollment_v1_')
                && str_ends_with($path, '.pdf')
                && $bytes === $contents
                && $mode === 'add'
                && $autorename)
            ->andReturn(['path_display' => '/intake.pdf']);

        $participant = $this->participant();
        $this->assertFalse($participant->hasMissingFields());

        new GenerateParticipantPdfJob($participant, neonDataHash: str_repeat('a', 64))->handle(
            new PdfIntakeFormService,
            new DropboxUploadService($client),
            new ParticipantPdfVersionService,
        );

        $this->assertSame([], Storage::allFiles());
        $this->assertFileDoesNotExist($tmpFile->getFileName());
        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(count($valid), $messages);

        foreach ($messages->values() as $index => $sent) {
            $message = $sent->getOriginalMessage();
            $this->assertSame([$valid[$index]], array_map(fn (Address $address): string => $address->getAddress(), $message->getTo()));
            $this->assertSame([], $message->getCc());
            $this->assertSame([], $message->getBcc());
            $this->assertSame([], $message->getAttachments());
            $this->assertSame('Intake Form for Test Participant (v1)', $message->getSubject());
            $this->assertStringContainsString('PDF version: 1', $message->getHtmlBody());
        }

        $this->assertCount(count($recipients) - count($invalid), $messages);

        new GenerateParticipantPdfJob($participant, neonDataHash: str_repeat('a', 64))->handle(
            new PdfIntakeFormService,
            new DropboxUploadService($client),
            new ParticipantPdfVersionService,
        );
        $this->assertDatabaseCount('participant_pdf_versions', 1);
        $this->assertCount(count($valid), Mail::getSymfonyTransport()->messages());
    }

    public function test_missing_required_fields_do_not_generate_or_record_a_version(): void
    {
        Mail::fake();
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('upload');

        $participant = $this->participant(dob: '');
        $this->assertSame(['assessment' => ['dob']], $participant->getMissingFields());

        new GenerateParticipantPdfJob($participant, neonDataHash: str_repeat('a', 64))->handle(
            new PdfIntakeFormService,
            new DropboxUploadService($client),
            new ParticipantPdfVersionService,
        );

        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('participant_pdf_versions', 0);
        Mail::assertNothingSent();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_email_failure_retries_the_original_version_filename_and_changed_fields(): void
    {
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'sender@example.org');
        config()->set('mail.intake_form_recipients', ['intake@example.org']);
        $this->travelTo(now()->startOfMinute());

        $pdf = Mockery::mock('overload:mikehaertl\\pdftk\\Pdf');
        $pdf->shouldReceive('fillForm', 'flatten')->andReturnSelf();
        $pdf->shouldReceive('execute')->andReturnTrue();
        $pdf->shouldReceive('getTmpFile')->andReturnUsing(fn (): File => new File('%PDF-1.4 retry test', '.pdf'));

        $service = new ParticipantPdfVersionService;
        $service->generate($this->participant(), new PdfIntakeFormService, str_repeat('a', 64));

        $paths = [];
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('upload')->twice()->withArgs(function (string $path) use (&$paths): bool {
            $paths[] = $path;

            return str_contains($path, '_Enrollment_v2_');
        })->andReturn(['path_display' => '/intake.pdf']);

        $failEmail = true;
        Event::listen(MessageSending::class, function () use (&$failEmail): void {
            throw_if($failEmail, RuntimeException::class, 'Test SMTP failure.');
        });
        $queuedJob = Mockery::mock(Job::class);
        $queuedJob->shouldReceive('uuid')->andReturn('00000000-0000-0000-0000-000000000123');
        $queuedJob->shouldReceive('attempts')->andReturn(1, 2);
        $job = new GenerateParticipantPdfJob($this->participant(email: 'updated@example.org'), str_repeat('b', 64));
        $job->setJob($queuedJob);

        try {
            $job->handle(new PdfIntakeFormService, new DropboxUploadService($client), $service);
            $this->fail('Expected the first email attempt to fail.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Test SMTP failure.', $runtimeException->getMessage());
        }

        $originalVersion = ParticipantPdfVersion::query()->where('version', 2)->firstOrFail();
        $this->travel(1)->minutes();
        $failEmail = false;
        $job->handle(new PdfIntakeFormService, new DropboxUploadService($client), $service);

        $this->assertDatabaseCount('participant_pdf_versions', 2);
        $this->assertSame(['email'], $originalVersion->fresh()->changed_fields);
        $this->assertSame($paths[0], $paths[1]);
        $this->assertStringEndsWith($originalVersion->filename, $paths[1]);
        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('Intake Form for Test Participant (v2)', $message->getSubject());
        $this->assertStringContainsString('<li>email</li>', $message->getHtmlBody());
        $this->assertStringNotContainsString('No PDF fields changed', $message->getHtmlBody());
    }

    private function participant(string $dob = 'Test', string $email = 'first@example.org'): ParticipantUpdateData
    {
        return new ParticipantUpdateData(
            id: '123',
            firstName: 'Test',
            lastName: 'Participant',
            contactInfo: $this->filledDto(ContactInfoDTO::class, ['email' => $email]),
            children: [$this->filledDto(ChildDTO::class)],
            disclosure: $this->filledDto(DisclosureDTO::class),
            assessment: $this->filledDto(AssessmentDTO::class, ['dob' => $dob]),
            survey: $this->filledDto(SurveyDTO::class),
            servicePlan: $this->filledDto(ServicePlanDTO::class),
        );
    }

    private function filledDto(string $class, array $overrides = []): object
    {
        // Populate every string field to exercise the completed-form path.
        $reflection = new ReflectionClass($class);
        $arguments = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $arguments[] = $overrides[$parameter->getName()] ?? 'Test';
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
