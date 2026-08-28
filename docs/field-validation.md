# Field validation — the compatibility claims against real plugins

*Repository documentation; not shipped in the plugin zip.*

The Compatibility screen and the readme FAQ name specific third-party
plugins: consent platforms the bridge can read, cache and minification
plugins whose settings it inspects, page builders and multilingual plugins it
recognises. Until 1.0 every one of those claims was verified against a
**simulation** — a stub page implementing a vendor's documented JavaScript
API, or a seed that defined a plugin's constant by hand. That is how a
settings reader asking LiteSpeed for an option name it never wrote stayed
green for two releases: the emulator mirrored the same wrong name.

The field suite installs the **real plugins at their current wordpress.org
release** into a fresh Docker WordPress and runs the same kinds of
assertions against them. It is the answer to "is it actually true?", and,
run monthly, a rot detector for the day a vendor changes an API.

## Running it

```sh
npm run test:field:docker                 # every group, sequentially (~3 min each)
bash tests/wp/field.sh cmp-complianz      # one or more groups

# or by hand, to iterate on one group:
bash tests/wp/field-setup.sh cache-litespeed
WP_BASE_URL=http://127.0.0.1:8890 npm run test:field -- --project=cache-litespeed
bash tests/wp/teardown.sh
```

Docker only — `playwright.field.config.js` refuses to start without
`WP_BASE_URL`. A run that silently fell back to Playground with none of the
plugins installed would be a suite that cannot fail.

`FIELD_VERSIONS="complianz-gdpr=7.3.0 litespeed-cache=7.5"` pins versions to
reproduce a failure; the default, and the schedule, is always the current
release.

In CI, `.github/workflows/field-validation.yml` runs the groups as a parallel
matrix on the 3rd of every month and on demand (from `main` — GitHub
schedules and dispatches workflows from the default branch only, so the
suite is live once the release carrying it has been merged there) (Actions → Field validation →
Run workflow, optionally with a group list and version pins). It is
informational — never a required check — and on failure it opens, or
comments on, an issue labelled `field-validation`; when a later run is green
it says so on the same issue.

## How it is built

- **One fresh stack per group** (`tests/wp/field-setup.sh`): consent
  platforms compete for `Detector::detected()`'s priority order, and cache
  plugins write `advanced-cache.php`, `WP_CACHE` and `.htaccess` rules
  outside their own directory — their uninstallers are exactly the
  third-party code this suite does not trust to clean up.
- **wp-cli runs as the web user** (uid 33) so plugin installs and the cache
  plugins' writes have the permissions they would have on a real site.
- **The group table** is `tests/wp/field-groups.sh`, read by the local loop,
  the Playwright config and the CI matrix alike.
- **Every spec proves the plugin is there first.** `requireField()` asks a
  probe mu-plugin (`tests/wp/field-seed.php`) which plugins are active and
  fails if the group's are not; negative assertions are preceded by a
  positive guard (the CMP's global exists; the cache marker matched; the
  builder rendered its widget). There is no `test.skip` in `tests/Field/`.
- **The harness may contact wordpress.org; the plugin never does.**
  Invariant 9 binds the plugin. The privacy-link canary set the precedent
  for CI-only outbound requests.

## Groups and what each proves

| Group | Plugin (wp.org slug) | Proves |
|---|---|---|
| `cmp-complianz` | complianz-gdpr | Compatibility row; fail-closed with no consent; `cmplz_set_consent()` grant auto-loads, deny re-gates (through Complianz's own reload); a clicked embed survives a withdrawal; the real banner button; stored consent on return |
| `cmp-cookieyes` | cookie-law-info | Same shape. The WordPress plugin's script exposes `getCkyConsent()` / `revisitCkyConsent()` and fires `cookieyes_consent_update`, but has **no** `performBannerAction()` and never fires `cookieyes_banner_load` (those are the hosted script's) — consent goes through the real banner buttons, and a stored consent is read at load |
| `cmp-wp-consent-api` | wp-consent-api | **The trap**: with no consent type registered the real `wp_has_consent()` returns true — the bridge must not grant, nor on a synthetic change event. With a type registered (a stub CMP mu-plugin): `wp_set_consent()` allow/deny, stored consent, clicked embed survives |
| `cmp-real-cookie-banner` | real-cookie-banner | Phase 1: RCB active, **no** content blocker — `consentApi.unblock()` resolves immediately, and everything must stay gated (red on the pre-1.0 adapter, green on the fix). Phase 2: a YouTube blocker created the way RCB stores one — `unblockSync()` names it for a governed URL only, `unblock()` stays pending, nothing auto-loads. Consent through RCB's real banner is the one **follow-up**: RCB renders no banner until its setup wizard has run |
| `cache-w3-total-cache` | w3-total-cache | Cache row; optimiser "could not be read" + exclusion list; the cached page is the gated one (per-request marker equal on two anonymous GETs); a settings save flushes; Load loads with minify (auto) on |
| `cache-wp-super-cache` | wp-super-cache | Cache row; the lone "nothing to exclude" sentence; the supercache **file WPSC writes** is the gated page (marker-matched to the live response) and a settings save deletes it; click loads (see the port note below) |
| `cache-litespeed` | litespeed-cache | The **real option rows** (`litespeed.conf.optm-js_defer` / `optm-js_comb`): off → "nothing risky on", comb → "combine", 2 → "delay"; click loads with defer + combine; the delay symptom on touch (below) |
| `cache-autoptimize` | autoptimize | Reader states; click loads with aggregation, and with the inline config folded into the bundle |
| `cache-wp-fastest-cache` | wp-fastest-cache | Reader "could not be read" + Exclude tab; cached page gated; click loads with minify + combine |
| `cache-sg-cachepress` | sg-cachepress | Reader combine on/off; click loads with combine |
| `builder-elementor` | elementor | Row text follows the buffer setting; HTML widget gated with the buffer off (Elementor renders through `the_content`) and on; the **video widget** — a player that exists only as a JSON attribute — gated by `ElementorVideoRule` on both paths, Load loads, Elementor's handler stands down without an error |
| `detect-only` | cookiebot, cloudflare, polylang, translatepress-multilingual, beaver-builder-lite-version | Each row and its advice; zero third-party requests on a gated page with all five active |

### Not field-validated (emulated only)

These cannot be installed without a licence or an account, so their rows
are still produced by the emulators in `tests/wp/seed.php`: **WP Rocket,
Borlabs Cookie, WPBakery, Divi, Bricks, Oxygen, WPML, Weglot**, the
**Cookiebot banner** itself (the plugin installs; the banner needs a Domain
Group ID), **Usercentrics** and **iubenda** (detected as untested; that is
all the plugin claims for them). One interoperation path is also still
unvalidated: consent given through **Real Cookie Banner's own banner** —
RCB renders none (and denies even the administrator its admin screen)
until its setup wizard has completed, which the harness does not reproduce
yet. The API contract the bridge relies on is measured (phase 2 above).

## What the first run found (2026-08-28)

- **LiteSpeed settings were never read.** `Compatibility.php` asked for
  `litespeed.optm.js_defer`; LiteSpeed stores `litespeed.conf.optm-js_defer`.
  Fixed in the reader and the emulator; the field group drives the real rows.
- **"Delay JavaScript until interaction" — the first click does nothing —
  is a touch symptom.** LiteSpeed releases delayed scripts on the first
  mouseover / click / keydown / wheel / touch / pointer event. A mouse user
  hovers before clicking, so the hover releases `gate.js` and the click
  lands on a live button; Chromium even fires a synthetic mouseover for the
  parked cursor ~200 ms after navigation with no interaction at all. On a
  phone the tap that releases the script is the same gesture as the click,
  and the first tap is swallowed — measured: 0 embeds after the first tap, 1
  after the second. The readme and Status wording ("the first click does
  nothing") is right for touch and overstated for desktop; the touch test
  records the measurement as an annotation.
- **WP Super Cache never serves on a non-standard port.** Phase 2 writes the
  supercache file under the hostname *without* the port
  (`supercache/127.0.0.1/…`), phase 1 looks it up under `HTTP_HOST` *with*
  it (`127.0.0.1:8890`) and never finds it, so every request regenerates;
  a port-less `Host` header is a WordPress canonical 301. On 80/443 the two
  agree. The harness therefore proves WPSC's group from the file WPSC
  writes rather than from a served response. Also: a wp-cli activation
  leaves `WPCACHEHOME` undefined (WPSC's admin UI writes it into
  wp-config.php), and without it `advanced-cache.php` never loads phase 1
  at all — the setup script sets it. And enabling WPSC's own debug log
  fatals before WordPress loads (`wp_rand()` undefined) — do not.
- **The Real Cookie Banner adapter was fail-open on a plain install.**
  `consentApi.unblock(url)` resolves *immediately* when no content blocker
  matches the URL, and the free tier ships no YouTube blocker; with the
  bridge on, all three gated embeds auto-loaded with no consent (RCB 5.3.0,
  measured). RCB also exposes `unblockSync(url)` (the matched blocker, or
  `undefined`) and `consentSync({url})` (`cookie: null` when no service
  matches); the adapter now requires one of those positive signals before
  it trusts `unblock()`. Pinned in `tests/E2E/cmp-bridge.spec.js` with
  `rcb-noblocker` and `rcb-legacy` stubs, and by the field group's phase 1.
- **Elementor's video widget was gated by nobody.** The server renders
  `<div class="elementor-widget-video" data-settings='{"video_type":
  "youtube","youtube_url":…}'>` and nothing else; Elementor's front-end
  JavaScript then loads `youtube.com/iframe_api` and builds the player.
  Measured: 10 requests to youtube.com plus i.ytimg.com, DoubleClick and
  jnn-pa.googleapis.com before any click, with whole-page gating on or off,
  and no placeholder. Fixed by `src/Detection/ElementorVideoRule.php`, which
  reads the same JSON: the wrapper's contents become the panel (the owner's
  media-library overlay becomes its poster) and `data-settings` is rewritten
  so Elementor's handler bails out the way it does for every non-YouTube
  type. Vimeo and Dailymotion widgets render a real `<iframe>` the iframe
  rule already gates; lightbox mode loads nothing before a click and is left
  to the owner's design. Second lesson from the same group: the zero-embed
  fast path (`Plugin::has_gateable_markup()`) probes tag names, so the new
  rule was silently skipped on the `the_content` path while the buffered
  page — which always carries a `<script>` — passed. The widget's class name
  is part of the probe now; the buffer-off field test is the pin.
- **Elementor fetches Google Fonts on every page** (`fonts.googleapis.com`,
  `fonts.gstatic.com`) — its typography, not an embed, and outside the
  plugin's remit; the harness turns it off the way an owner would
  (`elementor_google_font = 0`, Elementor → Settings → Advanced).
- **Complianz enqueues nothing until its wizard is complete** — and, on a
  site with no tag manager and no blocked scripts, until the wizard answers
  "uses social media / third-party services" are stored. Both are plain
  options; the setup script stores them.
- **CookieYes's WordPress plugin is not CookieYes's hosted script.** See the
  table: the adapter's load-time `getCkyConsent()` path is what matters on
  WordPress, and it works; the event-driven paths the vendor documents for
  the hosted script do not exist here.

## Selector liabilities

The real-banner tests drive vendor UI by selector: Complianz `.cmplz-accept`
/ `.cmplz-deny`; CookieYes `.cky-btn-accept` and
`[data-cky-tag="reject-button"]` / `[data-cky-tag="detail-reject-button"]`.
A vendor renaming those breaks one test each; the API-driven tests stand on
their own.
