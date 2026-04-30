<?php

namespace Lotimopa\ComposerUpdateReport;

use Composer\IO\IOInterface;

class Generator
{
    public function __construct(
        private readonly string $workingDir,
        private readonly IOInterface $io
    ) {}

    public function run(): void
    {
        $oldJson = $this->gitShow('HEAD');
        $newJson = @file_get_contents($this->workingDir . '/composer.lock');

        if (!$oldJson || !$newJson) {
            $this->io->writeError('<warning>[composer-update-report] Cannot read composer.lock from git HEAD.</warning>');
            return;
        }

        $old = json_decode($oldJson, true);
        $new = json_decode($newJson, true);

        if (!$old || !$new) {
            $this->io->writeError('<warning>[composer-update-report] Cannot parse composer.lock JSON.</warning>');
            return;
        }

        $diff = $this->computeDiff($old, $new);

        if (!$diff['hasChanges']) {
            $this->io->write('<info>[composer-update-report] No version changes detected.</info>');
            return;
        }

        $outputFile = $this->workingDir . '/composer-update-' . date('Y-m-d') . '.md';
        $content = $this->generateMarkdown($diff);

        if (file_put_contents($outputFile, $content) === false) {
            $this->io->writeError('<error>[composer-update-report] Cannot write to ' . $outputFile . '</error>');
            return;
        }

        $this->io->write('<info>[composer-update-report] Report generated: ' . $outputFile . '</info>');
    }

    private function gitShow(string $ref): ?string
    {
        $result = shell_exec(sprintf(
            'git -C %s show %s:composer.lock 2>/dev/null',
            escapeshellarg($this->workingDir),
            escapeshellarg($ref)
        ));

        return $result ?: null;
    }

    private function buildIndex(array $lock): array
    {
        $index = [];
        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $pkg) {
            $index[$pkg['name']] = $pkg['version'];
        }
        return $index;
    }

    private function normalizeVersion(string $v): string
    {
        return ltrim($v, 'v');
    }

    private function computeDiff(array $old, array $new): array
    {
        $oldPkgs = $this->buildIndex($old);
        $newPkgs = $this->buildIndex($new);

        $drupalCore = [];
        $drupalContrib = [];
        $symfony = [];
        $other = [];
        $added = [];
        $removed = [];

        foreach ($newPkgs as $name => $version) {
            if (!isset($oldPkgs[$name])) {
                $added[$name] = $version;
                continue;
            }
            if ($this->normalizeVersion($oldPkgs[$name]) !== $this->normalizeVersion($version)) {
                $change = ['name' => $name, 'from' => $oldPkgs[$name], 'to' => $version];
                if (str_starts_with($name, 'drupal/core')) {
                    $drupalCore[] = $change;
                } elseif (str_starts_with($name, 'drupal/')) {
                    $drupalContrib[] = $change;
                } elseif (str_starts_with($name, 'symfony/')) {
                    $symfony[] = $change;
                } else {
                    $other[] = $change;
                }
            }
        }

        foreach (array_keys($oldPkgs) as $name) {
            if (!isset($newPkgs[$name])) {
                $removed[$name] = $oldPkgs[$name];
            }
        }

        return [
            'drupalCore' => $drupalCore,
            'drupalContrib' => $drupalContrib,
            'symfony' => $symfony,
            'other' => $other,
            'added' => $added,
            'removed' => $removed,
            'hasChanges' => (bool) ($drupalCore || $drupalContrib || $symfony || $other || $added || $removed),
        ];
    }

    private function generateMarkdown(array $diff): string
    {
        $date = date('d/m/Y');
        $out = [];
        $out[] = "# Récapitulatif de la mise à jour du {$date}";
        $out[] = '';
        $out[] = "Basé sur le diff de `composer.lock`, voici le résumé de tous les paquets mis à jour.";

        $hasUpdates = $diff['drupalCore'] || $diff['drupalContrib'] || $diff['symfony'] || $diff['other'];

        if ($hasUpdates) {
            $out[] = '';
            $out[] = '### 🚀 Mises à jour majeures et mineures';

            if ($diff['drupalCore']) {
                $out[] = '';
                $out[] = '#### 🔵 Drupal Core';
                $out[] = '';
                foreach ($diff['drupalCore'] as $pkg) {
                    $out[] = "* `{$pkg['name']}` : `{$pkg['from']}` ➝ `{$pkg['to']}`";
                }
            }

            if ($diff['drupalContrib']) {
                $out[] = '';
                $out[] = '#### 🧩 Modules Contrib Drupal';
                $out[] = '';
                foreach ($diff['drupalContrib'] as $pkg) {
                    $out[] = "* `{$pkg['name']}` : `{$pkg['from']}` ➝ `{$pkg['to']}`";
                }
            }

            if ($diff['symfony'] || $diff['other']) {
                $out[] = '';
                $out[] = '#### 📦 Bibliothèques sous-jacentes (Vendor)';
                $out[] = '';

                if ($diff['symfony']) {
                    $groups = [];
                    foreach ($diff['symfony'] as $pkg) {
                        $groups[$pkg['from'] . '|||' . $pkg['to']][] = $pkg['name'];
                    }

                    if (count($groups) === 1) {
                        [$from, $to] = explode('|||', array_key_first($groups));
                        $names = $groups[array_key_first($groups)];
                        sort($names);
                        $out[] = "* **Composants Symfony** : Mise à jour de `{$from}` à `{$to}` :";
                        $out[] = '';
                        $out[] = '    * `' . implode('`, `', $names) . '`';
                        $out[] = '';
                    } else {
                        $out[] = '* **Composants Symfony** :';
                        $out[] = '';
                        foreach ($groups as $key => $names) {
                            [$from, $to] = explode('|||', $key);
                            sort($names);
                            $out[] = '    * `' . $from . '` ➝ `' . $to . '` : `' . implode('`, `', $names) . '`';
                            $out[] = '';
                        }
                    }
                }

                if ($diff['other']) {
                    $out[] = '* **Autres librairies** :';
                    $out[] = '';
                    foreach ($diff['other'] as $pkg) {
                        $out[] = "    * `{$pkg['name']}` : `{$pkg['from']}` ➝ `{$pkg['to']}`";
                    }
                    $out[] = '';
                }
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
}
