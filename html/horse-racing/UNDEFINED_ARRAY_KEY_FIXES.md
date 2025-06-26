# Quick Races - Undefined Array Key Fixes

## Issue Summary
The quick races page was showing multiple PHP warnings:
- `Warning: Undefined array key "horse_name"` on line 1198
- `Warning: Undefined array key "bet_amount"` on line 1199  
- `Warning: Undefined array key "status"` on lines 1201, 1202, 1203, 1204
- `Deprecated: strtoupper(): Passing null to parameter` on line 1203

## Root Cause Analysis
1. **Missing User Bet Data Retrieval**: The code was trying to display user bet information in the race schedule section, but no SQL query was retrieving user bet data.

2. **Wrong Data Source**: The code was trying to use `$betting_stats` array (which contains aggregated betting statistics) as the source for individual user bet information.

3. **Missing Null Checks**: The code didn't have proper null checks before accessing array keys, causing undefined array key warnings.

4. **Incorrect Table Join**: The original fix attempted to join with a non-existent `quick_race_horses` table.

## Fixes Implemented

### 1. Added User Bet Data Retrieval
```php
// Get user's bets for today's races
$user_bets = [];
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM quick_race_bets
        WHERE user_id = ? AND race_date = ?
        ORDER BY race_index
    ");
    $stmt->execute([$user_id, $current_date]);
    while ($row = $stmt->fetch()) {
        $user_bets[$row['race_index']] = $row;
    }
}
```

### 2. Fixed User Bet Assignment Logic
**Before:**
```php
$user_bet = null;
foreach ($betting_stats as $bet) {
    if ($bet['horse_index'] == $index) {
        $user_bet = $bet;
        break;
    }
}
```

**After:**
```php
$user_bet = isset($user_bets[$index]) ? $user_bets[$index] : null;
```

### 3. Added Comprehensive Null Checks
**Before:**
```php
Your bet: <?php echo $user_bet['horse_name']; ?><br>
Amount: <?php echo $user_bet['bet_amount']; ?> QR Coins
<?php if ($user_bet['status'] !== 'pending'): ?>
    <?php echo strtoupper($user_bet['status']); ?>
    <?php if ($user_bet['status'] === 'won'): ?>
        (+<?php echo $user_bet['actual_winnings']; ?> coins)
    <?php endif; ?>
<?php endif; ?>
```

**After:**
```php
Your bet: <?php echo isset($user_bet['horse_name']) ? htmlspecialchars($user_bet['horse_name']) : 'Unknown Horse'; ?><br>
Amount: <?php echo isset($user_bet['bet_amount']) ? htmlspecialchars($user_bet['bet_amount']) : '0'; ?> QR Coins
<?php if (isset($user_bet['status']) && $user_bet['status'] !== 'pending'): ?>
    <?php echo strtoupper($user_bet['status'] ?? 'UNKNOWN'); ?>
    <?php if ($user_bet['status'] === 'won' && isset($user_bet['actual_winnings'])): ?>
        (+<?php echo htmlspecialchars($user_bet['actual_winnings']); ?> coins)
    <?php endif; ?>
<?php endif; ?>
```

## Database Table Structure Verified
The `quick_race_bets` table contains all necessary columns:
- `horse_name` - varchar(100)
- `bet_amount` - int  
- `status` - enum('pending','won','lost')
- `actual_winnings` - int
- `race_index` - int
- `user_id` - int
- `race_date` - date

## Security Improvements
- Added `htmlspecialchars()` calls to prevent XSS attacks
- Used null coalescing operator (`??`) for safer null handling
- Proper parameter binding in SQL queries

## Testing Results
- ✅ PHP syntax validation passed
- ✅ No more undefined array key warnings
- ✅ No more deprecated function warnings
- ✅ Race schedule displays properly with or without user bets
- ✅ Proper fallback values when bet data is missing

## Files Modified
- `html/horse-racing/quick-races.php` - Fixed user bet data retrieval and display logic

## Impact
- Eliminated all PHP warnings related to undefined array keys
- Improved user experience with proper error handling
- Enhanced security with XSS prevention
- Made the race schedule display more robust and reliable 