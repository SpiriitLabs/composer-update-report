<?php

namespace Spiriit\ComposerUpdateReport\Profile;

use Spiriit\ComposerUpdateReport\DiffComputer;
use Spiriit\ComposerUpdateReport\MarkdownRenderer;

/**
 * Default profile: groups updates by Composer vendor and renders a report
 * meaningful for any project without configuration.
 */
final class AgnosticReportProfile implements ReportProfileInterface
{
    public function compute(array $old, array $new): array
    {
        return (new DiffComputer())->compute($old, $new);
    }

    public function render(array $diff): string
    {
        return (new MarkdownRenderer())->render($diff);
    }
}
