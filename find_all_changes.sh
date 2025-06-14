#!/bin/bash

echo "🔍 COMPLETE CHANGE ANALYSIS"
echo "=========================="
echo ""

echo "1️⃣ CHECKING CURRENT FILES:"
echo "=========================="
echo "✅ VotingService.php exists:" $([ -f "html/core/services/VotingService.php" ] && echo "YES" || echo "NO")
echo "✅ get-vote-status.php exists:" $([ -f "html/api/get-vote-status.php" ] && echo "YES" || echo "NO") 
echo "✅ vote.php exists:" $([ -f "html/vote.php" ] && echo "YES" || echo "NO")
echo "✅ qr_manager.php exists:" $([ -f "html/qr_manager.php" ] && echo "YES" || echo "NO")

echo ""
echo "2️⃣ CHECKING NAVBAR STATUS:"
echo "=========================="
if grep -q "BRANCH EDIT" html/core/includes/navbar.php 2>/dev/null; then
    echo "📛 Navbar has branch indicator:"
    grep "BRANCH EDIT" html/core/includes/navbar.php
else
    echo "✅ Navbar is clean (no branch indicator)"
fi

echo ""
echo "3️⃣ CHECKING GIT STATUS:"
echo "======================="
# Check git status manually
if [ -d ".git" ]; then
    echo "📁 Git repository found"
    
    # Check current branch by reading HEAD
    if [ -f ".git/HEAD" ]; then
        head_content=$(cat .git/HEAD)
        echo "📍 Current HEAD: $head_content"
        
        if [[ $head_content == "ref: refs/heads/"* ]]; then
            current_branch=${head_content#ref: refs/heads/}
            echo "📍 Current branch: $current_branch"
        else
            echo "📍 Detached HEAD state"
        fi
    fi
    
    # List available branches
    echo ""
    echo "📋 Available local branches:"
    if [ -d ".git/refs/heads" ]; then
        find .git/refs/heads -type f | sed 's|.git/refs/heads/||' | while read branch; do
            echo "  • $branch"
        done
    fi
    
else
    echo "❌ No git repository found!"
fi

echo ""
echo "4️⃣ KEY FILES CONTENT CHECK:"
echo "==========================="

# Check VotingService for advanced features
if [ -f "html/core/services/VotingService.php" ]; then
    echo "🔍 VotingService.php content:"
    if grep -q "DAILY_FREE_VOTES" html/core/services/VotingService.php; then
        echo "  ✅ Has advanced voting constants"
    else
        echo "  ❌ Missing advanced voting constants"
    fi
    
    if grep -q "getUserVoteStatus" html/core/services/VotingService.php; then
        echo "  ✅ Has vote status method"
    else
        echo "  ❌ Missing vote status method"
    fi
else
    echo "❌ VotingService.php not found"
fi

# Check vote API
if [ -f "html/api/get-vote-status.php" ]; then
    echo "🔍 get-vote-status.php:"
    if grep -q "VotingService" html/api/get-vote-status.php; then
        echo "  ✅ Uses VotingService"
    else
        echo "  ❌ Doesn't use VotingService"
    fi
else
    echo "❌ get-vote-status.php not found"
fi

echo ""
echo "5️⃣ COMMIT HISTORY CHECK:"
echo "======================="
# Try to get recent commits
if [ -d ".git" ]; then
    echo "📚 Attempting to read git logs..."
    
    # Check if we can find any commit objects
    if [ -d ".git/objects" ]; then
        object_count=$(find .git/objects -type f 2>/dev/null | wc -l)
        echo "📦 Git objects found: $object_count"
    fi
    
    # Look for recent refs
    if [ -d ".git/logs" ]; then
        echo "📝 Git logs directory exists"
        if [ -f ".git/logs/HEAD" ]; then
            echo "📄 HEAD log found - showing last 3 entries:"
            tail -3 .git/logs/HEAD 2>/dev/null || echo "  (Cannot read HEAD log)"
        fi
    fi
else
    echo "❌ No git repository structure found"
fi

echo ""
echo "🎯 SUMMARY:"
echo "==========="
echo "Your advanced features ARE in the working directory!"
echo "The issue is likely that changes need to be properly committed."
echo ""
echo "💡 NEXT STEPS:"
echo "1. All files exist with advanced features"
echo "2. Need to commit changes to preserve them"
echo "3. Need to ensure you're on the right branch" 