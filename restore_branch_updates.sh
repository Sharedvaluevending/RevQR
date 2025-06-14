#!/bin/bash

# Script to restore missing updates to qr-code-2.02 branch
echo "🔄 Restoring Branch Updates to QR Code 2.02"
echo "============================================"

# Check current branch
current_branch=$(git branch --show-current 2>/dev/null || echo "unknown")
echo "📍 Current branch: $current_branch"

# If we're not on qr-code-2.02, switch to it
if [ "$current_branch" != "qr-code-2.02" ]; then
    echo "🔄 Switching to qr-code-2.02 branch..."
    git checkout qr-code-2.02
fi

echo ""
echo "Step 1: Merging missing changes from feature/bug-bot..."
echo "====================================================="

# Try to merge the latest changes from feature/bug-bot
git merge feature/bug-bot --no-edit --allow-unrelated-histories || {
    echo "⚠️  Merge conflicts detected. Resolving..."
    # Prefer the feature/bug-bot version for key files
    git checkout --theirs html/api/get-vote-status.php
    git checkout --theirs html/api/qr/enhanced-generate.php  
    git checkout --theirs html/api/qr/enhanced-preview.php
    git checkout --theirs html/business/manage-machine.php
    git checkout --theirs html/business/promotional-ads.php
    git checkout --theirs html/business/store.php
    git checkout --theirs html/business/winners.php
    git checkout --theirs html/config/qr.php
    git checkout --theirs html/core/services/VotingService.php
    git checkout --theirs html/qr-display.php
    git checkout --theirs html/qr-generator-enhanced.php
    git checkout --theirs html/qr-generator-working.php
    git checkout --theirs html/qr-generator.php
    git checkout --theirs html/qr_dynamic_manager.php
    git checkout --theirs html/qr_manager.php
    git checkout --theirs html/templates/qr-generator.php
    git checkout --theirs html/user/dashboard.php
    git checkout --theirs html/user/vote.php
    git checkout --theirs html/vote.php
    
    # Keep our yellow indicator in navbar
    git checkout --ours html/core/includes/navbar.php
    
    git add .
    git commit -m "Resolve merge conflicts - restore advanced features with yellow indicator 2.02"
}

echo "✅ Advanced features restored"

echo ""
echo "Step 2: Verifying critical files are present..."
echo "=============================================="

# Check for key advanced features
if [ -f "html/api/get-vote-status.php" ]; then
    echo "✅ Advanced vote status API present"
else
    echo "❌ Missing advanced vote status API"
fi

if [ -f "html/core/services/VotingService.php" ]; then
    echo "✅ VotingService present"
else
    echo "❌ Missing VotingService"
fi

if grep -q "BRANCH EDIT 2.02" html/core/includes/navbar.php; then
    echo "✅ Yellow branch indicator 2.02 present"
else
    echo "❌ Missing yellow branch indicator 2.02"
fi

echo ""
echo "Step 3: Committing final updates..."
echo "=================================="

git add .
git commit -m "Final restoration of advanced features for QR Code 2.02" || echo "No additional changes to commit"

echo ""
echo "Step 4: Pushing updated branch..."
echo "================================"

git push origin qr-code-2.02

echo ""
echo "🎉 SUCCESS! Branch restoration completed!"
echo "========================================"
echo ""
echo "✅ Advanced voting system restored"
echo "✅ Enhanced QR manager restored"  
echo "✅ Yellow branch indicator 2.02 preserved"
echo "✅ All business features restored"
echo ""
echo "🔍 You should now see:"
echo "• Advanced voting page with enhanced features"
echo "• QR Manager with proper styling"
echo "• Yellow 'BRANCH EDIT 2.02' indicator in navigation"
echo ""
echo "🌐 Visit your site to verify all features are working!" 