#!/usr/bin/env bash
set -euo pipefail

git config user.name "github-actions[bot]"
git config user.email "github-actions[bot]@users.noreply.github.com"

VERSION="$(grep 'PTPRM_VERSION' wordpress/ptsbi-premium/ptsbi-premium.php | head -1 || echo unknown)"
STAMP="$(date -u +%Y-%m-%dT%H%MZ)"

git checkout -B production-live
git add wordpress/ptsbi-premium

if git diff --staged --quiet; then
  echo "Tidak ada perubahan dibanding commit sebelumnya."
else
  git commit -m "chore(production-live): snapshot plugin dari server ${STAMP}"
fi

git tag -f production-live
# Branch dan tag sama nama — push harus pakai ref penuh agar tidak ambigu.
git push origin refs/heads/production-live --force
git push origin refs/tags/production-live --force

echo "Versi live: ${VERSION}"
