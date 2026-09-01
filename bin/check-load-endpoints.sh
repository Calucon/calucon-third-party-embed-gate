#!/usr/bin/env bash
# Re-measure the two privacy claims the plugin makes about WHERE it loads
# embeds from after the click (PLAN.md §0, readme "External services"):
#
#   youtube-nocookie.com/embed/…   sets NO cookies (readme: "measured: 0
#                                  cookies instead of 5")
#   player.vimeo.com/video/…?dnt=1 does not set Vimeo's analytics cookie
#                                  `vuid` — that is what dnt=1 is documented
#                                  to suppress, and all the readme claims
#
# Runs in CI on a schedule — NEVER from the plugin (invariant 9). A provider
# can change its behaviour without notice; when it does, the readme's
# claims become untrue and the load target needs a rethink. FAIL means the
# claim no longer holds on a plain GET with no interaction. Every cookie is
# reported by NAME: the first run (2026-08-28) found Vimeo's CDN setting
# Cloudflare's bot-management cookie `__cf_bm` on the dnt=1 URL — its
# infrastructure, not tracking, and not something the readme claims either
# way; a count alone would have flagged it as a broken promise. The plain
# YouTube number is printed for comparison only.
#
# The ids are Calucon's own upload (y_pjE_p1HwE, "Kolkja Cycling") and
# Vimeo's own sample (76979871) — fixture provenance rules apply here too.
set -uo pipefail

UA="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 calucon-embed-gate-canary"

# The NAMES of the cookies a plain GET sets (redirects followed, no cookie
# jar sent), one per line; the single line "ERROR" when the request failed
# or ended non-2xx.
cookie_names() {
	local url="$1" headers code
	headers=$(curl -sS -D - -o /dev/null -L --max-redirs 5 --max-time 25 -A "$UA" "$url" 2>/dev/null) || { echo ERROR; return; }
	code=$(printf '%s' "$headers" | grep -i '^HTTP/' | tail -n1 | awk '{print $2}')
	if [[ "$code" != 2* ]]; then echo ERROR; return; fi
	printf '%s' "$headers" | grep -i '^set-cookie:' | sed -E 's/^[Ss]et-[Cc]ookie:[[:space:]]*([^=;]+).*/\1/' | sort -u
}

fail=0
# report LABEL URL RULE — RULE is "none" (no cookie at all), a cookie name
# that must not appear, or "-" (report only).
report() {
	local label="$1" url="$2" rule="$3" names count
	names=$(cookie_names "$url")
	if [[ "$names" == ERROR ]]; then
		echo "FAIL  $label: request failed or non-2xx — $url"; fail=1; return
	fi
	count=$(printf '%s' "$names" | grep -c . )
	names=$(printf '%s' "$names" | paste -sd, -)
	if [[ "$rule" == none && "$count" != 0 ]]; then
		echo "FAIL  $label: $count cookie(s) on a plain GET [$names] — the readme says 0 — $url"; fail=1
	elif [[ "$rule" != none && "$rule" != - ]] && printf '%s' ",$names," | grep -q ",$rule,"; then
		echo "FAIL  $label: sets $rule, which dnt=1 is supposed to suppress [$names] — $url"; fail=1
	else
		echo "ok    $label: $count cookie(s) [${names:-none}] — $url"
	fi
}

report "youtube-nocookie (load target: no cookies)" "https://www.youtube-nocookie.com/embed/y_pjE_p1HwE" none
report "vimeo dnt=1 (load target: no vuid)"          "https://player.vimeo.com/video/76979871?dnt=1" vuid
report "youtube.com (comparison only)"               "https://www.youtube.com/embed/y_pjE_p1HwE" -
exit $fail
