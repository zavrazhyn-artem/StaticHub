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

# Sister-bridge sanity: NSIS bundles the freshly built blastr.exe.
# Verify it was built with prod -ldflags — the recorded build flags
# in the binary string table must contain the prod DefaultAPIBase
# -X assignment, otherwise the installer ships a dev URL.
SISTER_BRIDGE="$REPO_ROOT/external/desktop/build/bin/blastr.exe"
SISTER_HITS=0
if [[ -f "$SISTER_BRIDGE" ]]; then
    SISTER_HITS=$(strings "$SISTER_BRIDGE" | grep -c -- "-X github.com/zavrazhyn-artem/blastr-desktop/internal/config\.DefaultAPIBase=https://blastr\.pro" || true)
fi
if [[ -f "$SISTER_BRIDGE" && "$SISTER_HITS" -eq 0 ]]; then
    cat >&2 <<EOF
Refusing to publish: bundled blastr.exe was not built with prod ldflags.
Rebuild bridge + installer with the wails build incantation in
tools/publish-bridge.sh.
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
