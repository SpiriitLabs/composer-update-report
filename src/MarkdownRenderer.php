<?php

namespace Spiriit\ComposerUpdateReport;

class MarkdownRenderer
{
    /**
     * Curated emoji + display label for well-known vendors. Any other vendor
     * falls back to a generic emoji and its capitalised vendor name, so the
     * report stays meaningful for any project without configuration.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const KNOWN_VENDORS = [
        'drupal' => ['🔵', 'Drupal'],
        'symfony' => ['🎵', 'Symfony'],
        'laravel' => ['🔴', 'Laravel'],
        'api-platform' => ['🟢', 'API Platform'],
        'doctrine' => ['🟠', 'Doctrine'],
        'twig' => ['🌿', 'Twig'],
        'league' => ['🤝', 'League'],
        'phpunit' => ['🧪', 'PHPUnit'],
        'phpstan' => ['🔬', 'PHPStan'],
    ];

    /** @param array<string, mixed> $diff */
    public function render(array $diff): string
    {
        $date = date('d/m/Y');
        $out = [];
        $out[] = "# Récapitulatif de la mise à jour du {$date}";
        $out[] = '';
        $out[] = "Basé sur le diff de `composer.lock`, voici le résumé de tous les paquets mis à jour.";

        if ($diff['updates']) {
            $out[] = '';
            $out[] = '### 🚀 Mises à jour majeures et mineures';

            foreach ($diff['updates'] as $vendor => $packages) {
                [$emoji, $label] = $this->vendorLabel($vendor);
                $out[] = '';
                $out[] = "#### {$emoji} {$label}";
                $out[] = '';
                $this->renderPackages($out, $packages);
            }
        }

        if ($diff['added']) {
            $out[] = '';
            $out[] = '### ✅ Nouveaux paquets';
            $out[] = '';
            foreach ($diff['added'] as $name => $version) {
                $out[] = "* `{$name}` : `{$version}`";
            }
        }

        if ($diff['removed']) {
            $out[] = '';
            $out[] = '### ❌ Paquets supprimés';
            $out[] = '';
            foreach ($diff['removed'] as $name => $version) {
                $out[] = "* `{$name}` : `{$version}`";
            }
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function vendorLabel(string $vendor): array
    {
        if ($vendor === DiffComputer::NO_VENDOR) {
            return ['📦', 'Autres librairies'];
        }

        return self::KNOWN_VENDORS[$vendor] ?? ['📦', ucfirst($vendor)];
    }

    /**
     * Renders a vendor's package list. Packages sharing the same before/after
     * version are grouped into a single entry (typical of Symfony components
     * released in lockstep); the rest are listed one per line.
     *
     * @param list<string>                                          $out
     * @param list<array{name: string, from: string, to: string}>  $packages
     */
    private function renderPackages(array &$out, array $packages): void
    {
        $groups = [];
        foreach ($packages as $pkg) {
            $groups[$pkg['from'] . '|||' . $pkg['to']][] = $pkg['name'];
        }

        foreach ($groups as $key => $names) {
            [$from, $to] = explode('|||', $key);

            if (count($names) > 1) {
                sort($names);
                $out[] = "* Mise à jour de `{$from}` à `{$to}` :";
                $out[] = '';
                $out[] = '    * `' . implode('`, `', $names) . '`';
                $out[] = '';
            } else {
                $out[] = "* `{$names[0]}` : `{$from}` ➝ `{$to}`";
            }
        }
    }
}
