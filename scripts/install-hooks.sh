#!/bin/bash
# Run this once on any machine (local or server) to install git hooks.
# Usage: bash scripts/install-hooks.sh

ROOT=$(git rev-parse --show-toplevel 2>/dev/null)

if [ -z "$ROOT" ]; then
  echo "Error: Not inside a git repository."
  exit 1
fi

HOOKS_DIR="$ROOT/.git/hooks"
SCRIPTS_DIR="$ROOT/scripts"

cp "$SCRIPTS_DIR/post-commit" "$HOOKS_DIR/post-commit"
chmod +x "$HOOKS_DIR/post-commit"

echo "Git hooks installed successfully."
echo "  post-commit → $HOOKS_DIR/post-commit"
