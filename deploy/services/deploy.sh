#!/usr/bin/env bash
# =============================================================================
# Deploy obs droplet stack: rsync configs + docker compose up -d.
#
# Usage:
#   OBS_HOST=root@<ip-or-dns> ./deploy/services/deploy.sh
#
# Requires on local:  rsync, ssh
# Requires on remote: docker, docker compose v2, doctl (logged in to DOCR)
#
# Sensitive files NOT pushed (managed manually on remote):
#   .env         — Pulse + Glitchtip + Grafana secrets
#   kubeconfig   — DOKS admin credentials for Headlamp
# =============================================================================
set -euo pipefail

OBS_HOST="${OBS_HOST:-root@obs.blastr.pro}"
OBS_PATH="${OBS_PATH:-/opt/blastr-services}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Syncing configs to ${OBS_HOST}:${OBS_PATH}"
rsync -avh --delete \
  --exclude='.env' \
  --exclude='kubeconfig' \
  --exclude='deploy.sh' \
  "${SCRIPT_DIR}/" \
  "${OBS_HOST}:${OBS_PATH}/"

echo
echo "==> Pulling latest images on remote"
ssh "${OBS_HOST}" "cd ${OBS_PATH} && docker compose pull"

echo
echo "==> Bringing stack up (apply changes)"
ssh "${OBS_HOST}" "cd ${OBS_PATH} && docker compose up -d --remove-orphans"

echo
echo "==> Current state"
ssh "${OBS_HOST}" "cd ${OBS_PATH} && docker compose ps"

echo
echo "Done. To follow pulse-worker logs:"
echo "  ssh ${OBS_HOST} 'docker logs -f blastr_obs_pulse_worker'"
