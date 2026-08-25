#!/usr/bin/env bash
# Bring every translation artefact back in step with the source strings.
#
#   1. regenerate the .pot from the PHP and JS sources
#   2. merge it into each .po, keeping existing translations
#   3. compile each .po to the .mo WordPress actually reads
#   4. rebuild the JSON the block editor reads (a .mo never reaches wp.i18n)
#
# New or changed strings come out of step 2 as untranslated or fuzzy entries;
# tests/Unit/TranslationTest.php fails until they are translated, which is the
# point — a half-translated panel tells a visitor half of what loading an embed
# does. Translate them in the .po (Poedit works, so does a text editor) and run
# this again.
#
# PO files are written unwrapped, one line per string: gettext's 78-column
# wrapping turns a one-word change into a multi-line diff. The readers on both
# sides handle either shape, so a file that comes back wrapped from Poedit or
# GlotPress is fine too.
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN=calucon-third-party-embed-gate
POT="languages/$DOMAIN.pot"

php tests/bin/generate-pot.php

for po in languages/"$DOMAIN"-*.po; do
	locale=$(basename "$po" .po)
	locale=${locale#"$DOMAIN"-}
	msgmerge --quiet --no-wrap --no-fuzzy-matching --backup=none --update "$po" "$POT"
	msgfmt --check --statistics -o "languages/$DOMAIN-$locale.mo" "$po"
	printf '  %s\n' "$locale"
done

php bin/make-json-translations.php
