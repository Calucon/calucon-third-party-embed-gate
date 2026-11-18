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
| `cmp-real-cookie-banner` | real-cookie-banner | Phase 1: RCB active, **no** content blocker — `consentApi.unblock()` resolves immediately, and everything must stay gated (red on the pre-1.0 adapter, green on the fix); the visitor answers the modal banner first, then the placeholder click works. Phase 2: RCB's own default content (its settings page creates the groups; the spec visits it once), a YouTube service in a Marketing group and a blocker stored the way RCB stores them — `unblockSync()` names the blocker for a governed URL only, `unblock()` waits; **the real banner**: "Continue without consent" leaves everything gated, "Accept all" auto-loads the two governed embeds while the unknown-host widget stays gated, and the stored consent loads them on the next visit |
| `cache-w3-total-cache` | w3-total-cache | Cache row; optimiser "could not be read" + exclusion list; the cached page is the gated one (per-request marker equal on two anonymous GETs); a settings save flushes; Load loads with minify (auto) on |
| `cache-wp-super-cache` | wp-super-cache | Cache row; the lone "nothing to exclude" sentence; the supercache **file WPSC writes** is the gated page (marker-matched to the live response) and a settings save deletes it; click loads (see the port note below) |
| `cache-litespeed` | litespeed-cache | The **real option rows** (`litespeed.conf.optm-js_defer` / `optm-js_comb`): off → "nothing risky on", comb → "combine", 2 → "delay"; click loads with defer + combine; the delay symptom on touch (below) |
| `cache-autoptimize` | autoptimize | Reader states; click loads with aggregation, and with the inline config folded into the bundle |
| `cache-wp-fastest-cache` | wp-fastest-cache | Reader "could not be read" + Exclude tab; cached page gated; a settings save flushes; click loads with minify + combine |
| `cache-sg-cachepress` | sg-cachepress | Reader combine on/off; click loads with combine |
| `builder-elementor` | elementor | Row text follows the buffer setting; HTML widget gated with the buffer off (Elementor renders through `the_content`) and on; the **video widget** — a player that exists only as a JSON attribute — gated by `ElementorVideoRule` on both paths, Load loads, Elementor's handler stands down without an error; the **atomic YouTube element** (Editor V4) the same, plus its panel not clipped on a phone and the loaded player filling Elementor's box; the atomic Google Maps element; a section's YouTube and a container's Vimeo **background video** — nothing requested, no panel, content intact |
| `builder-elementor-addons` | elementor, essential-addons-for-elementor-lite, premium-addons-for-elementor | Essential Addons' Sticky Video (Plyr's data attributes): a panel, the element kept, one click loads, also with an image overlay; Premium Addons' Video Box without an image, with autoplay, with the provider's thumbnail, with the owner's image (which becomes the poster) and for Vimeo: the whole box is the panel, the player has a usable size; Elementor's own Vimeo widget with an image overlay takes one click |
| `builder-beaver` | beaver-builder-lite-version | Row backgrounds from YouTube, Vimeo and an "embedded code": nothing requested, no panel behind the row, neither the background element nor the enqueued player API left, no script error; the video module gated and loading |
| `builder-siteorigin` | siteorigin-panels, so-widgets-bundle | The video widget with oEmbed off (a provider's page as a `<video>` source) for YouTube and Vimeo, and WordPress's own `[video]` shortcode: a panel, nothing requested, the loaded player sized to its container on a phone |
| `builder-kadence` | kadence-blocks | Row Layout with a YouTube and a Vimeo background: nothing requested, no panel left behind the row |
| `detect-only` | cookiebot, cloudflare, polylang, translatepress-multilingual, beaver-builder-lite-version | Each row and its advice; zero third-party requests on a gated page with all five active |

### Not field-validated (emulated only)

These cannot be installed without a licence or an account, so their rows
on the Compatibility screen are still produced by the emulators in
`tests/wp/seed.php`: **WP Rocket, Borlabs Cookie, WPBakery, Divi, Bricks,
Oxygen, WPML, Weglot**, the
**Cookiebot banner** itself (the plugin installs; the banner needs a Domain
Group ID), **Usercentrics** and **iubenda** (detected as untested; that is
all the plugin claims for them).

### Commercial builders: checked against the vendor's own page (1.1)

Enfold, Avada, WPBakery, Flatsome and Bricks cannot be installed here, and
an emulator written from memory is exactly what the first field run taught
this project to distrust. There is a third kind of evidence between "ran
the real plugin" and "wrote a simulation": **the vendor's demo site**. Its
pages are the product's real output, and its scripts and stylesheets are
the product's real front end. `tests/bin/vendor-page-gate.php` runs a saved
demo page through the pipeline; `tests/bin/vendor-page-run.js` serves the
result as that page in a headless browser, lets the vendor's own assets
load from the vendor, and records what else is contacted before a click.

Measured 2026-11-18, as saved and through the gate:

| Vendor page | As it is | Through the gate |
|---|---|---|
| Enfold, video element (two videos, "immediate" load) | youtube.com ×2, player.vimeo.com | none; two panels, buttons reachable by mouse, the loaded player fills Enfold's box |
| Enfold, slideshow with video slides | player.vimeo.com ×3 | none |
| Avada, Vimeo element (lazy-loaded frame) | player.vimeo.com ×2 | none; one panel |
| Avada, YouTube element with Avada's Privacy Consent on | none | untouched, none — no second placeholder on top of Avada's |

What that proves and what it does not:

- It proves the rule against the markup and the scripts the vendor ships
  **on that page, that day, with the options that demo uses**. It says
  nothing about an option the demo does not show, about the builder's
  editor, or about anything the product does server-side for a logged-in
  user. It is one run by hand, not a monthly job: the pages are the
  vendors', they are never committed, and a vendor may change them.
- Two rules have **no** live page behind them: WPBakery's row background
  (`data-vc-video-bg`) and Flatsome's banner background
  (`.ux-youtube[data-videoid]`). Neither appeared on a demo page that
  could be fetched; both are written from the vendors' published
  front-end scripts, which name the attribute, the class and what they do
  with them. Treat a field report against either as likely to be right.
- Divi needed no rule: its video modules render a real iframe (the iframe
  rule's job) and its section backgrounds are video files. Its map module,
  like Flatsome's and Enfold's, loads the Google Maps script from the page
  footer — outside the content filters; whole-page gating reads it, the
  default does not, and none of the three is handled by a dedicated rule.
- Bricks' lazy frame (`<iframe data-src>`) was already gated; its
  background video was not found on a live page and has no rule.

The readme says the same in one sentence. Do not let "works with Enfold"
grow out of this: what is claimed is the video element, the slide and
section frames, and nothing else.

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
- **Real Cookie Banner, the second time: its pre-load stub is not an
  answer.** RCB inlines a stub `consentApi` before its banner script loads:
  `unblockSync()` answers `undefined` (exactly like "no blocker"),
  `consentSync()` reports no cookie, and `unblock()`/`consent()` are queued
  until the real API replaces the stub and announces itself with a
  `consentApi` event on `window`. The adapter's fail-closed check, asked
  once at page load, decided "nothing governs this URL" on the stub and
  never asked again — "Accept all" then loaded nothing. It now waits for
  the real API (recognised by `wrapFn()`, which the stub lacks) and listens
  for the announcement; the `rcb-late` E2E stub replays RCB's own snippet
  and is red on the previous adapter. Three more things the banner run
  taught: RCB shows no banner to a `HeadlessChrome` user agent and installs
  the stub in its place (the spec runs under a normal Chrome UA); its
  buttons are `<a>` elements without `href` in a shadow root with generated
  class names, so they are driven by visible text; and a service group is
  invisible to the banner unless it carries RCB's `order` term meta
  (`CookieGroup::getOrdered()` selects on it) — a group without it can never
  be consented to.
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
- **The same defect, twice more, in the same plugin — and the group stayed
  green for three months.** The video-widget tests above pin the *classic*
  widget. Elementor 4 ships a second generation ("atomic" elements, Editor
  V4), on by default for a site whose first Elementor was 4.0 or later, and
  its YouTube element is a different element with a different script:
  `<div data-e-type="e-youtube" data-settings="…source…">`, empty, filled
  by `youtube-handler.js`. Measured 2026-11-17 on Elementor 4.4.1: 19
  third-party requests before any click, no placeholder, buffer on or off.
  A day later, looking at what else Elementor builds in the browser: a
  section's or container's YouTube/Vimeo **background video** is a
  `background_video_link` setting and an empty div — 30 to 39 requests. A
  competitor's changelog pointed at the first; nothing pointed at the
  second. `ElementorVideoRule` now reads the atomic element (the panel is
  its content; `source` is emptied, not removed, because the handler calls
  `source.match()`), and `BackgroundVideoRule` empties the background link
  — no panel, because nothing behind a section's content can be clicked.
  The lesson is about the suite, not the rule: **a field group covers the
  shapes someone seeded, not "the plugin".** When a builder ships a new
  generation of widgets, the old tests keep passing against the old ones.
  Three habits follow. Seed by *what the builder can embed*, not by the
  widget that was reported. Port the builder's own URL test rather than
  writing a tidier one — Elementor loads YouTube's API for anything on
  youtube.com, video id or not, so "is this a video URL" is the wrong
  question; "will their script contact the provider" is the right one. And
  give every such test a positive guard (the atomic element must be in the
  page) so a renamed element fails loudly instead of passing on nothing.
- **Then the same question was asked of every free builder at once**
  (2026-11-18, eleven plugins, one fresh WordPress each, every element that
  can show third-party content seeded and measured). It found the pattern
  again, five times over, and one case in WordPress itself:
  - a `<video>` whose source is a YouTube or Vimeo **page** — MediaElement,
    core's own player, builds the provider's player for it. SiteOrigin's
    video widget with oEmbed off, and the `[video]` shortcode with such an
    address (20–25 requests);
  - Plyr's `data-plyr-provider` embeds — Essential Addons' Sticky Video
    (21–26);
  - Premium Addons' Video Box, an iframe built from `data-src` on page
    load, or the provider's thumbnail as its automatic image (16–35);
  - Beaver Builder's row background (28–31), half of which is an ordinary
    enqueued script tag for the provider's player API — in the footer,
    outside every content filter, which is why `Integration\PlayerApiScripts`
    exists;
  - Visual Composer's row background (39), whose bundle injects the API on
    evaluation unless an element with its script's id already exists.
  And three kinds of panel that were *there* and no use: behind a row's
  content (Kadence, Beaver's embedded code, Brizy), under an image overlay
  that took the first click (Elementor with Vimeo, Essential Addons), and
  — the oldest — a loaded player 150 pixels tall in Elementor's 16:9 box,
  because nothing had ever measured the player after the click. Every one
  is fixed and has a test; the measurement's own table, with what it could
  not reach, is the commit message of the fix.
  What it also found and what is **not** fixed, because it is a different
  problem: maps drawn by a plugin's script with Google's Maps JavaScript API
  or Leaflet (Kadence in JavaScript mode, Otter, Stackable with a key,
  SiteOrigin, Premium Addons), captchas, web fonts, Lottie files, and
  players a builder loads on the visitor's own click on its lightbox or
  popup. The readme's FAQ says so. Brizy's YouTube element still makes two
  requests for the player API although its panel works; the stand-down
  that was measured is a decoy script element, and that is not something
  to ship without asking.
- **WP Fastest Cache was promised a flush it never got.** The Compatibility
  row says "flushed automatically" for every detected cache, and
  `CacheFlush` had calls for six of the seven. The group had no flush test,
  so nothing noticed. It has one now (`wpfc_clear_all_cache()`, verified
  against 1.5.5).
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
`[data-cky-tag="reject-button"]` / `[data-cky-tag="detail-reject-button"]`;
Real Cookie Banner by the visible texts "Accept all" and "Continue without
consent" (its classes are generated per build).
A vendor renaming those breaks one test each; the API-driven tests stand on
their own.
