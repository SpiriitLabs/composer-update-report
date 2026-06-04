<?php

namespace Spiriit\ComposerUpdateReport;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Spiriit\ComposerUpdateReport\Profile\AgnosticReportProfile;
use Spiriit\ComposerUpdateReport\Profile\DrupalReportProfile;
use Spiriit\ComposerUpdateReport\Profile\ReportProfileInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate'];
    }

    public function onPostUpdate(Event $event): void
    {
        $command = $_SERVER['argv'][1] ?? '';
        if (!in_array($command, ['update', 'u'], true)) {
            return;
        }

        $workingDir = getcwd();
        if ($workingDir === false) {
            $this->io->writeError('<error>[composer-update-report] Cannot determine working directory.</error>');
            return;
        }

        $extra = $this->composer->getPackage()->getExtra();
        $outputDir = $extra['composer-update-report']['output-dir'] ?? null;
        $profile = $this->resolveProfile($extra['composer-update-report']['profile'] ?? null);

        (new Generator($workingDir, $this->io, $outputDir, profile: $profile))->run();
    }

    /**
     * Resolves the report profile from the `extra.composer-update-report.profile`
     * setting. Defaults to the agnostic profile; an unknown value warns and
     * falls back to it.
     */
    private function resolveProfile(?string $name): ReportProfileInterface
    {
        return match ($name) {
            'drupal' => new DrupalReportProfile(),
            'agnostic', null => new AgnosticReportProfile(),
            default => $this->unknownProfile($name),
        };
    }

    private function unknownProfile(string $name): ReportProfileInterface
    {
        $this->io->writeError("<warning>[composer-update-report] Unknown profile '{$name}', falling back to 'agnostic'.</warning>");

        return new AgnosticReportProfile();
    }
}
