<?php

namespace Spiriit\ComposerUpdateReport\Tests\Profile;

use PHPUnit\Framework\TestCase;
use Spiriit\ComposerUpdateReport\Profile\DrupalMarkdownRenderer;

class DrupalMarkdownRendererTest extends TestCase
{
    private DrupalMarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new DrupalMarkdownRenderer();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function diff(array $overrides = []): array
    {
        return array_merge([
            'drupalCore' => [],
            'drupalContrib' => [],
            'symfony' => [],
            'other' => [],
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

    public function testDrupalCoreSection(): void
    {
        $diff = $this->diff(['drupalCore' => [
            ['name' => 'drupal/core', 'from' => '10.2.0', 'to' => '10.3.0'],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('#### 🔵 Drupal Core', $output);
        $this->assertStringContainsString('`drupal/core` : `10.2.0` ➝ `10.3.0`', $output);
    }

    public function testDrupalContribSection(): void
    {
        $diff = $this->diff(['drupalContrib' => [
            ['name' => 'drupal/pathauto', 'from' => '1.11.0', 'to' => '1.12.0'],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('#### 🧩 Modules Contrib Drupal', $output);
        $this->assertStringContainsString('`drupal/pathauto` : `1.11.0` ➝ `1.12.0`', $output);
    }

    public function testSymfonyGroupedWhenSameVersionChange(): void
    {
        $diff = $this->diff(['symfony' => [
            ['name' => 'symfony/console', 'from' => '6.4.6', 'to' => '6.4.8'],
            ['name' => 'symfony/http-kernel', 'from' => '6.4.6', 'to' => '6.4.8'],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('Mise à jour de `6.4.6` à `6.4.8`', $output);
        $this->assertStringContainsString('symfony/console', $output);
        $this->assertStringContainsString('symfony/http-kernel', $output);
        // Should not repeat the version line twice
        $this->assertSame(1, substr_count($output, 'Mise à jour de `6.4.6` à `6.4.8`'));
    }

    public function testSymfonyNotGroupedWhenDifferentVersionChanges(): void
    {
        $diff = $this->diff(['symfony' => [
            ['name' => 'symfony/console', 'from' => '6.4.6', 'to' => '6.4.8'],
            ['name' => 'symfony/routing', 'from' => '6.4.5', 'to' => '6.4.8'],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('**Composants Symfony**', $output);
        $this->assertStringContainsString('6.4.6` ➝ `6.4.8', $output);
        $this->assertStringContainsString('6.4.5` ➝ `6.4.8', $output);
    }

    public function testOtherLibrariesSection(): void
    {
        $diff = $this->diff(['other' => [
            ['name' => 'league/csv', 'from' => '9.14.0', 'to' => '9.15.0'],
        ]]);

        $output = $this->renderer->render($diff);

        $this->assertStringContainsString('**Autres librairies**', $output);
        $this->assertStringContainsString('`league/csv` : `9.14.0` ➝ `9.15.0`', $output);
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

        $this->assertStringNotContainsString('Drupal Core', $output);
        $this->assertStringNotContainsString('Modules Contrib', $output);
        $this->assertStringNotContainsString('Paquets supprimés', $output);
    }

    public function testOutputEndsWithNewline(): void
    {
        $output = $this->renderer->render($this->diff(['added' => ['foo/bar' => '1.0.0']]));

        $this->assertStringEndsWith("\n", $output);
    }
}
