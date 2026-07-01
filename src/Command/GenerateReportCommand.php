<?php

namespace Spiriit\ComposerUpdateReport\Command;

use Composer\Command\BaseCommand;
use Spiriit\ComposerUpdateReport\Generator;
use Spiriit\ComposerUpdateReport\Profile\ProfileFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Regenerates the update report on demand against arbitrary git refs, instead
 * of only after a `composer update`. Handy while a branch is in progress: diff
 * the current composer.lock against the base branch (e.g. origin/develop).
 */
final class GenerateReportCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('update-report')
            ->setDescription('Regenerate the composer update report from arbitrary git refs.')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Git ref for the "before" state (e.g. origin/develop).', 'HEAD')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Git ref for the "after" state (defaults to the working composer.lock).')
            ->addOption('report-profile', null, InputOption::VALUE_REQUIRED, 'Report profile (agnostic|drupal). Defaults to the extra config.')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Output directory. Defaults to the extra config.')
            ->setHelp(<<<'HELP'
                Compares the composer.lock of two git refs and writes the dated Markdown report.

                By default the "after" state is the current working composer.lock, so during a
                branch you can compare it against the base branch:

                    <info>composer update-report --from=origin/develop</info>

                Profile and output directory default to the <info>extra.composer-update-report</info>
                config and can be overridden per run with --report-profile and --output-dir.
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();

        $workingDir = getcwd();
        if ($workingDir === false) {
            $io->writeError('<error>[composer-update-report] Cannot determine working directory.</error>');

            return self::FAILURE;
        }

        $extra = $this->requireComposer()->getPackage()->getExtra()['composer-update-report'] ?? [];

        $outputDir = $input->getOption('output-dir') ?? ($extra['output-dir'] ?? null);
        $profile = ProfileFactory::create($input->getOption('report-profile') ?? ($extra['profile'] ?? null), $io);

        (new Generator($workingDir, $io, $outputDir, profile: $profile))
            ->runFromRefs($input->getOption('from'), $input->getOption('to'));

        return self::SUCCESS;
    }
}
