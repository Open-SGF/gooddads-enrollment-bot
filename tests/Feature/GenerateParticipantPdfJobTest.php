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
use App\Mail\IntakeFormMailable;
use App\Models\ParticipantPdfVersion;
use App\Services\DropboxUploadService;
use App\Services\ParticipantPdfVersionService;
use App\Services\PdfIntakeFormService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use mikehaertl\tmp\File;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use RuntimeException;
use Spatie\Dropbox\Client;
use Tests\TestCase;

final class GenerateParticipantPdfJobTest extends TestCase
{
    use RefreshDatabase;

    private int $generations = 0;

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

        config()->set('queue.default', 'database');
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'sender@example.org');
    }

    public static function recipientLists(): array
    {
        return [
            'empty list' => [[], []],
            'mixed recipients' => [['not-an-email', 'intake@example.org', 'enrollment@example.net'], ['intake@example.org', 'enrollment@example.net']],
        ];
    }

    #[DataProvider('recipientLists')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_queues_email_only_for_valid_recipients(array $recipients, array $valid): void
    {
        Mail::fake();
        config()->set('mail.intake_form_recipients', $recipients);
        $this->mockPdf();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('upload')->once()->andReturn([]);

        new GenerateParticipantPdfJob($this->participant(), str_repeat('a', 64))->handle(
            new PdfIntakeFormService,
            new DropboxUploadService($client),
            new ParticipantPdfVersionService,
        );

        Mail::assertQueued(IntakeFormMailable::class, count($valid));
        foreach ($valid as $recipient) {
            Mail::assertQueued(IntakeFormMailable::class, fn (IntakeFormMailable $mail): bool => $mail->hasTo($recipient));
        }
    }

    public function test_missing_required_fields_do_not_generate_or_record_a_version(): void
    {
        Mail::fake();
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('upload');

        new GenerateParticipantPdfJob($this->participant(dob: ''), str_repeat('a', 64))->handle(
            new PdfIntakeFormService,
            new DropboxUploadService($client),
            new ParticipantPdfVersionService,
        );

        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('participant_pdf_versions', 0);
        Mail::assertNothingQueued();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_upload_retries_keep_the_version_and_email_retries_only_send_email(): void
    {
        config()->set('mail.intake_form_recipients', ['intake@example.org', 'enrollment@example.net']);
        $this->travelTo(now()->startOfMinute());
        $this->mockPdf();

        $service = new ParticipantPdfVersionService;
        $service->generate($this->participant(), new PdfIntakeFormService, str_repeat('a', 64));

        $paths = [];
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('upload')->twice()->andReturnUsing(function (string $path, string $contents, string $mode, bool $autorename) use (&$paths): array {
            $paths[] = $path;
            $this->assertSame('overwrite', $mode);
            $this->assertFalse($autorename);
            throw_if(count($paths) === 1, RuntimeException::class, 'Test Dropbox failure.');

            return ['path_display' => $path];
        });
        $queuedJob = Mockery::mock(Job::class);
        $queuedJob->shouldReceive('uuid')->andReturn('00000000-0000-0000-0000-000000000123');
        $queuedJob->shouldReceive('attempts')->andReturn(1, 2);
        $job = new GenerateParticipantPdfJob($this->participant(email: 'updated@example.org'), str_repeat('b', 64));
        $job->setJob($queuedJob);

        $this->assertThrows(
            fn () => $job->handle(new PdfIntakeFormService, new DropboxUploadService($client), $service),
            RuntimeException::class,
            'Test Dropbox failure.',
        );

        $originalVersion = ParticipantPdfVersion::query()->where('version', 2)->firstOrFail();
        $this->assertDatabaseCount('jobs', 0);
        $this->travel(1)->minutes();
        $job->handle(new PdfIntakeFormService, new DropboxUploadService($client), $service);
        $this->assertSame($paths[0], $paths[1]);
        $this->assertStringEndsWith($originalVersion->filename, $paths[1]);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertCount(0, Mail::getSymfonyTransport()->messages());

        $failEmail = true;
        Event::listen(MessageSending::class, function () use (&$failEmail): void {
            throw_if($failEmail, RuntimeException::class, 'Test SMTP failure.');
        });
        $firstEmail = Queue::pop();
        $this->assertThrows(fn () => $firstEmail->fire(), RuntimeException::class, 'Test SMTP failure.');
        $firstEmail->release(0);

        // The other recipient can finish before the failed email retries.
        $failEmail = false;
        Queue::pop()->fire();
        Queue::pop()->fire();

        $this->assertSame(3, $this->generations);
        $this->assertDatabaseCount('participant_pdf_versions', 2);
        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        $recipients = [];
        foreach ($messages as $sent) {
            $message = $sent->getOriginalMessage();
            $recipients[] = $message->getTo()[0]->getAddress();
            $this->assertSame('Intake Form for Test Participant (v2)', $message->getSubject());
            $this->assertStringContainsString('<li>email</li>', $message->getHtmlBody());
        }

        $this->assertSame(['enrollment@example.net', 'intake@example.org'], $recipients);
    }

    private function mockPdf(): void
    {
        $pdf = Mockery::mock('overload:mikehaertl\\pdftk\\Pdf');
        $pdf->shouldReceive('fillForm', 'flatten')->andReturnSelf();
        $pdf->shouldReceive('execute')->andReturnUsing(function (): bool {
            $this->generations++;

            return true;
        });
        $pdf->shouldReceive('getTmpFile')->andReturnUsing(fn (): File => new File('%PDF-1.4 test', '.pdf'));
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
