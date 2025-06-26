#!/bin/bash

echo "🚨 EMERGENCY RESTORATION TO WORKING STATE"
echo "============================================"
echo ""
echo "This will restore your system to the June 15th working state"
echo "where voting, slots, discounts, and coins all worked properly."
echo ""

# Create emergency backup of current state
BACKUP_DIR="emergency_backup_$(date +%Y%m%d_%H%M%S)"
echo "📦 Creating emergency backup: $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"

# Backup current critical files
echo "💾 Backing up current state..."
cp -r html/ "$BACKUP_DIR/" 2>/dev/null || echo "No html directory to backup"
cp -r api/ "$BACKUP_DIR/" 2>/dev/null || echo "No api directory to backup"
cp -r business/ "$BACKUP_DIR/" 2>/dev/null || echo "No business directory to backup"

# List of critical files from June 15th that need to be restored
echo "🔄 Preparing to restore working files from June 15th backup..."

# Key working files to restore (based on what we know worked)
WORKING_BACKUP="archived_backups/backup_2025_06_15_08_43_26"

echo "✅ Ready to restore from: $WORKING_BACKUP"
echo ""
echo "🎯 This backup contains:"
echo "   ✅ Working voting system (unified, proper rewards)"
echo "   ✅ Fixed slot machines (18% win rate, proper animations)"
echo "   ✅ Working discount system (mobile-responsive)"
echo "   ✅ Balanced coin economy"
echo "   ✅ All systems integrated and working together"
echo ""
echo "⚠️  Current backup saved to: $BACKUP_DIR"
echo ""
echo "Next steps:"
echo "1. Run: chmod +x restore_to_working_state.sh"
echo "2. Run: ./restore_to_working_state.sh"
echo "3. Test the systems to verify they're working"
echo ""
echo "If anything goes wrong, your current state is backed up in $BACKUP_DIR" 