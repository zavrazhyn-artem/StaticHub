#!/usr/bin/env bash
# =============================================================================
# BlastR — Production deploy script
#
# Usage:
#   ./deploy.sh           # auto-bumps patch (v0.1.2 → v0.1.3)
#   ./deploy.sh v1.2.0    # deploy a specific version tag
#
# Requirements: docker, doctl (authenticated), kubectl, helm
# Must be run from the main branch with a clean working tree.
# =============================================================================
set -euo pipefail

REGISTRY="registry.digitalocean.com/blastr/app"
NAMESPACE="blastr"
HELM_RELEASE="blastr"
HELM_CHART="deploy/helm/blastr"
VALUES_FILE="deploy/helm/blastr/values.yaml"

# ── Colours ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

step()  { echo -e "\n${CYAN}${BOLD}▶ $*${RESET}"; }
ok()    { echo -e "${GREEN}✓ $*${RESET}"; }
warn()  { echo -e "${YELLOW}⚠ $*${RESET}"; }
die()   { echo -e "${RED}✗ $*${RESET}" >&2; exit 1; }

# ── Helpers ───────────────────────────────────────────────────────────────────

bump_patch() {
    local ver="${1#v}"
    local major minor patch
    IFS='.' read -r major minor patch <<< "$ver"
    echo "v${major}.${minor}.$((patch + 1))"
}

current_deployed_tag() {
    kubectl get deployment -n "$NAMESPACE" blastr-web \
        -o jsonpath='{.spec.template.spec.containers[0].image}' 2>/dev/null \
        | sed 's/.*://' || echo ""
}

# ── Guard: main branch only ───────────────────────────────────────────────────
step "Validating environment"

BRANCH=$(git rev-parse --abbrev-ref HEAD)
[[ "$BRANCH" == "main" ]] || die "Must be on main branch (current: $BRANCH)"
ok "Branch: main"

if ! git diff --quiet || ! git diff --cached --quiet; then
    die "Working tree is dirty. Commit or stash changes before deploying."
fi
ok "Working tree clean"

git fetch origin main --quiet
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
[[ "$LOCAL" == "$REMOTE" ]] || die "Local main is behind origin/main. Run: git pull"
ok "Up to date with origin/main"

# ── Determine version ─────────────────────────────────────────────────────────
step "Determining version"

if [[ $# -ge 1 ]]; then
    NEW_TAG="$1"
    [[ "$NEW_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] \
        || die "Invalid version format: $NEW_TAG (expected vMAJOR.MINOR.PATCH)"
    ok "Version from argument: $NEW_TAG"
else
    CURRENT_TAG=$(current_deployed_tag)
    if [[ -z "$CURRENT_TAG" || "$CURRENT_TAG" == "0.1.0" ]]; then
        CURRENT_TAG=$(git tag --sort=-v:refname | head -1)
    fi
    [[ -n "$CURRENT_TAG" ]] \
        || die "Cannot determine current version. Pass explicitly: ./deploy.sh v0.1.3"
    NEW_TAG=$(bump_patch "$CURRENT_TAG")
    ok "Auto-bumped: $CURRENT_TAG → $NEW_TAG"
fi

FULL_IMAGE="${REGISTRY}:${NEW_TAG}"
echo -e "  Image: ${BOLD}${FULL_IMAGE}${RESET}"

echo ""
read -rp "$(echo -e "${YELLOW}Deploy ${NEW_TAG} to production? [y/N]${RESET} ")" CONFIRM
[[ "${CONFIRM,,}" == "y" ]] || { echo "Aborted."; exit 0; }

# ── Login to DO Container Registry ───────────────────────────────────────────
step "Authenticating with DO Container Registry"
doctl registry login --expiry-seconds 3600
ok "Registry authenticated"

# ── Build Docker image ────────────────────────────────────────────────────────
step "Building Docker image: $FULL_IMAGE"
docker build \
    --file docker/app/Dockerfile \
    --tag "$FULL_IMAGE" \
    --tag "${REGISTRY}:latest" \
    .
ok "Image built"

# ── Push to registry ──────────────────────────────────────────────────────────
step "Pushing image to registry"
docker push "$FULL_IMAGE"
docker push "${REGISTRY}:latest"
ok "Image pushed: $FULL_IMAGE"

# ── Git tag ───────────────────────────────────────────────────────────────────
step "Tagging git commit"
git tag "$NEW_TAG"
git push origin "$NEW_TAG"
ok "Tagged: $NEW_TAG"

# ── Helm upgrade ──────────────────────────────────────────────────────────────
step "Running Helm upgrade ($NEW_TAG)"
helm upgrade "$HELM_RELEASE" "$HELM_CHART" \
    --namespace "$NAMESPACE" \
    --values "$VALUES_FILE" \
    --set "image.tag=${NEW_TAG}" \
    --timeout 5m \
    --atomic \
    --cleanup-on-fail
ok "Helm upgrade complete"

# ── Verify rollout ────────────────────────────────────────────────────────────
step "Waiting for web rollout"
kubectl rollout status deployment/blastr-web -n "$NAMESPACE" --timeout=5m
ok "Web deployment is live"

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}============================================${RESET}"
echo -e "${GREEN}${BOLD}  Deployed: ${NEW_TAG}${RESET}"
echo -e "${GREEN}${BOLD}============================================${RESET}"
echo -e "  Image:   ${FULL_IMAGE}"
echo -e "  Commit:  $(git rev-parse --short HEAD)"
echo ""
echo -e "  Rollback if needed:"
echo -e "  ${YELLOW}helm rollback ${HELM_RELEASE} -n ${NAMESPACE}${RESET}"
echo ""
