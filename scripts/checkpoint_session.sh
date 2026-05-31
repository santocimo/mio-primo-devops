#!/usr/bin/env bash
set -euo pipefail

# One-click checkpoint for this repository without staging runtime/home noise.
# Stages only project paths, updates SESSION_HANDOFF.md, commits, and pushes.

usage() {
  cat <<'EOF'
Usage:
  scripts/checkpoint_session.sh -m "Commit message" -s "Summary" -n "Next step" [-b branch]

Options:
  -m  Commit message (required)
  -s  Short summary of what was completed (required)
  -n  Next action to resume quickly next session (required)
  -b  Branch to push (default: current branch)

Notes:
  - Refuses to run on master/main to avoid polluted-history pushes.
  - Stages only project paths: api, app-mobile, docker-compose.yml, SESSION_HANDOFF.md, README.md, CHANGELOG.md.
EOF
}

require_arg() {
  local name="$1"
  local value="$2"
  if [[ -z "$value" ]]; then
    echo "Missing required argument: $name" >&2
    usage
    exit 1
  fi
}

COMMIT_MSG=""
SUMMARY=""
NEXT_STEP=""
TARGET_BRANCH=""

while getopts ":m:s:n:b:h" opt; do
  case "$opt" in
    m) COMMIT_MSG="$OPTARG" ;;
    s) SUMMARY="$OPTARG" ;;
    n) NEXT_STEP="$OPTARG" ;;
    b) TARGET_BRANCH="$OPTARG" ;;
    h)
      usage
      exit 0
      ;;
    :)
      echo "Option -$OPTARG requires an argument." >&2
      usage
      exit 1
      ;;
    \?)
      echo "Invalid option: -$OPTARG" >&2
      usage
      exit 1
      ;;
  esac
done

require_arg "-m" "$COMMIT_MSG"
require_arg "-s" "$SUMMARY"
require_arg "-n" "$NEXT_STEP"

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

CURRENT_BRANCH="$(git branch --show-current)"
if [[ -z "$TARGET_BRANCH" ]]; then
  TARGET_BRANCH="$CURRENT_BRANCH"
fi

if [[ "$CURRENT_BRANCH" == "master" || "$CURRENT_BRANCH" == "main" ]]; then
  echo "Refusing to run on $CURRENT_BRANCH. Switch to a dedicated branch first." >&2
  exit 2
fi

if [[ "$TARGET_BRANCH" != "$CURRENT_BRANCH" ]]; then
  echo "Target branch ($TARGET_BRANCH) must match current branch ($CURRENT_BRANCH)." >&2
  exit 3
fi

LAST_COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo "none")"
NOW_UTC="$(date -u +"%Y-%m-%d %H:%M UTC")"

cat > SESSION_HANDOFF.md <<EOF
# Session Handoff

Last update: $NOW_UTC

## Branch
$CURRENT_BRANCH

## Last commit before checkpoint
$LAST_COMMIT

## Completed in this session
$SUMMARY

## Next step
$NEXT_STEP

## Quick restart checklist
1. git switch $CURRENT_BRANCH
2. cd app-mobile && npm run build
3. Quick smoke test: login -> dashboard -> logout
EOF

SAFE_PATHS=(
  "api"
  "app-mobile"
  "docker-compose.yml"
  "SESSION_HANDOFF.md"
  "README.md"
  "CHANGELOG.md"
)

for p in "${SAFE_PATHS[@]}"; do
  if [[ -e "$p" ]]; then
    git add -A -- "$p"
  fi
done

if git diff --cached --quiet; then
  echo "No staged project changes to commit. Handoff was updated only if changed." >&2
  exit 4
fi

# Guard against accidentally staging very large blobs.
while IFS= read -r file; do
  [[ -z "$file" ]] && continue
  if [[ -f "$file" ]]; then
    size_bytes=$(wc -c < "$file")
    if [[ "$size_bytes" -gt 95000000 ]]; then
      echo "Refusing commit: staged file too large ($size_bytes bytes): $file" >&2
      exit 5
    fi
  fi
done < <(git diff --cached --name-only)

git commit -m "$COMMIT_MSG"
git push -u origin "$CURRENT_BRANCH"

echo "Checkpoint completed on branch: $CURRENT_BRANCH"
