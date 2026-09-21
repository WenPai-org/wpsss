#!/usr/bin/env bash
# Product-repo copy of kit tag. Usage: scripts/vendor-wenpai-admin-ui.sh 0.1.0
set -euo pipefail
ver="${1:?tag without v}"
root="$(cd "$(dirname "$0")/.." && pwd)"
dest="$root/lib/wenpai-admin-ui"
src="${WENPAI_ADMIN_UI_SRC:-$HOME/Projects/wenpai-admin-ui}"
if [[ ! -d "$src/css" ]]; then
  echo "need kit checkout at $src or set WENPAI_ADMIN_UI_SRC" >&2
  exit 1
fi
rm -rf "$dest"
mkdir -p "$dest"
cp "$src/LICENSE" "$src/NOTICE" "$dest/"
printf '%s\n' "$ver" > "$dest/VERSION"
cp -R "$src/css" "$src/php" "$src/icons" "$dest/"
echo "vendored $ver -> $dest"
