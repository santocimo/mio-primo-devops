#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if ! command -v git >/dev/null 2>&1; then
  echo "Error: git is not installed."
  exit 1
fi

REMOTE="origin"
BRANCH="master"
DEFAULT_MESSAGE="Save latest changes"

MESSAGE="${1:-$DEFAULT_MESSAGE}"

# Show status
git status --short

echo
read -r -p "Continue and commit/push these changes? [y/N] " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
  echo "Aborted."
  exit 0
fi

# Stage all tracked changes only
git add -u

git diff --cached --quiet || true

git commit -m "$MESSAGE"

echo "Pushing to $REMOTE/$BRANCH..."
git push "$REMOTE" "$BRANCH"

echo "Code pushed successfully."
