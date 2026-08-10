=== Consent Gate ===
Contributors: calucon
Tags: embeds, privacy, two-click, youtube, iframe
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Two-click embeds: third-party iframes and embed scripts load only after the visitor asks for them. No banner, no consent platform.

== Description ==

When an editor pastes a YouTube URL, WordPress turns it into an iframe — and on every page view, before the visitor has been offered any choice, their browser contacts the provider. Measured on a plain GET to `www.youtube.com/embed/…` with no playback and no scripts run: five cookies, two of them ~18-month identifiers. The same request on `www.youtube-nocookie.com` sets zero.

Consent Gate replaces third-party embeds with a server-rendered placeholder until the visitor clicks to load them — the two-click pattern (Zwei-Klick-Lösung). Nothing third-party is contacted before that click: no script, no iframe, no thumbnail, no preconnect. Nothing is stored on the visitor's device before that click either — including by this plugin.

**What it does**

* Gates third-party iframes, embed SDK scripts and legacy `<embed>`/`<object>` markup in post content, blocks, widgets, comments and archive descriptions — including HTML that has been minified by caching plugins, where most implementations silently fail, and lazy-loaded markup that parks the real URL in a `data-src` attribute.
* Gates content delivered over AJAX and the REST API to visitors ("load more", infinite scroll), while editors always see the original markup.
* Gates by host, not by a provider allowlist: an unknown third-party iframe is gated by default.
* Loads from privacy-preserving endpoints after the click where they exist: `youtube-nocookie.com` (measured: 0 cookies instead of 5), Vimeo with `dnt=1`.
* Renders the placeholder server-side, so a visitor without JavaScript still gets a real, working link to the content.
* Rebuilds embeds from an attribute safelist — `sandbox` is preserved, `autoplay` never survives, inline styles and event handlers are never copied.
* Strips `preconnect`/`dns-prefetch`/`preload`/`prefetch` resource hints pointing at gated providers and their CDN hosts (`i.ytimg.com`, `pbs.twimg.com`, …).
* Removes embeds from feeds and excerpts instead of showing a meaningless placeholder; a plain fallback link to the content stays for feed readers.
* Per-block override in the editor: gate a specific embed always, never, or per the site default.
* Optional, off by default: remember consent in the visitor's browser (per embed, per provider, or for all embeds; session or with an expiry), with a withdrawal control via the `[consent_gate_withdraw]` shortcode.
* Accessible placeholder: named group, a real button, visible focus, sufficient contrast, focus kept after activation. Zero axe-core violations in CI.
* Never phones home. The plugin makes no outbound request from your server or your visitors' browsers, on any path, for any reason.

**What it is not**

Consent Gate is a technical measure. It is not a consent management platform, it does not produce consent records for accountability purposes, it does not scan your site, and it does not make legal claims about your site. What it technically does: it prevents the embed providers' requests until the visitor acts, and the click is scoped to the embed (or, if you enable memory, the scope you configure). You remain responsible for your privacy policy, which still has to name the providers you embed from, and for your legal bases. If you need a documented consent record, you need a consent management platform.

**Customisation**

* Settings screen: per-provider on/off, privacy-variant on/off, custom note and button text; own-host, never-gate and always-gate lists; rule toggles including opt-in third-party image gating; appearance presets, corner styles and colour pickers with a live preview and an automatic readability check — no CSS needed; opt-in whole-page buffering for page builders; consent memory; a generated Content-Security-Policy snippet; a Compatibility overview (detected cache plugin, consent platform, page builder — and what the plugin does about each); a read-only Status scan of recent content.
* Theme override: copy `templates/placeholder.php` to `{your-theme}/consent-gate/placeholder.php`.
* CSS custom properties on `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …) for restyling without specificity wars.
* Documented filters: `consent_gate_providers`, `consent_gate_provider_for_url`, `consent_gate_should_gate`, `consent_gate_is_own_host`, `consent_gate_own_hosts`, `consent_gate_placeholder_html`, `consent_gate_payload`, `consent_gate_note_text`, `consent_gate_action_text`, `consent_gate_fallback_url`, plus the `consent_gate_before_render` and `consent_gate_embed_gated` actions. Adding a provider is a ten-line filter in `functions.php`.

== Frequently Asked Questions ==

= Does this make my site GDPR compliant? =

No plugin can claim that, and this one does not. Consent Gate implements a technical measure: it prevents third-party embed requests (and the storage they trigger on the visitor's device) until the visitor explicitly asks for the content. Whether your site's overall processing is lawful depends on things a plugin cannot know. The relevant background — § 25 TDDDG / ePrivacy Art. 5(3) for terminal-equipment storage, GDPR Art. 6(1)(a) for the processing after the click — is described in the documentation, and your privacy policy still has to name the providers you use.

= Why is there no cookie banner? =

Because there is nothing to announce at page load. If nothing third-party loads until the visitor asks for it, there is no third-party storage to consent to on page load. The consent is the click, given for the one embed it belongs to.

= An embed from my page builder is not being gated =

Page builders render outside WordPress's content filters. Enable "Gate the whole page output" under Settings → Consent Gate → Detection. It is off by default because whole-page buffering can conflict with other buffering plugins.

= The placeholder looks unstyled after an update =

If your minification setup serves CSS from a long-cached URL that does not change with the file contents, browsers can keep the old stylesheet for a long time. A hard reload fixes it; the plugin cannot.

= Does `loading="lazy"` on an iframe count as consent? =

No. Lazy loading defers the request to scroll time — it is still made without consent. Consent Gate gates lazy iframes like any other.

= How do I report a security issue? =

Privately, please — through GitHub's private vulnerability reporting on the plugin repository (https://github.com/Calucon/WP-Embed/security/advisories/new), not in a public issue or support topic. The repository's SECURITY.md describes what counts: besides the usual classes, any way to make a page contact a third party before the click is a vulnerability.

== Changelog ==

= 0.3.0 =
* Appearance made novice-friendly: the colour fields are now WordPress colour pickers (no hex typing), a corner-style choice (square, rounded, pill button) joins the panel-style presets, and the settings screen shows a live preview of the placeholder that updates as you change anything.
* The preview includes an automatic readability check: every colour pair (panel text, button text, fallback link) is measured against the WCAG 4.5:1 contrast minimum, in plain language, as you pick colours.
* The preview is rendered through the same pipeline as the front end — template overrides and text filters included — and is inert: the settings screen still makes no third-party request.

= 0.2.0 =
* Detection hardening: exclusion ranges are scanned sequentially, so a stray `<!--` inside a script (JSON-LD, legacy script-hiding) or an unclosed `<pre>` can no longer disable gating for the rest of the page.
* Gates attribute-swapped lazy loading (`data-src`, `data-lazy-src`, `data-original`), legacy `<embed>`/`<object>` markup, and `srcdoc` embeds that reference third parties; invisible tracking iframes (zero-sized, `display:none`) are removed instead of becoming a visible dead panel.
* Gates content delivered to visitors over AJAX and REST ("load more", infinite scroll); editors keep seeing original markup. New surfaces: Text-widget visual mode, comments and term/archive/author descriptions on classic themes.
* Whole-page gating repaired for page-builder sites: styles and scripts are injected into the buffered page (buttons work now), scanning is scoped to the body, and hint tags printed by performance plugins are scrubbed.
* Activation fixes: unknown widgets no longer share one consent/removal group (scoped per host); `id`, `name`, `class` and `data-secret` survive the rebuild, so the YouTube JS API, `<form target>` and WordPress-to-WordPress embed resizing work after consent; loading and error states are announced to assistive technology, with a link to the provider as the error fallback.
* Resource hints: `preload`/`prefetch`/`prerender` covered, the `wp_preload_resources` filter hooked, and providers' sibling CDN hosts scrubbed.
* Feeds carry a plain fallback link where an embed was removed.
* New: per-block "Gate this embed" override and a withdrawal block in the editor; Appearance presets and colours; Compatibility and Status screens; always-gate host list; opt-in third-party image gating.
* Providers registered from a theme's `functions.php` now appear in the settings table, the CSP snippet and hint scrubbing; five new documented hooks.
* Multisite-aware uninstall; page caches are flushed on deactivation.
* The full E2E, accessibility (axe) and real-WordPress integration suites now run in CI on every change.

= 0.1.0 =
* Initial release: core gate (minification-tolerant scanner, host matcher, iframe and script rules), built-in provider set with privacy-preserving load targets, server-rendered accessible placeholder, settings screen, template override, feeds/excerpts/widgets/resource-hint handling, opt-in consent memory with withdrawal shortcode, CSP snippet generator.
