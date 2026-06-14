# Christopher Ross - Shadow

[![CI](https://github.com/thisismyurl/thisismyurl-shadow/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-shadow/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

Shadow by Christopher Ross is a local-first WordPress diagnostics and remediation plugin built to help site owners understand what matters, act safely, and recover with confidence. This repository is the source for the first public beta.

For the current version, see the plugin header in `thisismyurl-shadow.php` and the `Stable tag` in [`readme.txt`](readme.txt). For shipped notes, see [`CHANGELOG.md`](CHANGELOG.md).

## What ships today

Shadow by Christopher Ross currently exposes:

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

## Beta scope

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

## Quick start

### Site owners

1. Install and activate the plugin.
2. Open the Shadow by Christopher Ross dashboard.
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

When WP-CLI is available, Shadow by Christopher Ross registers commands for:

- `wp thisismyurl-shadow diagnostics list`
- `wp thisismyurl-shadow diagnostics run <diagnostic>`
- `wp thisismyurl-shadow scan run`
- `wp thisismyurl-shadow treatments list`
- `wp thisismyurl-shadow treatments apply <finding>`
- `wp thisismyurl-shadow readiness export`

## Documentation map

Start with these documents when evaluating or contributing:

- [docs/CORE_PHILOSOPHY.md](docs/CORE_PHILOSOPHY.md)
- [docs/FEATURES.md](docs/FEATURES.md)
- [docs/INDEX.md](docs/INDEX.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [SUPPORT.md](SUPPORT.md)
- [SECURITY.md](SECURITY.md)

## Source of truth

Public documentation should treat these as the authoritative count sources:

- the live inventory returned by `Diagnostic_Registry::get_diagnostic_definitions()`
- the treatment counts returned by `Treatment_Metadata::get_counts()`
- [docs/FEATURES.md](docs/FEATURES.md)

Planning notes, archived reports, and placeholder code should not be used for headline totals.

## Accessibility and privacy

Shadow by Christopher Ross is built for people who use keyboards, screen readers, zoom, reduced motion, simpler language, and lower-stress workflows. The docs should help a busy site owner understand what a finding means, what happens next, and how to recover if something goes wrong.

Shadow by Christopher Ross runs locally. The current beta does not require an account, does not require cloud infrastructure, and should not make unexpected third-party requests.

See [docs/ACCESSIBILITY.md](docs/ACCESSIBILITY.md), [PRIVACY.md](PRIVACY.md), and [docs/BUSINESS_MODEL.md](docs/BUSINESS_MODEL.md).

---

## Support and donations

I build these tools because WordPress sites in the wild keep hitting the same problems, and a small, focused plugin is usually the right fix. They're free to use, with no tracking and no ads.

If one of them saves you time, here are the genuine ways to help:

- **Sponsor the work.** [GitHub Sponsors](https://github.com/sponsors/thisismyurl) is the simplest way, and the Sponsor button at the top of this repo lists it alongside Bitcoin, Dogecoin, PayPal, and Interac e-transfer. Any amount helps, and none of it is expected.
- **Contribute code or ideas.** A pull request, a bug report, or a tested edge case is worth as much as a donation. See [CONTRIBUTING.md](CONTRIBUTING.md) to get started.
- **Share it.** A note on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps other people find work that might save them the same afternoon.

### Report issues and questions

- **Found a bug or want a feature?** Open an issue on the [Issues](../../issues) tab. Include your WordPress and PHP versions and the steps to reproduce it.
- **Have a question?** Start a thread on the [Discussions](../../discussions) tab.

### Contributing code

Code contributions are welcome. The short version:

1. Fork the repository and clone your fork.
2. Create a branch with a clear name, like `feature/short-descriptive-name`.
3. Make your change and test it against the edge cases.
4. Run the coding-standards check before you open the pull request.
5. Open a pull request that explains what changed and why.

The full workflow and standards live in [CONTRIBUTING.md](CONTRIBUTING.md). Contributing is never required, but it is always appreciated.

## About Christopher Ross

This plugin is built and maintained by [Christopher Ross](https://thisismyurl.com/), the WordPress development and technical SEO practice of Christopher Ross. I help teams build WordPress sites that stay secure, fast, and maintainable, and I write small, focused plugins like this one for the problems those sites keep running into.

### My background

- On the web since 1996, and in WordPress since 2007
- WordPress.org plugin developer with 19 plugins published since 2009
- Technical SEO practitioner focused on performance, security, and search visibility
- Lead instructor and curriculum architect at the M.L. Campbell Training Center, the Sherwin-Williams® international training facility for its industrial wood division

### Ways to connect

- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## Contributors

- **Christopher Ross** ([@thisismyurl](https://github.com/thisismyurl)) — author and maintainer
- Thanks to everyone who has reported issues, tested edge cases, and contributed code

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*
