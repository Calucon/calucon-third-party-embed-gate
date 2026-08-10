#!/usr/bin/env bash
# Tear down the WordPress integration stack, including the data volume —
# the stack is disposable by design; setup.sh rebuilds it from scratch.
set -euo pipefail
cd "$(dirname "$0")"
docker compose -f docker-compose.yml down --volumes --remove-orphans
