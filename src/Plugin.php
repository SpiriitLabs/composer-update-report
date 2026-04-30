<?php

namespace Lotimopa\ComposerUpdateReport;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
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
        (new Generator(getcwd(), $this->io))->run();
    }
}
