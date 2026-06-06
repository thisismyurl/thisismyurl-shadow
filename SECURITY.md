# Security Policy

Christopher Ross Shadow handles site diagnostics, admin workflows, and remediation guidance, so security issues matter a great deal.

We appreciate responsible disclosure and will treat good-faith reports seriously and respectfully.

---

## Supported Versions

Security fixes are prioritized for:

- the current public release
- the current development branch when the issue has not yet shipped

If you are reporting against an older version, please include the exact version number anyway.

---

## How to Report a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Instead, report privately using one of these routes:

1. **GitHub private security reporting** at https://github.com/thisismyurl/thisismyurl-shadow/security/advisories/new
2. Email **security@thisismyurl.com** with a note that the report is security-sensitive

Please include:

- affected plugin version
- WordPress version
- PHP version
- steps to reproduce
- proof of concept if available
- impact assessment (what an attacker could do)
- whether authentication is required

The clearer the report, the faster we can verify and respond.

---

## Response Targets

Our goals are:

- **Acknowledgement:** within 3 business days
- **Initial triage:** within 7 business days
- **Remediation plan:** as quickly as responsibly possible based on severity

These are targets, not guarantees, but we aim to communicate clearly throughout the process.

---

## Coordinated Disclosure

We support coordinated disclosure.

Please give us reasonable time to investigate and prepare a fix before publishing details publicly. In return, we will:

- acknowledge valid reports
- communicate clearly about severity and impact
- work toward a fix responsibly
- credit researchers where appropriate and desired

---

## Scope Guidance

Security issues may include, for example:

- privilege escalation
- nonce or capability bypass
- CSRF vulnerabilities
- unsafe file operations
- data exposure
- stored or reflected XSS
- SQL injection
- unsafe external request behavior
- insecure defaults that create serious user risk

Reports that are purely theoretical, require unrealistic assumptions, or depend on a heavily modified local environment may be lower priority, but they are still welcome.

---

## Our Security Principles

Christopher Ross Shadow follows these core expectations:

- **Safe by Default**
- **Privacy First**
- **Least surprise for users**
- **Clear explanation of risk and impact**
- **Defensive engineering with resilient fallbacks**

See also: [`docs/CORE_PHILOSOPHY.md`](docs/CORE_PHILOSOPHY.md)

---

## Thank You

If you take the time to report a security issue responsibly, you are helping protect real WordPress site owners.

We appreciate that.