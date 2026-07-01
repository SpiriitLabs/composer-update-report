<?php

namespace Spiriit\ComposerUpdateReport;

use Composer\IO\IOInterface;
use Spiriit\ComposerUpdateReport\Profile\AgnosticReportProfile;
use Spiriit\ComposerUpdateReport\Profile\ReportProfileInterface;

class Generator
{
    public function __construct(
        private readonly string $workingDir,
        private readonly IOInterface $io,
        private readonly ?string $outputDir = null,
        private readonly GitReaderInterface $gitReader = new ShellGitReader(),
        private readonly ReportProfileInterface $profile = new AgnosticReportProfile(),
    ) {}

    public function run(): void
    {
        $newJson = @file_get_contents($this->workingDir . '/composer.lock');

        if (!$newJson) {
            $this->io->writeError('<warning>[composer-update-report] Cannot read composer.lock.</warning>');
            return;
        }

        // Baseline = the project's composer.lock at the start of the day. The
        // first run of the day records it from git HEAD; later runs reuse it so
        // the report always reflects every update made during the whole day,
        // even if composer.lock was committed in between (HEAD moving).
        //
        // It lives inside the repository's git directory (never tracked by git)
        // so it can't leak into the project tree as a stray untracked file.
        $baselineFile = $this->baselineFile();
        $hasBaseline = $baselineFile !== null && is_file($baselineFile);
        $oldJson = $hasBaseline
            ? @file_get_contents($baselineFile)
            : $this->gitReader->show($this->workingDir, 'HEAD');

        if (!$oldJson) {
            $this->io->writeError('<warning>[composer-update-report] Cannot read composer.lock from git HEAD.</warning>');
            return;
        }

        // Persist the day's baseline on the first run so subsequent runs merge
        // into a single consolidated report instead of overwriting it. Done
        // before writing the report so the baseline exists even if there are no
        // changes yet this run.
        if (!$hasBaseline && $baselineFile !== null) {
            $baselineDir = dirname($baselineFile);
            if (is_dir($baselineDir) || @mkdir($baselineDir, 0o755, true)) {
                @file_put_contents($baselineFile, $oldJson);
            }
        }

        $outputFile = $this->generate($oldJson, $newJson);

        if ($outputFile !== null) {
            $this->io->write('<info>[composer-update-report] Report ' . ($hasBaseline ? 'updated' : 'generated') . ': ' . $outputFile . '</info>');
        }
    }

    /**
     * Regenerates the report on demand from explicit git refs, bypassing the
     * day baseline entirely (so it never interferes with the automatic hook).
     *
     * @param string      $fromRef git ref for the "before" state (e.g. origin/develop)
     * @param string|null $toRef   git ref for the "after" state, or null to use
     *                             the current working composer.lock
     */
    public function runFromRefs(string $fromRef, ?string $toRef = null): void
    {
        $oldJson = $this->gitReader->show($this->workingDir, $fromRef);

        if (!$oldJson) {
            $this->io->writeError('<error>[composer-update-report] Cannot read composer.lock from git ref: ' . $fromRef . '</error>');
            return;
        }

        $newJson = $toRef !== null
            ? $this->gitReader->show($this->workingDir, $toRef)
            : @file_get_contents($this->workingDir . '/composer.lock');

        if (!$newJson) {
            $this->io->writeError('<error>[composer-update-report] Cannot read composer.lock from ' . ($toRef ?? 'working directory') . '.</error>');
            return;
        }

        $outputFile = $this->generate($oldJson, $newJson);

        if ($outputFile !== null) {
            $this->io->write('<info>[composer-update-report] Report generated: ' . $outputFile . ' (' . $fromRef . ' → ' . ($toRef ?? 'working tree') . ')</info>');
        }
    }

    /**
     * Computes the diff between two raw composer.lock JSON strings, renders it
     * with the configured profile and writes the dated report file.
     *
     * @return string|null the written report path, or null when nothing was
     *                     written (no changes, parse error or I/O failure)
     */
    private function generate(string $oldJson, string $newJson): ?string
    {
        $old = json_decode($oldJson, true);
        $new = json_decode($newJson, true);

        if (!$old || !$new) {
            $this->io->writeError('<warning>[composer-update-report] Cannot parse composer.lock JSON.</warning>');
            return null;
        }

        $diff = $this->profile->compute($old, $new);

        if (!$diff['hasChanges']) {
            $this->io->write('<info>[composer-update-report] No version changes detected.</info>');
            return null;
        }

        $outputBase = $this->outputDir
            ? $this->workingDir . '/' . trim($this->outputDir, '/')
            : $this->workingDir;

        if (!is_dir($outputBase) && !mkdir($outputBase, 0o755, true)) {
            $this->io->writeError('<error>[composer-update-report] Cannot create directory: ' . $outputBase . '</error>');
            return null;
        }

        $outputFile = $outputBase . '/composer-update-' . date('Y-m-d') . '.md';
        $content = $this->profile->render($diff);

        if (file_put_contents($outputFile, $content) === false) {
            $this->io->writeError('<error>[composer-update-report] Cannot write to ' . $outputFile . '</error>');
            return null;
        }

        return $outputFile;
    }

    /**
     * Path of the day's baseline file, kept inside the git directory so it is
     * never tracked. Returns null when the working dir is not a git repository.
     */
    private function baselineFile(): ?string
    {
        $gitDir = $this->gitReader->gitDir($this->workingDir);

        if ($gitDir === null) {
            return null;
        }

        return $gitDir . '/composer-update-report/baseline-' . date('Y-m-d') . '.json';
    }
}
