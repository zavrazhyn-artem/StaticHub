#!/usr/bin/env bash
# =============================================================================
# Hot-patch bridge.exe and/or addon.zip into the running blastr-web pod
# without a full image rebuild + helm upgrade.
#
# Usage:
#   ./deploy/bridge-deploy.sh                  # push both bridge + addon
#   ./deploy/bridge-deploy.sh --bridge-only    # bridge only
#   ./deploy/bridge-deploy.sh --addon-only     # addon only
#
# ⚠️  Patch is ephemeral — survives until the next `make build + make deploy`.
#     resources/desktop/ is baked into the image on the next full build.
# =============================================================================
set -euo pipefail

NAMESPACE="${NAMESPACE:-blastr}"
CONTAINER="${CONTAINER:-web}"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESOURCES_DIR="${REPO_ROOT}/resources/desktop"

PUSH_BRIDGE=true
PUSH_ADDON=true

for arg in "$@"; do
  case $arg in
    --bridge-only) PUSH_ADDON=false ;;
    --addon-only)  PUSH_BRIDGE=false ;;
  esac
done

# ── Git checks ────────────────────────────────────────────────────────────────
echo "==> Checking git state"

BRANCH=$(git -C "$REPO_ROOT" rev-parse --abbrev-ref HEAD)
if [ "$BRANCH" != "main" ]; then
  echo "ERROR: Not on main branch (currently on '${BRANCH}')"
  echo "       Switch to main and merge your changes before deploying."
  exit 1
fi
echo "    Branch: main ✓"

if ! git -C "$REPO_ROOT" diff --quiet || ! git -C "$REPO_ROOT" diff --cached --quiet; then
  echo "ERROR: Uncommitted changes detected. Commit or stash before deploying."
  echo
  git -C "$REPO_ROOT" status --short
  exit 1
fi
echo "    Working tree: clean ✓"

COMMIT=$(git -C "$REPO_ROOT" rev-parse --short HEAD)
echo "    Commit: ${COMMIT}"

# ── Resolve pod ───────────────────────────────────────────────────────────────
echo
echo "==> Finding blastr-web pod in namespace '${NAMESPACE}'"
POD=$(kubectl get pods -n "$NAMESPACE" --no-headers \
  | awk '/^blastr-web/ && /Running/ {print $1; exit}')

if [ -z "$POD" ]; then
  echo "ERROR: No running blastr-web pod found in namespace '${NAMESPACE}'"
  exit 1
fi
echo "    Pod: ${POD} ✓"

REMOTE_DIR="/var/www/html/resources/desktop"

# ── Bridge ────────────────────────────────────────────────────────────────────
if $PUSH_BRIDGE; then
  BRIDGE_BIN="${RESOURCES_DIR}/bridge.exe"
  BRIDGE_VER_FILE="${RESOURCES_DIR}/bridge-version.txt"

  if [ ! -f "$BRIDGE_BIN" ]; then
    echo "ERROR: ${BRIDGE_BIN} not found — build the desktop app first"
    exit 1
  fi

  VERSION=$(cat "$BRIDGE_VER_FILE" 2>/dev/null || echo "unknown")
  echo
  echo "==> Uploading bridge.exe v${VERSION}"
  kubectl cp "$BRIDGE_BIN" "${NAMESPACE}/${POD}:${REMOTE_DIR}/bridge.exe" -c "$CONTAINER"

  kubectl exec -n "$NAMESPACE" "$POD" -c "$CONTAINER" -- \
    sh -c "echo -n '${VERSION}' > ${REMOTE_DIR}/bridge-version.txt"

  kubectl exec -n "$NAMESPACE" "$POD" -c "$CONTAINER" -- \
    php artisan cache:forget desktop:bridge:sha256 2>/dev/null || true

  echo "    Done ✓"
fi

# ── Addon ─────────────────────────────────────────────────────────────────────
if $PUSH_ADDON; then
  ADDON_ZIP="${RESOURCES_DIR}/addon.zip"
  ADDON_VER_FILE="${RESOURCES_DIR}/addon-version.txt"

  if [ ! -f "$ADDON_ZIP" ]; then
    echo "ERROR: ${ADDON_ZIP} not found"
    exit 1
  fi

  ADDON_VERSION=$(cat "$ADDON_VER_FILE" 2>/dev/null || echo "unknown")
  echo
  echo "==> Uploading addon.zip v${ADDON_VERSION}"
  kubectl cp "$ADDON_ZIP" "${NAMESPACE}/${POD}:${REMOTE_DIR}/addon.zip" -c "$CONTAINER"

  kubectl exec -n "$NAMESPACE" "$POD" -c "$CONTAINER" -- \
    sh -c "echo -n '${ADDON_VERSION}' > ${REMOTE_DIR}/addon-version.txt"

  kubectl exec -n "$NAMESPACE" "$POD" -c "$CONTAINER" -- \
    php artisan cache:forget desktop:addon:sha256 2>/dev/null || true

  echo "    Done ✓"
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo
echo "Deployed from commit ${COMMIT} to pod ${POD}."
echo "Clients will pick up the new version on next heartbeat."
echo
echo "⚠️  Patch is ephemeral. To make permanent:"
echo "    cd deploy && make build TAG=<tag> && make deploy TAG=<tag>"
