<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ParticipantPdfVersion;
use App\Services\ParticipantPdfVersionService;
use App\Services\PdfIntakeFormService;
use App\Transformers\NeonDTOTransformer;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use mikehaertl\tmp\File;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;
use Throwable;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ParticipantPdfConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
            define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 2).'/vendor/autoload.php');
        }
    }

    protected function tearDown(): void
    {
        DB::purge();
        DB::table('participant_pdf_versions')->where('participant_id', '123')->delete();
        DB::table('participant_pdf_histories')->where('participant_id', '123')->delete();

        parent::tearDown();
    }

    public function test_concurrent_unchanged_generations_create_only_one_version(): void
    {
        if (! function_exists('pcntl_fork') || DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('This lock regression test requires MySQL and pcntl.');
        }

        $pdf = Mockery::mock('overload:mikehaertl\\pdftk\\Pdf');
        $pdf->shouldReceive('fillForm', 'flatten')->andReturnSelf();
        $pdf->shouldReceive('execute')->andReturnUsing(function (): bool {
            Sleep::usleep(50_000);

            return true;
        });
        $pdf->shouldReceive('getTmpFile')->andReturnUsing(fn (): File => new File('%PDF-1.4 concurrency test', '.pdf'));

        $record = array_fill_keys(['contactInfo', 'children', 'disclosure', 'assessment', 'survey', 'servicePlan'], ['records' => []]);
        $record['contactInfo']['records'][] = [
            'persons_id' => ['value' => '123'],
            'firstName' => ['displayValue' => 'Test'],
            'lastName' => ['displayValue' => 'Participant'],
        ];
        $participant = NeonDTOTransformer::transformParticipantData($record);
        $hash = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));

        DB::disconnect();
        $children = [];
        for ($index = 0; $index < 4; $index++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    new ParticipantPdfVersionService()->generate($participant, new PdfIntakeFormService, $hash);
                    exit(0);
                } catch (Throwable) {
                    exit(1);
                }
            }

            $children[] = $pid;
        }

        $exitCodes = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $exitCodes[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
        }

        DB::purge();

        $this->assertSame([0, 0, 0, 0], $exitCodes);
        $versions = ParticipantPdfVersion::query()->where('participant_id', '123')->orderBy('version')->get();
        $this->assertSame([1], $versions->pluck('version')->all());
    }
}
