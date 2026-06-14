# Shadow Accessibility & Inclusion Commitment

**Last Updated:** April 3, 2026  
**Status:** Active and ongoing  
**Applies To:** Product design, engineering, documentation, support, and community interactions

---

## Why This Matters

Shadow by Christopher Ross is meant to help people feel safer and more confident running WordPress.

That goal is incomplete if the plugin is harder to use for people with disabilities, people using assistive technology, people under stress, or people who simply need clearer language and more predictable interfaces.

Accessibility is not decoration. It is not a bonus feature. It is part of whether the product is genuinely helpful.

---

## Our Standard

We strive to build Shadow by Christopher Ross so it works well for people who use:

- screen readers
- keyboards instead of a mouse
- zoomed or magnified interfaces
- high contrast or lower-vision workflows
- reduced motion settings
- voice control tools
- captions, transcripts, or clearer written explanations
- plain-language content because jargon creates friction

We also aim to support people who are:

- neurodivergent
- fatigued, stressed, or overwhelmed
- new to WordPress
- working on mobile devices or constrained connections

---

## Product Commitments

### 1. Accessibility Is a Release Requirement
A feature is not “done” if it excludes people.

### 2. Plain English Is a Feature
We explain what a finding means, why it matters, and what a safe next step looks like without unnecessary jargon.

### 3. Safe, Predictable Interaction
Risky or important actions should be clearly labeled, reversible when possible, and supported by understandable feedback.

### 4. Keyboard and Screen-Reader Respect
We aim for meaningful labels, usable focus order, visible focus states, and controls that make sense without relying on sight or a mouse.

### 5. Cognitive Load Matters
We try to reduce ambiguity, surprise, clutter, and fear-based messaging.

### 6. Inclusion Goes Beyond Compliance
Meeting a checklist is not the ceiling. The real goal is to help more people succeed with less frustration.

---

## Development Strategy

Accessibility and inclusion should show up at every stage:

### Planning
When deciding whether to build something, we ask:
- Will this help real site owners?
- Will this create confusion or unnecessary stress?
- Could this exclude people using assistive technology or different interaction styles?

### Design
We prefer:
- clear hierarchy
- understandable labels
- readable spacing and contrast
- less cognitive overload
- predictable flows and language

### Engineering
Contributors should consider:
- keyboard behavior
- screen-reader clarity
- visible focus indication
- reduced-motion friendliness
- error state clarity
- safe defaults and clear confirmation

### Documentation
Good documentation should:
- explain the why, not just the click path
- avoid unexplained acronyms and jargon
- separate current reality from future plans
- help people who are learning at different speeds and in different ways

---

## What We Mean by Inclusive Functionality

For Shadow by Christopher Ross, inclusive functionality includes things like:

- diagnostics written in human terms instead of fear-based technical shorthand
- clear finding descriptions for non-developers
- settings pages that can be navigated with a keyboard
- controls and states that are understandable with assistive technology
- documentation that is structured, scannable, and respectful of different learning needs
- workflows that preserve user confidence instead of creating panic

---

## Accessibility Feedback Is Welcome

If you hit an accessibility barrier, we want to know.

Examples include:
- missing or unclear labels
- keyboard traps or hard-to-reach controls
- poor contrast or unreadable UI
- unclear instructions or overly technical wording
- motion or animation concerns
- confusing error messaging

Please report accessibility issues through the support or issue route that best fits the situation.

See:
- [`../SUPPORT.md`](../SUPPORT.md)
- [`../CONTRIBUTING.md`](../CONTRIBUTING.md)

---

## Honesty Note

We do **not** claim perfection.

We do claim that accessibility and disability inclusion are real priorities, and that barriers should be treated as important product problems to fix — not as edge-case inconveniences.

---

## Manual Verification Log

The admin-screen accessibility fixes in 1.6165 were implemented and reviewed at
the **code level** by an accessibility specialist (WCAG 2.2 AA). The items below
require a **live assistive-technology pass** to be certified — automated and code
review cannot substitute for them. Until a row is signed off with a date and
tester, treat it as **unverified**.

> Status legend: ⬜ PENDING (not yet run) · ✅ PASS · ❌ FAIL (with fix note)

| # | Check | Surface | Tool / AT | Status | Date | Tester | Notes |
|---|-------|---------|-----------|--------|------|--------|-------|
| 1 | Visible keyboard-focus indicator on tabs and the resolution card header | Settings, Resolution | NVDA + Firefox, keyboard-only | ⬜ PENDING | | | |
| 2 | Live-region announcements fire per action (assertive Run Fix, polite elsewhere) | Dashboard, Settings, File-write | NVDA (browse + forms) | ⬜ PENDING | | | |
| 3 | Focus restored to the affected card after a Resolution-Centre reload | Resolution | NVDA + Firefox, keyboard | ⬜ PENDING | | | |
| 4 | Focus restored to `#wps-detail-heading` after a Guardian treatment reload | Dashboard detail | NVDA + Firefox | ⬜ PENDING | | | |
| 5 | Admin-notice dismiss moves focus off the vanishing control | Any admin notice | NVDA, keyboard-only | ⬜ PENDING | | | |
| 6 | Attention table announces its caption; confirm no missing `<th>` columns | Dashboard | NVDA table nav | ⬜ PENDING | | | |
| 7 | `aria-current="page"` announces on the active tab; inactive tabs silent | Settings | VoiceOver + Safari | ⬜ PENDING | | | |
| 8 | Gauges, status colours, and glyphs legible in Windows High Contrast Mode | Dashboard | `forced-colors: active` | ⬜ PENDING | | | |
| 9 | `wps-res-feedback-msg` polite region announces once (no double-read) | Resolution | NVDA | ⬜ PENDING | | | |
| 10 | Error messages announce via the live region (old `alert()` is gone) | Resolution | NVDA, keyboard-only | ⬜ PENDING | | | |
| 11 | Automated audit: no critical/serious violations | All 4 admin screens | axe DevTools + Lighthouse | ⬜ PENDING | | | |

When every row is ✅ with a date, add a one-line summary at the top of this
section, e.g. *"Certified against NVDA 2025.x + Firefox, VoiceOver + Safari, and
keyboard-only on YYYY-MM-DD."*

---

## Related Documents

- [`CORE_PHILOSOPHY.md`](CORE_PHILOSOPHY.md)
- [`NEXT_STEPS.md`](NEXT_STEPS.md)
- [`../SUPPORT.md`](../SUPPORT.md)
- [`../CONTRIBUTING.md`](../CONTRIBUTING.md)
