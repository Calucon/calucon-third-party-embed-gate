<img src=".wordpress-org/icon.svg" alt="" width="96" align="right">

# Consent Gate — a two-click embed plugin for WordPress

Hold third-party embeds until the visitor asks for them, so nothing is
contacted and nothing is stored before a click. No cookie banner, no
subscription, no consent platform.

**Status: M1–M6 implemented** (M5 without the CMP bridges, M6 without local
thumbnails — see below). The core claim — zero third-party requests before
interaction — is enforced by an end-to-end test that is never skipped.

## What it does

- Replaces third-party iframes and embed SDK scripts with a **server-rendered
  placeholder** — a named group with a real button and a working fallback
  link, so it works with JavaScript disabled.
- Survives **minified HTML** (attribute quotes stripped, newlines inside
  tags) — the single most common reason competing implementations silently
  fail in the field.
- Gates **by host, not by allowlist**: an unknown third-party iframe is gated
  by default.
- After the click, loads from **privacy-preserving endpoints** where they
  exist: `youtube-nocookie.com` (measured 0 cookies instead of 5), Vimeo with
  `dnt=1`.
- Rebuilds embeds from an **attribute safelist**: `sandbox` preserved
  exactly, `autoplay` never survives, `style`/`srcdoc`/`on*` never copied.
- Strips `preconnect`/`dns-prefetch` hints to gated providers; strips embeds
  from feeds and excerpts instead of gating them.
- Optional, **off by default**: consent memory in the visitor's browser
  (nothing is ever written before the first click), with a withdrawal
  control via `[consent_gate_withdraw]`.
- **Never phones home.** No telemetry, no CDN assets, no outbound request on
  any path.

## Development

```sh
composer install && composer test    # unit + fixture suite, no WordPress needed
npm install && npm run test:e2e      # Playwright: the zero-request test, a11y, layout

npm run test:wp                      # the same claims on a REAL WordPress —
                                     # WordPress Playground (no Docker needed)
npm run test:wp:docker               # or against a docker compose WordPress stack
```

The WordPress integration suite (`tests/WP/`) runs against a real install —
real hooks, real theme, real enqueue pipeline, feeds, REST and wp-admin —
seeded identically on both backends by `tests/wp/seed.php`. It has already
paid for itself: it caught that modern WordPress reserves embed height on
the iframe itself (not the legacy `::before` spacer), which made gated
panels collapse invisible on current block themes until the CSS was fixed.

CI (`.github/workflows/ci.yml`) runs the coding-standards report and the
unit suite on PHP 7.4 and 8.4 for every pull request. Every merge to `main`
publishes a GitHub release with the installable plugin zip
(`.github/workflows/release.yml`); the same zip can be built locally with
`bash bin/build-zip.sh`.

`CLAUDE.md` carries the working rules and traps; `PLAN.md` is the founding
document with the full rationale, the invariants (§1), and seventeen edge
cases. Fixtures live in `tests/Fixtures/<case>/{input,expected}.html`; every
pass-through case is asserted byte-identical.

## Deliberately not (yet) included

- **CMP bridges** (Complianz, Borlabs, …): shipping an untested bridge is
  worse than none — without one the plugin simply keeps gating, which is the
  fail-closed behaviour PLAN.md §6.4 requires.
- **Local thumbnails**: off-by-default by design with a licensing caveat
  (PLAN.md §5.4); needs real-filesystem testing before it ships.
- **Compliance claims**: the plugin is a technical measure. It prevents the
  requests; it cannot know your site's processing purposes. Your privacy
  policy still has to name your providers (PLAN.md §14).

## Where this comes from

The implementation generalises a working pattern on a live WordPress site,
where it gates 40 embeds across 22 pages with zero third-party requests
before interaction. Every measurement quoted in `PLAN.md` is real, taken from
that site:

- `www.youtube.com/embed/…` sets **5 cookies** on a plain GET with no
  playback and no scripts run — two of them ~18-month identifiers.
- `www.youtube-nocookie.com/embed/…` sets **none**.
- A plain WordPress-to-WordPress oEmbed preview set a cookie with a
  **one-year** lifetime.

## Licence

GPLv2 or later, to match WordPress and to keep the WordPress.org route open.
