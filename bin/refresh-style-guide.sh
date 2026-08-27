#!/usr/bin/env bash
# Has the German style guide changed since docs/de-style-guide.md was written?
#
# docs/de-style-guide.md is a distilled copy of five handbook pages. A summary
# goes stale silently: the German team edits a rule, the vendored copy keeps
# saying the old thing, and the translation agent keeps applying it. Nothing in
# the repo would notice.
#
# So this fetches the five pages, reduces each to its visible text, and compares
# a hash against the one recorded when the page was last read. It reports; it
# fails nothing. CHANGED means "read that page and update the vendored copy",
# not "something is broken" — the same contract as bin/check-privacy-links.sh.
#
# Like that script, this is a development tool. The PLUGIN never makes an
# outbound request (invariant 9); a script in bin/ that a maintainer runs by
# hand is not the plugin.
#
#   bash bin/refresh-style-guide.sh            # check
#   bash bin/refresh-style-guide.sh --update   # record the current pages as the
#                                              # baseline, after re-distilling
set -uo pipefail
cd "$(dirname "$0")/.."

DOC=docs/de-style-guide.md
HASHES=tests/Support/data/de-style-guide-hashes.txt
update=0
[ "${1:-}" = "--update" ] && update=1

if [ ! -r "$DOC" ]; then
	echo "missing $DOC" >&2
	exit 1
fi

# The URL list lives in the doc itself, so the two cannot drift apart.
pages=$(sed -n '/<!-- style-guide-sources/,/-->/p' "$DOC" | grep -E '^[a-z]+\s+https://')
if [ -z "$pages" ]; then
	echo "no <!-- style-guide-sources --> block in $DOC" >&2
	exit 1
fi

# Visible text only. Script, style and the surrounding chrome are stripped, then
# whitespace is collapsed, so a nonce, a changed menu or a reflow does not read
# as a rule change. Perfect it is not — a false CHANGED costs one page read.
page_hash() {
	curl -sS -L --max-redirs 5 --max-time 25 -A 'calucon-embed-gate-style-guide-check' "$1" 2>/dev/null \
		| php -r '
			$html = stream_get_contents( STDIN );
			$html = preg_replace( "#<(script|style|nav|footer|header)\b.*?</\\1>#is", " ", $html );
			$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, "UTF-8" );
			$text = preg_replace( "/\s+/u", " ", $text );
			echo substr( hash( "sha256", trim( $text ) ), 0, 16 );
		'
}

declare -A recorded=()
if [ -r "$HASHES" ]; then
	while read -r name hash _; do
		[ -n "${name:-}" ] && recorded[$name]=$hash
	done < "$HASHES"
fi

changed=0
tmp=$(mktemp)
printf '# Written by bin/refresh-style-guide.sh --update.\n' >> "$tmp"
printf '# sha256(visible text), first 16 hex, of each style-guide page as it read\n' >> "$tmp"
printf '# when docs/de-style-guide.md was last distilled from it.\n' >> "$tmp"

while read -r name url; do
	[ -z "${name:-}" ] && continue
	now=$(page_hash "$url")
	if [ -z "$now" ]; then
		printf 'UNREACHABLE  %-16s %s\n' "$name" "$url"
		# Keep whatever was recorded; an offline run must not erase the baseline.
		[ -n "${recorded[$name]:-}" ] && printf '%s\t%s\n' "$name" "${recorded[$name]}" >> "$tmp"
		continue
	fi
	printf '%s\t%s\n' "$name" "$now" >> "$tmp"

	was=${recorded[$name]:-}
	if [ -z "$was" ]; then
		printf 'NEW          %-16s %s\n' "$name" "$url"
		changed=1
	elif [ "$was" != "$now" ]; then
		printf 'CHANGED      %-16s %s\n' "$name" "$url"
		changed=1
	else
		printf 'unchanged    %-16s\n' "$name"
	fi
done <<< "$pages"

if [ "$update" = 1 ]; then
	mv "$tmp" "$HASHES"
	echo
	echo "Baseline written to $HASHES."
else
	rm -f "$tmp"
	echo
	if [ "$changed" = 1 ]; then
		cat <<-'EOF'
		A page has changed since docs/de-style-guide.md was distilled from it.

		Read the page, update docs/de-style-guide.md where a rule actually moved,
		then record the new baseline:

		    bash bin/refresh-style-guide.sh --update

		Do not run --update without reading the pages: that records the change as
		accepted and the vendored copy keeps saying the old thing.
		EOF
	else
		echo "docs/de-style-guide.md is in step with all five handbook pages."
	fi
fi
