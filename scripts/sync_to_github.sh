#!/bin/bash

# Safe GitHub Sync Script for Fleetbase Droplet
# This script ensures credentials are never uploaded and syncs local changes to GitHub.

# Exit immediately if a command exits with a non-zero status
set -e

# Define project directory relative to script path
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

echo "=================================================="
echo "Starting safe GitHub sync in: $PROJECT_DIR"
echo "=================================================="

# 1. Verify .gitignore has .env
if ! grep -q "^\.env$" .gitignore && ! grep -q "^/\.env$" .gitignore; then
    echo "⚠️  WARNING: '.env' was not found in your .gitignore!"
    echo "Adding '.env' to .gitignore to protect your secrets..."
    echo ".env" >> .gitignore
    echo "✅ Added '.env' to .gitignore."
else
    echo "✅ Confirmed: '.env' is listed in .gitignore."
fi

# 2. Check if .env is already tracked by Git
echo "Running safety checks on tracking status..."
if git ls-files --error-unmatch .env >/dev/null 2>&1; then
    echo "⚠️  CRITICAL: '.env' is currently tracked by Git!"
    echo "Removing '.env' from Git tracking cache (retaining the local file)..."
    git rm --cached .env
    git commit -m "chore: untrack .env file to protect secrets"
    echo "✅ '.env' is now successfully untracked."
else
    echo "✅ Confirmed: '.env' is NOT tracked by Git."
fi

# 3. Detect current branch
CURRENT_BRANCH=$(git branch --show-current)
echo "Current branch: $CURRENT_BRANCH"

# 4. Check for unstaged or uncommitted changes
if [ -z "$(git status --porcelain)" ]; then
    echo "ℹ️  No changes detected on the server. Your working directory is clean."
    echo "=================================================="
    echo "Sync process complete. Nothing to push."
    echo "=================================================="
    exit 0
fi

# 5. Stage and push changes
echo "Staging changes..."
git add .

echo "Committing changes..."
git commit -m "sync cloud changes"

echo "Pushing changes to remote repository (branch: $CURRENT_BRANCH)..."
git push origin "$CURRENT_BRANCH"

echo "=================================================="
echo "🎉 Safe sync completed successfully!"
echo "=================================================="
