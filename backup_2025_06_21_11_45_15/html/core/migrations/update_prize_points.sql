USE revenueqr;

-- Update existing records to have correct prize points
UPDATE spin_results SET prize_points = 500 WHERE prize_won = '500 Points!';
UPDATE spin_results SET prize_points = 200 WHERE prize_won = '200 Points';
UPDATE spin_results SET prize_points = 50 WHERE prize_won = '50 Points';
UPDATE spin_results SET prize_points = -20 WHERE prize_won = '-20 Points';
UPDATE spin_results SET prize_points = 0 WHERE prize_won IN ('Try Again', 'Lord Pixel!', 'Extra Vote', 'Lose All Votes');

-- Show the updated results
SELECT user_id, prize_won, prize_points, spin_time FROM spin_results WHERE user_id = 2 ORDER BY spin_time DESC LIMIT 5; 