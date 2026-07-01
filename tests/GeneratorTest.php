<?php

namespace Spiriit\ComposerUpdateReport\Tests;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use Spiriit\ComposerUpdateReport\Generator;
use Spiriit\ComposerUpdateReport\GitReaderInterface;
use Spiriit\ComposerUpdateReport\Profile\AgnosticReportProfile;

class GeneratorTest extends TestCase
{
    private string $workingDir;

    protected function setUp(): void
    {
        $this->workingDir = sys_get_temp_dir() . '/cur-test-' . uniqid();
        mkdir($this->workingDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workingDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->workingDir);
    }

    private function reportPath(): string
    {
        return $this->workingDir . '/composer-update-' . date('Y-m-d') . '.md';
    }

    /** Fake git reader returning preset lock JSON per ref. */
    private function gitReader(array $refs): GitReaderInterface
    {
        return new class ($refs) implements GitReaderInterface {
            public function __construct(private array $refs) {}

            public function show(string $workingDir, string $ref): ?string
            {
                return $this->refs[$ref] ?? null;
            }

            public function gitDir(string $workingDir): ?string
            {
                return null;
            }
        };
    }

    public function testRunFromRefsDiffsRefAgainstWorkingLock(): void
    {
        $old = json_encode(LockFixture::make([LockFixture::pkg('drupal/core', '10.6.11')]));
        $new = LockFixture::make([LockFixture::pkg('drupal/core', '10.6.12')]);
        file_put_contents($this->workingDir . '/composer.lock', json_encode($new));

        $generator = new Generator(
            $this->workingDir,
            new BufferIO(),
            null,
            $this->gitReader(['origin/develop' => $old]),
            new AgnosticReportProfile(),
        );

        $generator->runFromRefs('origin/develop');

        $this->assertFileExists($this->reportPath());
        $report = file_get_contents($this->reportPath());
        $this->assertStringContainsString('10.6.11', $report);
        $this->assertStringContainsString('10.6.12', $report);
    }

    public function testRunFromRefsUsesExplicitToRef(): void
    {
        $old = json_encode(LockFixture::make([LockFixture::pkg('drupal/core', '10.6.11')]));
        $to = json_encode(LockFixture::make([LockFixture::pkg('drupal/core', '10.7.0')]));
        // A different working lock proves the --to ref wins over the working file.
        file_put_contents($this->workingDir . '/composer.lock', json_encode(LockFixture::make()));

        $generator = new Generator(
            $this->workingDir,
            new BufferIO(),
            null,
            $this->gitReader(['origin/develop' => $old, 'HEAD' => $to]),
            new AgnosticReportProfile(),
        );

        $generator->runFromRefs('origin/develop', 'HEAD');

        $this->assertFileExists($this->reportPath());
        $this->assertStringContainsString('10.7.0', file_get_contents($this->reportPath()));
    }

    public function testRunFromRefsMissingFromRefWritesNothing(): void
    {
        file_put_contents($this->workingDir . '/composer.lock', json_encode(LockFixture::make()));
        $io = new BufferIO();

        $generator = new Generator(
            $this->workingDir,
            $io,
            null,
            $this->gitReader([]),
            new AgnosticReportProfile(),
        );

        $generator->runFromRefs('does/not-exist');

        $this->assertFileDoesNotExist($this->reportPath());
        $this->assertStringContainsString('Cannot read composer.lock from git ref', $io->getOutput());
    }

    public function testRunFromRefsDoesNotTouchDayBaseline(): void
    {
        $old = json_encode(LockFixture::make([LockFixture::pkg('drupal/core', '10.6.11')]));
        file_put_contents($this->workingDir . '/composer.lock', json_encode(LockFixture::make([LockFixture::pkg('drupal/core', '10.6.12')])));

        // gitDir() returns null in the fake reader, so no baseline file can ever
        // be created; assert none appears in the working dir either.
        $generator = new Generator(
            $this->workingDir,
            new BufferIO(),
            null,
            $this->gitReader(['origin/develop' => $old]),
            new AgnosticReportProfile(),
        );

        $generator->runFromRefs('origin/develop');

        $this->assertEmpty(glob($this->workingDir . '/baseline-*.json') ?: []);
    }
}
