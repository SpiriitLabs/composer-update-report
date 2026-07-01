<?php

namespace Spiriit\ComposerUpdateReport\Profile;

use Composer\IO\IOInterface;

/**
 * Resolves a report profile from its `extra.composer-update-report.profile`
 * name. Shared by the automatic hook and the manual command so both accept the
 * exact same values.
 */
final class ProfileFactory
{
    public static function create(?string $name, IOInterface $io): ReportProfileInterface
    {
        return match ($name) {
            'drupal' => new DrupalReportProfile(),
            'agnostic', null => new AgnosticReportProfile(),
            default => self::unknownProfile($name, $io),
        };
    }

    private static function unknownProfile(string $name, IOInterface $io): ReportProfileInterface
    {
        $io->writeError("<warning>[composer-update-report] Unknown profile '{$name}', falling back to 'agnostic'.</warning>");

        return new AgnosticReportProfile();
    }
}
