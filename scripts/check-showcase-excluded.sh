#!/usr/bin/env bash
# Fails if the design-system showcase page ended up in a production build (run it after `npm run build`). The page is meant for
# developers only: it is reachable while developing or in a build made on purpose with VITE_DESIGN_SYSTEM_SHOWCASE=true.
set -euo pipefail

build_dir="${1:-public/build}"

if grep -rl "Design system showcase" "$build_dir" >/dev/null 2>&1; then
    echo "The design-system showcase is in the production build:" >&2
    grep -rl "Design system showcase" "$build_dir" >&2
    exit 1
fi

echo "The design-system showcase is not in the production build."
