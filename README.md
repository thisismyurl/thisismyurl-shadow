# Shadow

[![CI](https://github.com/thisismyurl/thisismyurl-shadow/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-shadow/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

Shadow is a local-first WordPress diagnostics and remediation plugin.

It runs on your server, reports what it finds, and where it can, fixes it. No account, no external service, no data leaving the site.

## What ships today

Shadow groups its work into a few areas:

- Diagnostics across categories like performance, security, configuration, and content health. Each one reports a finding you can read and act on.
- Treatments that act on findings: most run automatically, the rest give you step-by-step guidance for the ones a plugin shouldn't touch on its own.
- Dashboard and findings views, plus WordPress Site Health integration so the results show up where you already look.
- File-write review, local backup, and recovery workflows for anything that changes files on disk.
- Activity logging and KPI tracking, with admin behavior that understands multisite.
- Top-level runtime wrappers and WP-CLI commands for running Shadow without the dashboard.

For current diagnostic and treatment counts, see [docs/FEATURES.md](docs/FEATURES.md) — those numbers change as the registry grows and any figure hardcoded here goes stale within a release.

## Beta scope

Shadow is in beta. Here's the honest line between what's solid and what isn't.

**In scope and working:** diagnostics, the treatments that ship enabled, the dashboard and findings views, Site Health integration, local backup and recovery, logging, and the WP-CLI surface.

**Not yet:** some treatments are guidance-only on purpose, because automating them on a live site is riskier than the problem they solve. Those are labeled in the findings view. The remediation set will keep growing, and the API around treatment classes may still shift between beta releases.

If you're running Shadow on a production site, take a backup first and read the finding before you apply a treatment.

## Non-negotiable ideas

A few principles built into the design:

- **Local-first.** Shadow does its work on your server. It does not phone home, and it does not need an account to function.
- **Read before you write.** Every treatment that changes a file goes through write review and is backed up locally first, so you can undo it.
- **The site owner stays in control.** Anything Shadow can't safely automate becomes guidance, not a silent change.

## Quick start

### Site owners

1. Install and activate the plugin.
2. Open the Shadow dashboard.
3. Run a scan, read the findings, and apply treatments one at a time.

Take a backup before applying treatments on a production site.

### Contributors

1. Clone the repo.
2. `composer install`
3. `composer test:smoke` for a fast pass, or `composer test:phpunit` for the full suite.

If your environment needs an explicit PHP binary for PHPUnit:

```bash
php8.3 ./vendor/bin/phpunit --configuration phpunit.xml.dist
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the longer version, including coding standards and how the diagnostic and treatment registries are structured.

### WP-CLI

```
wp thisismyurl-shadow diagnostics list
wp thisismyurl-shadow diagnostics run <diagnostic>
wp thisismyurl-shadow scan run
wp thisismyurl-shadow treatments list
wp thisismyurl-shadow treatments apply <finding>
wp thisismyurl-shadow readiness export
```

## Documentation map

- [docs/CORE_PHILOSOPHY.md](docs/CORE_PHILOSOPHY.md) — why Shadow works the way it does
- [docs/FEATURES.md](docs/FEATURES.md) — capabilities and current counts
- [docs/INDEX.md](docs/INDEX.md) — the full documentation index
- [CONTRIBUTING.md](CONTRIBUTING.md) — how to work on the plugin
- [SUPPORT.md](SUPPORT.md) — where to get help
- [SECURITY.md](SECURITY.md) — how to report a vulnerability

## Source of truth

When you need exact numbers, read them from the code, not from notes or this README. The authoritative sources are:

- `Diagnostic_Registry::get_diagnostic_definitions()` for diagnostics
- `Treatment_Metadata::get_counts()` for treatments

Planning documents drift. These methods don't.

## Accessibility and privacy

Shadow is local-first. No account is required, and it makes no unexpected third-party requests. What runs on your site stays on your site.

See [docs/ACCESSIBILITY.md](docs/ACCESSIBILITY.md), [PRIVACY.md](PRIVACY.md), and [docs/BUSINESS_MODEL.md](docs/BUSINESS_MODEL.md).

## Versioning

Versions follow `X.Yjjj.hhmm` — year, Julian day, 24-hour time of the build.

## About

Shadow is built and maintained by [Christopher Ross](https://thisismyurl.com/). I've been building on WordPress since 2007 and I build tools like this because the same problems keep showing up on real sites — and a small, focused plugin is usually the right fix. No tracking, no ads.

**WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/) · **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl) · **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## Support and sponsorship

Shadow is free and stays free. If it saved you time and you want to give something back, sponsorship helps me keep maintaining it: [GitHub Sponsors](https://github.com/sponsors/thisismyurl), Bitcoin, Dogecoin, PayPal, or Interac e-transfer.

Found a bug? Open an issue on the Issues tab. Have a question? The Discussions tab is the place for it.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---
*This project follows the [10 Core Pillars](PILLARS.md).*
