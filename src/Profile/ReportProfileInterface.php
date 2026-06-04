<?php

namespace Spiriit\ComposerUpdateReport\Profile;

/**
 * A report profile pairs a diff strategy with a Markdown renderer, so the
 * report layout can be tailored to a technology (e.g. Drupal) while the
 * Generator stays agnostic about how the diff is bucketed and rendered.
 */
interface ReportProfileInterface
{
    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array<string, mixed> the diff, including a `hasChanges` flag
     */
    public function compute(array $old, array $new): array;

    /** @param array<string, mixed> $diff */
    public function render(array $diff): string;
}
