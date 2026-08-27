#!/usr/bin/env bash
# The German translation pipeline, in stages, with gates between them.
#
# Only de_DE and de_DE_formal are written by hand. de_AT, de_CH and
# de_CH_informal are DERIVED from those two, and the five .mo files and five
# editor JSONs are compiled from all of them — so a mistake in a source locale
# does not stay one mistake. It becomes five wrong .po files, five wrong .mo
# files and five wrong JSONs, and the last four look like independent
# confirmation that the first one was right.
#
# That is not hypothetical: a reviewer at translate.wordpress.org flagged six
# glossary terms, every one of which had already been copied into all five
# locales and compiled.
#
# So nothing is derived until the sources have passed:
#
#   1  sources   regenerate the POT; merge it into de_DE and de_DE_formal ONLY
#   2  gate      nothing missing, no placeholder lost
#   3  gate      the de_DE style guide
#   4  gate      the de_DE glossary
#   5  derive    de_AT, de_CH, de_CH_informal — plugin strings and readme
#   6  compile   five .mo files, five editor JSONs
#   7  verify    the whole suite, derived artefacts included
#
# A failing gate stops the run BEFORE stage 5, so no derived file is ever
# written from unverified sources. Re-run after fixing; the stages are
# idempotent.
#
# What the gates cannot check is whether the German reads like German. The
# style guide's first instruction — "Niemand liest gerne eine wörtliche
# Übersetzung" — is invisible to every test here, and literal translation has
# been this project's most common translation defect by some distance. Before
# submitting to translate.wordpress.org, read the German on its own:
#
#     php bin/translation-review.php
#
# PO files are written unwrapped, one line per string: gettext's 78-column
# wrapping turns a one-word change into a multi-line diff. The readers on both
# sides handle either shape, so a file that comes back wrapped from Poedit or
# GlotPress is fine too.
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN=calucon-third-party-embed-gate
POT="languages/$DOMAIN.pot"
PHPUNIT=vendor/bin/phpunit

stage() { printf '\n\033[1m%s\033[0m\n' "$*"; }
fail()  { printf '\n\033[31m%s\033[0m\n' "$*" >&2; exit 1; }

if [ ! -x "$PHPUNIT" ]; then
	fail "$PHPUNIT is missing — run composer install first."
fi

# ---------------------------------------------------------------- 1. sources
stage '1/7  Sources — regenerating the POT and merging the two hand-written locales'
php tests/bin/generate-pot.php

for locale in de_DE de_DE_formal; do
	msgmerge --quiet --no-wrap --no-fuzzy-matching --backup=none --update \
		"languages/$DOMAIN-$locale.po" "$POT"
	printf '  %s merged\n' "$locale"
done

# ------------------------------------------------------------------ 2. gate
stage '2/7  Gate — every string translated, every placeholder intact'
if ! "$PHPUNIT" --group translation-sources --filter 'TranslationTest|ReadmeTranslationTest' --no-coverage; then
	fail "Untranslated or broken strings in de_DE / de_DE_formal.

Translate them (Poedit, or any text editor), then run this again.
Nothing has been derived — de_AT, de_CH and de_CH_informal are untouched."
fi

# ------------------------------------------------------------------ 3. gate
stage '3/7  Gate — the German style guide'
if ! "$PHPUNIT" --filter StyleGuideTest --no-coverage; then
	fail "The German departs from the de_DE style guide.

Whitespace rules (protected spaces before a Gedankenstrich or a unit) are
mechanical:  php bin/fix-style.php
Everything else — address form, quotes, contractions — needs a wording change.
Nothing has been derived."
fi

# ------------------------------------------------------------------ 4. gate
stage '4/7  Gate — the German glossary'
if ! "$PHPUNIT" --filter GlossaryTest --no-coverage; then
	fail "A word the de_DE glossary rules out is back in the translation.

Use the prescribed term. Nothing has been derived."
fi

departures=$(php bin/glossary-report.php --count)
printf '  advisory: %s departures from the full glossary — read them, do not skim.\n' "$departures"
printf '      php bin/glossary-report.php\n'
printf '  Most are context the glossary does not cover (editor = the block editor,\n'
printf '  header = an HTTP header). The ones that are not hide in exactly that noise:\n'
printf '  "screen" sat in this list for two releases and shipped as "Seite" anyway.\n'

# ---------------------------------------------------------------- 5. derive
stage '5/7  Derive — de_AT, de_CH and de_CH_informal from the verified sources'
php bin/derive-german-locales.php
php bin/derive-readme-locales.php

# --------------------------------------------------------------- 6. compile
stage '6/7  Compile — .mo files and block-editor JSON'
for po in languages/"$DOMAIN"-*.po; do
	locale=$(basename "$po" .po)
	locale=${locale#"$DOMAIN"-}
	msgfmt --check --statistics -o "languages/$DOMAIN-$locale.mo" "$po" 2>&1 | sed "s/^/  $locale: /"
done
php bin/make-json-translations.php

# ---------------------------------------------------------------- 7. verify
stage '7/7  Verify — the whole suite, derived artefacts included'
if ! "$PHPUNIT" --no-coverage; then
	fail 'The suite is red after deriving. The derived files on disk are not trustworthy.'
fi

stage 'Done.'
cat <<'NOTE'
  Before submitting to translate.wordpress.org, read the German as German:

      php bin/translation-review.php              # the du branch
      php bin/translation-review.php --formal     # the Sie branch

  No test can tell a literal translation from a good one. That part is yours.
NOTE
