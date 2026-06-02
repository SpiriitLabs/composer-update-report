# composer-update-report

A Composer plugin that automatically generates a Markdown report after each `composer update`, by comparing the current `composer.lock` with the version recorded in Git.

## Requirements

- PHP >= 8.1
- Composer >= 2.0
- The project must be versioned with **Git** (the plugin reads `composer.lock` from `HEAD`)

## Installation

```bash
composer require --dev spiriitlabs/composer-update-report
```

> The plugin activates automatically upon installation — no additional configuration is required.

## How it works

On each `composer update`, the plugin:

1. Reads `composer.lock` from `HEAD` (state before the update)
2. Compares it with the current `composer.lock` (state after the update)
3. Categorises the changes
4. Generates a `composer-update-YYYY-MM-DD.md` file at the project root

If no version changes are detected, no file is created.

### Same-day updates are merged

If you run several `composer update` during the same day, the reports are **merged** into a single `composer-update-YYYY-MM-DD.md` rather than overwritten or appended.

The first run of the day records the starting state of `composer.lock` (from Git `HEAD`) into a hidden baseline file (`.composer-update-YYYY-MM-DD.base.json`). Every subsequent run recomputes the diff against that baseline, so the report always reflects **all the updates of the day** as one consolidated summary — even if `composer.lock` was committed between two updates. The baseline file is per-day and can be safely ignored/deleted (it is git-ignored by default).

## Report contents

The report is structured into sections:

| Section                   | Contents                                                          |
|---------------------------|-------------------------------------------------------------------|
| **Drupal Core**           | Updates to `drupal/core*` packages                                |
| **Drupal Contrib Modules**| Updates to `drupal/*` packages                                    |
| **Vendor Libraries**      | Symfony components (grouped by version) and other libraries       |
| **New packages**          | Packages absent from the previous `composer.lock`                 |
| **Removed packages**      | Packages present in the previous `composer.lock` but now removed  |

### Example generated report

```markdown
# Update summary — 30/04/2026

Based on the `composer.lock` diff, here is a summary of all updated packages.

### 🚀 Major and minor updates

#### 🔵 Drupal Core

* `drupal/core` : `10.2.5` ➝ `10.3.0`

#### 🧩 Drupal Contrib Modules

* `drupal/pathauto` : `1.11.0` ➝ `1.12.0`

#### 📦 Vendor libraries

* **Symfony components** — updated from `6.4.6` to `6.4.8`:

    * `symfony/console`, `symfony/http-kernel`, `symfony/routing`

* **Other libraries**:

    * `league/csv` : `9.14.0` ➝ `9.15.0`

### ✅ New packages

* `drupal/gin` : `3.0.0`

### ❌ Removed packages

* `drupal/obsolete_module` : `1.0.0`
```

## Configuration

By default the report is generated at the project root. You can change the output directory by adding the following to your `composer.json`:

```json
{
    "extra": {
        "composer-update-report": {
            "output-dir": "reports/composer"
        }
    }
}
```

The directory is created automatically if it does not exist. The path is relative to the project root.

## Notes

- The report includes both `require` and `require-dev` packages.
- Symfony components sharing the same before/after version are grouped into a single line for readability.
- The generated file is not committed automatically; it is intended to be copied into a ticket, a PR, or a tracking tool.
- If `composer.lock` does not yet exist in `HEAD` (first commit, empty repository), the plugin prints a warning and generates nothing.

## License

MIT — see [LICENSE](LICENSE)
