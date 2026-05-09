#!/usr/bin/env bash
#
# Package the BlastR WoW addon source into a zip the bridge will fetch
# from /api/desktop/addon. Drops the artifact into resources/desktop/
# alongside a single-line version file (the manifest's source of truth).
#
# Usage:
#   tools/build-addon.sh                   # version pulled from BlastR.toc
#   tools/build-addon.sh --version 0.1.56  # override
#
# The zip's top-level layout matches what wow.InstallFromZipBytes
# expects:
#   addon.zip
#   ├── BlastR/
#   │   ├── BlastR.toc
#   │   └── ...
#   └── BlastR_RCLootCouncil/
#       ├── BlastR_RCLootCouncil.toc
#       └── ...

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE_DIR="$REPO_ROOT/external/desktop/internal/wow/addonfiles"
DEST_DIR="$REPO_ROOT/resources/desktop"
ZIP_PATH="$DEST_DIR/addon.zip"
VERSION_PATH="$DEST_DIR/addon-version.txt"
ADDON_DIRS=("BlastR" "BlastR_RCLootCouncil")

VERSION_OVERRIDE=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            VERSION_OVERRIDE="$2"
            shift 2
            ;;
        --version=*)
            VERSION_OVERRIDE="${1#*=}"
            shift
            ;;
        -h|--help)
            sed -n '2,18p' "$0"
            exit 0
            ;;
        *)
            echo "Unknown arg: $1" >&2
            exit 1
            ;;
    esac
done

# Find the version. With no --version, parse "## Version: X.Y.Z" from
# BlastR.toc — the parent addon's toc is canonical (the RC sub-addon
# must move in lockstep).
if [[ -n "$VERSION_OVERRIDE" ]]; then
    VERSION="$VERSION_OVERRIDE"
else
    TOC="$SOURCE_DIR/BlastR/BlastR.toc"
    [[ -f "$TOC" ]] || { echo "Missing $TOC — can't infer version" >&2; exit 1; }
    VERSION="$(grep -E '^## Version:' "$TOC" | head -1 | sed 's/^## Version:[[:space:]]*//' | tr -d '[:space:]')"
    [[ -n "$VERSION" ]] || { echo "Couldn't parse version from $TOC" >&2; exit 1; }
fi

# Sanity: every required addon dir present.
for dir in "${ADDON_DIRS[@]}"; do
    [[ -d "$SOURCE_DIR/$dir" ]] || { echo "Missing addon source dir: $SOURCE_DIR/$dir" >&2; exit 1; }
done

mkdir -p "$DEST_DIR"
rm -f "$ZIP_PATH"

# Zip from inside SOURCE_DIR so the archive's top-level entries are
# the addon names (BlastR/, BlastR_RCLootCouncil/), matching what
# wow.InstallFromZipBytes' prefix matcher looks for. Uses python3's
# zipfile when /usr/bin/zip is absent so we don't depend on apt installs.
if command -v zip >/dev/null 2>&1; then
    (
        cd "$SOURCE_DIR"
        zip -rq "$ZIP_PATH" "${ADDON_DIRS[@]}" \
            -x '*.DS_Store' '*/.DS_Store' '*/Thumbs.db' '*/.git/*'
    )
else
    python3 - "$ZIP_PATH" "$SOURCE_DIR" "${ADDON_DIRS[@]}" <<'PY'
import os, sys, zipfile

dest, src_root, *dirs = sys.argv[1:]
SKIP = {'.DS_Store', 'Thumbs.db'}

with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
    for d in dirs:
        root = os.path.join(src_root, d)
        for cur, sub, files in os.walk(root):
            sub[:] = [s for s in sub if s != '.git']
            for f in files:
                if f in SKIP:
                    continue
                full = os.path.join(cur, f)
                arc = os.path.relpath(full, src_root).replace(os.sep, '/')
                z.write(full, arc)
PY
fi

printf '%s\n' "$VERSION" > "$VERSION_PATH"

SIZE_KB=$(( $(stat -c%s "$ZIP_PATH" 2>/dev/null || stat -f%z "$ZIP_PATH") / 1024 ))
SHA256=$(sha256sum "$ZIP_PATH" 2>/dev/null | awk '{print $1}' || shasum -a 256 "$ZIP_PATH" | awk '{print $1}')

cat <<EOF
Built addon release:
  version  ${VERSION}
  path     ${ZIP_PATH}
  size     ${SIZE_KB} KiB
  sha256   ${SHA256}

Commit resources/desktop/addon.zip + addon-version.txt to publish.
EOF
