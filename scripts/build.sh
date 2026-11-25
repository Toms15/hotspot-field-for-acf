#!/usr/bin/env bash
# Copies the distributable plugin into build/hotspot-field-for-acf, leaving out
# everything listed in .distignore.
set -euo pipefail

cd "$(dirname "$0")/.."

target="build/hotspot-field-for-acf"
rm -rf "$target"
mkdir -p "$target"
rsync -a --exclude-from=.distignore ./ "$target/"
echo "Built $target"
