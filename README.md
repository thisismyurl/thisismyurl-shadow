# This Is My URL - Shadow

This Is My URL Shadow is a local-first WordPress diagnostics and remediation plugin built to help site owners understand what matters, act safely, and recover with confidence.

This repository is the source for the first public beta.

- Status: Public beta
- Requires WordPress: 6.4+
- Requires PHP: 8.1+
- License: GPL v2 or later

For the current version, see the plugin header in `thisismyurl-shadow.php` and the `Stable tag` in [`readme.txt`](readme.txt). For shipped notes, see [`CHANGELOG.md`](CHANGELOG.md).

## What Ships Today

This Is My URL Shadow currently exposes:

- 230 display-ready diagnostics across 11 categories via `Diagnostic_Registry::get_diagnostic_definitions()`.
- 101 executable treatment classes via `Treatment_Registry::get_all()`.
- 93 automated treatments and 8 guidance-only treatment entries via `Treatment_Metadata::get_counts()`.
- dashboard, findings, and WordPress Site Health integration.
- file-write review, local backup, and recovery workflows.
- activity logging, KPI tracking, and multisite-aware admin behavior.
- top-level runtime wrappers and WP-CLI commands for diagnostics, scans, treatments, and readiness export.

The plugin is built around a few non-negotiable ideas:

- advice instead of pressure
- accessibility as a product requirement
- safe-by-default workflows
- plain-English explanations
- no required cloud dependency for core functionality

## Beta Scope

This beta is focused on the core plugin experience.

Included in the current beta:

- local diagnostics and findings management
- remediation workflows with apply, undo, review, and rollback guidance
- backup and restore safeguards for riskier operations
- WordPress Site Health and dashboard reporting
- WP-CLI coverage for common diagnostic and treatment workflows
- accessibility-first admin copy and lower-stress recovery paths

Not part of the current beta:

- required registration
- paid tiers
- cloud-only features
- telemetry by default

## Quick Start

### Site Owners

1. Install and activate the plugin.
2. Open the This Is My URL Shadow dashboard.
3. Review findings by category.
4. Apply safe fixes where appropriate.
5. Use file review or backup workflows before higher-risk changes.

### Contributors

1. Clone the repository.
2. Install Composer dependencies.
3. Read the philosophy and feature inventory before changing behavior or copy.
4. Run the available tests before opening a pull request.

```bash
git clone https://github.com/thisismyurl/thisismyurl-shadow.git
cd thisismyurl-shadow
composer install
composer test:smoke
composer test:phpunit
```

If your environment needs an explicit PHP binary for PHPUnit:

```bash
php8.3 ./vendor/bin/phpunit --configuration phpunit.xml.dist
```

### WP-CLI

When WP-CLI is available, This Is My URL Shadow registers commands for:

- `wp thisismyurl-shadow diagnostics list`
- `wp thisismyurl-shadow diagnostics run <diagnostic>`
- `wp thisismyurl-shadow scan run`
- `wp thisismyurl-shadow treatments list`
- `wp thisismyurl-shadow treatments apply <finding>`
- `wp thisismyurl-shadow readiness export`

## Documentation Map

Start with these documents when evaluating or contributing:

- [docs/CORE_PHILOSOPHY.md](docs/CORE_PHILOSOPHY.md)
- [docs/FEATURES.md](docs/FEATURES.md)
- [docs/INDEX.md](docs/INDEX.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [SUPPORT.md](SUPPORT.md)
- [SECURITY.md](SECURITY.md)

## Source Of Truth

Public documentation should treat these as the authoritative count sources:

- the live inventory returned by `Diagnostic_Registry::get_diagnostic_definitions()`
- the treatment counts returned by `Treatment_Metadata::get_counts()`
- [docs/FEATURES.md](docs/FEATURES.md)

Planning notes, archived reports, and placeholder code should not be used for headline totals.

## Accessibility And Privacy

This Is My URL Shadow is built for people who use keyboards, screen readers, zoom, reduced motion, simpler language, and lower-stress workflows. The docs should help a busy site owner understand what a finding means, what happens next, and how to recover if something goes wrong.

This Is My URL Shadow runs locally. The current beta does not require an account, does not require cloud infrastructure, and should not make unexpected third-party requests.

See [docs/ACCESSIBILITY.md](docs/ACCESSIBILITY.md), [PRIVACY.md](PRIVACY.md), and [docs/BUSINESS_MODEL.md](docs/BUSINESS_MODEL.md).

## Contributors

- **Christopher Ross** ([@thisismyurl](https://github.com/thisismyurl)) — author and maintainer
- **Contributors:** Thanks to everyone who's reported issues, tested edge cases, and contributed code

## License

GPL v2 or later.


---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*

