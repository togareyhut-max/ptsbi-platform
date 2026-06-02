#!/usr/bin/env bash
# Restart/repair Tarombo production via SSH (safe/manual).
#
# Usage:
#   SSH_HOST=5.175.245.78 SSH_USER=togaa bash scripts/ops/restart-tarombo-over-ssh.sh
#
# Optional:
#   SSH_PORT=22
#   REMOTE_APP_DIR=/home/togaa/tarombo-app
set -euo pipefail

SSH_PORT="${SSH_PORT:-22}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-/home/togaa/tarombo-app}"

if [[ -z "${SSH_HOST:-}" || -z "${SSH_USER:-}" ]]; then
  echo "ERROR: set SSH_HOST and SSH_USER"
  exit 1
fi

SSH_TARGET="${SSH_USER}@${SSH_HOST}"

echo "==> Restart/repair Tarombo on ${SSH_TARGET}:${REMOTE_APP_DIR}"
ssh -p "${SSH_PORT}" -o StrictHostKeyChecking=accept-new "${SSH_TARGET}" bash -s <<EOF
set -euo pipefail
cd "${REMOTE_APP_DIR}"
docker compose -f docker-compose.server.yml up -d --build
chmod +x scripts/*.sh 2>/dev/null || true
if [[ -f scripts/post-deploy-tarombo.sh ]]; then
  bash scripts/post-deploy-tarombo.sh
fi
echo "==> Health ping:"
curl -fsS "https://tarombo.ptsbi.org/v1/ping" || (echo "ERROR: ping failed" && exit 2)
echo "==> DB health:"
curl -fsS "https://tarombo.ptsbi.org/v1/health/db" || (echo "ERROR: db health failed" && exit 3)
EOF

echo "Done."

