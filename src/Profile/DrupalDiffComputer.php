<?php

namespace Spiriit\ComposerUpdateReport\Profile;

class DrupalDiffComputer
{
    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    public function compute(array $old, array $new): array
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

    /**
     * @param array<string, mixed> $lock
     * @return array<string, string>
     */
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
}
