<?php

namespace Spiriit\ComposerUpdateReport\Profile;

/**
 * Drupal-specific profile: keeps the original report layout with dedicated
 * sections for Drupal Core, contrib modules, Symfony components and other
 * underlying libraries.
 */
final class DrupalReportProfile implements ReportProfileInterface
{
    public function compute(array $old, array $new): array
    {
        return (new DrupalDiffComputer())->compute($old, $new);
    }

    public function render(array $diff): string
    {
        return (new DrupalMarkdownRenderer())->render($diff);
    }
}
