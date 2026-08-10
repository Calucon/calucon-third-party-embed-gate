=== Consent Gate ===
Contributors: calucon
Tags: embeds, privacy, two-click, youtube, iframe
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Two-click embeds: third-party iframes and embed scripts load only after the visitor asks for them. No banner, no consent platform.

== Description ==

When an editor pastes a YouTube URL, WordPress turns it into an iframe — and on every page view, before the visitor has been offered any choice, their browser contacts the provider. Measured on a plain GET to `www.youtube.com/embed/…` with no playback and no scripts run: five cookies, two of them ~18-month identifiers. The same request on `www.youtube-nocookie.com` sets zero.

Consent Gate replaces third-party embeds with a server-rendered placeholder until the visitor clicks to load them — the two-click pattern (Zwei-Klick-Lösung). Nothing third-party is contacted before that click: no script, no iframe, no thumbnail, no preconnect. Nothing is stored on the visitor's device before that click either — including by this plugin.

**What it does**

* Gates third-party iframes and embed SDK scripts in post content, blocks and widgets — including HTML that has been minified by caching plugins, where most implementations silently fail.
* Gates by host, not by a provider allowlist: an unknown third-party iframe is gated by default.
* Loads from privacy-preserving endpoints after the click where they exist: `youtube-nocookie.com` (measured: 0 cookies instead of 5), Vimeo with `dnt=1`.
* Renders the placeholder server-side, so a visitor without JavaScript still gets a real, working link to the content.
* Rebuilds embeds from an attribute safelist — `sandbox` is preserved, `autoplay` never survives, inline styles and event handlers are never copied.
* Strips `preconnect`/`dns-prefetch` resource hints pointing at gated providers.
* Removes embeds from feeds and excerpts instead of showing a meaningless placeholder; the plain fallback link stays for feed readers.
* Optional, off by default: remember consent in the visitor's browser (per embed, per provider, or for all embeds; session or with an expiry), with a withdrawal control via the `[consent_gate_withdraw]` shortcode.
* Accessible placeholder: named group, a real button, visible focus, sufficient contrast, focus kept after activation. Zero axe-core violations in CI.
* Never phones home. The plugin makes no outbound request from your server or your visitors' browsers, on any path, for any reason.

**What it is not**

Consent Gate is a technical measure. It is not a consent management platform, it does not produce consent records for accountability purposes, it does not scan your site, and it does not make legal claims about your site. What it technically does: it prevents the embed providers' requests until the visitor acts, and the click is scoped to the embed (or, if you enable memory, the scope you configure). You remain responsible for your privacy policy, which still has to name the providers you embed from, and for your legal bases. If you need a documented consent record, you need a consent management platform.

**Customisation**

* Settings screen: per-provider on/off, privacy-variant on/off, custom note and button text; own-host and never-gate lists; rule toggles; opt-in whole-page buffering for page builders; consent memory; a generated Content-Security-Policy snippet.
* Theme override: copy `templates/placeholder.php` to `{your-theme}/consent-gate/placeholder.php`.
* CSS custom properties on `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …) for restyling without specificity wars.
* Documented filters: `consent_gate_providers`, `consent_gate_should_gate`, `consent_gate_is_own_host`, `consent_gate_own_hosts`, `consent_gate_placeholder_html`, `consent_gate_payload`, and more. Adding a provider is a ten-line filter in `functions.php`.

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

== Changelog ==

= 0.1.0 =
* Initial release: core gate (minification-tolerant scanner, host matcher, iframe and script rules), built-in provider set with privacy-preserving load targets, server-rendered accessible placeholder, settings screen, template override, feeds/excerpts/widgets/resource-hint handling, opt-in consent memory with withdrawal shortcode, CSP snippet generator.
