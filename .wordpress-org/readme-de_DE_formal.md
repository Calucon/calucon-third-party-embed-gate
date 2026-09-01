# readme.txt auf Deutsch (de_DE_formal, „Sie“)

<!-- readme.txt: 62d0ac1a72f01248 -->

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

**EN:** YouTube, Maps and social embeds load only when the visitor clicks — the two-click solution for WordPress. Nothing contacted, nothing stored before.

**DE:** YouTube, Maps und Social-Media-Einbettungen laden erst auf Klick – die Zwei-Klick-Lösung. Vorher keine Anfrage an Drittanbieter, keine Speicherung.

*(147 Zeichen — wordpress.org erlaubt 150. „Drittanbieter“ und „Zwei-Klick-Lösung“ sind die Wörter, nach denen im deutschsprachigen Raum gesucht wird; deshalb stehen sie drin.)*

---

## Beschreibung

**EN:** Every YouTube video, Google Map and Instagram post on your site contacts its provider the moment the page opens — before the visitor has agreed to anything. Calucon Third-Party Embed Gate holds those embeds behind a click-to-load placeholder. Until the visitor presses "Load", nothing is requested from the provider and nothing is stored on their device — not by the provider, and not by this plugin. That is the two-click solution (Zwei-Klick-Lösung), done properly: no cookie banner, no consent platform, no account, no subscription. It works the moment it is activated.

**DE:** Jedes YouTube-Video, jede Karte von Google Maps und jeder Instagram-Beitrag auf Ihrer Website kontaktiert seinen Anbieter, sobald die Seite geöffnet wird – noch bevor der Besucher irgendetwas zugestimmt hat. Calucon Third-Party Embed Gate hält diese Einbettungen hinter einem Platzhalter zurück, der erst auf Klick lädt. Bis der Besucher auf „Laden“ drückt, wird beim Anbieter nichts angefragt und auf seinem Gerät nichts gespeichert – weder vom Anbieter noch von diesem Plugin. Das ist die Zwei-Klick-Lösung, sauber umgesetzt: kein Cookie-Banner, keine Consent-Plattform, kein Konto, kein Abonnement. Das Plugin wirkt, sobald es aktiviert ist.

**EN:** See it on the [live demo](…) — all 36 providers on one page, 30 of them with live content, and zero third-party requests until you press a button — or read the details on the [plugin page](…).

**DE:** Sehen Sie sich die [Live-Demo](https://calucon.de/third-party-embed-gate-showcase/) an – alle 36 Anbieter auf einer Seite, 30 davon mit echten Inhalten, null Anfragen an Drittanbieter, bis Sie einen Button drücken – oder lesen Sie die Details auf der [Plugin-Seite](https://calucon.de/third-party-embed-gate/).

### Warum das wichtig ist

**EN:** A plain request to `www.youtube.com/embed/…` — no playback, no scripts run — sets six cookies, four of them identifiers that live about six months (measured August 2026). Every visitor gets them on every page with a video, whether or not they ever press play. The same request to `www.youtube-nocookie.com` sets none, and that is where this plugin loads YouTube from after the click.

**DE:** Eine einfache Anfrage an `www.youtube.com/embed/…` – ohne Wiedergabe und ohne ausgeführte Skripte – setzt sechs Cookies, vier davon Kennungen mit rund sechs Monaten Laufzeit (gemessen im August 2026). Jeder Besucher bekommt sie auf jeder Seite mit einem Video, ganz gleich, ob er die Wiedergabe je startet. Dieselbe Anfrage an `www.youtube-nocookie.com` setzt keines – und von dort lädt dieses Plugin YouTube nach dem Klick.

### So funktioniert es

**EN:** 1. You keep writing posts as before: paste a URL, WordPress makes the embed, and editors see the normal embed in the block editor.

**DE:** 1. Sie schreiben Beiträge wie bisher: URL einfügen, WordPress erzeugt die Einbettung, und im Block-Editor sehen Redakteure die gewohnte Einbettung.

**EN:** 2. Visitors see a placeholder instead — rendered on the server, so it is there before any JavaScript runs: the provider's name and icon, one sentence on what loading means, a real "Load" button, and a plain link to the content for anyone who prefers to open it there.

**DE:** 2. Besucher sehen stattdessen einen Platzhalter. Er wird auf dem Server gerendert und ist deshalb da, bevor überhaupt JavaScript läuft: Name und Symbol des Anbieters, ein Satz dazu, was das Laden bedeutet, ein echter „Laden“-Button und ein einfacher Link zum Inhalt für alle, die ihn lieber dort öffnen.

**EN:** 3. On the click, that one embed loads — from the privacy-preserving address where the provider has one. Nothing else on the page changes, and nothing loads for embeds the visitor did not ask for.

**DE:** 3. Mit dem Klick lädt genau diese eine Einbettung – von der datenschutzfreundlichen Adresse, sofern der Anbieter eine hat. Sonst ändert sich auf der Seite nichts, und für Einbettungen, die der Besucher nicht angefordert hat, lädt nichts.

### Was Sie bekommen

**EN:** Works on activation, with no configuration, no account and no external service.

**DE:** Wirkt sofort nach der Aktivierung – ohne Konfiguration, ohne Konto und ohne externen Dienst.

**EN:** Names 36 embed types — every one WordPress offers out of the box, from YouTube and Vimeo to Spotify, Google Maps, X, Instagram, TikTok and Calendly — with an icon, a notice, an optional privacy-policy link and a working no-JavaScript link. Anything it does not know is gated all the same: the plugin gates by host, not by a list, so a new tracker is never let through by accident.

**DE:** Kennt 36 Einbettungstypen namentlich – jeden, den WordPress von Haus aus anbietet, von YouTube und Vimeo über Spotify, Google Maps, X, Instagram und TikTok bis Calendly. Zu jedem gehören ein Symbol, ein Hinweistext, ein optionaler Link zur Datenschutzerklärung und ein funktionierender Link ohne JavaScript. Was das Plugin nicht kennt, wird genauso gesperrt: Maßgeblich ist der Host, nicht eine Liste – so rutscht kein neuer Tracker versehentlich durch.

**EN:** Finds the embeds your caching and optimisation plugins have already minified — attribute quotes stripped, newlines inside tags — which is where most implementations silently fail. Also lazy-loaded markup (`data-src`), the loader scripts and stylesheets some embeds bring along, and content delivered over AJAX and the REST API ("load more", infinite scroll).

**DE:** Findet auch die Einbettungen, die Ihr Caching- oder Optimierungs-Plugin schon minifiziert hat – ohne Anführungszeichen an den Attributen, mit Zeilenumbrüchen mitten im Tag. Genau daran scheitern die meisten Umsetzungen still. Ebenso erkannt werden Lazy-Loading-Markup (`data-src`), die Loader-Skripte und Stylesheets, die manche Einbettungen mitbringen, und Inhalte, die per AJAX oder REST-API nachgeladen werden („Mehr laden“, Endlos-Scrollen).

**EN:** Accessible and JavaScript-free by design: a named group, a real button, visible focus, sufficient contrast, focus kept after loading; zero axe-core violations in CI. Without JavaScript the link still works.

**DE:** Barrierefrei und auch ohne JavaScript benutzbar: benannte Gruppe, ein echter Button, sichtbarer Fokus, ausreichender Kontrast, der Fokus bleibt nach dem Laden erhalten; null axe-core-Verstöße in der CI. Ohne JavaScript führt der Link weiterhin zum Inhalt.

**EN:** Loads from privacy-preserving endpoints where they exist: `youtube-nocookie.com`, Vimeo with `dnt=1`. Rebuilds every embed from an attribute safelist — `sandbox` preserved, `autoplay` never survives — and strips the `preconnect` and `dns-prefetch` hints that would contact the provider early.

**DE:** Lädt von datenschutzfreundlichen Adressen, wo es sie gibt: `youtube-nocookie.com`, Vimeo mit `dnt=1`. Baut jede Einbettung aus einer Freigabeliste von Attributen neu auf – `sandbox` bleibt erhalten, `autoplay` überlebt nie – und entfernt Resource Hints wie `preconnect` und `dns-prefetch`, die den Anbieter vorzeitig kontaktieren würden.

**EN:** Looks like your site, without CSS: quick styles, colours that follow your theme's palette, corners, borders, shadows, button styles and dark-mode colours, with a live preview and an automatic readability check — plus a poster image per embed from your own media library, never fetched from the provider, and per-embed button and notice text in the block editor.

**DE:** Passt sich ohne CSS an Ihre Website an: Schnellstile, Farben, die der Palette Ihres Themes folgen, Ecken, Ränder, Schatten, Button-Stile und eigene Farben für den Dunkelmodus. Eine Live-Vorschau zeigt das Ergebnis, eine automatische Lesbarkeitsprüfung achtet auf den Kontrast. Dazu kommen ein Posterbild pro Einbettung aus Ihrer eigenen Mediathek – nie beim Anbieter geholt – sowie Button- und Hinweistext pro Einbettung im Block-Editor.

**EN:** Speaks German: the plugin ships translated for all five German locales (Germany du and Sie, Austria, Switzerland), and the texts you type are registered for WPML and Polylang.

**DE:** Deutsch ist dabei: Das Plugin wird für alle fünf deutschen Sprachvarianten übersetzt mitgeliefert (Deutschland du und Sie, Österreich, Schweiz), und die Texte, die Sie selbst eingeben, sind für WPML und Polylang zur Übersetzung angemeldet.

**EN:** Optional and off by default: remember the visitor's choice in their browser (per embed, per provider or for all; for the session or a number of days) with a withdrawal block and shortcode — and a bridge to your consent platform, so a consent given there loads the embeds and a withdrawal there re-gates them.

**DE:** Optional und standardmäßig aus: Die Entscheidung des Besuchers lässt sich in seinem Browser merken – pro Einbettung, pro Anbieter oder für alle Einbettungen, für die Sitzung oder für eine bestimmte Zahl von Tagen. Für den Widerruf gibt es einen Block und einen Shortcode. Dazu kommt eine Brücke zu Ihrer Consent-Plattform: Eine dort erteilte Einwilligung lädt die Einbettungen, ein Widerruf dort sperrt sie wieder.

**EN:** Never phones home. No telemetry, no update check against a private server, no remote font or script — no outbound request from your server or your visitors' browsers, on any path, for any reason.

**DE:** Funkt nie nach Hause. Keine Telemetrie, keine Update-Prüfung bei einem privaten Server, keine entfernte Schrift, kein entferntes Skript – keine ausgehende Anfrage von Ihrem Server oder aus den Browsern Ihrer Besucher, auf keinem Weg und aus keinem Grund.

### Funktioniert mit

**EN:** Caching and optimisation plugins: W3 Total Cache, WP Super Cache, LiteSpeed Cache, Autoptimize, WP Fastest Cache, SiteGround Optimizer, WP Rocket. Gating happens on the server, so the cached page is the gated one; Status & tools names the files to exclude from "delay JavaScript" and where that plugin keeps its list.

**DE:** Caching- und Optimierungs-Plugins: W3 Total Cache, WP Super Cache, LiteSpeed Cache, Autoptimize, WP Fastest Cache, SiteGround Optimizer, WP Rocket. Gesperrt wird auf dem Server, gespeichert wird also die bereits gesperrte Seite. Unter Status und Werkzeuge stehen die Dateien, die Sie von „JavaScript verzögern“ ausnehmen sollten, samt dem Ort, an dem das jeweilige Plugin seine Ausschlussliste führt.

**EN:** Consent platforms, through the optional bridge: WP Consent API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, Real Cookie Banner. The bridge only reads the platform's answer; with any other platform, or no answer, gating stands.

**DE:** Consent-Plattformen über die optionale Brücke: WP Consent API, Complianz, Cookiebot, CookieYes, Borlabs Cookie 3, Real Cookie Banner. Die Brücke liest nur die Antwort der Plattform; bei jeder anderen Plattform und ohne Antwort bleibt die Sperre bestehen.

**EN:** Page builders: Elementor's HTML and video widgets are gated out of the box. For a builder that renders outside WordPress's content filters, "Gate the whole page output" under Detection reads the finished page instead.

**DE:** Page-Builder: Die HTML- und Video-Widgets von Elementor werden von Haus aus gesperrt. Rendert ein Builder außerhalb der Inhaltsfilter von WordPress, liest das Plugin mit der Option „Die gesamte Seitenausgabe sperren“ unter Erkennung stattdessen die fertige Seite.

**EN:** Multilingual sites: WPML, Polylang, TranslatePress, Weglot.

**DE:** Mehrsprachige Websites: WPML, Polylang, TranslatePress, Weglot.

**EN:** Every month those claims are re-tested on a real WordPress against the current versions of the plugins that are free to install; the ones that are not (WP Rocket, Borlabs Cookie, WPML, Weglot, Cookiebot's banner) are tested against simulations of their documented behaviour.

**DE:** Diese Angaben werden monatlich auf einem echten WordPress mit den aktuellen Versionen der frei installierbaren Plugins nachgeprüft. Die übrigen – WP Rocket, Borlabs Cookie, WPML, Weglot und das Banner von Cookiebot – werden anhand von Simulationen ihres dokumentierten Verhaltens geprüft.

### Was es nicht ist

**EN:** Calucon Third-Party Embed Gate is a technical measure, not a consent management platform. It prevents the embed providers' requests until the visitor acts, and the click is consent for that one embed (or, with consent memory on, for the scope you configure). It does not produce consent records for accountability purposes, it does not audit your site for other trackers, and it makes no legal claim about your site. Your privacy policy still has to name the providers you embed from, and your legal bases remain yours. If you need a documented consent record, you need a consent management platform.

**DE:** Calucon Third-Party Embed Gate ist eine technische Maßnahme, keine Consent-Management-Plattform. Es verhindert die Anfragen der Einbettungsanbieter, bis der Besucher handelt, und der Klick ist die Einwilligung für diese eine Einbettung – oder, wenn das Merken der Einwilligung aktiviert ist, für den von Ihnen eingestellten Bereich. Es erzeugt keine Einwilligungsnachweise für Rechenschaftszwecke, es untersucht Ihre Website nicht auf andere Tracker und es trifft keine rechtlichen Aussagen über Ihre Website. Verantwortlich bleiben Sie: für Ihre Datenschutzerklärung, die weiterhin die Anbieter nennen muss, von denen Sie einbetten, und für Ihre Rechtsgrundlagen. Wer einen dokumentierten Einwilligungsnachweis braucht, braucht eine Consent-Management-Plattform.

---

## Installation

**EN:** 1. In your WordPress admin, go to **Plugins → Add New** …

**DE:** 1. Gehen Sie im WordPress-Adminbereich zu **Plugins → Installieren**, suchen Sie nach „Calucon Third-Party Embed Gate“, klicken Sie auf **Jetzt installieren** und dann auf **Aktivieren**. Aus einer heruntergeladenen ZIP-Datei installieren Sie stattdessen über **Plugins → Installieren → Plugin hochladen**.

**EN:** 2. That is all. Third-party embeds on the front end are now click-to-load …

**DE:** 2. Das war alles. Einbettungen von Drittanbietern laden im Frontend jetzt erst auf Klick, und vor dem Klick des Besuchers wird kein Drittanbieter kontaktiert. Redakteure sehen im Block-Editor weiterhin die normale Einbettung – am Schreiben ändert sich also nichts.

**EN:** 3. Optional: open **Settings → Calucon Third-Party Embed Gate** …

**DE:** 3. Optional: Unter **Einstellungen → Calucon Third-Party Embed Gate** lassen sich Design, Verhalten pro Anbieter, Erkennungsregeln, das Merken der Einwilligung und die Brücke zur Consent-Plattform anpassen. Für den Schutz ist nichts davon nötig.

**EN:** If you turn on consent memory, give visitors a way back …

**DE:** Wenn Sie das Merken der Einwilligung aktivieren, geben Sie Ihren Besuchern einen Weg zurück: Setzen Sie den Block „Einwilligungen widerrufen“ oder den Shortcode `[calucon_embed_gate_withdraw]` in Ihre Datenschutzerklärung.

**EN:** **Requirements:** WordPress 5.9 or newer and PHP 7.4 or newer …

**DE:** **Voraussetzungen:** WordPress 5.9 oder neuer und PHP 7.4 oder neuer. Kein Build-Schritt, keine Laufzeit-Abhängigkeiten und keine ausgehende Anfrage von Ihrer Website, auf keinem Weg.

---

## Häufige Fragen

**EN:** Does this make my site GDPR compliant?

**DE:** Macht das meine Website DSGVO-konform?

**DE (Antwort):** Das kann kein Plugin behaupten, und dieses behauptet es nicht. Calucon Third-Party Embed Gate setzt eine technische Maßnahme um: Es verhindert Anfragen an Einbettungsanbieter – und die Speicherung, die diese auf dem Gerät des Besuchers auslösen –, bis der Besucher den Inhalt ausdrücklich anfordert. Ob die Verarbeitung auf Ihrer Website insgesamt rechtmäßig ist, hängt von Dingen ab, die ein Plugin nicht wissen kann. Der Hintergrund – § 25 TDDDG bzw. Art. 5 Abs. 3 ePrivacy-Richtlinie für die Speicherung auf Endgeräten, Art. 6 Abs. 1 lit. a DSGVO für die Verarbeitung nach dem Klick – ist in der Dokumentation beschrieben, und Ihre Datenschutzerklärung muss weiterhin die Anbieter nennen, die Sie einsetzen.

**EN:** Why is there no cookie banner?

**DE:** Warum gibt es kein Cookie-Banner?

**DE (Antwort):** Weil es beim Seitenaufruf nichts anzukündigen gibt. Wenn nichts von Drittanbietern lädt, bevor der Besucher es anfordert, gibt es beim Seitenaufruf auch keine Speicherung von Drittanbietern, in die eingewilligt werden müsste. Die Einwilligung ist der Klick – und gilt für die eine Einbettung, zu der er gehört.

**EN:** I already run a cookie banner (Complianz, Cookiebot, …). Do they fight?

**DE:** Ich habe schon ein Cookie-Banner (Complianz, Cookiebot, …). Kommen sich die beiden in die Quere?

**DE (Antwort):** Nein. Von Haus aus lässt das Plugin das Banner unbeachtet und sperrt weiter: Besucher sehen Ihr Banner für dessen Kategorien und den Platzhalter für Einbettungen. Doppelt blockiert dabei nichts, denn der Platzhalter enthält kein Iframe und kein Skript, das der Blocker eines Banners abfangen könnte. Wenn Ihnen eine Entscheidung statt zweier lieber ist, aktivieren Sie die Brücke zur Consent-Plattform unter Einstellungen → Calucon Third-Party Embed Gate → Einwilligung merken: Eine in der Plattform erteilte Einwilligung lädt dann die Einbettungen, ein Widerruf dort sperrt sie wieder. Die Brücke arbeitet mit den in dieser Ansicht genannten Plattformen; bei jeder anderen hält sie sich heraus. Soll für einen bestimmten Anbieter lieber der Blocker Ihrer Plattform greifen, deaktivieren Sie diesen Anbieter unter „Anbieter“ – dann tritt das Plugin dafür zurück.

**EN:** Does a visitor have to click every single time?

**DE:** Muss ein Besucher wirklich jedes Mal klicken?

**DE (Antwort):** Standardmäßig ja: einmal pro Einbettung, auf jeder Seite, und es wird nichts auf dem Gerät des Besuchers gespeichert, um sich das zu merken. Wenn Ihnen das zu viel Reibung ist, kann „Einwilligung merken“ die Entscheidung im Browser des Besuchers speichern – für die eine Einbettung, für alles von diesem Anbieter oder für alle Einbettungen –, und zwar bis der Browser geschlossen wird oder für eine von Ihnen gewählte Anzahl Tage. Die Option ist standardmäßig aus und speichert nichts vor dem ersten Klick des Besuchers. Wenn Sie sie aktivieren, geben Sie Besuchern einen Weg zurück: Der Block „Einwilligungen widerrufen“ oder der Shortcode `[calucon_embed_gate_withdraw]` löscht das Gespeicherte.

**EN:** I use a caching or minification plugin — will this still work?

**DE:** Ich nutze ein Caching- oder Minifizierungs-Plugin – funktioniert das trotzdem?

**DE (Antwort):** Ja. Gesperrt wird auf dem Server, gespeichert wird also die bereits gesperrte Seite, und minifiziertes HTML ist eingeplant und kein Problem – der Scanner ist dafür gebaut. Auch Deferring, Zusammenfassen oder spätes Nachladen des Plugin-Skripts funktionieren.

Eine Einstellung sollten Sie kennen: „JavaScript bis zur Interaktion verzögern“ hält alle Skripte zurück, bis der Besucher die Seite zum ersten Mal berührt – und diese Interaktion wird dafür verbraucht, die Skripte einzuschalten. Sein erster Klick auf einen „Laden“-Button bewirkt dann nichts und er muss ein zweites Mal klicken. Durch den zusätzlichen Klick wird kein Drittanbieter kontaktiert, aber der Platzhalter wirkt kaputt. Unter Einstellungen → Status und Werkzeuge stehen die genauen Dateien, die Sie in die Ausschlussliste Ihres Optimierungs-Plugins eintragen, samt dem, was sich über die JavaScript-Einstellungen dieses Plugins auslesen ließ.

Werden Ihre Assets über einen CDN-Hostnamen ausgeliefert, gilt dieser als Ihr eigener: Die meisten CDN-Plugins filtern die WordPress-Funktionen, die angeben, wo Ihre Dateien liegen. Ein CDN, das stattdessen die fertige Seite umschreibt, lässt sich so nicht erkennen. Deshalb bleiben Skripte und Stylesheets, deren Pfad `/wp-content/` oder `/wp-includes/` enthält, unangetastet – ganz gleich, welcher Host sie ausliefert. Bilder sind davon nicht erfasst, was einer der Gründe dafür ist, dass Bilder von Drittanbietern standardmäßig nicht gesperrt werden.

Und wenn der Platzhalter nach einem Update ungestylt aussieht: Liefert Ihre Minifizierung CSS von einer URL aus, die lange im Cache bleibt, halten Browser womöglich am alten Stylesheet fest. Ein harter Reload behebt das; das Plugin kann es nicht.

**EN:** An embed from my page builder is not being gated

**DE:** Eine Einbettung aus meinem Page-Builder wird nicht gesperrt

**DE (Antwort):** Die HTML- und Video-Widgets von Elementor werden von Haus aus gesperrt. Andere Page-Builder rendern außerhalb der Inhaltsfilter von WordPress, an denen das Plugin standardmäßig ansetzt: Aktivieren Sie „Die gesamte Seitenausgabe sperren“ unter Einstellungen → Calucon Third-Party Embed Gate → Erkennung, dann liest das Plugin stattdessen die fertige Seite. Die Option ist standardmäßig aus, weil das Puffern der gesamten Seite mit anderen puffernden Plugins kollidieren kann.

**EN:** Something on my site is gated and I want it to load normally

**DE:** Etwas auf meiner Website ist gesperrt und soll normal laden

**DE (Antwort):** Öffnen Sie Einstellungen → Calucon Third-Party Embed Gate → Anbieter und klicken Sie auf „Prüfen, was auf meiner Website läuft“. Der Scan listet jede Einbettung in Ihren neuesten Beiträgen und Seiten auf, samt der Adresse, die sie kontaktieren würde. Neben jeder können Sie sie entweder benennen – die Sperre bleibt dann bestehen, der Platzhalter bekommt aber einen richtigen Namen und ein Symbol – oder durchlassen; dann lädt sie für jeden Besucher ohne Platzhalter. Einen Hostnamen müssen Sie sich nie selbst zusammensuchen, und nichts ändert sich, bis Sie speichern. Von Ihnen durchgelassene Hosts bleiben oben in derselben Ansicht aufgelistet und lassen sich dort mit einem Klick wieder sperren.

**EN:** Which embeds does it recognise by name?

**DE:** Welche Einbettungen erkennt es namentlich?

**DE (Antwort):** *(Die Anbieterliste selbst bleibt unübersetzt – Eigennamen. Der einleitende Satz:)* Namentlich erkannt werden derzeit:

**DE (Schlusssatz):** Manche Einbettungen bringen zum Player ein Loader-Skript oder Stylesheets mit (VideoPress, Scribd, Wolfram Cloud). Diese werden zusammen mit der Einbettung gesperrt, zu der sie gehören, und laden mit demselben Klick – nicht davor.

**EN:** Can I add a provider that is not in the list?

**DE:** Kann ich einen Anbieter ergänzen, der nicht in der Liste steht?

**DE (Antwort):** Ja, ohne Code: Anbieter → *Eigene Anbieter* nimmt einen Namen, die Einbettungs-Hosts (einen pro Zeile) und optional Skript-Hosts sowie die Art der Einbettung entgegen, die das Button-Symbol bestimmt. Nach dem Speichern erscheint der Eintrag in der Anbietertabelle mit eigenem Hinweistext, Button-Text und Datenschutz-Link. Unbekannte Hosts werden ohnehin gesperrt – ein eigener Anbieter gibt einem solchen Host nur einen richtigen Namen und eigene Texte. Hosts, um die sich die mitgelieferten Anbieter kümmern, bleiben bei diesen, und eigene Anbieter sind immer gesperrt; um einen Host durchzulassen, ist die Liste „Diese Hosts nie sperren“ unter Erkennung der richtige Ort.

**EN:** Can I change how the placeholder looks without writing CSS?

**DE:** Kann ich das Aussehen des Platzhalters ohne CSS ändern?

**DE (Antwort):** Ja. Der Tab „Design“ bietet Schnellstile, Farben, die der Palette Ihres Themes folgen können, sowie Einstellungen für Ecken, Rand, Schatten, Abstände, Button, Posterbild und Dunkelmodus – mit Live-Vorschau und automatischer Lesbarkeitsprüfung. Ein Häkchen im Tab „Anbieter“ ergänzt in jedem Platzhalter einen Link auf die Datenschutzerklärung des jeweiligen Anbieters; standardmäßig ist das aus, die URL lässt sich pro Anbieter abweichend setzen, und durch das Anzeigen des Links wird beim Anbieter nichts abgerufen. Eigenes CSS wirkt weiterhin darüber: Der Platzhalter stellt CSS-Custom-Properties bereit und lässt sich per Template überschreiben (siehe docs/customizing.md im Plugin-Ordner).

**EN:** Is the plugin available in German?

**DE:** Gibt es das Plugin auf Deutsch?

**DE (Antwort):** Ja. Deutsch wird für alle fünf deutschen Sprachvarianten mitgeliefert, die WordPress anbietet – Deutschland informell und formell („de_DE“, „de_DE_formal“), Österreich („de_AT“) sowie Schweiz formell und informell („de_CH“, „de_CH_informal“, mit ss statt ß) – und deckt alles ab, was eine Person liest: den Platzhalter, den Ihre Besucher sehen, die Einstellungsansicht und die Steuerelemente im Block-Editor. Stellen Sie die Sprache Ihrer Website ein, der Rest folgt. Weitere Sprachen sind über translate.wordpress.org willkommen; eine Übersetzung von dort hat Vorrang vor der mitgelieferten.

**EN:** Is Google Consent Mode v2 supported?

**DE:** Wird Google Consent Mode v2 unterstützt?

**DE (Antwort):** Consent Mode wird bewusst weder gelesen noch geschrieben. Es ist ein Signal, das Consent-Plattformen an Googles Tags senden; Google veröffentlicht keine Schnittstelle, über die andere Skripte es lesen könnten, und kein Consent-Mode-Signal steuert Iframes wie YouTube-Einbettungen. Die Brücke verbindet sich stattdessen mit der Consent-Plattform selbst – also mit der Quelle, aus der Consent Mode seinen Zustand bezieht –, was der verlässliche Weg ist, dieselbe Entscheidung des Besuchers zu berücksichtigen. Calucon Third-Party Embed Gate sendet außerdem nie `gtag('consent', …)`-Updates: Ein Klick auf eine Einbettung ist eine Einwilligung für diese Einbettung, keine websiteweite Marketing-Einwilligung – sie als solche zu melden wäre schlicht falsch.

**EN:** Does `loading="lazy"` on an iframe count as consent?

**DE:** Zählt `loading="lazy"` an einem Iframe als Einwilligung?

**DE (Antwort):** Nein. Lazy Loading verschiebt die Anfrage auf den Moment des Scrollens – gestellt wird sie trotzdem ohne Einwilligung. Lazy-Iframes werden gesperrt wie alle anderen auch.

**EN:** A provider offers both an embed code and a script — which should I paste?

**DE:** Ein Anbieter bietet Einbettungscode und Skript an – was soll ich nehmen?

**DE (Antwort):** Gesperrt wird beides, es ist also keine Datenschutzfrage, sondern eine der Darstellung: Nehmen Sie den einfachen `<iframe>`-Einbettungscode, wo der Anbieter einen anbietet. Ein Iframe rendert sich selbst; ein Loader-Skript muss die Einbettung erst finden und zeichnen, und manche Anbieter-Skripte tun das nur, während die Seite geparst wird – nach dem Klick des Besuchers bleiben sie dann leer, mit oder ohne dieses Plugin. Bleibt eine skriptbasierte Einbettung nach dem Laden leer, probieren Sie den Iframe-Code des Anbieters.

**EN:** Do I need the Content-Security-Policy section?

**DE:** Brauche ich den Abschnitt zur Content-Security-Policy?

**DE (Antwort):** Nur, wenn Ihre Website einen Content-Security-Policy-Header sendet – die meisten WordPress-Websites tun das nicht. Der Abschnitt unter Status und Werkzeuge kann Ihre eigene Startseite darauf prüfen (aus Ihrem Browser heraus, nichts verlässt Ihre Website) und sagt Ihnen, ob die aktivierten Anbieter bereits erlaubt sind; falls nicht, listet er die Zeilen auf, die zu ergänzen sind.

**EN:** How do I report a security issue?

**DE:** Wie melde ich ein Sicherheitsproblem?

**DE (Antwort):** Bitte vertraulich – über die private Sicherheitsmeldung („private vulnerability reporting“) im Plugin-Repository auf GitHub (https://github.com/Calucon/calucon-third-party-embed-gate/security/advisories/new), nicht in einem öffentlichen Issue oder Support-Thread. Die SECURITY.md im Repository beschreibt, was zählt: neben den üblichen Klassen ist jeder Weg, eine Seite vor dem Klick einen Drittanbieter kontaktieren zu lassen, eine Sicherheitslücke.

---

## Externe Dienste

**EN:** This plugin makes no request to any external service, on any page, at any time …

**DE:** Dieses Plugin stellt keine Anfrage an einen externen Dienst – auf keiner Seite, zu keinem Zeitpunkt. Es kontaktiert keine API, lädt kein entferntes Skript, keine Schrift, kein Bild und keine Update-Prüfung und sendet keine Telemetrie. Sein ganzer Zweck ist die Gegenrichtung: Es verhindert, dass Ihre Seiten Einbettungsanbieter kontaktieren.

**EN:** Third-party content enters the picture only after a visitor clicks the "Load" button on an embed placeholder …

**DE:** Inhalte von Drittanbietern kommen erst ins Spiel, wenn ein Besucher auf den „Laden“-Button eines Platzhalters klickt. In diesem Moment lädt der Browser des Besuchers diese eine Einbettung beim Anbieter (zum Beispiel YouTube, Vimeo oder Google Maps) – genau so, wie es ohne dieses Plugin geschehen wäre, nur eben auf Wunsch des Besuchers statt automatisch. Jeder Platzhalter nennt den Anbieter und verlinkt dessen Datenschutzerklärung schon vor dem Klick, sofern der optionale Link unter „Anbieter“ aktiviert ist. Die Hostnamen der Anbieter im Quelltext des Plugins existieren ausschließlich dazu, solche Inhalte zu erkennen und zu sperren. Das Plugin selbst sendet keine Daten irgendwohin.

---

## Screenshots

1. **DE:** Eine gesperrte YouTube-Einbettung, wie ein Besucher sie sieht: ein serverseitig gerenderter Platzhalter mit benannter Gruppe, einem echten „Laden“-Button und einem funktionierenden Ersatzlink – vor dem Klick wird beim Anbieter nichts angefragt.
2. **DE:** Die Design-Einstellungen: Schnellstile, Farben, die der Palette Ihres Themes folgen, Abschnitte für Form, Button, Posterbild, Widerrufs-Button und Dunkelmodus, eine Live-Vorschau des echten Platzhalters und eine automatische Lesbarkeitsprüfung.
3. **DE:** Der Inhalts-Scan unter Status und Werkzeuge: jede Einbettung in Ihren neuesten Beiträgen und Seiten, die Adresse, die sie kontaktieren würde, ob sie gesperrt ist, und ein Klick, um einen unbekannten Host zu benennen oder durchzulassen.
4. **DE:** Der Tab „Anbieter“: Anbieter gruppiert nach Art der Einbettung, mit Filterfeld – ein/aus pro Anbieter, datenschutzfreundliche Ladevarianten, individueller Hinweis- und Button-Text, der Link zur Datenschutzerklärung und Ihre eigenen Anbieter.
5. **DE:** Die Steuerung pro Einbettung im Block-Editor: diese Einbettung immer sperren, nie sperren oder nach Website-Standard behandeln, ein Posterbild aus der eigenen Mediathek setzen und dieser einen Einbettung eigenen Button- und Hinweistext geben.
6. **DE:** Der Content-Security-Policy-Helfer: Er prüft Ihre eigene Website auf eine solche Richtlinie, nennt die genauen Zeilen für die von Ihnen aktivierten Anbieter und zeigt, welcher Anbieter welchen Host braucht.

---

## Upgrade Notice (1.0.0)

**DE:** Behebt einen Fall, in dem ein CDN vor Ihren Assets zusammen mit der Sperre der gesamten Seitenausgabe dazu führen konnte, dass das Plugin die eigenen Skripte Ihrer Website sperrte. In einer bestimmten Konfiguration wurde dabei auch das Skript des Plugins selbst gesperrt – dann war jeder Platzhalter nur noch ein Button, der nichts bewirkte. Unter Status und Werkzeuge steht jetzt, welche Dateien Sie in Ihrem Caching- oder Minifizierungs-Plugin ausnehmen sollten und wo dieses Plugin seine Ausschlussliste führt. An den Einstellungen ändert sich nichts.

---

## Upgrade Notice (0.12.1)

**DE:** Nur Deutsch: Korrekturen nach der Prüfung durch das deutsche Übersetzungsteam – ein Tab in den Einstellungen heißt jetzt „Design“, und mehrere Begriffe und Sätze wurden an Glossar und Styleguide von WordPress Deutsch angeglichen. Für englischsprachige Websites ändert sich nichts.

---

## Upgrade Notice (0.12.0)

**DE:** Bringt Deutsch für alle fünf deutschen Sprachvarianten – den Platzhalter, den Ihre Besucher sehen, die Einstellungsansicht und die Editor-Steuerung. Stellen Sie die Sprache Ihrer Website auf Deutsch, der Rest folgt. Sonst ändert sich nichts.

---

## Abschnittsüberschriften

Diese kurzen Strings listet GlotPress einzeln auf:

**EN:** Description → **DE:** Beschreibung
**EN:** Why it matters → **DE:** Warum das wichtig ist
**EN:** How it works → **DE:** So funktioniert es
**EN:** What you get → **DE:** Was Sie bekommen
**EN:** Works with → **DE:** Funktioniert mit
**EN:** What it is not → **DE:** Was es nicht ist
**EN:** For developers → **DE:** Für Entwickler
**EN:** Installation → **DE:** Installation
**EN:** Frequently Asked Questions → **DE:** Häufige Fragen
**EN:** External services → **DE:** Externe Dienste
**EN:** Screenshots → **DE:** Screenshots
**EN:** Upgrade Notice → **DE:** Update-Hinweis
**EN:** Changelog → **DE:** Änderungsprotokoll

Der String **„Calucon Third-Party Embed Gate“** (Priorität hoch) ist der
Plugin-Name und bleibt unübersetzt.

---

## Für Entwickler (die fünf Aufzählungspunkte)

**EN:** Theme override: copy `templates/placeholder.php` to `{your-theme}/calucon-embed-gate/placeholder.php`.

**DE:** Theme-Override: `templates/placeholder.php` nach `{your-theme}/calucon-embed-gate/placeholder.php` kopieren.

**EN:** CSS custom properties on `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …) for restyling without specificity wars.

**DE:** CSS-Custom-Properties auf `.cg-embed` (`--cg-bg`, `--cg-fg`, `--cg-accent`, …), um ohne Spezifitäts-Kämpfe umzugestalten.

**EN:** WP-CLI: `wp calucon-embed-gate scan` (is every embed gated? `--format=json` for CI and automation) and `wp calucon-embed-gate providers`. Both read-only.

**DE:** WP-CLI: `wp calucon-embed-gate scan` (ist jede Einbettung gesperrt? `--format=json` für CI und Automatisierung) und `wp calucon-embed-gate providers`. Beide lesen nur.

**EN:** Documented filters: `calucon_embed_gate_providers`, … Adding a provider is a ten-line filter in `functions.php`.

**DE:** Dokumentierte Filter: `calucon_embed_gate_providers`, `calucon_embed_gate_provider_for_url`, `calucon_embed_gate_should_gate`, `calucon_embed_gate_is_own_host`, `calucon_embed_gate_own_hosts`, `calucon_embed_gate_placeholder_html`, `calucon_embed_gate_payload`, `calucon_embed_gate_note_text`, `calucon_embed_gate_action_text`, `calucon_embed_gate_fallback_url`, `calucon_embed_gate_www_equivalence`, `calucon_embed_gate_cmp_config`, `calucon_embed_gate_asset_version`, `calucon_embed_gate_the_content_priority`, `calucon_embed_gate_render_block_priority` sowie die Aktionen `calucon_embed_gate_before_render`, `calucon_embed_gate_embed_gated` und `calucon_embed_gate_flush_caches`. Zu jedem Hook sind Signatur, Auslösezeitpunkt und Rückgabewert in `docs/customizing.md` dokumentiert, die im Plugin mitgeliefert wird (wp-content/plugins/calucon-third-party-embed-gate/docs/customizing.md) und auf GitHub lesbar ist. Einen Anbieter hinzuzufügen ist ein Filter von zehn Zeilen in der `functions.php`.

**EN:** Stable since 1.0: the markup contract (`cg-` classes, `data-cg-*` attributes, `--cg-*` custom properties), the documented hooks, the template variables, the settings keys and the WP-CLI commands do not change across minor releases; provider descriptors and the tested-platform lists are data and may. `docs/customizing.md` ships inside the plugin and is written for developers and AI coding agents alike.

**DE:** Stabil seit 1.0: Der Markup-Vertrag (`cg-`-Klassen, `data-cg-*`-Attribute, `--cg-*`-Custom-Properties), die dokumentierten Hooks, die Template-Variablen, die Einstellungsschlüssel und die WP-CLI-Befehle bleiben über Minor-Releases hinweg unverändert; die Beschreibungen der Anbieter und die Listen der getesteten Plattformen sind Daten und können sich ändern. `docs/customizing.md` wird im Plugin mitgeliefert und ist für Entwickler wie für KI-Agenten geschrieben.

---

## „Welche Einbettungen erkennt es namentlich?“ – die beiden fehlenden Absätze

**EN:** Videos: YouTube, Vimeo, Dailymotion, TED, VideoPress and WordPress.tv, TikTok. Audio: … 3D: Matterport, Sketchfab.

**DE:** Videos: YouTube, Vimeo, Dailymotion, TED, VideoPress und WordPress.tv, TikTok. Audio: Spotify, SoundCloud, Apple Music, Mixcloud, Pocket Casts. Karten: Google Maps, OpenStreetMap. Social-Media-Beiträge: X, Instagram, Facebook, Reddit, Tumblr, Bluesky, Pinterest, Imgur, GIPHY, Strava. Dokumente: Scribd, Speaker Deck, Issuu, Wolfram Cloud, Amazon Kindle, Kickstarter. Formulare und Kalender: Google Kalender, Google Formulare, Typeform, Calendly, Crowdsignal. 3D: Matterport, Sketchfab.

**EN:** Everything else is gated too — that does not depend on a list. …

**DE:** Alles andere wird ebenfalls gesperrt – das hängt nicht an einer Liste. Eine Einbettung von einem unbenannten Host bekommt denselben Platzhalter und denselben Button, benannt nach dem Host, den sie kontaktieren würde, mit einem Link zum Inhalt selbst. Ein namentlich bekannter Anbieter ergänzt lediglich den Namen, das Symbol, den Link zur Datenschutzerklärung und einen aufgeräumteren „Auf … öffnen“-Link. Ein paar der Einbettungsblöcke von WordPress selbst sind noch nicht benannt (Flickr, SmugMug, Animoto, ReverbNation, Cloudup); Sie können sie unter Anbieter → Eigene Anbieter selbst benennen.

---

## Nicht übersetzt

Das Änderungsprotokoll (der größte Teil der 166 Strings) und die älteren
Update-Hinweise bleiben Englisch. Sie werden fast nie gelesen, altern mit jedem
Release und kosten mehr Pflege, als sie einbringen. Unübersetzte Strings zeigt
wordpress.org einfach im Original.
