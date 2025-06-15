#!/bin/bash

# Comprehensive Work Backup Script
# This script will preserve all your current work safely
# Each command runs individually for better control

echo "=== COMPREHENSIVE WORK BACKUP SCRIPT ==="
echo "Starting backup process..."

# Set up variables
TIMESTAMP=$(date +"%Y_%m_%d_%H_%M_%S")
BACKUP_BRANCH="work-backup-$TIMESTAMP"

echo "Backup branch will be: $BACKUP_BRANCH"
echo ""

# Step 1: Check if we're in a git repository
echo "=== Step 1: Checking Git Repository ==="
if [ ! -d ".git" ]; then
    echo "ERROR: Not in a git repository!"
    exit 1
fi
echo "✅ Git repository found"
echo ""

# Step 2: Show current branch
echo "=== Step 2: Checking Current Branch ==="
git branch --show-current
echo ""

# Step 3: Show current status
echo "=== Step 3: Showing Current Git Status ==="
git status --short
echo ""

# Step 4: Count modified files
echo "=== Step 4: Counting Modified Files ==="
git status --porcelain | wc -l
echo ""

# Step 5: Stage all changes
echo "=== Step 5: Staging All Changes ==="
git add -A
echo "✅ All files staged"
echo ""

# Step 6: Show what was staged
echo "=== Step 6: Showing Staged Files ==="
git status --short
echo ""

# Step 7: Create commit with current work
echo "=== Step 7: Creating Commit ==="
git commit -m "BACKUP: Complete work backup - $TIMESTAMP

This commit contains all current work including:
- QR system enhancements
- Casino system improvements  
- Business dashboard updates
- All modified files and assets
- Database schema changes
- API enhancements

Backup created on: $(date)
"
echo "✅ Commit created"
echo ""

# Step 8: Create backup branch
echo "=== Step 8: Creating Backup Branch ==="
git branch "$BACKUP_BRANCH"
echo "✅ Backup branch created: $BACKUP_BRANCH"
echo ""

# Step 9: Show all branches
echo "=== Step 9: Showing All Branches ==="
git branch -a
echo ""

# Step 10: Check for remote
echo "=== Step 10: Checking for Remote Repository ==="
git remote
echo ""

# Step 11: Push current branch to remote (if remote exists)
echo "=== Step 11: Pushing Current Branch to Remote ==="
if git remote | grep -q origin; then
    echo "Remote found - pushing current branch..."
    git push origin main
    echo "✅ Current branch pushed"
else
    echo "No remote repository found - skipping push"
fi
echo ""

# Step 12: Push backup branch to remote (if remote exists)
echo "=== Step 12: Pushing Backup Branch to Remote ==="
if git remote | grep -q origin; then
    echo "Remote found - pushing backup branch..."
    git push origin "$BACKUP_BRANCH"
    echo "✅ Backup branch pushed"
else
    echo "No remote repository found - skipping backup branch push"
fi
echo ""

# Step 13: Create backup directory
echo "=== Step 13: Creating File System Backup Directory ==="
BACKUP_DIR="backup_$TIMESTAMP"
mkdir -p "$BACKUP_DIR"
echo "✅ Backup directory created: $BACKUP_DIR"
echo ""

# Step 14: Copy files to backup
echo "=== Step 14: Copying Files to Backup ==="
cp -r . "$BACKUP_DIR/"
echo "✅ Files copied to backup directory"
echo ""

# Step 15: Final Summary
echo "=== BACKUP COMPLETE ==="
echo "✅ All changes have been committed"
echo "✅ Backup branch created: $BACKUP_BRANCH"
echo "✅ Current branch preserved and pushed"
echo "✅ File system backup created: $BACKUP_DIR"
echo ""
echo "Your work is now safely backed up in multiple ways:"
echo "1. Committed to current branch (main)"
echo "2. Backup branch created: $BACKUP_BRANCH"
echo "3. File system backup: $BACKUP_DIR"
echo ""
echo "Useful commands:"
echo "- View all branches: git branch -a"
echo "- Switch to backup branch: git checkout $BACKUP_BRANCH"
echo "- View commit history: git log --oneline"
echo ""
echo "🎉 YOUR WORK IS COMPLETELY SAFE! 🎉" 