# Customizing Calucon Third-Party Embed Gate

A reference for developers and AI agents customizing this plugin on a site —
from `functions.php`, a small plugin, or WP-CLI. Everything here works
without touching plugin files (never edit those; updates overwrite them).

## The contract your customization must keep

Calucon Third-Party Embed Gate's entire product is: **nothing third-party loads before the
visitor clicks**. Not a script, not an iframe, not a thumbnail, not a
`preconnect`. When customizing:

- **Don't** load a preview image, poster, or favicon from the provider in a
  custom template or placeholder filter. If you want thumbnails, they must
  be served from this site. The supported way: every embed block has a
  **Set poster image** control (Calucon Third-Party Embed Gate panel in the block inspector)
  that takes a media-library image; the server refuses any poster URL that
  does not resolve to this site's own host, so a CDN-offloaded media
  library needs its CDN host declared under **Detection → Own hosts**.
- **Don't** add `autoplay` (a WCAG failure, and not what the visitor asked
  for), and don't widen `allow`/`sandbox` beyond what the original embed had.
- **Don't** write cookies/localStorage before the click "to remember
  something" — pre-consent storage is the problem this plugin removes.
- **Don't** put compliance claims ("GDPR compliant") into custom notes or
  button texts. The plugin is a technical measure; it makes no legal claims.
- **Don't** emit a literal `<iframe` inside placeholder markup — gated
  content may be re-processed, and raw iframes would be re-detected.
- Keep the placeholder's server-rendered fallback link working: a visitor
  with JavaScript disabled must still get a real link to the content.

The plugin fails closed: an unknown third-party iframe is gated by the
generic rule even if your descriptor is wrong. A broken customization shows
a generic panel — it never silently lets a tracker through.

## Verifying your changes

After any customization, verify from the shell (both commands are
read-only and make no outbound request):

```sh
wp calucon-embed-gate scan --format=json     # every embed found in recent
                                       # content + whether it is gated
wp calucon-embed-gate providers --format=json # providers as the gate resolves
                                        # them (builtins + your filter)
```

`scan` also reports a `provider` column: the resolved provider id, or
`generic`/`generic-script` for a host no descriptor claims.

In `scan` output, every third-party row should read `status: gated`.
Other statuses mean "loads without consent, because a setting says so":
`rule-disabled` (detection rule off), `provider-disabled` (owner disabled
it), `own-host` (treated as this site). `no-usable-url` passes through
because there is nothing to gate.

Browser-level check: open the page in devtools, Network tab, filter out
your own domain — before any click the list must be empty.

## Settings

One option, `calucon_embed_gate_options`, sanitized against a schema on every
read (malformed input falls back to safe defaults — you cannot corrupt it):

```sh
wp option get calucon_embed_gate_options --format=json
wp option patch update calucon_embed_gate_options detection images 1
wp option patch update calucon_embed_gate_options appearance preset card
wp option patch update calucon_embed_gate_options consent memory session
```

Shape (see `src/Support/Options.php` for the authoritative schema):

- `custom_providers[]`: `id` (`custom-<slug>`, generated once), `label`, `hosts`,
  `script_hosts`, `kind` — the Providers tab's *Your own providers* rows
- `providers.{id}`: `enabled` (bool; ignored for `custom-*` ids, which are
  always gated), `privacy_variant` (bool), `note`, `action` (strings),
  `privacy_url` (https only; overrides the built-in policy link)
- `display`: `privacy_link` (bool, default false — the provider's
  privacy-policy link inside every panel)
- `detection`: `iframes`, `scripts`, `images` (bool; images off by
  default), `own_hosts`, `never_gate`, `always_gate` (host lists, `*.`
  wildcards allowed), `www_equivalence`, `output_buffer` (bool)
- `appearance`: `preset` (`default|minimal|card`), `corners`
  (`''|square|rounded|pill|custom`) + `radius` (0–48 px, with `custom`),
  `border_width` (`''` or `'0'`–`'10'`), `shadow` (`''|none|soft|strong`),
  `density` (`''|compact|spacious`), `button_size` (`''|small|large`),
  `button_style` (`''|outline`), `button_width` (`''|full`), `hover`
  (`''|strong|none`), `play_icon` (bool; kind-aware glyph), `note_size`
  (`''|small`), `align` (`''|center`), `poster_panel` (`''|center|bar`),
  `poster_dim` (`''|light|strong`), `withdraw_style` (`''|outline|link`),
  `dark` (bool) + `dark_bg`/`dark_fg`/`dark_accent`/`dark_accent_fg`; the
  colours `bg`/`fg`/`accent`/`accent_fg`/`border_color`/`link` and the
  dark ones each take `''` (inherit the theme), a `#hex`, or
  `preset:<slug>` (follow that theme-palette colour by name) — see
  `Options::defaults()` for the authoritative list
- `consent`: `memory` (`off|session|persistent`), `scope`
  (`embed|provider|all`), `duration_days` (1–730)
- `cmp`: `bridge` (bool, off by default), `borlabs_group` (slug, default
  `external-media`), `tcf` (bool, experimental)

Cache plugins are flushed automatically when this option changes.

## Adding a provider

**No code needed for the common case.** Settings → Calucon Third-Party
Embed Gate → Providers → *Your own providers*: a name, the embed hosts (one
per line; pasted URLs are reduced to their host), optional script hosts,
and a kind for the button icon. After saving, the provider appears in the
table above with the same note, button-text and privacy-policy-link
controls as the built-ins. Adding one can never change *what* is gated: unknown hosts are gated
either way (a row only adds the name, icon and texts), hosts a built-in
provider handles are refused at save time (with a notice) and ignored at
run time, and owner-defined providers are always gated — there is no Gate
checkbox for them; exempting a host is the never-gate list's explicit job.
At most 100 rows of 50 hosts. With the consent-platform bridge on, your
own providers follow the same category consent as every other embed; the
experimental TCF bridge only recognises providers with a vendor id, so
they stay gated under it (fail closed). They never rewrite the load URL; for
`load_host`/`load_path`, path captures, companion classes or hint scrubbing,
register a descriptor in code:

Providers are **descriptor arrays**, not classes. Register via the
`calucon_embed_gate_providers` filter. Order of assembly: built-ins → this
filter → the owner's own providers (with every host a registered provider
handles stripped) → the per-provider settings from the Providers table, so
a site owner's note, button text, privacy URL or on/off choice applies to
code-registered providers too:

```php
add_filter( 'calucon_embed_gate_providers', function ( array $providers ): array {
	$providers[] = array(
		'id'          => 'example-videos',
		'label'       => 'ExampleVideos',
		'match'       => array(
			'iframe_host' => array( 'embed.example-videos.com', 'example-videos.com' ),
			// Optional: named captures interpolated (URL-encoded) into
			// load_path / fallback as {id}-style tokens.
			'iframe_path' => '#^/embed/(?<id>[A-Za-z0-9_-]+)#',
			// Optional: captures from the query string (Dailymotion keeps the
			// id in ?video=). Never decides the match; a template whose
			// placeholder finds no capture is dropped, never shipped literally.
			// One pattern, or a LIST of them (one per parameter) when the
			// provider writes them in any order: 'iframe_query' => array(
			// '/(?:^|&)u=(?<u>[a-z0-9_.-]+)/i', '/(?:^|&)d=(?<d>…)/i' ).
			'iframe_query' => '/(?:^|&)video=(?<id>[A-Za-z0-9]+)/',
			// Script-strategy providers: 'script_host' (+ optional 'script_path'
			// with captures, e.g. Crowdsignal's /p/{id}.js) and 'companion_class'.
			// A script, inline loader or stylesheet from these hosts that sits
			// next to a panel of the same provider becomes a SILENT companion:
			// gated without a panel, loaded by gate.js after that panel's click.
			// "Next to" is literal — same block, no blank line between them —
			// so a second embed from the same provider keeps its own panel.
		),
		// Optional privacy-preserving load target used after the click.
		'load_host'   => 'embed-nocookie.example-videos.com',
		'load_path'   => '/embed/{id}',
		// The no-JS fallback link (and the error-state link).
		'fallback'    => 'https://example-videos.com/watch/{id}',
		'controller'  => 'Example Videos Inc., City, Country',
		'privacy_url' => 'https://example-videos.com/privacy', // Linked in the panel (Providers tab toggle) and shown by the CLI.
		'kind'        => 'video', // video | map | audio | social | form | calendar | document | image | 3d | '' — picks the optional button glyph.
		// Reserved: 'custom' (bool) marks owner-defined providers from the
		// settings screen — never set it on a code-registered descriptor.
		'aspect'      => '16/9',
	);
	return $providers;
} );
```

Inline scripts are gated only when they name a known provider's script host,
and then only if they either inject a loader (Scribd, Crowdsignal surveys) or
sit next to an already-gated panel of that provider (Wolfram's `embed()`
call). A script of your own that merely mentions a provider URL keeps running
untouched, and an inline script never gets a panel of its own. The script body
is carried in the payload and re-run after consent, with `document.write`
shimmed to append so a loader that uses it cannot replace the page. Stylesheets in content are gated only as companions of
a gated provider (Wolfram Cloud); a theme's own third-party stylesheets are
outside an embed gate's scope (the Compatibility screen reports them).

All keys and defaults: `src/Providers/Provider.php` (`normalize()`); the 36
built-in descriptors in `src/Providers/Builtin/Descriptors.php` are working
examples of every pattern, including script-strategy embeds
(`match.script_host`, `script_path`, `companion_class`,
`companion_fallback`), query-string captures (`match.iframe_query`) and
sibling CDN hosts for resource-hint scrubbing (`scrub_hint_hosts`).

Notes:

- Without a descriptor, embeds from unknown hosts are still gated
  generically — a descriptor adds a proper label, a privacy-preserving
  load target, a working fallback link, and the provider row in settings.
- `load_query` merges query parameters into the original URL at load time
  (e.g. Vimeo's `dnt=1`); `autoplay` never survives, by design.
- Match hosts explicitly, including subdomains (`youtube.com` does not
  imply `www.youtube.com` in `match`).
- Then verify: `wp calucon-embed-gate providers` must list it;
  `wp calucon-embed-gate scan` must show its embeds `gated` with your label.

The withdrawal control renders as
`<span class="cg-withdraw-block"><button class="cg-withdraw" …></button><span
class="cg-withdraw__status">…</span></span>`. The wrapper is block-level so a
block theme's constrained layout can place it — auto margins do nothing to a
bare inline-level button, which then hugs the page edge. Style the button
with `.cg-withdraw`; the wrapper only carries layout.

## After a script embed loads

A loader script (X, Instagram, Reddit, TikTok, …) normally scans the page
once, when it first runs. Injected after a click it may need a nudge to draw
an embed that was not there at parse time. `gate.js` ships hooks for the
providers where that is known to be needed (`strava`, `twitter`, `instagram`,
`facebook`) and lets a site add its own — before or after the script loads:

```js
window.caluconEmbedGateReadyHooks = window.caluconEmbedGateReadyHooks || {};
window.caluconEmbedGateReadyHooks.tiktok = function () {
	if ( window.tiktokEmbed && window.tiktokEmbed.lib ) {
		window.tiktokEmbed.lib.render();
	}
};
```

The key is the provider id (`wp calucon-embed-gate providers` lists them).
A hook registered here wins over the built-in one for that provider.

Where a provider publishes **both** an iframe embed code and a loader script,
prefer the iframe: it renders on its own, whereas some loaders only draw
while the document is still parsing and come up empty when injected later —
which happens with or without this plugin.

## Testing successive builds

Assets are enqueued with the plugin version, so `gate.css?ver=1.2.3` only
changes when the version does — correct for a live site, awkward on a test
site where several builds of one version replace each other and the browser
keeps the copy it already has. Clearing a page cache or CDN will not help:
the stale file is in the browser.

Set any of `SCRIPT_DEBUG`, `WP_DEBUG`, or `WP_ENVIRONMENT_TYPE` to something
other than `production` in `wp-config.php` on that site, and the plugin
appends each file's modification time to its version instead, so every build
gets fresh URLs.

If the site is genuinely production and you would rather not say otherwise —
a live site you also test on — set the plugin's own flag instead:

```php
define( 'CALUCON_EMBED_GATE_DEV_ASSETS', true );
```

It wins over everything above, in both directions: `false` keeps stable URLs
on a site that is otherwise flagged for development.

For full control — a multi-server site wanting a build hash, which is stable
across machines in a way a timestamp is not — filter the result:

```php
add_filter( 'calucon_embed_gate_asset_version', function ( $version, $file ) {
	return $version . '.' . MY_BUILD_HASH;   // $file is e.g. 'assets/js/gate.js'
}, 10, 2 );
```

## Adjusting behaviour with filters

```php
// Change the note/button text in context (return plain text; it is escaped).
add_filter( 'calucon_embed_gate_note_text', fn( $note, $provider, $ctx ) =>
	'youtube' === $provider['id'] ? 'Video hosted by YouTube. Loads on click.' : $note, 10, 3 );

// Exempt a host entirely. WARNING: its requests then happen without
// consent on every page view — this is the owner's decision to defend.
add_filter( 'calucon_embed_gate_should_gate', fn( $gate, $url, $ctx ) =>
	false !== strpos( $url, 'widgets.already-covered.example' ) ? false : $gate, 10, 3 );
```

All hooks: `calucon_embed_gate_asset_version`, `calucon_embed_gate_providers`, `calucon_embed_gate_provider_for_url`,
`calucon_embed_gate_should_gate`, `calucon_embed_gate_is_own_host`,
`calucon_embed_gate_own_hosts`, `calucon_embed_gate_placeholder_html`,
`calucon_embed_gate_payload`, `calucon_embed_gate_note_text`,
`calucon_embed_gate_action_text`, `calucon_embed_gate_fallback_url`,
`calucon_embed_gate_www_equivalence`, `calucon_embed_gate_cmp_config`,
`calucon_embed_gate_the_content_priority`, `calucon_embed_gate_render_block_priority`; actions
`calucon_embed_gate_before_render`, `calucon_embed_gate_embed_gated`, `calucon_embed_gate_flush_caches`.

## The consent platform bridge

With `cmp.bridge` enabled and a **tested** platform installed (WP Consent
API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, Real Cookie
Banner), the front end loads `assets/js/cmp-bridge.js`, which reads the
platform's documented public JS API: an affirmative grant for the embeds'
category auto-activates gated embeds, a withdrawal re-gates what the
bridge (not a visitor's own click) activated. Everything is client-side
and read-only — the server always renders the gated placeholder (cache
safety), the bridge writes nothing to the platform, and any missing or
silent platform means gating stands.

`calucon_embed_gate_cmp_config` filters the config handed to the script (or
disables the bridge by returning `null`):

```php
// This site's CMP files embeds under a custom category.
add_filter( 'calucon_embed_gate_cmp_config', function ( $config ) {
	if ( is_array( $config ) ) {
		$config['category'] = 'external-media';
	}
	return $config;
} );

// Add a TCF Global Vendor List id for a custom provider (tcf flag on).
add_filter( 'calucon_embed_gate_cmp_config', function ( $config ) {
	if ( is_array( $config ) && isset( $config['tcf'] ) ) {
		$config['tcf']['vendors']['example-videos'] = 123;
	}
	return $config;
} );
```

If you prefer the platform's own content blocker for a specific provider,
disable that provider under **Providers** — Calucon Third-Party Embed Gate then passes its
embeds through and the platform's blocker is the only gate. Do not run
both gates plus the bridge for the same provider expecting them to stack;
one authority per embed is the design.

Google Consent Mode v2 is deliberately not a bridge source: it has no
public read API, it is written by consent platforms for Google tags, and
no consent-mode signal governs iframes. Bridge the platform instead — it
is where Consent Mode's state comes from. The plugin also never sends
`gtag('consent', …)` updates; a click on one embed is not a site-wide
marketing consent.

## Bilingual and multilingual sites

Two kinds of text live in this plugin, and they are translated in two
different places.

**The plugin's own strings** — every built-in provider's notice and button
label, the whole settings screen, the editor controls — are gettext strings.
German ships with the plugin (`de_DE` and `de_DE_formal`); any other language
comes from translate.wordpress.org. Nothing to configure: WordPress serves
each request in that request's language.

The practical consequence is worth stating plainly: **on a bilingual site,
leave the built-in wording alone and you get both languages for free.** The
moment you type your own notice or button text for a provider, that one string
is yours in every language until you translate it — you have traded a
translated string for a fixed one.

**The texts you type** — a per-provider notice, button label or
privacy-policy URL, and the names of your own providers — are stored in the
plugin's option, so they are content, not code. They are registered for
translation in the shipped `wpml-config.xml`, and both WPML and Polylang read
that file:

| Text | Where you translate it |
|---|---|
| Per-provider notice / button label | WPML → String Translation, or Polylang → Translations → Strings |
| Per-provider privacy-policy URL | same — a URL is just a string, so `…/privacy` and `…/privacy?hl=de` can differ per language |
| Your own providers' names | same |
| Per-block button and notice text | translated with the post, in the translated post's block |

The plugin re-reads those texts while the page is being built, so the string
your multilingual plugin returns for *that* page's language is the one the
panel shows. Only the texts are re-read: which providers are enabled, the host
lists and every detection rule come from the values loaded at startup, so a
translation layer can reword a panel and can never ungate an embed.

With WPML or Polylang active, Status & tools → Compatibility names it and
repeats where those strings are translated, so nobody has to remember this
page.

With WPML or Polylang active, Status & tools → Compatibility names it and
repeats where those strings are translated, so nobody has to remember this
page.

The settings screen deliberately keeps showing the original text you typed —
that is the source string your translations hang off, and editing it there
edits the original, not a translation.

**Without WPML or Polylang** — two languages by hand, a language switcher of
your own — use the filters instead, keyed off the current locale:

```php
add_filter( 'calucon_embed_gate_action_text', function ( $action, $provider ) {
	if ( 'youtube' !== $provider['id'] || 'de_DE' !== determine_locale() ) {
		return $action;
	}
	return 'Video von YouTube laden';
}, 10, 2 );
```

`calucon_embed_gate_note_text` does the same for the notice, and
`calucon_embed_gate_providers` can rewrite a whole descriptor — including
`privacy_url` — per locale.

## Styling

Prefer the Appearance tab (presets, corners, colour pickers, live preview
with a built-in contrast check). For CSS, override the custom properties —
no `!important`, no specificity war:

```css
.cg-embed {
	--cg-bg: #f6f7f7;
	--cg-fg: #1d2327;
	--cg-accent: #2271b1;
	--cg-accent-fg: #ffffff; /* keep ≥ 4.5:1 against --cg-accent */
	--cg-radius: 8px;
}
```

## Replacing the placeholder markup

Copy `templates/placeholder.php` to
`{your-theme}/calucon-embed-gate/placeholder.php`. The template documents the
minimum contract it must keep (container classes/attributes, a real
`<button type="button">`, the server-rendered fallback link). Keep the
panel's name on `role="group"` + `aria-label` — do not substitute a
heading; the correct heading level cannot be known from inside an embed.

`$show_button` is `false` when the panel sits inside `<noscript>` — markup a
browser renders only with scripting off, where no script could ever wire a
button up. Render the note and the link there, not the button; a template that
ignores the variable keeps its button, which is what older overrides do.

The template also receives `$privacy_url` and `$privacy_label` — the
provider's own policy page and its link text, both `''` unless the site
enabled the privacy link. If your template renders them, keep the
`cg-embed__privacy` class: scripts (this plugin's and other people's) find
the fallback and privacy links by class, never as "the last link in the
panel". A template copied from 0.9.x has no such block, so the link stays
invisible however the setting is configured — add it when you update.

The template receives a `$poster` variable — the site-origin poster image
chosen in the block editor ('' when none). If your template renders it,
keep the `cg-embed--poster` container class and `cg-embed__poster` image
class (gate.js removes the image on activation by that class), keep
`alt="" aria-hidden="true"`, and never replace it with a provider-hosted
image URL.

## What you cannot do from a customization

Reordering: the gate runs late on content filters; it sees the output of
shortcodes and blocks. Page-builder output that bypasses content filters
needs the "Gate the whole page output" setting instead of custom code.
And there is no supported way to make the plugin fetch anything remote —
that boundary is the product.
