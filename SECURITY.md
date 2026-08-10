# Security Policy

## Supported versions

The latest release is the supported release. Fixes ship as a new release,
never as patches to old versions.

## What counts as a vulnerability here

Beyond the usual classes (XSS, privilege escalation, CSRF in the admin), the
product claim itself is a security property. Please report:

- **Any way to make a page contact a third party before the click** — a
  gating bypass through markup the scanner mishandles, a provider descriptor
  that leaks a request, a `preconnect`/prefetch that survives gating. The
  zero-requests E2E test is the executable form of this claim; a way to
  defeat it in the field is a vulnerability even if the test stays green.
- **Escaping flaws in the placeholder** — anything that lets attacker-influenced
  embed markup (attributes, URLs, provider labels) break out of the
  `data-cg-payload` attribute or the panel HTML.
- **Privilege widening on activation** — a rebuilt iframe or script carrying
  more capability than the original (a dropped `sandbox`, a surviving
  `autoplay`, an attribute outside the safelist).
- **Storage before consent** — any path that writes cookies, localStorage or
  sessionStorage before the visitor's first click.

## Reporting

Please report vulnerabilities privately via
[GitHub private vulnerability reporting](https://github.com/Calucon/WP-Embed/security/advisories/new)
rather than a public issue.

Coordinated disclosure: please allow a fix to be released before publishing
details. Reports are handled on a best-effort basis — this is a free plugin
maintained without a security team behind it. There is no bug bounty.

## Dependencies

The plugin has **no third-party runtime dependencies**: no Composer packages
outside development, no bundled JavaScript libraries, no CDN assets, and it
makes no outbound requests of its own. The attack surface is the plugin's own
code.
