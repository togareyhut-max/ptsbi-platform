#!/usr/bin/env bash
set -euo pipefail

git config user.name "github-actions[bot]"
git config user.email "github-actions[bot]@users.noreply.github.com"

VERSION="$(grep -oP "define\(\s*'PTPRM_VERSION',\s*'\K[^']+" wordpress/ptsbi-premium/ptsbi-premium.php | head -1 || echo unknown)"
STAMP="$(date -u +%Y-%m-%dT%H%MZ)"
TAG="baseline-v${VERSION}"

git checkout -B production-live
git add wordpress/ptsbi-premium

if git diff --staged --quiet; then
  echo "Tidak ada perubahan dibanding commit sebelumnya."
else
  git commit -m "chore(production-live): snapshot plugin dari server ${STAMP}"
fi

git tag -f "${TAG}"
git push origin refs/heads/production-live --force
git push origin "refs/tags/${TAG}" --force

echo "Versi live: ${VERSION} (tag ${TAG})"
