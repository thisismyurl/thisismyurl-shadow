# Christopher Ross Shadow Feature Inventory

Last updated: April 5, 2026
Status: Public beta source of truth

This document is the canonical source for public feature counts and shipped scope.

## Live Count Snapshot

The current codebase exposes:

- 230 display-ready diagnostics from `Diagnostic_Registry::get_diagnostic_definitions()`.
- 101 executable treatment classes from `Treatment_Registry::get_all()`.
- 93 automated treatment entries and 8 guidance-only entries from `Treatment_Metadata::get_counts()`.

Use these numbers in public-facing documentation unless the underlying registry or metadata changes.

## Count Definitions

Use these terms consistently:

| Term | Meaning | Count in public totals? |
| --- | --- | --- |
| Shipped | Visible and usable in the current beta release | Yes |
| Automated treatment | A treatment that can perform an apply action in plugin workflows | Yes |
| Guidance-only treatment | A treatment that returns manual steps instead of making changes | Yes, but separate from automated totals |
| Experimental | Present in code but not part of the current public release story | No |
| Placeholder | Scaffold or incomplete implementation | No |

## Source Of Truth

When counts need to appear in public docs, use:

1. `Diagnostic_Registry::get_diagnostic_definitions()` for diagnostics
2. `Treatment_Metadata::get_counts()` for treatment maturity totals
3. this file

Do not pull headline counts from raw file totals, branch-local experiments, or planning notes.

## Shipped Product Areas

### Diagnostics

Christopher Ross Shadow scans a WordPress installation and groups findings across 11 categories. The current registry-backed counts are:

| Category | Count |
| --- | ---: |
| Accessibility | 12 |
| Code Quality | 7 |
| Database | 12 |
| Design | 17 |
| Monitoring | 10 |
| Performance | 63 |
| Security | 46 |
| SEO | 28 |
| Settings | 28 |
| WordPress Health | 2 |
| Workflows | 5 |
| Total | 230 |

Diagnostics are intended to explain what a problem means, not just report that it exists.

### Treatments And Remediation

The treatment system supports three practical modes:

- automated apply and undo for suitable changes
- guarded workflows for higher-risk changes
- guidance-only responses where automation would be irresponsible

The current treatment inventory is:

- 101 executable treatment classes
- 93 automated treatment entries
- 8 guidance-only treatment entries

Public docs should be clear that not every finding is meant to be fixed automatically.

### File Review

Christopher Ross Shadow includes file-write review workflows for operations that should not bypass user inspection. This is part of the plugin's safety model, especially for higher-risk remediation.

### Backup And Recovery

The plugin includes local backup and restore workflows to help users recover safely before or after significant changes.

### Dashboard And Findings

The dashboard surfaces findings, progress, and status information in one place, with bridges into native WordPress health surfaces where appropriate.

### Activity And KPI Tracking

Christopher Ross Shadow records activity and impact metrics so users can see what changed and why it mattered.

### Runtime And CLI Surfaces

The plugin also exposes top-level runtime wrappers and WP-CLI commands for:

- listing diagnostics
- running one diagnostic or a full scan
- listing and applying treatments
- exporting readiness inventory

## What The Beta Is Not

The current public beta should not be described as:

- a cloud-required service
- a paid platform
- a marketing suite
- a telemetry-first product
- an all-purpose site builder

## Public Messaging Rules

When writing public copy:

- lead with local-first diagnostics and safe remediation
- mention accessibility and plain-English guidance
- be honest that this is a beta
- do not imply required registration or payment
- keep future services clearly hypothetical unless they ship
