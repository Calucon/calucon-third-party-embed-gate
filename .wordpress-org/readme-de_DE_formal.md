# readme.txt auf Deutsch (de_DE_formal, „Sie“)

<!-- readme.txt: c6fcef96ac3de224 -->
<!-- Dieser Stempel bindet die Übersetzung an den englischen Stand.
     tests/Unit/ReadmeTranslationTest.php schlägt fehl, sobald readme.txt sich ändert. -->

Die Beschreibung eines Plugins wird nicht mitgeliefert, sondern auf
translate.wordpress.org im Projekt **Stable Readme** übersetzt. Sobald die
Übersetzung freigegeben ist, zeigt wordpress.org deutschen Besuchern die
deutsche Plugin-Seite.

**So benutzen Sie diese Datei:** GlotPress zeigt jeden Absatz als eigenen String.
Suchen Sie links den englischen Text und kopieren Sie hier den deutschen darunter. Die
Reihenfolge entspricht der readme.txt.

**Nicht ändern, ohne es zu wollen:** Die Antworten zu DSGVO, Cookie-Banner und
Consent Mode sind bewusst zurückhaltend formuliert. Das Plugin ist eine
technische Maßnahme und behauptet nirgends, eine Website rechtskonform zu
machen. Diese Zurückhaltung muss die Übersetzung halten.

---

## Kurzbeschreibung

**EN:** YouTube, Maps and other third-party embeds load only after the visitor clicks — the two-click pattern. No request, no cookie, no banner.

**DE:** YouTube, Maps und andere Drittanbieter-Einbettungen laden erst, wenn der Besucher klickt. Zwei-Klick-Lösung: keine Anfrage, kein Cookie, kein Banner.

*(149 Zeichen — wordpress.org erlaubt 150. „Drittanbieter“ und „Zwei-Klick-Lösung“ sind die Wörter, nach denen im deutschsprachigen Raum gesucht wird; deshalb stehen sie drin.)*

---

## Beschreibung

**EN:** When an editor pastes a YouTube URL, WordPress turns it into an iframe — and on every page view, before the visitor has been offered any choice, their browser contacts the provider. Measured on a plain GET to `www.youtube.com/embed/…` with no playback and no scripts run: five cookies, two of them ~18-month identifiers. The same request on `www.youtube-nocookie.com` sets zero.

**DE:** Wer eine YouTube-URL in den Editor einfügt, bekommt von WordPress ein Iframe – und bei jedem Seitenaufruf kontaktiert der Browser des Besuchers den Anbieter, bevor diesem irgendeine Wahl gelassen wurde. Gemessen bei einem einfachen GET auf `www.youtube.com/embed/…`, ohne Wiedergabe und ohne ausgeführte Skripte: fünf Cookies, zwei davon Kennungen mit rund 18 Monaten Laufzeit. Dieselbe Anfrage an `www.youtube-nocookie.com` setzt keines.

**EN:** Calucon Third-Party Embed Gate replaces third-party embeds with a server-rendered placeholder until the visitor clicks to load them — the two-click pattern (Zwei-Klick-Lösung). Nothing third-party is contacted before that click: no script, no iframe, no thumbnail, no preconnect. Nothing is stored on the visitor's device before that click either — including by this plugin.

**DE:** Calucon Third-Party Embed Gate ersetzt Einbettungen von Drittanbietern durch einen serverseitig gerenderten Platzhalter, bis der Besucher sie per Klick lädt – die Zwei-Klick-Lösung. Vor diesem Klick wird kein Drittanbieter kontaktiert: kein Skript, kein Iframe, kein Vorschaubild, kein Preconnect. Und vor diesem Klick wird auch nichts auf dem Gerät des Besuchers gespeichert – auch nicht von diesem Plugin.

**EN:** See it in action on the [live demo](…), or read the details on the [plugin page](…).

**DE:** Sehen Sie sich die [Live-Demo](https://calucon.de/third-party-embed-gate-showcase/) an oder lesen Sie die Details auf der [Plugin-Seite](https://calucon.de/third-party-embed-gate/).

### Was es tut

**EN:** Gates third-party iframes, embed SDK scripts and legacy `<embed>`/`<object>` markup in post content, blocks, widgets, comments and archive descriptions — including HTML that has been minified by caching plugins, where most implementations silently fail, and lazy-loaded markup that parks the real URL in a `data-src` attribute.

**DE:** Sperrt Iframes von Drittanbietern, Einbettungs-Skripte und altes `<embed>`/`<object>`-Markup in Beitragsinhalten, Blöcken, Widgets, Kommentaren und Archivbeschreibungen – auch in HTML, das ein Caching-Plugin minifiziert hat, woran die meisten Umsetzungen still scheitern, und in Lazy-Loading-Markup, das die echte URL in einem `data-src`-Attribut parkt.

**EN:** Gates content delivered over AJAX and the REST API to visitors ("load more", infinite scroll), while editors always see the original markup.

**DE:** Sperrt auch Inhalte, die per AJAX oder REST-API an Besucher ausgeliefert werden („Mehr laden“, Endlos-Scrollen) – Redakteure sehen dagegen immer das ursprüngliche Markup.

**EN:** Gates by host, not by a provider allowlist: an unknown third-party iframe is gated by default.

**DE:** Sperrt anhand des Hosts, nicht anhand einer Anbieterliste: Ein unbekanntes Iframe eines Drittanbieters wird standardmäßig gesperrt.

**EN:** Ships a descriptor for almost every embed type WordPress offers out of the box — a proper name, an icon, a privacy-policy link and a working no-JavaScript link — plus the loader scripts and stylesheets those embeds bring with them. The few that are not named yet are listed in the FAQ; they are gated all the same.

**DE:** Bringt für fast jeden Einbettungstyp, den WordPress von Haus aus kennt, einen eigenen Eintrag mit – richtiger Name, Symbol, Link zur Datenschutzerklärung und ein funktionierender Link ohne JavaScript – dazu die Loader-Skripte und Stylesheets, die diese Einbettungen mitbringen. Die wenigen, die noch keinen Namen haben, stehen in den FAQ; gesperrt werden sie trotzdem.

**EN:** Loads from privacy-preserving endpoints after the click where they exist: `youtube-nocookie.com` (measured: 0 cookies instead of 5), Vimeo with `dnt=1`.

**DE:** Lädt nach dem Klick von datenschutzfreundlichen Adressen, wo es sie gibt: `youtube-nocookie.com` (gemessen: 0 statt 5 Cookies), Vimeo mit `dnt=1`.

**EN:** Renders the placeholder server-side, so a visitor without JavaScript still gets a real, working link to the content.

**DE:** Rendert den Platzhalter serverseitig – auch ein Besucher ohne JavaScript bekommt also einen echten, funktionierenden Link zum Inhalt.

**EN:** Rebuilds embeds from an attribute safelist — `sandbox` is preserved, `autoplay` never survives, inline styles and event handlers are never copied.

**DE:** Baut Einbettungen aus einer Positivliste von Attributen neu auf – `sandbox` bleibt erhalten, `autoplay` überlebt nie, Inline-Styles und Event-Handler werden nie übernommen.

**EN:** Strips `preconnect`/`dns-prefetch`/`preload`/`prefetch` resource hints pointing at gated providers and their CDN hosts (`i.ytimg.com`, `pbs.twimg.com`, …).

**DE:** Entfernt Resource Hints (`preconnect`/`dns-prefetch`/`preload`/`prefetch`), die auf gesperrte Anbieter und deren CDN-Hosts zeigen (`i.ytimg.com`, `pbs.twimg.com`, …).

**EN:** Removes embeds from feeds and excerpts instead of showing a meaningless placeholder; a plain fallback link to the content stays for feed readers.

**DE:** Entfernt Einbettungen aus Feeds und Auszügen, statt dort einen sinnlosen Platzhalter zu zeigen; für Feed-Leser bleibt ein einfacher Link zum Inhalt.

**EN:** Per-block override in the editor: gate a specific embed always, never, or per the site default.

**DE:** Überschreiben pro Block im Editor: eine bestimmte Einbettung immer sperren, nie sperren oder nach Website-Standard behandeln.

**EN:** Optional poster image behind the consent panel, chosen per embed from your media library — served from your own site, never fetched from the provider. Per-embed button and notice text in the block editor, too.

**DE:** Optionales Posterbild hinter dem Platzhalter, pro Einbettung aus Ihrer Mediathek gewählt – ausgeliefert von Ihrer eigenen Website, nie beim Anbieter geholt. Button- und Hinweistext lassen sich im Block-Editor ebenfalls pro Einbettung setzen.

**EN:** German included: the plugin's own texts ship translated for every German locale WordPress offers …

**DE:** Deutsch ist dabei: Die Texte des Plugins werden für jede deutsche Sprachvariante mitgeliefert, die WordPress anbietet – Deutschland (du und Sie), Österreich und Schweiz (mit ss statt ß) – Platzhaltertexte, Einstellungsseite und Block-Editor gleichermaßen.

**EN:** Multilingual sites: the texts you type (per-provider and per-block notices and button labels, provider privacy-policy URLs, your own providers' names) are registered for WPML and Polylang via a shipped wpml-config.xml.

**DE:** Mehrsprachige Websites: Die Texte, die Sie selbst eingeben (Hinweise und Button-Beschriftungen pro Anbieter und pro Block, Datenschutz-URLs der Anbieter, Namen Ihrer eigenen Anbieter), sind über die mitgelieferte wpml-config.xml für WPML und Polylang zur Übersetzung angemeldet.

**EN:** Optional, off by default: remember consent in the visitor's browser (per embed, per provider, or for all embeds; session or with an expiry), with a withdrawal control via the `[calucon_embed_gate_withdraw]` shortcode.

**DE:** Optional, standardmäßig aus: die Einwilligung im Browser des Besuchers merken (pro Einbettung, pro Anbieter oder für alle Einbettungen; für die Sitzung oder mit Ablaufdatum), samt Widerrufsmöglichkeit über den Shortcode `[calucon_embed_gate_withdraw]`.

**EN:** Optional, off by default: a bridge to your consent platform. When a tested platform (WP Consent API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, Real Cookie Banner) reports consent for the embeds' category, gated embeds load without a second click — and a withdrawal there re-gates them. The bridge only reads the platform's state; with an untested platform, or when the platform gives no answer, gating stands unchanged.

**DE:** Optional, standardmäßig aus: eine Brücke zu Ihrer Consent-Plattform. Meldet eine getestete Plattform (WP Consent API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, Real Cookie Banner) eine Einwilligung für die Kategorie der Einbettungen, laden gesperrte Einbettungen ohne zweiten Klick – und ein Widerruf dort sperrt sie wieder. Die Brücke liest nur den Zustand der Plattform; bei einer nicht getesteten Plattform und immer dann, wenn die Plattform keine Antwort gibt, bleibt die Sperre unverändert bestehen.

**EN:** Accessible placeholder: named group, a real button, visible focus, sufficient contrast, focus kept after activation. Zero axe-core violations in CI.

**DE:** Barrierefreier Platzhalter: benannte Gruppe, ein echter Button, sichtbarer Fokus, ausreichender Kontrast, Fokus bleibt nach der Aktivierung erhalten. Null axe-core-Verstöße in der CI.

**EN:** Never phones home. The plugin makes no outbound request from your server or your visitors' browsers, on any path, for any reason.

**DE:** Funkt nie nach Hause. Das Plugin stellt keine ausgehende Anfrage – weder von Ihrem Server noch aus den Browsern Ihrer Besucher, auf keinem Weg und aus keinem Grund.

### Was es nicht ist

**EN:** Calucon Third-Party Embed Gate is a technical measure. It is not a consent management platform, it does not produce consent records for accountability purposes, it does not scan your site, and it does not make legal claims about your site. What it technically does: it prevents the embed providers' requests until the visitor acts, and the click is scoped to the embed (or, if you enable memory, the scope you configure). You remain responsible for your privacy policy, which still has to name the providers you embed from, and for your legal bases. If you need a documented consent record, you need a consent management platform.

**DE:** Calucon Third-Party Embed Gate ist eine technische Maßnahme. Es ist keine Consent-Management-Plattform, es erzeugt keine Einwilligungsnachweise für Rechenschaftszwecke, es durchsucht Ihre Website nicht und es trifft keine rechtlichen Aussagen über Ihre Website. Was es technisch tut: Es verhindert die Anfragen der Einbettungsanbieter, bis der Besucher handelt, und der Klick gilt für die eine Einbettung (oder, wenn Sie das Merken aktivieren, für den von Ihnen eingestellten Bereich). Verantwortlich bleiben Sie – für Ihre Datenschutzerklärung, die weiterhin die Anbieter nennen muss, von denen Sie einbetten, und für Ihre Rechtsgrundlagen. Wer einen dokumentierten Einwilligungsnachweis braucht, braucht eine Consent-Management-Plattform.

---

## Installation

**EN:** Calucon Third-Party Embed Gate works the moment it is activated …

**DE:** Calucon Third-Party Embed Gate wirkt, sobald es aktiviert ist – es sperrt Einbettungen von Drittanbietern von Haus aus, ohne Konfiguration, ohne Konto und ohne externen Dienst.

**EN:** 1. In your WordPress admin, go to **Plugins → Add New** …

**DE:** 1. Gehen Sie im WordPress-Adminbereich zu **Plugins → Installieren**, suchen Sie nach „Calucon Third-Party Embed Gate“, klicken Sie auf **Jetzt installieren** und dann auf **Aktivieren**. Aus einer heruntergeladenen ZIP-Datei installieren Sie stattdessen über **Plugins → Installieren → Plugin hochladen**: Datei wählen, installieren, aktivieren.

**EN:** 2. That is all that is required …

**DE:** 2. Mehr ist nicht nötig. Ihre bestehenden Einbettungen werden im Frontend jetzt durch einen Platzhalter zum Anklicken ersetzt, und vor dem Klick des Besuchers wird kein Drittanbieter kontaktiert. Redakteure sehen im Block-Editor weiterhin die normale Einbettung – am Schreiben ändert sich also nichts.

**EN:** 3. Optional: open **Settings → Calucon Third-Party Embed Gate** …

**DE:** 3. Optional: Unter **Einstellungen → Calucon Third-Party Embed Gate** lassen sich Darstellung, Verhalten pro Anbieter, Erkennungsregeln, das Merken der Einwilligung und die optionale Brücke zur Consent-Plattform anpassen. Für den Schutz ist nichts davon nötig – die Voreinstellungen sperren alles, was von Drittanbietern kommt.

**EN:** If you turn on consent memory and want to offer visitors a way to take it back …

**DE:** Wenn Sie das Merken der Einwilligung aktivieren und Besuchern einen Weg zurück anbieten möchten, fügen Sie den Block „Einwilligungen widerrufen“ ein oder setzen Sie den Shortcode `[calucon_embed_gate_withdraw]` auf Ihre Datenschutzseite.

**EN:** **Requirements:** WordPress 5.9 or newer and PHP 7.4 or newer …

**DE:** **Voraussetzungen:** WordPress 5.9 oder neuer und PHP 7.4 oder neuer. Kein Build-Schritt, keine Laufzeit-Abhängigkeiten und keine ausgehende Anfrage von Ihrer Website, auf keinem Weg.

---

## Häufige Fragen

**EN:** Does this make my site GDPR compliant?

**DE:** Macht das meine Website DSGVO-konform?

**DE (Antwort):** Das kann kein Plugin behaupten, und dieses behauptet es nicht. Calucon Third-Party Embed Gate setzt eine technische Maßnahme um: Es verhindert Anfragen an Einbettungsanbieter – und die Speicherung, die diese auf dem Gerät des Besuchers auslösen –, bis der Besucher den Inhalt ausdrücklich anfordert. Ob die Verarbeitung auf Ihrer Website insgesamt rechtmäßig ist, hängt von Dingen ab, die ein Plugin nicht wissen kann. Der Hintergrund – § 25 TDDDG bzw. Art. 5 Abs. 3 ePrivacy-Richtlinie für die Speicherung auf Endgeräten, Art. 6 Abs. 1 lit. a DSGVO für die Verarbeitung nach dem Klick – ist in der Dokumentation beschrieben, und Ihre Datenschutzerklärung muss weiterhin die Anbieter nennen, die Sie einsetzen.

**EN:** Why is there no cookie banner?

**DE:** Warum gibt es kein Cookie-Banner?

**DE (Antwort):** Weil es beim Seitenaufruf nichts anzukündigen gibt. Wenn nichts von Drittanbietern lädt, bevor der Besucher es anfordert, gibt es beim Seitenaufruf auch keine Speicherung von Drittanbietern, in die eingewilligt werden müsste. Die Einwilligung ist der Klick – und gilt für die eine Einbettung, zu der er gehört.

**EN:** Is the plugin available in German?

**DE:** Gibt es das Plugin auf Deutsch?

**DE (Antwort):** Ja. Deutsch wird für alle fünf deutschen Sprachvarianten mitgeliefert, die WordPress anbietet – Deutschland informell und formell („de_DE“, „de_DE_formal“), Österreich („de_AT“) sowie Schweiz formell und informell („de_CH“, „de_CH_informal“, mit ss statt ß) – und deckt alles ab, was eine Person liest: den Platzhalter, den Ihre Besucher sehen, die Einstellungsseite und die Steuerelemente im Block-Editor. Stellen Sie die Sprache Ihrer Website ein, der Rest folgt. Weitere Sprachen sind über translate.wordpress.org willkommen; eine Übersetzung von dort hat Vorrang vor der mitgelieferten.

**EN:** Does a visitor have to click every single time?

**DE:** Muss ein Besucher wirklich jedes Mal klicken?

**DE (Antwort):** Standardmäßig ja: einmal pro Einbettung, auf jeder Seite, und es wird nichts auf dem Gerät des Besuchers gespeichert, um sich das zu merken. Wenn Ihnen das zu viel Reibung ist: Unter Einstellungen → Calucon Third-Party Embed Gate → Einwilligung merken lässt sich die Entscheidung im Browser des Besuchers speichern – für die eine Einbettung, für alles von diesem Anbieter oder für alle Einbettungen – entweder bis der Browser geschlossen wird oder für eine von Ihnen gewählte Anzahl Tage. Die Option ist standardmäßig aus und speichert nichts vor dem ersten Klick des Besuchers. Wenn Sie sie aktivieren, geben Sie Besuchern einen Weg zurück: Der Block „Einwilligungen widerrufen“ oder der Shortcode `[calucon_embed_gate_withdraw]` löscht das Gespeicherte.

**EN:** I already run a cookie banner (Complianz, Cookiebot, …). Do they fight?

**DE:** Ich habe schon ein Cookie-Banner (Complianz, Cookiebot, …). Kommen sich die beiden in die Quere?

**DE (Antwort):** Nein. Von Haus aus ignoriert Calucon Third-Party Embed Gate das Banner und sperrt weiter – Besucher sehen Ihr Banner für dessen Kategorien und den Platzhalter für Einbettungen, und nichts blockiert doppelt (der Platzhalter enthält kein Iframe und kein Skript, das der Blocker eines Banners abfangen könnte). Wenn Ihnen eine Entscheidung statt zweier lieber ist, aktivieren Sie die Brücke zur Consent-Plattform unter Einstellungen → Calucon Third-Party Embed Gate → Einwilligung merken: Eine Einwilligung, die Ihr Besucher in der Plattform gibt, lädt die Einbettungen dann automatisch, und ein Widerruf dort sperrt sie wieder. Die Brücke funktioniert nur mit den auf dieser Seite genannten Plattformen – bei jeder anderen hält sie sich heraus und die Sperre bleibt bestehen. Soll für einen bestimmten Anbieter lieber der Blocker Ihrer Plattform greifen, schalten Sie diesen Anbieter unter „Anbieter“ ab, dann tritt Calucon Third-Party Embed Gate dafür zurück.

**EN:** Is Google Consent Mode v2 supported?

**DE:** Wird Google Consent Mode v2 unterstützt?

**DE (Antwort):** Consent Mode wird bewusst weder gelesen noch geschrieben. Es ist ein Signal, das Consent-Plattformen an Googles Tags senden; Google veröffentlicht keine Schnittstelle, über die andere Skripte es lesen könnten, und kein Consent-Mode-Signal steuert Iframes wie YouTube-Einbettungen. Die Brücke verbindet sich stattdessen mit der Consent-Plattform selbst – also mit der Quelle, aus der Consent Mode seinen Zustand bezieht –, was der verlässliche Weg ist, dieselbe Entscheidung des Besuchers zu berücksichtigen. Calucon Third-Party Embed Gate sendet außerdem nie `gtag('consent', …)`-Updates: Ein Klick auf eine Einbettung ist eine Einwilligung für diese Einbettung, keine websiteweite Marketing-Einwilligung – sie als solche zu melden wäre schlicht falsch.

**EN:** An embed from my page builder is not being gated

**DE:** Eine Einbettung aus meinem Page-Builder wird nicht gesperrt

**DE (Antwort):** Page-Builder rendern außerhalb der Inhaltsfilter von WordPress. Aktivieren Sie „Die gesamte Seitenausgabe sperren“ unter Einstellungen → Calucon Third-Party Embed Gate → Erkennung. Die Option ist standardmäßig aus, weil das Puffern der gesamten Seite mit anderen puffernden Plugins kollidieren kann.

**EN:** The placeholder looks unstyled after an update

**DE:** Der Platzhalter sieht nach einem Update ungestylt aus

**DE (Antwort):** Wenn Ihre Minifizierung CSS von einer URL ausliefert, die sich mit dem Dateiinhalt nicht ändert, können Browser das alte Stylesheet lange behalten. Ein harter Reload behebt das; das Plugin kann es nicht.

**EN:** Does `loading="lazy"` on an iframe count as consent?

**DE:** Zählt `loading="lazy"` an einem Iframe als Einwilligung?

**DE (Antwort):** Nein. Lazy Loading verschiebt die Anfrage auf den Moment des Scrollens – gestellt wird sie trotzdem ohne Einwilligung. Calucon Third-Party Embed Gate sperrt Lazy-Iframes wie alle anderen.

**EN:** How do I report a security issue?

**DE:** Wie melde ich ein Sicherheitsproblem?

**DE (Antwort):** Bitte vertraulich – über die private Sicherheitsmeldung („private vulnerability reporting“) im Plugin-Repository auf GitHub (https://github.com/Calucon/calucon-third-party-embed-gate/security/advisories/new), nicht in einem öffentlichen Issue oder Support-Thread. Die SECURITY.md im Repository beschreibt, was zählt: neben den üblichen Klassen ist jeder Weg, eine Seite vor dem Klick einen Drittanbieter kontaktieren zu lassen, eine Sicherheitslücke.

**EN:** Which embeds does it recognise by name?

**DE:** Welche Einbettungen erkennt es namentlich?

**DE (Antwort):** *(Die Anbieterliste selbst bleibt unübersetzt – Eigennamen. Der einleitende Satz:)* Namentlich erkannt werden derzeit:

**DE (Schlusssatz):** Manche dieser Einbettungen bringen ein Loader-Skript oder Stylesheets mit dem Player mit (VideoPress, Scribd, Wolfram Cloud). Diese werden zusammen mit der Einbettung gesperrt, zu der sie gehören, und laden mit demselben Klick – nicht davor.

**EN:** Something on my site is gated and I want it to load normally

**DE:** Etwas auf meiner Website ist gesperrt und soll normal laden

**DE (Antwort):** Öffnen Sie Einstellungen → Calucon Third-Party Embed Gate → Anbieter und klicken Sie auf „Nachsehen, was auf meiner Website läuft“. Der Scan listet jede Einbettung auf, die er in Ihren neuesten Beiträgen und Seiten findet, samt der Adresse, die sie kontaktieren würde. Neben jeder können Sie sie entweder benennen – die Sperre bleibt dann bestehen, der Platzhalter bekommt aber einen richtigen Namen und ein Symbol – oder durchlassen, womit ihre Einbettungen für jeden Besucher ohne Platzhalter laden. So oder so müssen Sie nie selbst einen Hostnamen herausfinden, und nichts ändert sich, bis Sie speichern. Von Ihnen durchgelassene Hosts bleiben oben auf derselben Seite aufgelistet und lassen sich dort mit einem Klick wieder sperren.

**EN:** A provider offers both an embed code and a script — which should I paste?

**DE:** Ein Anbieter bietet Einbettungscode und Skript an – was soll ich nehmen?

**DE (Antwort):** Gesperrt wird beides, es ist also keine Datenschutzfrage, sondern eine der Darstellung: Nehmen Sie den einfachen `<iframe>`-Einbettungscode, wo der Anbieter einen anbietet. Ein Iframe rendert sich selbst; ein Loader-Skript muss die Einbettung erst finden und zeichnen, und manche Anbieter-Skripte tun das nur, während die Seite geparst wird – nach dem Klick des Besuchers bleiben sie dann leer, mit oder ohne dieses Plugin. Bleibt eine skriptbasierte Einbettung nach dem Laden leer, probieren Sie den Iframe-Code des Anbieters.

**EN:** Can I add a provider that is not in the list?

**DE:** Kann ich einen Anbieter ergänzen, der nicht in der Liste steht?

**DE (Antwort):** Ja, ohne Code: Anbieter → *Eigene Anbieter* nimmt einen Namen, die Einbettungs-Hosts (einen pro Zeile) und optional Skript-Hosts sowie die Art der Einbettung entgegen, die das Button-Symbol bestimmt. Nach dem Speichern erscheint der Eintrag in der Anbietertabelle mit eigenem Hinweistext, Button-Text und Datenschutz-Link. Unbekannte Hosts werden ohnehin gesperrt – ein eigener Anbieter gibt einem solchen Host nur einen richtigen Namen und eigene Texte. Hosts, um die sich die mitgelieferten Anbieter kümmern, bleiben bei diesen, und eigene Anbieter sind immer gesperrt; um einen Host durchzulassen, ist die Liste „Diese Hosts nie sperren“ unter Erkennung der richtige Ort.

**EN:** Can placeholders link the provider's privacy policy?

**DE:** Können Platzhalter die Datenschutzerklärung des Anbieters verlinken?

**DE (Antwort):** Ja: Ein Häkchen im Reiter „Anbieter“ ergänzt in jedem Platzhalter einen Link auf die Datenschutzseite des jeweiligen Anbieters, damit ein Besucher vor dem Anfordern nachlesen kann, was das Laden bedeutet. Standardmäßig ist das aus. Pro Anbieter lässt sich eine abweichende URL setzen (etwa eine deutschsprachige Seite). Der Link ist reines Markup – durch das Anzeigen wird beim Anbieter nichts abgerufen.

**EN:** Do I need the Content-Security-Policy section?

**DE:** Brauche ich den Abschnitt zur Content-Security-Policy?

**DE (Antwort):** Nur, wenn Ihre Website einen Content-Security-Policy-Header sendet – die meisten WordPress-Websites tun das nicht. Der Abschnitt unter Status und Werkzeuge kann Ihre eigene Startseite darauf prüfen (aus Ihrem Browser heraus, nichts verlässt Ihre Website) und sagt Ihnen, ob die aktivierten Anbieter bereits erlaubt sind; falls nicht, listet er die Zeilen auf, die zu ergänzen sind.

**EN:** Can I change how the placeholder looks without writing CSS?

**DE:** Kann ich das Aussehen des Platzhalters ohne CSS ändern?

**DE (Antwort):** Ja. Der Reiter „Darstellung“ bietet Schnellstile, Farben, die der Palette Ihres Themes folgen können, sowie Einstellungen für Ecken, Rahmen, Schatten, Abstände, Button, Posterbild und Dunkelmodus – mit Live-Vorschau und automatischer Lesbarkeitsprüfung. Eigenes CSS wirkt weiterhin darüber: Der Platzhalter stellt CSS-Custom-Properties bereit und lässt sich per Template überschreiben (siehe docs/customizing.md im Plugin-Ordner).

---

## Externe Dienste

**EN:** This plugin makes no request to any external service, on any page, at any time …

**DE:** Dieses Plugin stellt keine Anfrage an einen externen Dienst – auf keiner Seite, zu keinem Zeitpunkt. Es kontaktiert keine API, lädt kein entferntes Skript, keine Schrift, kein Bild und keine Update-Prüfung und sendet keine Telemetrie. Sein ganzer Zweck ist die Gegenrichtung: Es verhindert, dass Ihre Seiten Einbettungsanbieter kontaktieren.

**EN:** Third-party content enters the picture only after a visitor explicitly clicks the "Load" button …

**DE:** Inhalte von Drittanbietern kommen erst ins Spiel, wenn ein Besucher ausdrücklich auf den „Laden“-Button eines Platzhalters klickt. In diesem Moment lädt der Browser des Besuchers diese eine Einbettung beim Anbieter (zum Beispiel YouTube, Vimeo oder Google Maps) – genau so, wie es ohne dieses Plugin geschehen wäre, nur eben auf Wunsch des Besuchers statt automatisch. Jeder Platzhalter nennt den Anbieter und verlinkt – wenn der optionale Link unter „Anbieter“ aktiviert ist – dessen bekannte Datenschutzerklärung schon vor dem Klick; die Hostnamen der Anbieter im Quelltext des Plugins existieren ausschließlich dazu, solche Inhalte zu erkennen und zu sperren. Das Plugin selbst sendet keine Daten irgendwohin.

---

## Screenshots

1. **DE:** Eine gesperrte YouTube-Einbettung, wie ein Besucher sie sieht: ein serverseitig gerenderter Platzhalter mit benannter Gruppe, einem echten „Laden“-Button und einem funktionierenden Ersatzlink. Vor dem Klick des Besuchers wird beim Anbieter nichts angefragt.
2. **DE:** Die Darstellungseinstellungen – Schnellstile, Farben, die der Theme-Palette folgen oder eigene sind, und Abschnitte für Form, Button, Posterbild, Widerrufs-Button und Dunkelmodus – mit Live-Vorschau des echten Platzhalters und automatischer Lesbarkeitsprüfung, die jedes Farbpaar unterhalb des Mindestkontrasts von 4,5:1 markiert.
3. **DE:** Der Inhalts-Scan unter Status und Werkzeuge: jede Einbettung in Ihren neuesten Beiträgen und Seiten, die Adresse, die sie kontaktieren würde, und ob sie gesperrt ist – mit einem Klick, um einem unbekannten Host einen richtigen Namen zu geben oder ihn durchzulassen, ohne selbst eine Adresse herauszufinden. Nichts ändert sich, bis Sie speichern.
4. **DE:** Der Reiter „Anbieter“: Anbieter gruppiert nach Art der Einbettung, mit Filterfeld – ein/aus pro Anbieter, datenschutzfreundliche Ladevarianten, eigene Hinweis- und Button-Texte, der Datenschutz-Link samt eigener URL pro Anbieter und Ihre eigenen Anbieter. Ohne Code.
5. **DE:** Die Steuerung pro Einbettung im Block-Editor: eine bestimmte Einbettung immer sperren, nie sperren oder nach Website-Standard behandeln, ein optionales Posterbild aus der eigenen Mediathek setzen und dieser einen Einbettung eigenen Button- und Hinweistext geben.
6. **DE:** Der Abschnitt zur Content-Security-Policy: was eine solche Richtlinie überhaupt ist, ob Ihre Website bereits eine sendet, welche Zeilen für die von Ihnen aktivierten Anbieter zu ergänzen sind und welcher Anbieter welchen Host braucht.

---

## Upgrade Notice (0.12.0)

**DE:** Bringt Deutsch für alle fünf deutschen Sprachvarianten – den Platzhalter, den Ihre Besucher sehen, die Einstellungsseite und die Editor-Steuerung. Stellen Sie die Sprache Ihrer Website auf Deutsch, der Rest folgt. Sonst ändert sich nichts.

---

## Abschnittsüberschriften

Diese kurzen Strings listet GlotPress einzeln auf:

**EN:** Description → **DE:** Beschreibung
**EN:** **What it does** → **DE:** **Was es tut**
**EN:** **What it is not** → **DE:** **Was es nicht ist**
**EN:** **Customisation** → **DE:** **Anpassung**
**EN:** Installation → **DE:** Installation
**EN:** Frequently Asked Questions → **DE:** Häufige Fragen
**EN:** External services → **DE:** Externe Dienste
**EN:** Screenshots → **DE:** Screenshots
**EN:** Upgrade Notice → **DE:** Update-Hinweis
**EN:** Changelog → **DE:** Änderungsprotokoll

Der String **„Calucon Third-Party Embed Gate“** (Priorität hoch) ist der
Plugin-Name und bleibt unübersetzt.

---

## Anpassung (die fünf Aufzählungspunkte)

**EN:** Tabbed settings screen (Providers / Detection / Appearance / Consent memory / Status & tools): …

**DE:** Einstellungsseite mit Reitern (Anbieter / Erkennung / Darstellung / Einwilligung merken / Status und Werkzeuge): eigene Anbieter (Name + Hosts, ohne Code), ein/aus pro Anbieter, datenschutzfreundliche Variante ein/aus, eigener Hinweis- und Button-Text, ein optionaler Link zur Datenschutzerklärung des Anbieters in jedem Platzhalter (standardmäßig aus, ein Häkchen schaltet ihn ein); Listen für eigene Hosts, nie zu sperrende und immer zu sperrende Hosts; Regel-Schalter samt optionaler Sperre für Bilder von Drittanbietern; Darstellungs-Vorlagen, Eckenformen mit eigenem Radius, Rahmenbreite und -farbe, Schatten, Abstände, Button-Größe/-Stil/-Breite/-Hover, ein optionales Symbol passend zur Art der Einbettung, Größe des Hinweistexts, Ausrichtung des Platzhalters, Linkfarbe, Platzierung und Abdunkeln des Posterbilds, Stile für den Widerrufs-Button und optionale Farben für den Dunkelmodus – in Abschnitte gegliedert, mit Schnellstilen, Farbwählern, Live-Vorschau (dunkle Seite, Posterbild, Smartphone-Breite), Zurücksetzen mit einem Klick und automatischer Lesbarkeitsprüfung, ganz ohne CSS; optionales Puffern der gesamten Seite für Page-Builder; Einwilligung merken; ein erzeugter Content-Security-Policy-Schnipsel; eine Kompatibilitätsübersicht (erkanntes Cache-Plugin, Consent-Plattform, Page-Builder – und was das Plugin jeweils tut); ein Scan der aktuellen Inhalte, der jeden gefundenen Host benennen oder durchlassen kann, ohne dass Sie eine Adresse tippen und ohne dass vor dem Speichern etwas geschrieben wird.

**EN:** Theme override: copy `templates/placeholder.php` to `{your-theme}/calucon-embed-gate/placeholder.php`.

**DE:** Theme-Override: `templates/placeholder.php` nach `{your-theme}/calucon-embed-gate/placeholder.php` kopieren.

**EN:** CSS custom properties on `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …) for restyling without specificity wars.

**DE:** CSS-Custom-Properties auf `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …), um ohne Spezifitäts-Kämpfe umzugestalten.

**EN:** WP-CLI: `wp calucon-embed-gate scan` … and `wp calucon-embed-gate providers`; the shipped `docs/customizing.md` …

**DE:** WP-CLI: `wp calucon-embed-gate scan` (ist jede Einbettung gesperrt? `--format=json` für CI und Automatisierung) und `wp calucon-embed-gate providers`; die mitgelieferte `docs/customizing.md` ist eine in sich geschlossene Anpassungs-Referenz für Entwickler und KI-Agenten.

**EN:** Documented filters: `calucon_embed_gate_providers`, … Adding a provider is a ten-line filter in `functions.php`.

**DE:** Dokumentierte Filter: `calucon_embed_gate_providers`, `calucon_embed_gate_provider_for_url`, `calucon_embed_gate_should_gate`, `calucon_embed_gate_is_own_host`, `calucon_embed_gate_own_hosts`, `calucon_embed_gate_placeholder_html`, `calucon_embed_gate_payload`, `calucon_embed_gate_note_text`, `calucon_embed_gate_action_text`, `calucon_embed_gate_fallback_url`, `calucon_embed_gate_www_equivalence`, `calucon_embed_gate_cmp_config`, `calucon_embed_gate_asset_version`, `calucon_embed_gate_the_content_priority`, `calucon_embed_gate_render_block_priority` sowie die Aktionen `calucon_embed_gate_before_render`, `calucon_embed_gate_embed_gated` und `calucon_embed_gate_flush_caches`. Zu jedem Hook sind Signatur, Auslösezeitpunkt und Rückgabewert in `docs/customizing.md` dokumentiert, die im Plugin mitgeliefert wird (wp-content/plugins/calucon-third-party-embed-gate/docs/customizing.md) und auf GitHub lesbar ist. Einen Anbieter hinzuzufügen ist ein Filter von zehn Zeilen in der `functions.php`.

---

## „Welche Einbettungen erkennt es namentlich?“ – die beiden fehlenden Absätze

**EN:** Videos: YouTube, Vimeo, Dailymotion, TED, VideoPress and WordPress.tv, TikTok. Audio: … 3D: Matterport, Sketchfab.

**DE:** Videos: YouTube, Vimeo, Dailymotion, TED, VideoPress und WordPress.tv, TikTok. Audio: Spotify, SoundCloud, Apple Music, Mixcloud, Pocket Casts. Karten: Google Maps, OpenStreetMap. Social-Media-Beiträge: X, Instagram, Facebook, Reddit, Tumblr, Bluesky, Pinterest, Imgur, GIPHY, Strava. Dokumente: Scribd, Speaker Deck, Issuu, Wolfram Cloud, Amazon Kindle, Kickstarter. Formulare und Kalender: Google Kalender, Google Formulare, Typeform, Calendly, Crowdsignal. 3D: Matterport, Sketchfab.

**EN:** Everything else is gated too — that does not depend on a list. …

**DE:** Alles andere wird ebenfalls gesperrt – das hängt nicht an einer Liste. Eine Einbettung von einem unbenannten Host bekommt denselben Platzhalter und denselben Button, benannt nach dem Host, den sie kontaktieren würde, mit einem Link zum Inhalt selbst. Ein namentlich bekannter Anbieter ergänzt lediglich den Namen, das Symbol, den Link zur Datenschutzerklärung und einen aufgeräumteren „Auf … öffnen“-Link. Ein paar der Einbettungsblöcke von WordPress selbst sind noch nicht benannt (Flickr, SmugMug, Animoto, ReverbNation, Cloudup); Sie können sie unter Anbieter → Eigene Anbieter selbst benennen.

---

## Nicht übersetzt

Das Änderungsprotokoll (der größte Teil der 166 Strings) und die älteren
Update-Hinweise bleiben Englisch. Sie werden fast nie gelesen, altern mit jedem
Release und kosten mehr Pflege, als sie einbringen. Unübersetzte Strings zeigt
wordpress.org einfach im Original.
