#!/usr/bin/env bash
#
# Publish the freshly built NSIS installer for public download.
# Mirror of tools/publish-bridge.sh but targets the unauthenticated
# /desktop/install.exe endpoint that brand-new users hit before
# pairing.
#
# Usage:
#   tools/publish-installer.sh <version>
#
# Expects external/desktop/build/bin/BlastR-amd64-installer.exe to
# already exist (run `wails build -platform windows/amd64 -nsis`
# first). Copies the .exe into resources/desktop/ alongside the
# version file the download page reads.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE_EXE="$REPO_ROOT/external/desktop/build/bin/BlastR-amd64-installer.exe"
DEST_DIR="$REPO_ROOT/resources/desktop"
DEST_EXE="$DEST_DIR/installer.exe"
DEST_VERSION="$DEST_DIR/installer-version.txt"

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 <version>" >&2
    exit 1
fi
VERSION="$1"

[[ -f "$SOURCE_EXE" ]] || {
    echo "Missing $SOURCE_EXE — run wails build -nsis first." >&2
    exit 1
}

# Sister-bridge sanity: NSIS bundles the freshly built blastr.exe, so
# if that one points at local.blastr.pro, this installer ships the
# same broken URL. Refuse to publish unless the matching bridge.exe
# was already published with the same prod-ldflags build.
SISTER_BRIDGE="$REPO_ROOT/external/desktop/build/bin/blastr.exe"
if [[ -f "$SISTER_BRIDGE" ]] && strings "$SISTER_BRIDGE" | grep -q "local\.blastr\.pro"; then
    cat >&2 <<EOF
Refusing to publish: bundled blastr.exe points to local.blastr.pro.
Rebuild bridge + installer with prod ldflags first
(see tools/publish-bridge.sh for the wails build incantation).
EOF
    exit 1
fi

mkdir -p "$DEST_DIR"
cp "$SOURCE_EXE" "$DEST_EXE"
printf '%s\n' "$VERSION" > "$DEST_VERSION"

SIZE_KB=$(( $(stat -c%s "$DEST_EXE" 2>/dev/null || stat -f%z "$DEST_EXE") / 1024 ))
SHA256=$(sha256sum "$DEST_EXE" 2>/dev/null | awk '{print $1}' || shasum -a 256 "$DEST_EXE" | awk '{print $1}')

cat <<EOF
Published installer release:
  version  ${VERSION}
  path     ${DEST_EXE}
  size     ${SIZE_KB} KiB
  sha256   ${SHA256}

Commit resources/desktop/installer.exe + installer-version.txt to ship.
EOF
