# The German WordPress style guide, as this project applies it

Vendored from the German polyglots handbook, the way
`tests/Support/data/de-glossary.csv` is vendored from the same site. It exists so
the translation agent works from a fixed, reviewable copy instead of whatever the
network returns today, and so a change by the German team shows up as a diff in a
pull request rather than as a silent shift in behaviour.

**This is a summary, not the authority.** Where it disagrees with the handbook,
the handbook wins — take it up there, not here. Source pages, and the stamps
`bin/refresh-style-guide.sh` checks them against, are at the bottom.

Not shipped. The rest of `docs/` goes into the zip — `customizing.md` is the
on-site reference for developers — but this file is contributor material, so
`bin/build-zip.sh` drops it from the staged copy.

---

## 0. The rule that outranks the rest

> „Niemand liest gerne eine wörtliche Übersetzung."
> — *Stilistisches*

**Keine wörtliche Übersetzung, sondern sinngemäße Umsetzung.** Convey the
meaning; do not carry the English word order, its appositions or its list
constructions across. This is the project's most common translation defect and
no test can see it. Every one of these passed every mechanical check:

| Shipped | What went wrong |
|---|---|
| „der Content-Security-Policy-Helfer: was eine Richtlinie ist, eine Prüfung der eigenen Website, …" | an English colon-list carried across item by item into a construction German does not tolerate |
| „eine **Art für** das Button-Symbol" | English "a kind for" transplanted whole |
| „was ein **Mensch** liest" | literally correct, wrong register — „Person" |
| „…, mit einem Klick rückgängig zu machen" | an English apposition kept as a dangling infinitive |

Read the German **without the English in view** — `php bin/translation-review.php`
prints it that way, worst suspects first. It is the only reliable way to notice
that a sentence is not German prose.

And the second-order rule, from the same page: **glossary entries override every
other rule here**, including the compound rules in §4.

---

## 1. Anrede — which branch you are in

> „Bei der Übersetzung von WordPress verwenden wir als Standard die informelle
> Schreibweise (kleingeschriebenes „du"). Parallel dazu werden Übersetzungen in
> formeller Schreibweise (großgeschriebenes „Sie") in einem eigenen Zweig
> angeboten."
> — *Allgemein*

- `de_DE`, `de_AT`, `de_CH_informal` → **du**, lowercase mid-sentence
- `de_DE_formal`, `de_CH` → **Sie**
- Pronouns are only half of it: *„Nimm den einfachen Einbettungscode"* carries no
  pronoun and is still du. In the Sie branch write *„Nehmen Sie …"*.

*Checked:* `StyleGuideTest::test_the_two_address_forms_stay_apart`,
`::test_the_formal_branch_has_no_du_imperative`, `::test_du_is_lowercase`.

---

## 2. Stilistisches — judgement rules

None of these are machine-checked. They are the reason a human, or an agent
holding this page, has to write the German.

- **Aktiv formulieren.** Long sentences split into several sensible ones.
- **Verständlich für technisch weniger versierte Anwender.**
- **Keine Anthropomorphismen** — *„Hardware oder Software sollten keine
  menschlichen Eigenschaften oder Gefühle zugeschrieben werden."* No feelings, no
  behaviour, no intelligence. Software does not "want", "know", "try" or
  "understand".
- **Kein Humor, keine US-amerikanischen Bezüge, kein Dialekt.**
- **Kein Fachjargon, keine Buzzwords** — except terminology the glossary defines.
- **Sprachliche Gleichbehandlung:** in documentation, aim for it; in **product
  translations, use the generic masculine consistently.**
- **Imperativ:** where the English has one, German has one too. **In headings
  prefer nouns to imperatives** (§5).
- **UI-Begriffe im Infinitiv** — „Benutzer hinzufügen", not „Benutzer hinzufügst".
- **Akronyme:** first use spells the term out with the acronym in brackets. Where
  it is not translated into German: acronym + English term + German gloss.
- **Abkürzungen:** only common ones, and with the internal space — `z. B.`,
  `d. h.`, `u. a.`, `i. d. R.` *(checked)*.

### Umgangssprache — spell it out

| Never | Write |
|---|---|
| fürs, vorm, drauf, nochmal | für das, vor dem, darauf, noch einmal |

*(checked, plus `garnicht`, `runter`, `rauf`)*

---

## 3. Rechtschreibung — mostly mechanical

- **Typografische Anführungszeichen** `„…"`, never a straight `"` in prose.
  Straight quotes inside HTML attributes, `<code>`, backticks and
  `[shortcode]` tokens are markup, not prose. *(checked)*
- **`&` → „und".** *„Das &-Zeichen wird im Deutschen selten verwendet."*
  *(checked)*
- **Geschütztes Leerzeichen (U+00A0) vor dem Gedankenstrich**, and between a
  number and `%` or a unit. Typed directly — never as `&nbsp;`, which displays
  wrong. *(checked; `php bin/fix-style.php` applies both)*
  - The trap in the other direction: U+00A0 is invisible, so a literal
    search-and-replace over a string that already contains one **matches
    nothing**. Assert every edit. This has cost the repo time twice.
  - The closing dash of a parenthetical takes the comma the sentence needs, and
    the rule does not apply there — `–,` (Duden § 77 E2).
- **WordPress**, never „Wordpress". WordPress.org / WordPress.com unchanged.
  *(checked)*
- **Zahlen:** digits before a unit or currency (`1 km`, `20 EUR`); thousands
  separated by a point — `1.000.000`, not `1,000,000`.
- **Datum:** `TT.MM.JJJJ`, single-digit days without a leading zero („6. Mai"),
  24-hour time with a colon („9:00 Uhr").
- **Listen:** capitalisation and punctuation consistent within a list. Full
  sentences take a capital and a full stop; continuations of the lead-in take
  lowercase and a semicolon (comma on the last); fragments take a capital and no
  full stop.
- **Keine HTML-Entities** for German characters — write the character.

---

## 4. Durchkoppeln — compounds

Four cases, and **never a space between the parts**:

1. **Muss-Bindestrich** — the parts must be hyphenated: *„Die einzelnen
   Bestandteile der Wortzusammensetzung müssen mit Bindestrich(en) verbunden
   werden."* No space before or after the hyphen.
2. **Kein Bindestrich** — written as one word.
3. **Kein Bindestrich, aber Leerzeichen** — the narrow exception for mixed
   or foreign (especially English) compounds.
4. **Kann-Bindestrich** — *„Wir tolerieren beide Schreibweisen: Entweder
   zusammengeschrieben oder mit Bindestrich(en), aber immer ohne Leerzeichen."*

**Glossar entries override all four.** A *Deppenleerzeichen* — „Zwei Klick
Lösung" for „Zwei-Klick-Lösung" — is wrong under every case.

---

## 5. Titel und Überschriften

- Short: *„möglichst kurz und nicht wesentlich länger als der Quellentext."*
- *„In Titeln keine Einschübe oder Klammern verwenden. Der im Englischen häufig
  anzutreffende Punkt am Ende eines Titels entfällt im Deutschen."*
- English gerund → substantivised verb without an article: *Adding* →
  *Hinzufügen*.
- *About …* → „Informationen zu …"
- *To …* / *How to …* → „So …"
- An untranslated English document keeps a translated title with „(engl.)".

Not machine-checkable: the rules need to know which strings are titles, and
nothing in the PO marks that.

---

## 6. Things that are never translated

- **Plugin and theme names** — brand names.
- **Placeholders**: `%s`, `%d`, `%1$s`, `%(author)s`. Never translated, never
  renamed, never dropped, never invented. Reorder the German sentence around
  them instead. *(checked — a missing argument is a PHP 8 `sprintf()` throw, not
  a wording problem.)*
- **Date/time format characters** (`dddd`, `MMMM`) — they are code.
- Glossary rows marked **Produktname**: YouTube, WooCommerce, Google Fonts,
  Site Kit, LinkedIn, Polldaddy, Happiness Engineer …

Example names: use neutral ones — *Hans Mustermann*. Imperial measurements are
converted to metric.

---

## Source pages

Fetched **2026-08-27** from
`https://de.wordpress.org/team/handbook/polyglots-team/style-guide/`.

`bin/refresh-style-guide.sh` re-fetches these and reports any that have changed
since. It is informational, like `bin/check-privacy-links.sh` — a CHANGED line
means read the page and update this file, not that anything is broken.

<!-- style-guide-sources
allgemein	https://de.wordpress.org/team/handbook/polyglots-team/style-guide/allgemein/
rechtschreibung	https://de.wordpress.org/team/handbook/polyglots-team/style-guide/rechtschreibung/
komposita	https://de.wordpress.org/team/handbook/polyglots-team/style-guide/rechtschreibung/komposita/
stilistisches	https://de.wordpress.org/team/handbook/polyglots-team/style-guide/stilistisches/
titel	https://de.wordpress.org/team/handbook/polyglots-team/style-guide/titel-ueberschriften-dokumententitel-etc/
-->
