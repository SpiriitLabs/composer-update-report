<?php

namespace Spiriit\ComposerUpdateReport\Tests;

use PHPUnit\Framework\TestCase;
use Spiriit\ComposerUpdateReport\DiffComputer;

class DiffComputerTest extends TestCase
{
    private DiffComputer $computer;

    protected function setUp(): void
    {
        $this->computer = new DiffComputer();
    }

    public function testNoChanges(): void
    {
        $lock = LockFixture::make([LockFixture::pkg('vendor/foo', '1.0.0')]);

        $diff = $this->computer->compute($lock, $lock);

        $this->assertFalse($diff['hasChanges']);
        $this->assertEmpty($diff['added']);
        $this->assertEmpty($diff['removed']);
        $this->assertEmpty($diff['updates']);
    }

    public function testUpdateIsGroupedByVendor(): void
    {
        $old = LockFixture::make([LockFixture::pkg('drupal/core', '10.2.0')]);
        $new = LockFixture::make([LockFixture::pkg('drupal/core', '10.3.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertArrayHasKey('drupal', $diff['updates']);
        $this->assertCount(1, $diff['updates']['drupal']);
        $this->assertSame('drupal/core', $diff['updates']['drupal'][0]['name']);
        $this->assertSame('10.2.0', $diff['updates']['drupal'][0]['from']);
        $this->assertSame('10.3.0', $diff['updates']['drupal'][0]['to']);
    }

    public function testPackagesOfSameVendorShareTheSameGroup(): void
    {
        $old = LockFixture::make([
            LockFixture::pkg('drupal/core', '10.2.0'),
            LockFixture::pkg('drupal/pathauto', '1.11.0'),
        ]);
        $new = LockFixture::make([
            LockFixture::pkg('drupal/core', '10.3.0'),
            LockFixture::pkg('drupal/pathauto', '1.12.0'),
        ]);

        $diff = $this->computer->compute($old, $new);

        $this->assertCount(1, $diff['updates']);
        $this->assertCount(2, $diff['updates']['drupal']);
    }

    public function testSymfonyVendorIsDetected(): void
    {
        $old = LockFixture::make([LockFixture::pkg('symfony/console', '6.4.6')]);
        $new = LockFixture::make([LockFixture::pkg('symfony/console', '6.4.8')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertArrayHasKey('symfony', $diff['updates']);
        $this->assertCount(1, $diff['updates']['symfony']);
    }

    public function testPackageWithoutVendorPrefixFallsIntoOtherBucket(): void
    {
        $old = LockFixture::make([LockFixture::pkg('monolog', '2.0.0')]);
        $new = LockFixture::make([LockFixture::pkg('monolog', '3.0.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertArrayHasKey(DiffComputer::NO_VENDOR, $diff['updates']);
        $this->assertCount(1, $diff['updates'][DiffComputer::NO_VENDOR]);
    }

    public function testVendorsAreOrderedByUpdateCountThenName(): void
    {
        $old = LockFixture::make([
            LockFixture::pkg('symfony/console', '6.4.6'),
            LockFixture::pkg('symfony/routing', '6.4.6'),
            LockFixture::pkg('drupal/core', '10.2.0'),
            LockFixture::pkg('acme/widget', '1.0.0'),
        ]);
        $new = LockFixture::make([
            LockFixture::pkg('symfony/console', '6.4.8'),
            LockFixture::pkg('symfony/routing', '6.4.8'),
            LockFixture::pkg('drupal/core', '10.3.0'),
            LockFixture::pkg('acme/widget', '1.1.0'),
        ]);

        $diff = $this->computer->compute($old, $new);

        // symfony has the most updates → first; acme and drupal tie on count → alphabetical.
        $this->assertSame(['symfony', 'acme', 'drupal'], array_keys($diff['updates']));
    }

    public function testNoVendorBucketIsAlwaysLast(): void
    {
        $old = LockFixture::make([
            LockFixture::pkg('monolog', '2.0.0'),
            LockFixture::pkg('symfony/console', '6.4.6'),
        ]);
        $new = LockFixture::make([
            LockFixture::pkg('monolog', '3.0.0'),
            LockFixture::pkg('symfony/console', '6.4.8'),
        ]);

        $diff = $this->computer->compute($old, $new);

        $keys = array_keys($diff['updates']);
        $this->assertSame(DiffComputer::NO_VENDOR, end($keys));
    }

    public function testAddedPackage(): void
    {
        $old = LockFixture::make([]);
        $new = LockFixture::make([LockFixture::pkg('drupal/gin', '3.0.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertArrayHasKey('drupal/gin', $diff['added']);
        $this->assertSame('3.0.0', $diff['added']['drupal/gin']);
    }

    public function testRemovedPackage(): void
    {
        $old = LockFixture::make([LockFixture::pkg('drupal/obsolete', '1.0.0')]);
        $new = LockFixture::make([]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertArrayHasKey('drupal/obsolete', $diff['removed']);
    }

    public function testVersionNormalizationIgnoresLeadingV(): void
    {
        $old = LockFixture::make([LockFixture::pkg('vendor/foo', 'v1.0.0')]);
        $new = LockFixture::make([LockFixture::pkg('vendor/foo', '1.0.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertFalse($diff['hasChanges']);
    }

    public function testPackagesDevAreIndexed(): void
    {
        $old = LockFixture::make([], [LockFixture::pkg('phpunit/phpunit', '10.0.0')]);
        $new = LockFixture::make([], [LockFixture::pkg('phpunit/phpunit', '11.0.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertArrayHasKey('phpunit', $diff['updates']);
        $this->assertCount(1, $diff['updates']['phpunit']);
    }

    public function testMultipleVendorsAndAddedAtOnce(): void
    {
        $old = LockFixture::make([
            LockFixture::pkg('drupal/core', '10.2.0'),
            LockFixture::pkg('drupal/pathauto', '1.11.0'),
            LockFixture::pkg('symfony/console', '6.4.6'),
        ]);
        $new = LockFixture::make([
            LockFixture::pkg('drupal/core', '10.3.0'),
            LockFixture::pkg('drupal/pathauto', '1.12.0'),
            LockFixture::pkg('symfony/console', '6.4.8'),
            LockFixture::pkg('drupal/gin', '3.0.0'),
        ]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertCount(2, $diff['updates']['drupal']);
        $this->assertCount(1, $diff['updates']['symfony']);
        $this->assertCount(1, $diff['added']);
        $this->assertEmpty($diff['removed']);
    }
}
