#!/bin/bash

echo "🔄 RESTORING TO WORKING STATE (WITH STASH)"
echo "=========================================="

# Show current status
echo "Current branch:"
git branch --show-current

echo ""
echo "Current uncommitted changes:"
git status --porcelain

echo ""
echo "🎯 Step 1: Stashing current changes..."
git stash push -m "Backup changes from big-scaling before restore - $(date)"

if [ $? -eq 0 ]; then
    echo "✅ Changes stashed successfully"
else
    echo "❌ Failed to stash changes"
    exit 1
fi

echo ""
echo "🎯 Step 2: Checking out 'my-complete-working-system' branch..."
git checkout my-complete-working-system

if [ $? -eq 0 ]; then
    echo "✅ Successfully switched to my-complete-working-system"
    echo ""
    echo "Current status:"
    git status
    
    echo ""
    echo "🧹 Cleaning up any untracked files..."
    git clean -fd
    
    echo ""
    echo "✅ RESTORATION COMPLETE!"
    echo "Your system should now be in the working state."
    echo ""
    echo "📝 Your previous changes are saved in git stash."
    echo "To see stashed changes: git stash list"
    echo "To restore them later: git stash pop"
    
else
    echo "❌ Failed to checkout. Trying 'complete-working-system'..."
    git checkout complete-working-system
    
    if [ $? -eq 0 ]; then
        echo "✅ Successfully switched to complete-working-system"
        git clean -fd
        echo "✅ RESTORATION COMPLETE!"
        echo ""
        echo "📝 Your previous changes are saved in git stash."
        echo "To see stashed changes: git stash list"
        echo "To restore them later: git stash pop"
    else
        echo "❌ Both branches failed. Restoring your stashed changes..."
        git stash pop
        echo "You're back where you started. Here are all available branches:"
        git branch -a
    fi
fi 