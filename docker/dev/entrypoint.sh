#!/bin/sh
# Minimal production entrypoint.
# Storage structure is pre-created in the Dockerfile; named volumes seed from it on first run.
# All migrations and cache warming are performed via Makefile targets before traffic is served.
set -e
exec "$@"
