<?php

namespace Spiriit\ComposerUpdateReport;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

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
        $extra = $this->composer->getPackage()->getExtra();
        $outputDir = $extra['composer-update-report']['output-dir'] ?? null;

        (new Generator($workingDir, $this->io, $outputDir))->run();
    }
}
