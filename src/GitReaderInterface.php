<?php

namespace Spiriit\ComposerUpdateReport;

interface GitReaderInterface
{
    public function show(string $workingDir, string $ref): ?string;
}
