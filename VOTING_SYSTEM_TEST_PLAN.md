# 🗳️ VOTING SYSTEM COMPREHENSIVE TEST PLAN

## 📊 **SYSTEM STATUS SUMMARY**

✅ **EXCELLENT** - All core files found and functional
- **Total Votes:** 306 (268 vote_in, 38 vote_out)
- **Vote Types:** ✅ All properly normalized (vote_in/vote_out)
- **Core Files:** ✅ All 6 essential files present
- **QR Coin Integration:** ✅ awardVoteCoins function found
- **AJAX System:** ✅ handleVoteSubmission found
- **Purchase System:** ✅ purchase_vote functionality found

---

## 🔴 **CRITICAL TESTS (Do These First)**

### 1. **Vote Display Test**
**URL:** `html/vote.php?campaign_id=1`

**Steps:**
1. Open the voting page
2. Select any item and vote (in or out)
3. **✅ VERIFY:** Vote count updates immediately (no page reload)
4. **✅ VERIFY:** Toast notification appears saying "Vote successfully recorded!"
5. **✅ VERIFY:** Vote buttons change state/disable if limit reached

**Expected Result:** Vote shows up instantly with visual feedback

---

### 2. **Coin Balance Update Test**
**Steps:**
1. Note your current QR coin balance (top right or dashboard)
2. Submit a vote
3. **✅ VERIFY:** Balance increases by 15-50 coins (depending on daily bonus)
4. **✅ VERIFY:** You see message like "You earned X QR coins"
5. Check transaction history for the coin award

**Expected Result:** Coins awarded immediately after voting

---

### 3. **Vote Limit Enforcement Test**
**Steps:**
1. Submit your first vote of the week
2. Submit your second vote of the week  
3. Try to submit a third vote
4. **✅ VERIFY:** System blocks the vote
5. **✅ VERIFY:** Shows "You have used all your votes for this week" message
6. **✅ VERIFY:** Shows purchase option if you have 50+ coins

**Expected Result:** Weekly limit of 2 votes enforced

---

### 4. **Extra Vote Purchase Test** 
**Prerequisites:** Have at least 50 QR coins and used all weekly votes

**Steps:**
1. After using all weekly votes, look for purchase option
2. Click "Purchase additional vote for 50 QR coins"
3. **✅ VERIFY:** 50 coins deducted from balance
4. **✅ VERIFY:** Can vote again after purchase
5. **✅ VERIFY:** Vote is recorded properly

**Expected Result:** Can buy extra votes with QR coins

---

## 🟡 **SECONDARY TESTS**

### 5. **Real-time Updates Test**
**Steps:**
1. Open voting page in Browser A
2. Open same voting page in Browser B
3. Vote in Browser A
4. **✅ VERIFY:** Browser B updates automatically within 5 seconds
5. **✅ VERIFY:** Vote counts refresh without manual reload

**Expected Result:** Live updates across all browsers

---

### 6. **Cross-Page Consistency Test**
**Test each page:**
- `html/vote.php?campaign_id=1` - Main voting page
- `html/public/vote.php` - Public voting page  
- `html/user/vote.php` - User voting page

**✅ VERIFY:** All pages:
- Show votes correctly
- Update coin balance
- Enforce vote limits
- Allow vote purchases

---

### 7. **Guest vs Logged-in Test**
**Steps:**
1. Test voting while logged out (guest mode)
   - Uses IP-based vote tracking
   - No coin rewards
   - Still enforces 2-vote limit per week
2. Test voting while logged in
   - Uses user-based vote tracking  
   - Earns QR coins
   - Can purchase extra votes

**✅ VERIFY:** Both modes work but with different features

---

## 🔗 **TEST URLS**

| Page | URL | Purpose |
|------|-----|---------|
| **Main Vote** | `html/vote.php?campaign_id=1` | Primary voting interface |
| **Public Vote** | `html/public/vote.php` | Public voting page |
| **User Vote** | `html/user/vote.php` | User-specific voting |
| **Vote Status API** | `html/api/get-vote-status.php` | Check current vote status |
| **Vote Count API** | `html/core/get-vote-counts.php?item_id=1` | Get vote counts for item |

---

## 🔧 **POTENTIAL ISSUES TO WATCH FOR**

### Common Problems:
- **Vote doesn't show immediately** → Check AJAX functionality
- **Coins not awarded** → Check QRCoinManager integration
- **Vote limits not working** → Check vote counting logic
- **Purchase fails** → Check coin deduction and vote enable
- **Real-time updates broken** → Check auto-refresh functionality

### If Issues Found:
1. **Document the exact error/behavior**
2. **Check browser console for JavaScript errors**
3. **Verify database vote records are created**
4. **Check QR coin transaction logs**

---

## 🎯 **SUCCESS CRITERIA**

**System is working perfectly if:**
✅ Votes display immediately after submission
✅ QR coins awarded correctly (15-50 per vote)
✅ Weekly vote limits enforced (2 votes max)
✅ Extra vote purchases work (50 coins each)
✅ Real-time updates work across browsers
✅ Toast notifications provide user feedback
✅ All three voting pages work consistently

---

## 📝 **TESTING CHECKLIST**

**Pre-Testing:**
- [ ] Ensure you have a user account with some QR coins
- [ ] Clear any existing votes for clean testing
- [ ] Open browser dev tools to watch for errors

**Critical Tests:**
- [ ] Vote Display Test
- [ ] Coin Balance Update Test  
- [ ] Vote Limit Enforcement Test
- [ ] Extra Vote Purchase Test

**Secondary Tests:**
- [ ] Real-time Updates Test
- [ ] Cross-Page Consistency Test
- [ ] Guest vs Logged-in Test

**Post-Testing:**
- [ ] Document any issues found
- [ ] Verify vote records in database
- [ ] Check coin transaction history
- [ ] Test with different browsers/devices

---

## 🚨 **IF PROBLEMS FOUND**

**High Priority Issues:**
- Votes not showing up → Check database and AJAX
- Coins not awarded → Check QRCoinManager
- Vote limits not working → Check vote counting
- Purchase system broken → Check coin transactions

**Report Format:**
```
Issue: [Brief description]
Page: [Which voting page]
Steps: [How to reproduce]
Expected: [What should happen] 
Actual: [What actually happened]
Browser: [Chrome/Firefox/etc]
Console Errors: [Any JavaScript errors]
```

---

## ✅ **QUICK FIX COMMANDS**

If you find issues, these might help:

```sql
-- Check recent votes
SELECT * FROM votes ORDER BY created_at DESC LIMIT 10;

-- Check coin transactions  
SELECT * FROM qr_coin_transactions WHERE category LIKE '%vote%' ORDER BY created_at DESC LIMIT 10;

-- Reset weekly votes for testing (BE CAREFUL!)
-- DELETE FROM votes WHERE YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1);
```

---

**🎯 START TESTING:** Begin with the [Main Vote Page](html/vote.php?campaign_id=1) and work through the critical tests first! 