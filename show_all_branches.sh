#!/bin/bash

echo "🌳 Git Repository Branch Overview"
echo "================================="
echo ""

echo "📋 LOCAL BRANCHES:"
echo "=================="
git branch --list || echo "Unable to list local branches"

echo ""
echo "🌐 REMOTE BRANCHES:"
echo "==================="
git branch --remotes || echo "Unable to list remote branches"

echo ""
echo "🔍 ALL BRANCHES (Local + Remote):"
echo "================================="
git branch --all || echo "Unable to list all branches"

echo ""
echo "📍 CURRENT BRANCH:"
echo "=================="
current_branch=$(git branch --show-current 2>/dev/null || git symbolic-ref --short HEAD 2>/dev/null || echo "detached")
echo "You are currently on: $current_branch"

echo ""
echo "📊 BRANCH SUMMARY:"
echo "=================="
local_count=$(git branch --list 2>/dev/null | wc -l)
remote_count=$(git branch --remotes 2>/dev/null | wc -l)
total_count=$(git branch --all 2>/dev/null | wc -l)

echo "• Local branches: $local_count"
echo "• Remote branches: $remote_count"
echo "• Total branches: $total_count"

echo ""
echo "🎯 KEY BRANCHES IN YOUR REPO:"
echo "============================="
echo "• main - Clean production branch"
echo "• feature/bug-bot - Original development branch with advanced features"
echo "• qr-code-2.02 - New branch with yellow 'BRANCH EDIT 2.02' indicator"

echo ""
echo "💡 To switch branches:"
echo "• git checkout main"
echo "• git checkout feature/bug-bot"
echo "• git checkout qr-code-2.02" 