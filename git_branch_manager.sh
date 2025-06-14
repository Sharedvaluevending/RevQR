#!/bin/bash

# Git Branch Manager Script for QR Code 2.02
# This script will:
# 1. Remove the yellow branch edit indicator
# 2. Push updates to feature/bug-bot (current main branch)
# 3. Create new branch "qr-code-2.02"
# 4. Add back yellow branch edit with "2.02" version

set -e  # Exit on any error

echo "🚀 Starting Git Branch Manager for QR Code 2.02"
echo "=============================================="

# Get current branch name
current_branch=$(git branch --show-current)
echo "📍 Current branch: $current_branch"

# Check if we have uncommitted changes
if ! git diff-index --quiet HEAD --; then
    echo "⚠️  You have uncommitted changes. Stashing them..."
    git stash push -m "Auto-stash before branch operations"
    stashed=true
else 
    stashed=false
fi

echo ""
echo "Step 1: Removing yellow branch edit indicator..."
echo "================================================"

# Remove the yellow branch edit from navbar
sed -i 's/<span class="badge ms-2" style="background-color: #ffc107; color: #000; font-size: 0.75rem; font-weight: bold;">BRANCH EDIT<\/span>//g' html/core/includes/navbar.php

echo "✅ Yellow branch edit removed from navigation"

# Commit the removal
git add html/core/includes/navbar.php
git commit -m "Remove yellow branch edit indicator before push"

echo ""
echo "Step 2: Pushing clean updates to feature/bug-bot..."
echo "=================================================="

# Push current branch with clean changes
git push origin $current_branch

echo "✅ Clean updates pushed to $current_branch"

echo ""
echo "Step 3: Creating new branch 'qr-code-2.02'..."
echo "============================================="

# Create and checkout new branch from current branch
git checkout -b "qr-code-2.02"

echo "✅ New branch 'qr-code-2.02' created and checked out"

echo ""
echo "Step 4: Adding back yellow branch edit with version 2.02..."
echo "=========================================================="

# Add back the yellow branch edit with version 2.02
sed -i 's/<span class="d-none d-sm-inline">Revenue QR<\/span>/<span class="d-none d-sm-inline">Revenue QR<\/span>\n            <span class="badge ms-2" style="background-color: #ffc107; color: #000; font-size: 0.75rem; font-weight: bold;">BRANCH EDIT 2.02<\/span>/g' html/core/includes/navbar.php

echo "✅ Yellow branch edit 2.02 added back to navigation"

# Commit the addition
git add html/core/includes/navbar.php
git commit -m "Add yellow branch edit indicator for version 2.02"

# Push the new branch
git push origin qr-code-2.02

echo ""
echo "Step 5: Verification..."
echo "======================"

# Check current branch
current_branch_final=$(git branch --show-current)
echo "📍 Current branch: $current_branch_final"

# Check if yellow indicator is present
if grep -q "BRANCH EDIT 2.02" html/core/includes/navbar.php; then
    echo "✅ Yellow branch edit 2.02 indicator is present in navigation"
else
    echo "❌ Yellow branch edit 2.02 indicator NOT found in navigation"
fi

# Restore stashed changes if any
if [ "$stashed" = true ]; then
    echo ""
    echo "🔄 Restoring previously stashed changes..."
    git stash pop
fi

echo ""
echo "🎉 SUCCESS! Git Branch Manager completed successfully!"
echo "====================================================="
echo ""
echo "Summary:"
echo "• Removed yellow branch edit indicator"
echo "• Pushed clean updates to $current_branch branch"
echo "• Created new branch 'qr-code-2.02'"
echo "• Added yellow 'BRANCH EDIT 2.02' indicator"
echo "• Current branch: $current_branch_final"
echo ""
echo "🔍 You can now verify the yellow '2.02' indicator is visible in your navigation!"
echo "🌐 Visit your site to see the yellow branch edit indicator showing version 2.02" 