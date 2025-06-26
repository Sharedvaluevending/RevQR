#!/bin/bash
# Horse Racing System Cron Job
# Updates horse fatigue and processes races every 5 minutes

cd /var/www/html/horse-racing
/usr/bin/php enhanced_race_engine.php >> ../logs/horse_racing_cron.log 2>&1
