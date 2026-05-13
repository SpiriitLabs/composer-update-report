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
}
