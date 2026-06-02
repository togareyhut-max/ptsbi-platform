#!/usr/bin/env bash
set -euo pipefail

git checkout main
git pull origin main
git checkout production-live -- wordpress/ptsbi-premium
git add wordpress/ptsbi-premium

if git diff --staged --quiet; then
  echo "main sudah sama dengan production-live untuk folder plugin."
else
  git commit -m "chore(main): samakan plugin dengan production-live (website live)"
  git push origin main
fi
