#!/bin/bash

# Quick Races Cron Job Setup Script
# This sets up the cron job to run the quick race engine every minute

echo "Setting up Quick Races cron job..."

# Create logs directory if it doesn't exist
mkdir -p /var/www/html/logs

# Create the cron job entry
CRON_JOB="* * * * * cd /var/www/html && php horse-racing/quick-race-engine.php >> logs/quick_races.log 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "quick-race-engine.php"; then
    echo "Quick races cron job already exists"
else
    # Add the cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "Quick races cron job added successfully"
fi

# Set proper permissions
chmod +x /var/www/html/horse-racing/quick-race-engine.php
chmod 755 /var/www/html/logs

echo "Quick Races setup complete!"
echo ""
echo "Cron job will run every minute to process races at:"
echo "- 9:35 AM (Morning Sprint)"
echo "- 12:00 PM (Lunch Rush)" 
echo "- 6:10 PM (Evening Thunder)"
echo "- 9:05 PM (Night Lightning)"
echo "- 2:10 AM (Midnight Express)"
echo "- 5:10 AM (Dawn Dash)"
echo ""
echo "To view logs: tail -f /var/www/html/logs/quick_races.log"
echo "To remove cron job: crontab -e (then delete the quick-race-engine line)" 