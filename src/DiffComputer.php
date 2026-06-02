<?php

namespace Spiriit\ComposerUpdateReport;

class DiffComputer
{
    /**
     * Bucket key used for packages whose name has no `vendor/` prefix.
     */
    public const NO_VENDOR = '_other';

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    public function compute(array $old, array $new): array
    {
        $oldPkgs = $this->buildIndex($old);
        $newPkgs = $this->buildIndex($new);

        $updates = [];
        $added = [];
        $removed = [];

        foreach ($newPkgs as $name => $version) {
            if (!isset($oldPkgs[$name])) {
                $added[$name] = $version;
                continue;
            }
            if ($this->normalizeVersion($oldPkgs[$name]) !== $this->normalizeVersion($version)) {
                $vendor = $this->vendorOf($name);
                $updates[$vendor][] = ['name' => $name, 'from' => $oldPkgs[$name], 'to' => $version];
            }
        }

        foreach (array_keys($oldPkgs) as $name) {
            if (!isset($newPkgs[$name])) {
                $removed[$name] = $oldPkgs[$name];
            }
        }

        return [
            'updates' => $this->sortVendors($updates),
            'added' => $added,
            'removed' => $removed,
            'hasChanges' => (bool) ($updates || $added || $removed),
        ];
    }

    /**
     * Extracts the Composer vendor (the part before the first `/`).
     * Packages without a vendor prefix fall into the generic bucket.
     */
    private function vendorOf(string $name): string
    {
        $pos = strpos($name, '/');

        return $pos === false ? self::NO_VENDOR : substr($name, 0, $pos);
    }

    /**
     * Orders vendor groups by descending number of updated packages, then
     * alphabetically, so the most heavily updated framework (Symfony, Drupal,
     * Laravel, …) naturally surfaces first. The "no vendor" bucket is last.
     *
     * @param array<string, list<array{name: string, from: string, to: string}>> $updates
     * @return array<string, list<array{name: string, from: string, to: string}>>
     */
    private function sortVendors(array $updates): array
    {
        uksort($updates, function (string $a, string $b) use ($updates): int {
            if ($a === self::NO_VENDOR) {
                return 1;
            }
            if ($b === self::NO_VENDOR) {
                return -1;
            }

            return (count($updates[$b]) <=> count($updates[$a])) ?: strcmp($a, $b);
        });

        return $updates;
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
