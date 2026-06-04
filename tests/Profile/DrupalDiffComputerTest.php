<?php

namespace Spiriit\ComposerUpdateReport\Tests\Profile;

use PHPUnit\Framework\TestCase;
use Spiriit\ComposerUpdateReport\Profile\DrupalDiffComputer;
use Spiriit\ComposerUpdateReport\Tests\LockFixture;

class DrupalDiffComputerTest extends TestCase
{
    private DrupalDiffComputer $computer;

    protected function setUp(): void
    {
        $this->computer = new DrupalDiffComputer();
    }

    public function testNoChanges(): void
    {
        $lock = LockFixture::make([LockFixture::pkg('vendor/foo', '1.0.0')]);

        $diff = $this->computer->compute($lock, $lock);

        $this->assertFalse($diff['hasChanges']);
        $this->assertEmpty($diff['added']);
        $this->assertEmpty($diff['removed']);
        $this->assertEmpty($diff['other']);
    }

    public function testDrupalCoreUpdate(): void
    {
        $old = LockFixture::make([LockFixture::pkg('drupal/core', '10.2.0')]);
        $new = LockFixture::make([LockFixture::pkg('drupal/core', '10.3.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertCount(1, $diff['drupalCore']);
        $this->assertSame('drupal/core', $diff['drupalCore'][0]['name']);
        $this->assertSame('10.2.0', $diff['drupalCore'][0]['from']);
        $this->assertSame('10.3.0', $diff['drupalCore'][0]['to']);
        $this->assertEmpty($diff['drupalContrib']);
    }

    public function testDrupalCoreRecommendedRoutesToDrupalCore(): void
    {
        $old = LockFixture::make([LockFixture::pkg('drupal/core-recommended', '10.2.0')]);
        $new = LockFixture::make([LockFixture::pkg('drupal/core-recommended', '10.3.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertCount(1, $diff['drupalCore']);
        $this->assertEmpty($diff['drupalContrib']);
    }

    public function testDrupalContribUpdate(): void
    {
        $old = LockFixture::make([LockFixture::pkg('drupal/pathauto', '1.11.0')]);
        $new = LockFixture::make([LockFixture::pkg('drupal/pathauto', '1.12.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertCount(1, $diff['drupalContrib']);
        $this->assertEmpty($diff['drupalCore']);
    }

    public function testSymfonyUpdate(): void
    {
        $old = LockFixture::make([LockFixture::pkg('symfony/console', '6.4.6')]);
        $new = LockFixture::make([LockFixture::pkg('symfony/console', '6.4.8')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertCount(1, $diff['symfony']);
        $this->assertEmpty($diff['other']);
    }

    public function testOtherLibraryUpdate(): void
    {
        $old = LockFixture::make([LockFixture::pkg('league/csv', '9.14.0')]);
        $new = LockFixture::make([LockFixture::pkg('league/csv', '9.15.0')]);

        $diff = $this->computer->compute($old, $new);

        $this->assertTrue($diff['hasChanges']);
        $this->assertCount(1, $diff['other']);
        $this->assertEmpty($diff['symfony']);
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
        $this->assertCount(1, $diff['other']);
    }

    public function testMultipleCategoriesAtOnce(): void
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
        $this->assertCount(1, $diff['drupalCore']);
        $this->assertCount(1, $diff['drupalContrib']);
        $this->assertCount(1, $diff['symfony']);
        $this->assertCount(1, $diff['added']);
        $this->assertEmpty($diff['removed']);
    }
}
