#!/usr/bin/env bash
# Is readme.txt's "Tested up to" still the current WordPress?
#
# wordpress.org marks a plugin "untested with your version" — and hides it
# from some searches — once a new major.minor is out and the header lags.
# Bumping it is a readme HEADER change (not prose, so the German listing
# stamp does not fire) and a release. Runs in CI on a schedule — never from
# the plugin (invariant 9). Exit 1 when behind; the workflow turns that into
# an issue.
set -uo pipefail
cd "$(dirname "$0")/.."

tested=$(sed -nE 's/^Tested up to:[[:space:]]*([0-9]+\.[0-9]+).*$/\1/p' readme.txt | head -n1)
if [[ -z "$tested" ]]; then
	echo "FAIL  readme.txt has no 'Tested up to:' line"; exit 1
fi

json=$(curl -sS --max-time 20 -A "calucon-embed-gate-canary" 'https://api.wordpress.org/core/version-check/1.7/' 2>/dev/null) || { echo "FAIL  api.wordpress.org did not answer"; exit 1; }
# The first offer is the latest release; the JSON lists older branches after
# it, and a greedy match would read the oldest (the first run did: 4.7).
current=$(printf '%s' "$json" | grep -oE '"current":"[0-9]+\.[0-9]+' | head -n1 | sed -E 's/.*"//')
if [[ -z "$current" ]]; then
	echo "FAIL  could not read the current WordPress version from api.wordpress.org"; exit 1
fi

# Compare major.minor numerically, not as strings (7.10 > 7.9).
ver() { printf '%d%03d' "${1%%.*}" "${1#*.}"; }
if (( $(ver "$tested") < $(ver "$current") )); then
	echo "BEHIND  Tested up to: $tested — WordPress is at $current. Test on $current, bump the header, ship a release."
	exit 1
fi
echo "ok    Tested up to: $tested — WordPress is at $current"
