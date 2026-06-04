<?php

namespace Spiriit\ComposerUpdateReport;

use Composer\IO\IOInterface;

class Generator
{
    public function __construct(
        private readonly string $workingDir,
        private readonly IOInterface $io,
        private readonly ?string $outputDir = null,
        private readonly GitReaderInterface $gitReader = new ShellGitReader(),
    ) {}

    public function run(): void
    {
        $newJson = @file_get_contents($this->workingDir . '/composer.lock');

        if (!$newJson) {
            $this->io->writeError('<warning>[composer-update-report] Cannot read composer.lock.</warning>');
            return;
        }

        $outputBase = $this->outputDir
            ? $this->workingDir . '/' . trim($this->outputDir, '/')
            : $this->workingDir;

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

        $old = json_decode($oldJson, true);
        $new = json_decode($newJson, true);

        if (!$old || !$new) {
            $this->io->writeError('<warning>[composer-update-report] Cannot parse composer.lock JSON.</warning>');
            return;
        }

        $diff = (new DiffComputer())->compute($old, $new);

        if (!$diff['hasChanges']) {
            $this->io->write('<info>[composer-update-report] No version changes detected.</info>');
            return;
        }

        if (!is_dir($outputBase) && !mkdir($outputBase, 0o755, true)) {
            $this->io->writeError('<error>[composer-update-report] Cannot create directory: ' . $outputBase . '</error>');
            return;
        }

        // Persist the day's baseline on the first run so subsequent runs merge
        // into a single consolidated report instead of overwriting it.
        if (!$hasBaseline && $baselineFile !== null) {
            $baselineDir = dirname($baselineFile);
            if (is_dir($baselineDir) || @mkdir($baselineDir, 0o755, true)) {
                @file_put_contents($baselineFile, $oldJson);
            }
        }

        $outputFile = $outputBase . '/composer-update-' . date('Y-m-d') . '.md';
        $content = (new MarkdownRenderer())->render($diff);

        if (file_put_contents($outputFile, $content) === false) {
            $this->io->writeError('<error>[composer-update-report] Cannot write to ' . $outputFile . '</error>');
            return;
        }

        $this->io->write('<info>[composer-update-report] Report ' . ($hasBaseline ? 'updated' : 'generated') . ': ' . $outputFile . '</info>');
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
