#!/bin/bash

echo "🤖 Switching to Bug Bot Branch (feature/bug-bot)"
echo "================================================"

echo "🔄 Switching to feature/bug-bot branch..."
git checkout feature/bug-bot

echo "✅ Now on feature/bug-bot branch"
echo ""
echo "🔍 Checking branch status..."

# Check if navbar has any branch indicator
if grep -q "BRANCH EDIT" html/core/includes/navbar.php; then
    echo "📛 Branch indicator found:"
    grep "BRANCH EDIT" html/core/includes/navbar.php
else
    echo "✅ No branch indicator (clean development branch)"
fi

echo ""
echo "🧪 Bug Bot Branch Features:"
echo "• Original development branch"
echo "• All advanced voting features"
echo "• Enhanced QR manager"
echo "• Latest business functionality"
echo "• No production yellow indicator"
echo ""
echo "🌐 You can now view your site to see the bug bot branch version"
echo ""
echo "💡 Branch switching options:"
echo "• Main branch: git checkout main"
echo "• QR Code 2.02: git checkout qr-code-2.02"
echo "• Bug Bot: git checkout feature/bug-bot (current)" 