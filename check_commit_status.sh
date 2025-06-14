#!/bin/bash

echo "🔍 Checking Commit Status and Changes"
echo "===================================="
echo ""

# Check current git status
echo "📊 Current Git Status:"
git status

echo ""
echo "📋 Recent Commits:"
git log --oneline -10

echo ""
echo "🔍 Checking what files are actually modified:"
echo "Modified files in working directory:"
git diff --name-only

echo ""
echo "🔍 Staged files (ready to commit):"
git diff --cached --name-only

echo ""
echo "🔍 Untracked files:"
git ls-files --others --exclude-standard

echo ""
echo "🎯 PROBLEM ANALYSIS:"
echo "==================="
echo "If you don't see your changes, it might be because:"
echo "1. Changes were made but not committed"
echo "2. Changes were committed to wrong branch"
echo "3. Working directory changes need to be saved"
echo ""
echo "💡 Let's check what branch we're actually on:"
git branch --show-current

echo ""
echo "🔧 SOLUTION OPTIONS:"
echo "==================="
echo "1. Commit current changes: git add . && git commit -m 'Save changes'"
echo "2. Check other branches: git checkout [branch-name]"
echo "3. Stash changes: git stash"
echo "4. See differences: git diff" 