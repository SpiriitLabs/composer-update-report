<?php

namespace Spiriit\ComposerUpdateReport;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Spiriit\ComposerUpdateReport\Command\CommandProvider;
use Spiriit\ComposerUpdateReport\Profile\ProfileFactory;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
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

    public function getCapabilities(): array
    {
        return [CommandProviderCapability::class => CommandProvider::class];
    }

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
        $profile = ProfileFactory::create($extra['composer-update-report']['profile'] ?? null, $this->io);

        (new Generator($workingDir, $this->io, $outputDir, profile: $profile))->run();
    }
}
