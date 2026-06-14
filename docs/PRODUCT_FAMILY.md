# Shadow Product Family

**Date:** April 5, 2026
**Version:** Current
**Status:** Active — Shadow by Christopher Ross is the only current product

---

## Current State

Only one product currently ships: **Shadow by Christopher Ross**. It is a free WordPress plugin.

---

## Shadow by Christopher Ross

**Type:** WordPress Plugin (free)
**Repository:** `thisismyurl/thisismyurl-shadow`
**Status:** Active, fully open source
**License:** Free — no artificial limitations

**What it is:** A focused WordPress diagnostic and dashboard tool.

**What it does:**
- 230 display-ready diagnostics spanning performance, security, SEO, accessibility, design, settings, database, monitoring, and more
- 101 executable treatment classes, including 93 automated and 8 guidance-only treatment entries
- Real-time admin dashboard displaying findings and site health
- File review plus local backup and recovery workflows for riskier changes
- Activity logging, KPI tracking, and WordPress Site Health integration
- Top-level runtime wrappers and WP-CLI support

**Public inventory rule:** `docs/FEATURES.md` is the documentation source of truth for counts, and only shipped / production-ready items are included in headline totals.

**What it is not:**
- Not a cloud service
- Not a SaaS platform
- Not a paid product
- Not a hosted backup service
- Not an LMS or learning platform

**Runs entirely on your WordPress server.** No external API, cloud tokens, or registration required.

---

## Naming Standards

To maintain consistency in documentation, code, and communications:

### Official Names

| Product | Type | Always Called | Avoid Calling |
|---------|------|---------------|--------------|
| **Shadow by Christopher Ross** | Free WordPress Plugin | "Shadow by Christopher Ross", "the plugin", or "Shadow by Christopher Ross" when referring to code namespaces | any unshipped product name |

### What Exists vs. What Is Planned

| Name | Status |
|------|--------|
| Shadow by Christopher Ross | Exists — free plugin |
| Shadow Cloud | Reserved name only — not built |
| Shadow Guardian | Reserved name only — not a shipped product |
| Shadow Academy | Reserved name only — not built |
| Shadow Vault | Reserved name only — not built |
| Shadow Pro | Reserved name only — not built |
| Shadow Theme | Reserved name only — not built |

### Code Namespace Standard
```php
// Current plugin namespace
namespace ThisIsMyURL\Shadow\Core;
```

The PHP namespace remains `ThisIsMyURL\Shadow\...` even though the public plugin name is written as "Shadow by Christopher Ross".

---

## Philosophy

Shadow's model is free whenever the feature runs locally on the user's own server and does not create ongoing infrastructure cost. If optional paid services are ever introduced, they should cover real service cost rather than gate the core plugin behind a paywall.

See [BUSINESS_MODEL.md](BUSINESS_MODEL.md) for the full model.
See [CORE_PHILOSOPHY.md](CORE_PHILOSOPHY.md) for guiding principles.

---

**Version:** Current
**Maintained By:** Shadow Team
**Repository:** [github.com/thisismyurl/thisismyurl-shadow](https://github.com/thisismyurl/thisismyurl-shadow)
