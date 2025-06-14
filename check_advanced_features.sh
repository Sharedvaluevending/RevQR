#!/bin/bash

echo "🔍 CHECKING ADVANCED FEATURES IN QR-CODE-2.02 BRANCH"
echo "====================================================="

echo -e "\n1. ✅ Checking VotingService.php for advanced voting system..."
if grep -q "DAILY_FREE_VOTES" html/core/services/VotingService.php; then
    echo "   ✅ DAILY_FREE_VOTES found"
else
    echo "   ❌ DAILY_FREE_VOTES missing"
fi

if grep -q "premium_votes_available" html/core/services/VotingService.php; then
    echo "   ✅ Premium voting system found"
else
    echo "   ❌ Premium voting system missing"
fi

echo -e "\n2. ✅ Checking vote.php for enhanced voting interface..."
if grep -q "voting-options" html/vote.php; then
    echo "   ✅ Enhanced voting options found"
else
    echo "   ❌ Enhanced voting options missing"
fi

if grep -q "QR Balance" html/vote.php; then
    echo "   ✅ QR Balance display found"
else
    echo "   ❌ QR Balance display missing"
fi

echo -e "\n3. ✅ Checking user/vote.php for analytics dashboard..."
if grep -q "success_rate" html/user/vote.php; then
    echo "   ✅ Success rate analytics found"
else
    echo "   ❌ Success rate analytics missing"
fi

if grep -q "coin_analytics" html/user/vote.php; then
    echo "   ✅ Coin analytics found"
else
    echo "   ❌ Coin analytics missing"
fi

if grep -q "device_analytics" html/user/vote.php; then
    echo "   ✅ Device analytics found"
else
    echo "   ❌ Device analytics missing"
fi

echo -e "\n4. ✅ Checking get-vote-status.php API..."
if [ -f "html/api/get-vote-status.php" ]; then
    echo "   ✅ Vote status API exists"
else
    echo "   ❌ Vote status API missing"
fi

echo -e "\n5. ✅ Checking QR Manager..."
if grep -q "qr_coin_cost" html/business/qr_manager.php; then
    echo "   ✅ QR Manager with coin costs found"
else
    echo "   ❌ QR Manager missing coin costs"
fi

echo -e "\n6. ✅ Checking branch indicator..."
if grep -q "BRANCH EDIT 2.02" html/core/includes/navbar.php; then
    echo "   ✅ Yellow 2.02 branch indicator found"
else
    echo "   ❌ Branch indicator missing"
fi

echo -e "\n🔄 Current Git Status:"
git branch --show-current
echo "Current commit: $(git rev-parse HEAD)"

echo -e "\n🎯 SOLUTION: If features appear missing, try:"
echo "   1. Clear your browser cache (Ctrl+F5)"
echo "   2. Check if you're logged in as a user (not guest)"
echo "   3. Visit /user/vote.php for advanced analytics"
echo "   4. Visit /vote.php for enhanced voting options" 