<?php

namespace Spiriit\ComposerUpdateReport\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final class CommandProvider implements CommandProviderCapability
{
    /**
     * @return array<int, \Composer\Command\BaseCommand>
     */
    public function getCommands(): array
    {
        return [new GenerateReportCommand()];
    }
}
