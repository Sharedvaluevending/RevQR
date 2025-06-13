-- Migration: Update Spin Wheel Prize Terminology
-- Date: 2025-01-07
-- Description: Updates existing spin_results to use "QR Coins" instead of "Points" terminology

USE `revenueqr`;

-- Update existing spin results to use QR Coins terminology
UPDATE `spin_results` SET `prize_won` = '50 QR Coins' WHERE `prize_won` = '50 Points';
UPDATE `spin_results` SET `prize_won` = '200 QR Coins' WHERE `prize_won` = '200 Points';
UPDATE `spin_results` SET `prize_won` = '500 QR Coins!' WHERE `prize_won` = '500 Points!';
UPDATE `spin_results` SET `prize_won` = '-20 QR Coins' WHERE `prize_won` = '-20 Points';

-- Show updated records count
SELECT 
    'Migration Summary' as info,
    COUNT(CASE WHEN prize_won LIKE '%QR Coins%' THEN 1 END) as qr_coin_prizes,
    COUNT(CASE WHEN prize_won LIKE '%Points%' THEN 1 END) as remaining_point_prizes,
    COUNT(*) as total_spin_results
FROM spin_results;

-- Show sample of updated records
SELECT prize_won, COUNT(*) as count 
FROM spin_results 
WHERE prize_won IN ('50 QR Coins', '200 QR Coins', '500 QR Coins!', '-20 QR Coins')
GROUP BY prize_won 
ORDER BY count DESC; 