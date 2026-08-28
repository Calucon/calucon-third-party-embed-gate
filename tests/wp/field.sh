#!/usr/bin/env bash
# Run the field-validation suite locally: every group (or the ones named),
# each on a fresh Docker WordPress, sequentially — one port, one stack.
#
#   npm run test:field:docker              # all groups (~3 min each)
#   bash tests/wp/field.sh cmp-complianz cache-litespeed
#
# CI (.github/workflows/field-validation.yml) runs the same groups as a
# parallel matrix from the same table (field-groups.sh). Exit status is
# non-zero if ANY group failed to set up or had a failing test; a group
# that fails to set up is reported as such and does not stop the others.
set -uo pipefail
cd "$(dirname "$0")"
# shellcheck source=field-groups.sh
source ./field-groups.sh
ROOT="$(cd ../.. && pwd)"
PORT="${CG_WP_PORT:-8890}"
URL="http://127.0.0.1:${PORT}"
RESULTS="$ROOT/field-results"
mkdir -p "$RESULTS"

if [ "$#" -gt 0 ]; then
	GROUPS_TO_RUN="$*"
else
	GROUPS_TO_RUN=$(field_group_ids | xargs)
fi

declare -A outcome
for group in $GROUPS_TO_RUN; do
	if ! field_group_slugs "$group" >/dev/null; then
		echo "unknown group '$group'" >&2
		outcome[$group]="UNKNOWN GROUP"
		continue
	fi
	echo
	echo "################  $group  ################"
	if ! bash ./field-setup.sh "$group"; then
		outcome[$group]="SETUP FAILED"
		continue
	fi
	if ( cd "$ROOT" && WP_BASE_URL="$URL" npx playwright test -c playwright.field.config.js --project="$group" ); then
		outcome[$group]="passed"
	else
		outcome[$group]="FAILED"
	fi
	# Each group's JSON report, kept under its own name for the summary.
	[ -f "$RESULTS/report.json" ] && mv "$RESULTS/report.json" "$RESULTS/$group-report.json"
done
bash ./teardown.sh >/dev/null 2>&1 || true

echo
echo "================  field validation  ================"
status=0
for group in $GROUPS_TO_RUN; do
	printf '  %-26s %s\n' "$group" "${outcome[$group]:-not run}"
	[ "${outcome[$group]:-}" = "passed" ] || status=1
done
exit $status
