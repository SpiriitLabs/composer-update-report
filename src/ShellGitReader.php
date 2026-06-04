<?php

namespace Spiriit\ComposerUpdateReport;

class ShellGitReader implements GitReaderInterface
{
    public function show(string $workingDir, string $ref): ?string
    {
        $result = shell_exec(sprintf(
            'git -C %s show %s:composer.lock 2>/dev/null',
            escapeshellarg($workingDir),
            escapeshellarg($ref),
        ));

        return $result ?: null;
    }

    public function gitDir(string $workingDir): ?string
    {
        $result = shell_exec(sprintf(
            'git -C %s rev-parse --absolute-git-dir 2>/dev/null',
            escapeshellarg($workingDir),
        ));

        $dir = is_string($result) ? trim($result) : '';

        return $dir !== '' ? $dir : null;
    }
}
