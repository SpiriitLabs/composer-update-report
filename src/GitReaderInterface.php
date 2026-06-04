<?php

namespace Spiriit\ComposerUpdateReport;

interface GitReaderInterface
{
    public function show(string $workingDir, string $ref): ?string;

    /**
     * Absolute path to the repository's git directory (handles worktrees and
     * submodules where .git is a file), or null when not in a git repository.
     */
    public function gitDir(string $workingDir): ?string;
}
