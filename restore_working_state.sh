#!/bin/bash

echo "🔄 RESTORING TO WORKING STATE"
echo "=============================="

# Show current status
echo "Current branch:"
git branch --show-current

echo ""
echo "Available branches with 'working' or 'complete' in name:"
git branch | grep -E "(working|complete)"

echo ""
echo "🎯 Checking out 'my-complete-working-system' branch..."
git checkout my-complete-working-system

if [ $? -eq 0 ]; then
    echo "✅ Successfully switched to my-complete-working-system"
    echo ""
    echo "Current status:"
    git status
    
    echo ""
    echo "🧹 Cleaning up any untracked files from big-scaling..."
    git clean -fd
    
    echo ""
    echo "✅ RESTORATION COMPLETE!"
    echo "Your system should now be in the working state."
else
    echo "❌ Failed to checkout. Trying 'complete-working-system'..."
    git checkout complete-working-system
    
    if [ $? -eq 0 ]; then
        echo "✅ Successfully switched to complete-working-system"
        git clean -fd
        echo "✅ RESTORATION COMPLETE!"
    else
        echo "❌ Both branches failed. Let's see what's available:"
        git branch -a
    fi
fi 