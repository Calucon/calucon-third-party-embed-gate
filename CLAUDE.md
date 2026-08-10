# CLAUDE.md — Consent Gate

## What this plugin is

Consent Gate is a free GPL WordPress plugin that replaces third-party embeds
(YouTube, Vimeo, Maps, …) with a server-rendered placeholder until the visitor
clicks to load them — the two-click pattern. **The entire product is that
nothing third-party loads before a click.** It is a technical measure, not a
consent management platform, and it never claims compliance.

The full design rationale lives in `PLAN.md`; this file is the traps and the
rules. Milestone status: M1 (core gate), M2 (providers + script strategy)
and M3 (configuration) are implemented; see PLAN.md §13.

## Invariants (PLAN.md §1) — if a change would break one of these, stop and ask

- [ ] 1. **Nothing third-party is contacted before the click.** Not a script, not an iframe, not a font, not a thumbnail, not a `preconnect`. This is the entire product. A feature that violates it is not a feature.
- [ ] 2. **The placeholder is rendered server-side.** A visitor with JavaScript disabled must still get a real, working link to the content — never a button that does nothing.
- [ ] 3. **Nothing is stored before consent.** Including by the plugin itself. `localStorage` is terminal-equipment storage; writing a "we showed the placeholder" flag would recreate the problem the plugin exists to remove.
- [ ] 4. **Never gate in an editing context.** The block editor, the REST block renderer, and the customizer must see the original markup. Gating there breaks editing and looks like data loss.
- [ ] 5. **The parser must tolerate minified HTML.** Attribute quotes stripped, newlines inside tags, attributes in any order. See §3.2 — this is the single most common reason competing implementations fail in the field.
- [ ] 6. **Gate on host, not on a provider allowlist.** An unknown third-party iframe is gated by default. The failure mode must be "gated something harmless" (visible, reportable) and never "let a new tracker through" (invisible).
- [ ] 7. **Never widen the privilege of what you rebuild.** If the original iframe carried `sandbox`, the replacement carries the same `sandbox`. Copy attributes from a safelist, never a loop over everything.
- [ ] 8. **No autoplay on activation.** The button says "Load". Audio starting unbidden is a WCAG 1.4.2 failure and is not what was asked for.
- [ ] 9. **The plugin never phones home.** No telemetry, no version check against a private endpoint, no remote font, no CDN asset. A privacy plugin that makes outbound requests is a contradiction, and WordPress.org will reject it.
- [ ] 10. **Never claim compliance.** The plugin is a technical measure. It cannot know the site's processing purposes. See PLAN.md §14.

## Architecture

| Path | Role |
|---|---|
| `consent-gate.php` | Plugin header + bootstrap only |
| `src/Plugin.php` | Wiring; no logic |
| `src/Detection/HtmlScanner.php` | Attribute-tolerant tag reader (§3.2) |
| `src/Detection/HostMatcher.php` | "is this ours?" (§3.4) |
| `src/Detection/IframeRule.php` | Gates cross-origin iframes; consumes the WP blockquote pair (§9.7) |
| `src/Providers/{Registry,Provider}.php` | Descriptors are data, not classes (§4.1) |
| `src/Rendering/PlaceholderRenderer.php` | The §5.1 markup contract — public API, version it |
| `src/Integration/{RenderBlock,TheContent}.php` | The only place WordPress hooks live |
| `assets/js/gate.js` | Dependency-free ES5; no build step |
| `templates/`, `Admin/`, `Cmp/`, … | Later milestones (PLAN.md §13) |

**Hard rule:** `Detection/`, `Providers/` and `Rendering/` are WordPress-free —
plain strings and arrays in, plain strings and arrays out. WordPress filters
and `__()` reach them only as injected callables (see `Plugin.php`). That is
what makes the fixture tests run in milliseconds without booting WordPress.
Never use `DOMDocument` on the render path (PLAN.md §3.1).

## The minified-HTML trap (PLAN.md §3.2)

Markup in the field routinely looks like this — quotes stripped, a newline
immediately after the tag name:

```html
<div
class=wp-block-embed__wrapper> <iframe
loading=lazy title="Kolkja Cycling" width=422 height=750 src="https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed" frameborder=0></iframe> </div>
```

A pattern like `/<iframe[^>]+src="([^"]+)"/` matches nothing here and **fails
silently** — the embed loads and the site owner believes they are protected.
A pattern that assumes quoted attributes will pass code review and fail in
production: the source site's first audit missed seven iframes on five pages
exactly this way. Any change to `HtmlScanner` must keep the minified fixtures
green, and any new fixture must include a minified variant.

## Commands

```sh
composer install               # dev deps (PHPUnit)
composer test                  # unit + fixture suite (no WordPress needed)
vendor/bin/phpunit --filter youtube-minified   # one fixture by name
php tests/bin/generate-fixtures.php            # write missing expected.html — review before committing
npm install                    # Playwright
npm run test:e2e               # E2E; in the remote/CI image:
PW_CHROMIUM_PATH=/opt/pw-browsers/chromium npm run test:e2e
```

Fixture layout: `tests/Fixtures/<case>/input.html` + `expected.html`.
Pass-through cases copy input to expected by hand and are asserted
**byte-identical** — that is what catches a scanner that "works" but reformats.

## How to add a provider

Providers are descriptor arrays (shape: PLAN.md §4.1) registered via the
`consent_gate_providers` filter, matched by `iframe_host` + `iframe_path`,
with `{id}`-style captures interpolated (URL-encoded) into `load_path`,
`fallback` and `thumbnail`. **A new provider without fixtures is incomplete**:
add at least one pretty-printed fixture and one minified variant, plus the
E2E page entry. Prefer a privacy-preserving `load_host` where one exists
(`youtube-nocookie.com`, Vimeo `dnt=1`).

## Accessibility contract (PLAN.md §8)

| Requirement | Criterion |
|---|---|
| Panel named via `role="group"` + `aria-label`; **no fake heading** | 1.3.1 |
| Real `<button type="button">`, not a div | 2.1.1, 4.1.2 |
| Visible focus indicator, 3:1 against panel background | 2.4.7, 1.4.11 |
| Button hit area ≥ 24×24 CSS px | 2.5.8 |
| Text/link contrast ≥ 4.5:1 against the panel's own background | 1.4.3 |
| Link affordance not by colour alone | 1.4.1 |
| Focus moves to the **container** after activation, never lost to `<body>` | 2.4.3 |
| No autoplay; no `autoplay` in the iframe `allow` list | 1.4.2 |
| Panel contents scroll rather than clip | 1.4.10 |
| Fallback link works with JavaScript off | invariant 2 |

The panel is named by `role="group"` + `aria-label`, **never a heading**: a
bold paragraph is a fake heading (1.3.1), and a real `<h3>` is wrong because
the correct level depends on where the embed sits — panels follow an `h1` on
single posts and an `h2` on archives, so any fixed level skips somewhere.

## Do not

- Do not rewrite post content in the database — gate at render time only.
- Do not add an outbound request, on any path, for any reason.
- Do not use `DOMDocument` on the render path.
- Do not autoplay, or let `autoplay` survive into the rebuilt `allow` list.
- Do not store anything (cookies, localStorage, sessionStorage) before the click.
- Do not claim compliance ("GDPR compliant", "DSGVO-konform", …) in any user-facing string.
- Do not emit a raw `<iframe` substring inside placeholder output (re-processing safety, §9.1).
- Do not treat `loading="lazy"` as consent — deferred is still without consent (§9.8).

## Legal reasoning

Lives in PLAN.md §0 (measurements), §6.2 (consent memory) and §14 (framing);
README copy summarises it. **Legal copy changes need a human** — never reword
notes, README legal text, or provider disclosures on your own initiative.

## Testing expectations

Every behavioural change ships with a fixture. Generated `expected.html`
files must be reviewed, not trusted. The zero-third-party-request E2E test
(`tests/E2E/zero-requests.spec.js`) is the product claim in executable form:
it is **never skipped and never marked flaky**. If it is red, the product is
broken, not the test.
