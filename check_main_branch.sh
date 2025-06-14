#!/bin/bash

echo "🔍 Checking Main Branch Content"
echo "==============================="

# Get current branch
current_branch=$(git branch --show-current 2>/dev/null || echo "unknown")
echo "📍 Currently on branch: $current_branch"

# List all branches
echo ""
echo "📋 Available branches:"
git branch -a 2>/dev/null || echo "Unable to list branches"

echo ""
echo "🔄 Switching to main branch..."

# Try to switch to main branch
if git checkout main 2>/dev/null; then
    echo "✅ Successfully switched to main branch"
    
    echo ""
    echo "📁 Files in main branch:"
    ls -la | head -20
    
    echo ""
    echo "📊 Git status on main:"
    git status --short
    
    echo ""
    echo "🔍 Checking if navbar has yellow indicator:"
    if [ -f "html/core/includes/navbar.php" ]; then
        if grep -q "BRANCH EDIT" html/core/includes/navbar.php; then
            echo "⚠️  Main branch has yellow indicator (should be clean)"
            grep "BRANCH EDIT" html/core/includes/navbar.php
        else
            echo "✅ Main branch is clean (no yellow indicator)"
        fi
    else
        echo "❌ Navbar file not found"
    fi
    
    echo ""
    echo "🔍 Checking key voting files in main:"
    
    if [ -f "html/api/get-vote-status.php" ]; then
        echo "✅ Vote status API present"
    else
        echo "❌ Vote status API missing"
    fi
    
    if [ -f "html/core/services/VotingService.php" ]; then
        echo "✅ VotingService present"
    else
        echo "❌ VotingService missing"
    fi
    
    if [ -f "html/vote.php" ]; then
        echo "✅ Main vote page present"
    else
        echo "❌ Main vote page missing"
    fi
    
    echo ""
    echo "📋 Recent commits on main:"
    git log --oneline -5 2>/dev/null || echo "Unable to show commit history"
    
else
    echo "❌ Unable to switch to main branch"
    echo "Trying alternative names..."
    
    if git checkout master 2>/dev/null; then
        echo "✅ Switched to master branch instead"
    else
        echo "❌ Unable to switch to main/master"
        echo "Available branches:"
        git branch --all
    fi
fi

echo ""
echo "🎯 Main branch analysis complete!" 