#!/usr/bin/env bash
#
# Publish the freshly built BlastR Desktop bridge so authenticated
# clients pick it up via /api/desktop/bridge on their next sync.
#
# Usage:
#   tools/publish-bridge.sh <version>
#
# Expects external/desktop/build/bin/blastr.exe to already exist
# (run `wails build -platform windows/amd64` first). Copies the exe
# into resources/desktop/ alongside the version file the manifest
# endpoint reads.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE_EXE="$REPO_ROOT/external/desktop/build/bin/blastr.exe"
DEST_DIR="$REPO_ROOT/resources/desktop"
DEST_EXE="$DEST_DIR/bridge.exe"
DEST_VERSION="$DEST_DIR/bridge-version.txt"

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 <version>" >&2
    exit 1
fi
VERSION="$1"

[[ -f "$SOURCE_EXE" ]] || {
    echo "Missing $SOURCE_EXE — build the bridge first." >&2
    exit 1
}

# Refuse to ship a dev build pointing at local.blastr.pro — that string
# only exists when DefaultAPIBase wasn't overridden via -ldflags. The
# release build incantation:
#
#   wails build -platform windows/amd64 \
#     -ldflags "-X github.com/zavrazhyn-artem/blastr-desktop/internal/config.DefaultAPIBase=https://blastr.pro \
#               -X github.com/zavrazhyn-artem/blastr-desktop/internal/config.Version=$VERSION"
if strings "$SOURCE_EXE" | grep -q "local\.blastr\.pro"; then
    cat >&2 <<EOF
Refusing to publish: $SOURCE_EXE points to local.blastr.pro.
Rebuild with prod ldflags (see comment in tools/publish-bridge.sh).
EOF
    exit 1
fi

mkdir -p "$DEST_DIR"
cp "$SOURCE_EXE" "$DEST_EXE"
printf '%s\n' "$VERSION" > "$DEST_VERSION"

SIZE_KB=$(( $(stat -c%s "$DEST_EXE" 2>/dev/null || stat -f%z "$DEST_EXE") / 1024 ))
SHA256=$(sha256sum "$DEST_EXE" 2>/dev/null | awk '{print $1}' || shasum -a 256 "$DEST_EXE" | awk '{print $1}')

cat <<EOF
Published bridge release:
  version  ${VERSION}
  path     ${DEST_EXE}
  size     ${SIZE_KB} KiB
  sha256   ${SHA256}

Commit resources/desktop/bridge.exe + bridge-version.txt to ship.
EOF
