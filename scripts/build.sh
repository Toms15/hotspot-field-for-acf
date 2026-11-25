#!/usr/bin/env bash
# Copies the distributable plugin into build/hotspot-field-for-acf: only files
# tracked by git (in their working-tree state), minus everything listed in
# .distignore. Untracked files never end up in the build.
set -euo pipefail

cd "$(dirname "$0")/.."

target="build/hotspot-field-for-acf"
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT

# 1. Tracked files that still exist (a tracked file deleted locally is skipped).
git ls-files -z --cached | while IFS= read -r -d '' file; do
	if [ -e "$file" ]; then printf '%s\0' "$file"; fi
done | rsync -a --from0 --files-from=- ./ "$staging/"

# 2. Drop everything .distignore lists.
rm -rf "$target"
mkdir -p "$target"
rsync -a --exclude-from=.distignore "$staging/" "$target/"

echo "Built $target"
