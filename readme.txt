=== Calucon Third-Party Embed Gate ===
Contributors: calucon
Donate link: https://ko-fi.com/calucon
Tags: embeds, privacy, two-click, youtube, iframe
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.12.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

YouTube, Maps and other third-party embeds load only after the visitor clicks — the two-click pattern. No request, no cookie, no banner.

== Description ==

When an editor pastes a YouTube URL, WordPress turns it into an iframe — and on every page view, before the visitor has been offered any choice, their browser contacts the provider. Measured on a plain GET to `www.youtube.com/embed/…` with no playback and no scripts run: five cookies, two of them ~18-month identifiers. The same request on `www.youtube-nocookie.com` sets zero.

Calucon Third-Party Embed Gate replaces third-party embeds with a server-rendered placeholder until the visitor clicks to load them — the two-click pattern (Zwei-Klick-Lösung). Nothing third-party is contacted before that click: no script, no iframe, no thumbnail, no preconnect. Nothing is stored on the visitor's device before that click either — including by this plugin.

See it in action on the [live demo](https://calucon.de/third-party-embed-gate-showcase/), or read the details on the [plugin page](https://calucon.de/third-party-embed-gate/).

**What it does**

* Gates third-party iframes, embed SDK scripts and legacy `<embed>`/`<object>` markup in post content, blocks, widgets, comments and archive descriptions — including HTML that has been minified by caching plugins, where most implementations silently fail, and lazy-loaded markup that parks the real URL in a `data-src` attribute.
* Gates content delivered over AJAX and the REST API to visitors ("load more", infinite scroll), while editors always see the original markup.
* Gates by host, not by a provider allowlist: an unknown third-party iframe is gated by default.
* Ships a descriptor for almost every embed type WordPress offers out of the box — a proper name, an icon, a privacy-policy link and a working no-JavaScript link — plus the loader scripts and stylesheets those embeds bring with them. The few that are not named yet are listed in the FAQ; they are gated all the same.
* Loads from privacy-preserving endpoints after the click where they exist: `youtube-nocookie.com` (measured: 0 cookies instead of 5), Vimeo with `dnt=1`.
* Renders the placeholder server-side, so a visitor without JavaScript still gets a real, working link to the content.
* Rebuilds embeds from an attribute safelist — `sandbox` is preserved, `autoplay` never survives, inline styles and event handlers are never copied.
* Strips `preconnect`/`dns-prefetch`/`preload`/`prefetch` resource hints pointing at gated providers and their CDN hosts (`i.ytimg.com`, `pbs.twimg.com`, …).
* Removes embeds from feeds and excerpts instead of showing a meaningless placeholder; a plain fallback link to the content stays for feed readers.
* Per-block override in the editor: gate a specific embed always, never, or per the site default.
* Optional poster image behind the consent panel, chosen per embed from your media library — served from your own site, never fetched from the provider. Per-embed button and notice text in the block editor, too.
* German included: the plugin's own texts ship translated for every German locale WordPress offers — Germany (du and Sie), Austria, and Switzerland (with ss for ß) — placeholder wording, the settings screen and the block-editor controls alike.
* Multilingual sites: the texts you type (per-provider and per-block notices and button labels, provider privacy-policy URLs, your own providers' names) are registered for WPML and Polylang via a shipped wpml-config.xml.
* Optional, off by default: remember consent in the visitor's browser (per embed, per provider, or for all embeds; session or with an expiry), with a withdrawal control via the `[calucon_embed_gate_withdraw]` shortcode.
* Optional, off by default: a bridge to your consent platform. When a tested platform (WP Consent API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, Real Cookie Banner) reports consent for the embeds' category, gated embeds load without a second click — and a withdrawal there re-gates them. The bridge only reads the platform's state; with an untested platform, or when the platform gives no answer, gating stands unchanged.
* Accessible placeholder: named group, a real button, visible focus, sufficient contrast, focus kept after activation. Zero axe-core violations in CI.
* Never phones home. The plugin makes no outbound request from your server or your visitors' browsers, on any path, for any reason.

**What it is not**

Calucon Third-Party Embed Gate is a technical measure. It is not a consent management platform, it does not produce consent records for accountability purposes, it does not scan your site, and it does not make legal claims about your site. What it technically does: it prevents the embed providers' requests until the visitor acts, and the click is scoped to the embed (or, if you enable memory, the scope you configure). You remain responsible for your privacy policy, which still has to name the providers you embed from, and for your legal bases. If you need a documented consent record, you need a consent management platform.

**Customisation**

* Tabbed settings screen (Providers / Detection / Appearance / Consent memory / Status & tools): your own providers (name + hosts, no code), per-provider on/off, privacy-variant on/off, custom note and button text, an optional provider privacy-policy link in every panel (off by default; one checkbox turns it on); own-host, never-gate and always-gate lists; rule toggles including opt-in third-party image gating; appearance presets, corner styles with a custom radius, border width and colour, shadow, spacing, button size/style/width/hover, an optional kind-aware button icon, notice size, panel alignment, link colour, poster placement and dimming, withdraw-button styles and optional dark-mode colours — sectioned, with quick styles, colour pickers, a live preview (dark page, poster, phone width), a one-click reset and an automatic readability check, no CSS needed; opt-in whole-page buffering for page builders; consent memory; a generated Content-Security-Policy snippet; a Compatibility overview (detected cache plugin, consent platform, page builder — and what the plugin does about each); a Status scan of recent content that can name or let through any host it finds, without you typing an address and without writing anything until you save.
* Theme override: copy `templates/placeholder.php` to `{your-theme}/calucon-embed-gate/placeholder.php`.
* CSS custom properties on `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …) for restyling without specificity wars.
* WP-CLI: `wp calucon-embed-gate scan` (is every embed gated? `--format=json` for CI and automation) and `wp calucon-embed-gate providers`; the shipped `docs/customizing.md` is a self-contained customization reference for developers and AI agents.
* Documented filters: `calucon_embed_gate_providers`, `calucon_embed_gate_provider_for_url`, `calucon_embed_gate_should_gate`, `calucon_embed_gate_is_own_host`, `calucon_embed_gate_own_hosts`, `calucon_embed_gate_placeholder_html`, `calucon_embed_gate_payload`, `calucon_embed_gate_note_text`, `calucon_embed_gate_action_text`, `calucon_embed_gate_fallback_url`, `calucon_embed_gate_www_equivalence`, `calucon_embed_gate_cmp_config`, `calucon_embed_gate_asset_version`, `calucon_embed_gate_the_content_priority`, `calucon_embed_gate_render_block_priority`, plus the `calucon_embed_gate_before_render`, `calucon_embed_gate_embed_gated` and `calucon_embed_gate_flush_caches` actions. Each one's signature, when it fires and what to return are documented in `docs/customizing.md`, which ships inside the plugin (wp-content/plugins/calucon-third-party-embed-gate/docs/customizing.md) and is readable on GitHub. Adding a provider is a ten-line filter in `functions.php`.

== Installation ==

Calucon Third-Party Embed Gate works the moment it is activated — it gates third-party embeds by default, with no configuration, no account and no external service.

1. In your WordPress admin, go to **Plugins → Add New**, search for "Calucon Third-Party Embed Gate", and click **Install Now**, then **Activate**. To install from a downloaded zip instead, go to **Plugins → Add New → Upload Plugin**, choose the file, install and activate.
2. That is all that is required. Your existing embeds are now replaced with a click-to-load placeholder on the front end, and nothing third-party is contacted before the visitor clicks. Editors keep seeing the normal embed in the block editor, so nothing changes about how you write posts.
3. Optional: open **Settings → Calucon Third-Party Embed Gate** to adjust appearance, per-provider behaviour, detection rules, consent memory and the optional consent-platform bridge. None of it is needed to be protected — the defaults gate everything third-party.

If you turn on consent memory and want to offer visitors a way to take it back, add the "Withdraw embed consents" block, or place the `[calucon_embed_gate_withdraw]` shortcode on your privacy-policy page.

**Requirements:** WordPress 5.9 or newer and PHP 7.4 or newer. No build step, no runtime dependencies, and no outbound request from your site on any path.

== Frequently Asked Questions ==

= Does this make my site GDPR compliant? =

No plugin can claim that, and this one does not. Calucon Third-Party Embed Gate implements a technical measure: it prevents third-party embed requests (and the storage they trigger on the visitor's device) until the visitor explicitly asks for the content. Whether your site's overall processing is lawful depends on things a plugin cannot know. The relevant background — § 25 TDDDG / ePrivacy Art. 5(3) for terminal-equipment storage, GDPR Art. 6(1)(a) for the processing after the click — is described in the documentation, and your privacy policy still has to name the providers you use.

= Why is there no cookie banner? =

Because there is nothing to announce at page load. If nothing third-party loads until the visitor asks for it, there is no third-party storage to consent to on page load. The consent is the click, given for the one embed it belongs to.

= Is the plugin available in German? =

Yes. German ships with the plugin for all five German locales WordPress offers — Deutschland informal and formal ("de_DE", "de_DE_formal"), Österreich ("de_AT"), and Schweiz formal and informal ("de_CH", "de_CH_informal", spelled with ss instead of ß) — and it covers everything a person reads: the placeholder your visitors see, the settings screen and the block-editor controls. Set your site language and it follows. Other languages are welcome via translate.wordpress.org; a translation from there overrides the bundled one.

= Does a visitor have to click every single time? =

By default, yes: once per embed, on every page, and nothing is stored on the visitor's device to remember it. If that is more friction than you want, Settings → Calucon Third-Party Embed Gate → Consent memory can remember the choice in the visitor's browser — for that one embed, for everything from that provider, or for all embeds — either until the browser is closed or for a number of days you choose. It is off by default and stores nothing before the visitor's first click. When you turn it on, give visitors a way back: the "Withdraw consent" block, or the `[calucon_embed_gate_withdraw]` shortcode, clears what was remembered.

= I already run a cookie banner (Complianz, Cookiebot, …). Do they fight? =

No. Out of the box Calucon Third-Party Embed Gate ignores the banner and keeps gating — visitors see your banner for its categories and the embed placeholder for embeds, and nothing double-blocks (the placeholder contains no iframe or script for a banner's blocker to catch). If you prefer one decision instead of two, enable the consent platform bridge under Settings → Calucon Third-Party Embed Gate → Consent memory: a consent your visitor gives in the platform then loads the embeds automatically, and a withdrawal there re-gates them. The bridge works only with the platforms listed on that screen — with any other platform it stays out of the way and gating stands. If you would rather have your platform's own blocker handle a specific provider, disable that provider under Providers and Calucon Third-Party Embed Gate steps aside for it.

= Is Google Consent Mode v2 supported? =

Consent Mode is deliberately not read or written directly. It is a signal that consent platforms send to Google's tags; Google publishes no API for other scripts to read it, and no Consent Mode signal governs iframes such as YouTube embeds. The bridge instead connects to the consent platform itself — the same place Consent Mode gets its state from — which is the reliable way to honour the same visitor choice. Calucon Third-Party Embed Gate also never sends `gtag('consent', …)` updates: a click on one embed is consent for that embed, not a site-wide marketing consent, and misreporting that would be wrong.

= An embed from my page builder is not being gated =

Page builders render outside WordPress's content filters. Enable "Gate the whole page output" under Settings → Calucon Third-Party Embed Gate → Detection. It is off by default because whole-page buffering can conflict with other buffering plugins.

= The placeholder looks unstyled after an update =

If your minification setup serves CSS from a long-cached URL that does not change with the file contents, browsers can keep the old stylesheet for a long time. A hard reload fixes it; the plugin cannot.

= Does `loading="lazy"` on an iframe count as consent? =

No. Lazy loading defers the request to scroll time — it is still made without consent. Calucon Third-Party Embed Gate gates lazy iframes like any other.

= How do I report a security issue? =

Privately, please — through GitHub's private vulnerability reporting on the plugin repository (https://github.com/Calucon/calucon-third-party-embed-gate/security/advisories/new), not in a public issue or support topic. The repository's SECURITY.md describes what counts: besides the usual classes, any way to make a page contact a third party before the click is a vulnerability.

= Which embeds does it recognise by name? =

Videos: YouTube, Vimeo, Dailymotion, TED, VideoPress and WordPress.tv, TikTok. Audio: Spotify, SoundCloud, Apple Music, Mixcloud, Pocket Casts. Maps: Google Maps, OpenStreetMap. Social posts: X, Instagram, Facebook, Reddit, Tumblr, Bluesky, Pinterest, Imgur, GIPHY, Strava. Documents: Scribd, Speaker Deck, Issuu, Wolfram Cloud, Amazon Kindle, Kickstarter. Forms and calendars: Google Calendar, Google Forms, Typeform, Calendly, Crowdsignal. 3D: Matterport, Sketchfab.

Everything else is gated too — that does not depend on a list. An embed from an unnamed host gets the same placeholder and the same button, named after the host it would contact, with a link to the content itself. What a named provider adds is the label, the icon, the privacy-policy link and a tidier "Open on …" link. A few of core's own embed blocks are not named yet (Flickr, SmugMug, Animoto, ReverbNation, Cloudup); you can name them yourself under Providers → Your own providers.

Some of these embeds bring a loader script or stylesheets along with the player (VideoPress, Scribd, Wolfram Cloud). Those are gated together with the embed they belong to and load on the same click, not before it.

= Something on my site is gated and I want it to load normally =

Open Settings → Calucon Third-Party Embed Gate → Providers and press "Check what is on my site". The scan lists every embed it finds in your recent posts and pages with the address it would contact. Next to each one you can either name it — which keeps the gate on but gives the placeholder a proper label and icon — or let it through, which means its embeds load for every visitor with no placeholder. Either way you never have to work out a host name yourself, and nothing changes until you press Save. Hosts you have let through stay listed at the top of the same screen with a one-click undo.

= A provider offers both an embed code and a script — which should I paste? =

Either is gated, so this is not a privacy question. It is a rendering one: prefer the plain `<iframe>` embed code where the provider offers one. An iframe renders by itself; a loader script has to notice the embed and draw it, and some providers' scripts only do that while the page is first parsing, so they can come up empty after the visitor clicks — with or without this plugin. If a script-based embed stays blank after loading, try the provider's iframe embed code instead.

= Can I add a provider that is not in the list? =

Yes, without code: Providers → *Your own providers* takes a name, the embed hosts (one per line) and, optionally, script hosts and a kind for the button icon. After saving it appears in the provider table with its own notice, button text and privacy-policy link. Unknown hosts are gated either way — a provider of your own only gives such a host a proper name and texts. Hosts the built-in providers handle stay with them, and your own providers are always gated; the never-gate list under Detection is the place to exempt a host.

= Can placeholders link the provider's privacy policy? =

Yes: one checkbox on the Providers tab adds a link to the provider's own policy page in every placeholder, so a visitor can read what loading the content means before asking for it. It is off by default. You can set a different URL per provider (for example a localised page). The link is plain markup — nothing is fetched from the provider by showing it.

= Do I need the Content-Security-Policy section? =

Only if your site sends a Content-Security-Policy header — most WordPress sites do not. The section on Status &amp; tools can check your own home page for one (from your browser, nothing leaves your site) and tells you whether the enabled providers are already allowed; if not, it lists the lines to add.

= Can I change how the placeholder looks without writing CSS? =

Yes. The Appearance tab has quick styles, colours that can follow your theme's palette, and controls for corners, border, shadow, spacing, the button, the poster image and dark mode, with a live preview and an automatic readability check. Your own CSS still works on top: the panel exposes CSS custom properties and a template override (see docs/customizing.md in the plugin folder).

== External services ==

This plugin makes no request to any external service, on any page, at any time. It contacts no API, loads no remote script, font, image or update check, and sends no telemetry. Its entire purpose is the opposite direction: it prevents your pages from contacting embed providers.

Third-party content enters the picture only after a visitor explicitly clicks the "Load" button on an embed placeholder. At that moment the visitor's browser loads that one embed from its provider (for example YouTube, Vimeo, or Google Maps) — exactly as it would have without this plugin, except that it now happens on the visitor's request instead of automatically. Each placeholder names the provider and — when the optional link is turned on under Providers — links the known provider's privacy policy before the click, and the provider hostnames in the plugin's source code exist solely so it can recognise and gate that content. No data is sent anywhere by the plugin itself.

== Screenshots ==

1. A gated YouTube embed as a visitor sees it: a server-rendered placeholder with a named panel, a real "Load" button and a working fallback link. Nothing is requested from the provider until the visitor clicks.
2. The Appearance settings — quick styles, colours that follow your theme's palette by name or your own, and sections for shape, button, poster image, withdraw button and dark mode — with a live preview of the real panel and an automatic readability check that flags any colour pair below the 4.5:1 contrast minimum.
3. The content scan on Status & tools: every embed found in your recent posts and pages, the address it would contact, and whether it is gated — with a one-click way to give an unknown host a proper name, or let it through, without working out an address yourself. Nothing changes until you save.
4. The Providers tab: providers grouped by what the embed is, with a filter box — per-provider on/off, privacy-preserving load variants, custom notice and button text, the privacy-policy link and its per-provider URL, and your own providers — no code required.
5. The per-embed control in the block editor: gate a specific embed always, never, or per the site default, set an optional poster image from your own media library, and give this one embed its own button and notice text.
6. The Content-Security-Policy helper: what a policy is, a check of your own site for one, the exact lines to add for the providers you have enabled, and which provider needs which host.

== Upgrade Notice ==

= 0.12.0 =
Adds German, in both the du and the Sie variant — the placeholder your visitors see, the settings screen and the editor controls. Set your site language to German and it follows. Nothing else changes; English sites see no difference.

= 0.11.0 =
Names the rest of WordPress's built-in embed types, and the content scan can now name or let through any host it finds without you typing an address. Fixes Scribd and Wolfram Cloud embeds, which contacted their provider before the click. Everything here was already gated.

= 0.10.0 =
The panel looks and behaves as before unless you opt in: the privacy-policy link and the new Appearance controls are off by default. Clear your page cache once after updating. Adds your own providers, a CSP helper and a much larger Appearance tab.

== Changelog ==

= 0.12.0 =
* New: German translations ship with the plugin, for all five German locales WordPress offers — Deutschland (du und Sie), Österreich, and Schweiz (Sie und du, written with ss instead of ß as Switzerland does). WordPress does not fall back between them, so each one needs its own file. Everything a person reads is covered: the placeholder your visitors see, all five settings tabs and the per-block controls in the editor. Set the site language to German and it follows; a translation from translate.wordpress.org still takes precedence over the bundled one.
* Changed: the plugin now loads its own translation files. Nothing changes for sites running in English.
* New: the Compatibility overview names a detected multilingual plugin (WPML, Polylang, TranslatePress, Weglot) and says where the texts you typed yourself are translated — for WPML and Polylang, the screen that holds them; the other two translate the finished page and need nothing.
* New: the Compatibility overview names a detected multilingual plugin (WPML, Polylang, TranslatePress, Weglot) and says where the texts you typed yourself are translated — for WPML and Polylang, the screen that holds them; the other two translate the finished page and need nothing.
* Fixed: on WPML and Polylang sites, the texts you type yourself — a provider's notice, button label or privacy-policy URL, and your own providers' names — showed in the site's default language on every translation. They are now read in the language of the page being built. Translate them in WPML's String Translation or Polylang's Strings screen; the shipped wpml-config.xml already registers them.

= 0.11.0 =
* New: page caches are flushed automatically when the plugin is activated and after it updates, not only when settings are saved or the plugin is deactivated — so a cached page cannot keep serving pre-update markup.
* New: the Providers tab is grouped by what the embed is (video, audio, social, documents…) with a filter box, so a long list stays manageable — and it no longer scrolls sideways on a phone. Each provider's wording and privacy-policy link sit behind a per-provider toggle.
* New: the content scan on Status & tools is now actionable. Every embed it finds can be named (so an unknown host gets a proper label and icon) or let through, without typing a host name anywhere — and hosts you have let through are listed with a one-click undo. Nothing changes until you press Save.
* Changed: the Dailymotion test fixture pointed at a re-uploaded television series; test fixtures now use placeholder ids unless the target is the provider's own, an institution's own, or ours.
* Fixed: the "Withdraw embed consents" control sat against the left edge of the page on block themes instead of lining up with the text around it.
* Fixed: the settings screen's read-only tables (Compatibility, the content scan, the Content-Security-Policy host list) pushed the page sideways on a phone; they now scroll within their own box.
* Fixed: on narrow screens the placeholder could be taller than the space reserved for the embed, hiding the fallback and privacy links behind a scrollbar that was easy to miss. The panel now grows to fit.
* Fixed: an embed whose script reserves an empty box of its own (Calendly's inline widget) left a tall blank gap above the placeholder; the gap is gone while gated and comes back when the embed loads. Calendly placeholders now link the booking page instead of the script host.
* Fixed: the settings screen could claim "unsaved changes" after merely switching tabs or opening a section. Only changing a value counts now.
* Fixed: Scribd embeds (an inline script that fetches Scribd's loader), VideoPress embeds (a resize loader) and Wolfram Cloud notebooks (stylesheets and an inline call) requested their provider before the click; these companions are now gated with their panel and load only after it. Scripts of your own that merely mention a provider's address are left alone.
* Fixed: a script of your own that merely names a provider's address in a comment could be removed and replaced with a placeholder, so the script stopped running. A provider address now only counts where a script actually loads it.
* Fixed: a second embed from the same provider on one page lost its placeholder and its link, and loaded on the first embed's click. Each embed is its own again.
* Fixed: a placeholder for a Scribd or Crowdsignal embed that came with no address to link to could show a broken "Open on …" link. It now links the provider's site.
* Fixed: with consent memory or a consent platform enabled, a returning visitor could get an embed that stayed blank because its loader ran before the script it needs. Also, remembering consent "for this embed only" treated every script-built embed as the same one, so a click on one could load another provider's embed on the next page view.
* Fixed: a placeholder inside a `<noscript>` block (Crowdsignal polls) offered a button that could never work, since that markup is only shown when scripting is off. It shows the notice and the link instead.
* Fixed: "Name this host" put a host found as a script into the embed-hosts field, where it matched nothing.
* Fixed: after running the content scan, the "Check what is on my site" button on the Providers tab did nothing — it now takes you back to the results.
* Fixed: the block editor's script and stylesheet were the last ones not cache-busted per build, so a rebuilt same-version install could keep the previous editor script.
* New: built-in providers for the rest of WordPress core's embed types — Dailymotion, TED, VideoPress and WordPress.tv, Mixcloud, Pocket Casts, Scribd, Speaker Deck, Issuu, Kickstarter, Wolfram Cloud and Amazon Kindle (players and documents), plus Imgur, Tumblr, Pinterest, Bluesky and Crowdsignal (script embeds, now with a real fallback link to the post instead of the script host). All of these were gated before under their host names; they now get a name, an icon, a privacy-policy link and a Providers-tab row.

= 0.10.0 =
* New: an optional privacy-policy link in each placeholder, pointing at the provider's own policy page (for the built-in providers that declare one; unknown embeds have no known policy). Off by default — a checkbox on the Providers tab turns it on.
* New: fine-grained appearance controls without CSS — custom corner radius, border width and colour, shadow strength, panel spacing, button size, an optional bundled play glyph on the button, notice text size and panel alignment, all mirrored in the live preview.
* New: the "Withdraw embed consents" control is now styled to match the panels (same colours and corners) with filled, outline and text-link variants.
* New: optional dark-mode colours, applied only when the visitor prefers a dark colour scheme.
* New: the Appearance tab is organised into sections with a one-click "Reset appearance to defaults".
* New: load-button style (filled or outline), full-width option and hover strength; panel placement over poster images (corner card, centred card, or bottom bar) with a poster preview in the settings.
* New: per-embed button and notice text in the block editor, next to the existing gate override and poster controls.
* New: quick styles — four one-click starting points (Dark cinema, Light minimal, Brand card, Soft pastel) that fill in every Appearance control for you to tweak.
* New: the button icon is now chosen by what the embed is — play for videos, a pin for maps, a note for audio, a generic symbol otherwise; poster dimming; a separate link colour; a phone-width preview toggle.
* New: multilingual sites — the custom notice and button texts (settings and per block) are registered for WPML and Polylang via a shipped wpml-config.xml.
* New: per-provider privacy policy URL override on the Providers tab, for a localised or moved policy page (https only).
* New: a "Settings" link next to the plugin on the Plugins screen, and a "Support development" link in its row details.
* New: your own providers — name any embed host on the Providers tab (with optional script hosts and a kind for the button icon); it then gets the same note, button text and privacy-policy link controls as the built-ins. No code needed — and nothing to break: unknown hosts are gated either way, hosts a built-in provider handles are refused with a notice, and your own providers are always gated.
* New: ten provider kinds for the button icon — video, map, audio/podcast, social post, form, calendar/booking, document, image/GIF, 3D/virtual tour, generic — each with its own glyph; the built-ins are classified accordingly (X, Instagram, Facebook, Reddit and Strava as social posts; Typeform and Google Forms as forms; Calendly and Google Calendar as calendars; Matterport and Sketchfab as 3D; GIPHY as image), and the Providers tab shows every provider's icon.
* New: the Content-Security-Policy section (Status & tools) now explains in plain language whether you need it at all, can check your own home page from the browser for an existing policy and say which provider hosts it still lacks, offers a Copy button, and lists which provider needs which host. It is collapsed by default — most sites send no policy.
* New: every colour can follow one of the theme's own palette colours by name — the panel then changes with the theme — or be set to a custom colour; the pickers also offer the palette as named swatches.
* Fixed: a placeholder with a poster image could show a dead scrollbar — the image now always fits the reserved box, whatever its ratio.
* Fixed: right-to-left sites (icon and status spacing now follow the text direction) and Windows High Contrast mode (panel, buttons and icon keep visible borders).
* Fixed: the error state after a failed load could link the wrong destination when the panel showed more than one link.

= 0.9.4 =
* Performance and robustness: the embed detector now handles pathological markup (thousands of unterminated code blocks) in linear time instead of quadratic, the zero-embed fast path is ~4x cheaper on every page view, and resource-hint scrubbing skips pages with no hint tags at all.
* Front end: with consent memory enabled, remembered consents are now restored with a single storage read per page instead of one per embed; a failed embed-SDK load no longer leaves a dead script element behind, a retry can no longer lose the placeholder, and withdrawing a platform consent now also clears a stale error notice.
* Internal clean-up with no behaviour change: dead code removed, asset handling and the settings screen reorganised, and the unused `thumbnail` provider-descriptor key (a leftover of the rejected auto-fetch feature) removed.

= 0.9.3 =
* The source repository moved to github.com/Calucon/calucon-third-party-embed-gate, matching the plugin slug; the security-report and issue links were updated accordingly (the old address redirects). No functional change.

= 0.9.2 =
* The Status screen's scan query parameter now carries the full plugin prefix (`calucon-embed-gate-scan`). No functional change.

= 0.9.1 =
* When "Gate the whole page output" is enabled, the plugin's stylesheet and script are now delivered through the standard enqueue API on every front-end page instead of being written into the buffered document at shutdown. Direct tag injection is gone entirely.
* The translation bridge for the WordPress-free layers now resolves through a generated map of literal gettext calls (`languages/strings.php`), so no translation function in the plugin ever receives a variable argument.
* The provider descriptor key `hint_hosts` is now `scrub_hint_hosts` — a clearer name for what it always was: hostnames whose `preconnect`/`dns-prefetch` resource hints the plugin removes. Nothing is ever requested from them.
* Fixed the Cloudflare cache-purge integration: it now registers with the official Cloudflare plugin's `cloudflare_purge_everything_actions` filter and fires the plugin's own `calucon_embed_gate_flush_caches` action (the previous direct hook call never reached the Cloudflare plugin). The LiteSpeed purge hook now fires only when LiteSpeed Cache is installed.

= 0.9.0 =
* Before the WordPress.org listing goes live — while no installed sites exist to break — the plugin's internal identifiers were aligned with its new name, with no legacy aliases: filters and actions are `calucon_embed_gate_*`, the shortcode is `[calucon_embed_gate_withdraw]`, the block is `calucon-embed-gate/withdraw`, the WP-CLI namespace is `wp calucon-embed-gate`, the theme template override directory is `{theme}/calucon-embed-gate/`, and the settings option was renamed. If you somehow installed a pre-release build, update those references and re-save the settings.
* The `.cg-embed` CSS classes, `--cg-*` custom properties and `data-cg-*` attributes are unchanged.

= 0.8.1 =
* Renamed the plugin's constants to match the plugin: CALUCON_EMBED_GATE_VERSION, _FILE and _DIR. The previous CONSENT_GATE_* names remain defined as aliases and will be removed no earlier than 0.9.0, in a release of their own.
* Updated the plugin page and demo links to their new addresses.
* Everything a site can depend on is unchanged: the calucon_embed_gate_* filters, the [calucon_embed_gate_withdraw] shortcode, the wp calucon-embed-gate CLI commands, the .cg-embed CSS classes and the theme template override path all keep their existing names. Nothing you have already set up needs changing.

= 0.8.0 =
* Renamed from "Consent Gate" to "Calucon Third-Party Embed Gate" (new slug `calucon-third-party-embed-gate`) during WordPress.org review, to make clear the plugin gates third-party embeds and is not a consent management platform. No functional change.
* Translations: the strings defined in the WordPress-free layers are now mirrored in `languages/strings.php` as literal gettext calls, so translate.wordpress.org can extract them. Removed the redundant `load_plugin_textdomain()` call (WordPress loads language packs automatically since 4.6).
* readme: added the "External services" section stating what the plugin does (and does not) contact.

= 0.7.5 =
* Compliance: documented the WordPress-free layer's `parse_url()` usage and replaced a WordPress 6.5-only function with a version-agnostic equivalent, so the plugin passes WordPress Plugin Check cleanly on the 5.9 minimum. No functional change.

= 0.7.4 =
* Documentation: added Installation and Screenshots sections to the readme for the WordPress.org listing, and linked the plugin page and live demo. Plugin URI now points to the plugin's home page. No functional change.

= 0.7.3 =
* Repository renamed to match the plugin (github.com/Calucon/consent-gate). Updated the Plugin URI and the issue/security-report links. No functional change.

= 0.7.2 =
* Added an optional way to support development: a Donate link, a support link in the plugin's own settings footer, and a GitHub Sponsor button. Plain links only — no third-party widget or remote image loads, so the plugin still makes no outbound request from wp-admin.

= 0.7.1 =
* Security hardening (pre-submission audit). Closed a host-classification gap where a crafted embed URL using a backslash or irregular slashes in its authority (e.g. `https://evil.example\@yoursite/`) parsed to your own host in PHP but connects to the third party in every browser — such URLs are now gated, matching how browsers resolve them. The fallback link now rejects non-navigable schemes (`javascript:`, `data:`), the inline settings JSON is emitted with the same tag-escaping as the embed payload, and provider note/button overrides are length-capped.
* Robustness: when a script-strategy SDK (X/Twitter, Instagram, …) is blocked by the browser, the other embeds of that provider keep their panels and fallback links instead of disappearing until reload.
* Every plugin PHP file now carries a direct-access guard, and the plugin declares its Domain Path — housekeeping for the WordPress.org directory.

= 0.7.0 =
* Consent platform bridge (off by default): when an installed, tested consent platform — WP Consent API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, or Real Cookie Banner — reports consent for the embeds' category, gated embeds load without a second click, and a withdrawal in the platform re-gates what the bridge loaded (an embed the visitor clicked personally stays). Client-side and read-only: the bridge stores nothing, sends nothing, and with an untested platform or no answer gating stands unchanged.
* IAB TCF v2.2 signals can additionally be honoured behind their own experimental flag; only providers with a Global Vendor List entry can ever be granted that way.
* The Compatibility screen now distinguishes tested platforms (bridge available or active) from untested ones (fail-closed, as before).

= 0.6.1 =
* Legacy Google Maps embeds (`maps.google.com/maps?q=…&output=embed`, the older share form that is still widespread) are now recognised as Google Maps instead of falling back to the generic gate. They were already gated either way; they now get the Google Maps label, note and resource-hint scrubbing.

= 0.6.0 =
* Poster images: every embed block gains a "Set poster image" control (Calucon Third-Party Embed Gate panel in the block inspector). The chosen media-library image is shown behind the consent panel until the visitor loads the embed — served from your own site, never fetched from the provider, so the zero-third-party-requests guarantee is untouched. The panel keeps its solid background on top of the image, so text contrast is preserved.
* Theme placeholder templates receive the poster as a `$poster` variable; see docs/customizing.md.

= 0.5.0 =
* WP-CLI: `wp calucon-embed-gate scan` reports every embed in recent content and whether it is gated (`--format=json` for CI and automation); `wp calucon-embed-gate providers` lists providers as the gate resolves them. Both read-only, no outbound requests.
* Ships `docs/customizing.md`: a self-contained reference for customizing the plugin from functions.php or WP-CLI — descriptor keys, filter examples, the template contract, and the invariants a customization must keep. Written to serve developers and AI coding agents alike.

= 0.4.0 =
* The settings screen is now tabbed: Providers, Detection, Appearance, Consent memory, and a read-only Status & tools tab (Status scan, Compatibility, CSP snippet). One page, one Save button — saving returns you to the tab you were on.
* Tabs follow the ARIA tabs pattern (arrow keys, Home/End) and are an enhancement: without JavaScript the page renders as before, every section visible.

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
