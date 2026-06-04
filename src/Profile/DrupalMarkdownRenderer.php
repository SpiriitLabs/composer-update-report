<?php

namespace Spiriit\ComposerUpdateReport\Profile;

class DrupalMarkdownRenderer
{
    /** @param array<string, mixed> $diff */
    public function render(array $diff): string
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
