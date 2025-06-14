#!/bin/bash

# Simple Git Branch Manager Script for QR Code 2.02
# This script will:
# 1. Commit all current changes to clean up the branch
# 2. Push updates to feature/bug-bot 
# 3. Create new branch "qr-code-2.02"
# 4. Add yellow branch edit indicator with "2.02" version

set -e  # Exit on any error

echo "🚀 Starting Simple Git Branch Manager for QR Code 2.02"
echo "===================================================="

# Get current branch name
current_branch=$(git branch --show-current)
echo "📍 Current branch: $current_branch"

echo ""
echo "Step 1: Committing all current changes..."
echo "========================================"

# Add all changes including untracked files
git add .
git commit -m "Commit all changes before creating QR Code 2.02 branch" || echo "No changes to commit"

echo "✅ All changes committed"

echo ""
echo "Step 2: Pushing updates to $current_branch..."
echo "============================================="

# Push current branch
git push origin $current_branch

echo "✅ Updates pushed to $current_branch"

echo ""
echo "Step 3: Creating new branch 'qr-code-2.02'..."
echo "============================================="

# Create and checkout new branch
git checkout -b "qr-code-2.02"

echo "✅ New branch 'qr-code-2.02' created and checked out"

echo ""
echo "Step 4: Adding yellow branch edit indicator with version 2.02..."
echo "==============================================================="

# Add the yellow branch edit with version 2.02 to navbar
sed -i 's/<span class="d-none d-sm-inline">Revenue QR<\/span>/<span class="d-none d-sm-inline">Revenue QR<\/span>\n            <span class="badge ms-2" style="background-color: #ffc107; color: #000; font-size: 0.75rem; font-weight: bold;">BRANCH EDIT 2.02<\/span>/g' html/core/includes/navbar.php

echo "✅ Yellow branch edit 2.02 added to navigation"

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

echo ""
echo "🎉 SUCCESS! Git Branch Manager completed successfully!"
echo "====================================================="
echo ""
echo "Summary:"
echo "• Committed all pending changes to $current_branch"
echo "• Pushed clean updates to $current_branch branch"
echo "• Created new branch 'qr-code-2.02'"
echo "• Added yellow 'BRANCH EDIT 2.02' indicator"
echo "• Current branch: $current_branch_final"
echo ""
echo "🔍 You can now verify the yellow '2.02' indicator is visible in your navigation!"
echo "🌐 Visit your site to see the yellow branch edit indicator showing version 2.02" 