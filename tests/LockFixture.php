<?php

namespace Spiriit\ComposerUpdateReport\Tests;

class LockFixture
{
    public static function make(array $packages = [], array $packagesDev = []): array
    {
        return [
            'packages' => $packages,
            'packages-dev' => $packagesDev,
        ];
    }

    public static function pkg(string $name, string $version): array
    {
        return ['name' => $name, 'version' => $version];
    }
}
