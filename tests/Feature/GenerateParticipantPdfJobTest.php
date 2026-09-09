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
use App\Services\DropboxUploadService;
use App\Services\PdfIntakeFormService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use Spatie\Dropbox\Client;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

final class GenerateParticipantPdfJobTest extends TestCase
{
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
        Log::spy();

        // Stub the PDF tool, not the job, so tests do not need the pdftk executable.
        $pdf = Mockery::mock('overload:mikehaertl\\pdftk\\Pdf');
        $pdf->shouldReceive('fillForm', 'needAppearances', 'flatten')->andReturnSelf();
        $pdf->shouldReceive('saveAs')->andReturnUsing(
            fn (string $path): bool => file_put_contents($path, '%PDF-1.4 test attachment') !== false
        );
        $pdf->shouldReceive('getError')->andReturn('');

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('upload')->once()->andReturn(['path_display' => '/intake.pdf']);

        $participant = new ParticipantUpdateData(
            id: '123',
            firstName: 'Test',
            lastName: 'Participant',
            contactInfo: $this->filledDto(ContactInfoDTO::class),
            children: [$this->filledDto(ChildDTO::class)],
            disclosure: $this->filledDto(DisclosureDTO::class),
            assessment: $this->filledDto(AssessmentDTO::class),
            survey: $this->filledDto(SurveyDTO::class),
            servicePlan: $this->filledDto(ServicePlanDTO::class),
        );
        $this->assertFalse($participant->hasMissingFields());

        new GenerateParticipantPdfJob($participant)->handle(
            new PdfIntakeFormService,
            new DropboxUploadService($client),
        );

        $this->assertCount(1, Storage::files('participant-forms/123'));
        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(count($valid), $messages);

        foreach ($messages->values() as $index => $sent) {
            $message = $sent->getOriginalMessage();
            $this->assertSame([$valid[$index]], array_map(fn (Address $address): string => $address->getAddress(), $message->getTo()));
            $this->assertSame([], $message->getCc());
            $this->assertSame([], $message->getBcc());
            $this->assertCount(1, $message->getAttachments());
        }

        foreach ($invalid as $recipient) {
            Log::shouldHaveReceived('warning')
                ->with('Skipping PDF email for invalid recipient.', ['recipient' => $recipient])
                ->once();
        }

        if ($invalid === []) {
            Log::shouldNotHaveReceived('warning');
        } else {
            Log::shouldHaveReceived('warning')->times(count($invalid));
        }

        Log::shouldNotHaveReceived('error');
    }

    private function filledDto(string $class): object
    {
        // Populate every string field to exercise the completed-form path.
        $reflection = new ReflectionClass($class);

        return $reflection->newInstanceArgs(array_fill(0, count($reflection->getConstructor()->getParameters()), 'Test'));
    }
}
