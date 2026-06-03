<?php

namespace Spiriit\ComposerUpdateReport\Tests;

use PHPUnit\Framework\TestCase;
use Spiriit\ComposerUpdateReport\DiffComputer;
use Spiriit\ComposerUpdateReport\MarkdownRenderer;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function diff(array $overrides = []): array
    {
        return array_merge([
            'updates' => [],
            'added' => [],
            'removed' => [],
            'hasChanges' => true,
        ], $overrides);
    }

    public function testTitleContainsCurrentDate(): void
    {
        $output = $this->renderer->render($this->diff());

        $this->assertStringContainsString('# Récapitulatif de la mise à jour du ' . date('d/m/Y'), $output);
    }

    public function testKnownVendorGetsCuratedLabel(): void
    {
        $diff = $this->diff(['updates' => [
            'drupal' => [
                ['name' => 'drupal/core', 'from' => '10.2.0', 'to' => '10.3.0'],
            ],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('#### 🔵 Drupal', $output);
        $this->assertStringContainsString('`drupal/core` : `10.2.0` ➝ `10.3.0`', $output);
    }

    public function testSymfonyVendorGetsCuratedLabel(): void
    {
        $diff = $this->diff(['updates' => [
            'symfony' => [
                ['name' => 'symfony/console', 'from' => '6.4.6', 'to' => '6.4.8'],
            ],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('#### 🎵 Symfony', $output);
        $this->assertStringContainsString('`symfony/console` : `6.4.6` ➝ `6.4.8`', $output);
    }

    public function testUnknownVendorFallsBackToCapitalisedName(): void
    {
        $diff = $this->diff(['updates' => [
            'acme' => [
                ['name' => 'acme/widget', 'from' => '1.0.0', 'to' => '1.1.0'],
            ],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('#### 📦 Acme', $output);
        $this->assertStringContainsString('`acme/widget` : `1.0.0` ➝ `1.1.0`', $output);
    }

    public function testNoVendorBucketIsLabelledOtherLibraries(): void
    {
        $diff = $this->diff(['updates' => [
            DiffComputer::NO_VENDOR => [
                ['name' => 'monolog', 'from' => '2.0.0', 'to' => '3.0.0'],
            ],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('#### 📦 Autres librairies', $output);
        $this->assertStringContainsString('`monolog` : `2.0.0` ➝ `3.0.0`', $output);
    }

    public function testPackagesGroupedWhenSameVersionChange(): void
    {
        $diff = $this->diff(['updates' => [
            'symfony' => [
                ['name' => 'symfony/console', 'from' => '6.4.6', 'to' => '6.4.8'],
                ['name' => 'symfony/http-kernel', 'from' => '6.4.6', 'to' => '6.4.8'],
            ],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('Mise à jour de `6.4.6` à `6.4.8`', $output);
        $this->assertStringContainsString('symfony/console', $output);
        $this->assertStringContainsString('symfony/http-kernel', $output);
        // The shared version line must appear only once.
        $this->assertSame(1, substr_count($output, 'Mise à jour de `6.4.6` à `6.4.8`'));
    }

    public function testPackagesNotGroupedWhenDifferentVersionChanges(): void
    {
        $diff = $this->diff(['updates' => [
            'symfony' => [
                ['name' => 'symfony/console', 'from' => '6.4.6', 'to' => '6.4.8'],
                ['name' => 'symfony/routing', 'from' => '6.4.5', 'to' => '6.4.8'],
            ],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('`symfony/console` : `6.4.6` ➝ `6.4.8`', $output);
        $this->assertStringContainsString('`symfony/routing` : `6.4.5` ➝ `6.4.8`', $output);
    }

    public function testAddedPackagesSection(): void
    {
        $diff = $this->diff(['added' => ['drupal/gin' => '3.0.0']]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('### ✅ Nouveaux paquets', $output);
        $this->assertStringContainsString('`drupal/gin` : `3.0.0`', $output);
    }

    public function testRemovedPackagesSection(): void
    {
        $diff = $this->diff(['removed' => ['drupal/obsolete' => '1.0.0']]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('### ❌ Paquets supprimés', $output);
        $this->assertStringContainsString('`drupal/obsolete` : `1.0.0`', $output);
    }

    public function testEmptySectionsAreAbsent(): void
    {
        $diff = $this->diff(['added' => ['new/pkg' => '1.0.0']]);

        $output = $this->renderer->render($diff);

        $this->assertStringNotContainsString('Mises à jour majeures', $output);
        $this->assertStringNotContainsString('Paquets supprimés', $output);
    }

    public function testOutputEndsWithNewline(): void
    {
        $output = $this->renderer->render($this->diff(['added' => ['foo/bar' => '1.0.0']]));

        $this->assertStringEndsWith("\n", $output);
    }
}
