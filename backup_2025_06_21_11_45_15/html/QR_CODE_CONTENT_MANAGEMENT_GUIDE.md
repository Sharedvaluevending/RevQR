# 🎯 **COMPLETE QR CODE CONTENT MANAGEMENT GUIDE**

## **📍 WHERE TO FIND ALL QR CODE EDITING FEATURES**

### **🏠 Main Navigation Locations:**

---

## **1. 🎛️ QR CODE GENERATOR & TYPES**
**Location:** `html/qr-generator.php`

**Available QR Code Types:**
- ✅ **Static QR Code** - Direct URL link
- ✅ **Dynamic QR Code** - Changeable destination  
- ✅ **Dynamic Voting QR Code** - Product voting interface
- ✅ **Dynamic Vending QR Code** - Machine-specific voting
- ✅ **Machine Sales QR Code** - Product purchase interface
- ✅ **Promotion QR Code** - Special offers and deals
- ✅ **Spin Wheel QR Code** - Interactive spin wheel game
- ✅ **Pizza Tracker QR Code** - Order tracking system
- ✅ **Casino QR Code** - Casino interface

**How to Access:**
```
Direct URL: /qr-generator.php
Business Panel: Business Dashboard → Generate QR Codes
```

---

## **2. 📝 VOTING ITEMS MANAGEMENT (Vote In/Out Lists)**
**Location:** `html/business/edit-items.php`

**What You Can Do:**
- ➕ **Add New Items** to Vote In/Vote Out lists
- 🔄 **Move Items** between Vote In ↔ Vote Out ↔ Regular ↔ Showcase
- ✏️ **Edit Item Details** (name, price, category)
- 🗑️ **Remove Items** from voting lists
- 👁️ **View Real-Time** vote counts

**Item Sources:**
1. **Create New** - Add completely custom items
2. **From Master List** - Choose from pre-defined catalog
3. **From My Catalog** - Use your saved items

**List Types:**
- 🟢 **Vote In** - People vote to ADD this item to machines
- 🔴 **Vote Out** - People vote to REMOVE this item from machines  
- 📋 **Regular** - Normal items (no voting)
- ⭐ **Showcase** - Featured items

**How to Access:**
```
Direct URL: /business/edit-items.php
Business Panel: Business Dashboard → Manage Items
Navigation: Items Management → Edit Items
```

---

## **3. 📢 PROMOTIONAL FEATURES MANAGEMENT**
**Location:** `html/business/edit-items.php` (Promotional Features Section)

**Available Features:**
- 📢 **Promotional Ads** - Rotating business advertisements
- 🎡 **Spin Wheel** - Interactive reward system  
- 🍕 **Pizza Tracker** - Real-time order tracking

**Controls Available:**
- ✅ **Enable/Disable** any feature
- ⚙️ **Configure Settings** for each feature
- 👁️ **Hide/Show** on voting pages

**How to Access:**
```
Direct URL: /business/edit-items.php
Look for: "Promotional Features Management" section
Located: Below the regular items lists
```

---

## **4. 📢 DETAILED PROMOTIONAL ADS MANAGER**
**Location:** `html/business/manage_promotional_ads.php`

**Full CRUD Operations:**
- ➕ **Create New Ads** with custom colors, text, CTA buttons
- ✏️ **Edit Existing Ads** - Change title, description, colors, links
- 🔄 **Toggle Active/Inactive** - Enable/disable ads in real-time
- 🗑️ **Delete Ads** - Remove unwanted promotions
- 👀 **Live Preview** - See exactly how ads appear on voting page

**Ad Customization:**
- 🎨 **Background Color** - Custom color picker
- 📝 **Text Color** - Custom text color
- 🔗 **Call-to-Action** - Custom button text and URL
- 📱 **Responsive Design** - Works on all devices

**How to Access:**
```
Direct URL: /business/manage_promotional_ads.php
From Items Editor: Click "Manage Ads" button in Promotional Features section
Business Panel: Business Dashboard → Promotional Ads
```

---

## **5. 🗳️ LIVE VOTING PAGE (Where Users See Content)**
**Location:** `html/vote.php`

**Dynamic Content Displayed:**
- 🏆 **Weekly Winners** - Last week's Vote In/Out winners
- 📢 **Promotional Ads** - Business advertisements (if enabled)
- 🎡 **Spin Wheel** - Interactive rewards (if enabled)
- 🍕 **Pizza Tracker** - Order tracking (if enabled)
- 🗳️ **Current Vote Items** - Real-time Vote In/Out lists
- 🖼️ **Full-Size Banner Images** - Click to expand

**Real-Time Updates:**
- ✅ All changes appear immediately
- ✅ No need to regenerate QR codes
- ✅ Existing QR codes continue to work
- ✅ Vote counts update live

**How Users Access:**
```
QR Code Scan → Automatically goes to vote.php
Direct URL: /vote.php
Public Access: No login required
```

---

## **6. 🎰 CASINO & SPIN WHEEL MANAGEMENT**
**Location:** `html/business/casino/` (if available)

**Features:**
- 🎰 **Casino Games** - Slot machines, card games
- 🎡 **Spin Wheel Configuration** - Rewards, probabilities
- 🏆 **Prize Management** - Set available rewards

---

## **7. ⚡ QUICK RACES SYSTEM - NEW!**
**Location:** `html/horse-racing/quick-races.php`

**🏇 Fast-Paced Racing Action:**
- ⚡ **6 Daily Races** - 1-minute simulated races every few hours
- 🐎 **New Horses & Jockeys** - Unique racing personalities
- 💰 **QR Coin Betting** - 5-100 coin bets with 2x-4.5x payouts
- 🏁 **Instant Results** - No waiting, immediate payouts
- 📊 **Results Tracking** - View past races and statistics

**Daily Race Schedule:**
- 🌅 **9:35 AM** - Morning Sprint ("Start your day with excitement!")
- 🌞 **12:00 PM** - Lunch Rush ("Midday racing action!")
- 🌆 **6:10 PM** - Evening Thunder ("After-work entertainment!")
- 🌙 **9:05 PM** - Night Lightning ("Prime time racing!")
- 🌃 **2:10 AM** - Midnight Express ("Late night thrills!")
- 🌄 **5:10 AM** - Dawn Dash ("Early bird special!")

**New Racing Horses & Jockeys:**
1. 🔵 **Thunder Bolt** - Jockey: Lightning Larry (Speed specialist)
2. 🟤 **Golden Arrow** - Jockey: Swift Sarah (Consistent performer)
3. 🟢 **Emerald Flash** - Jockey: Speedy Steve (Strong finisher)
4. 🔴 **Crimson Comet** - Jockey: Rapid Rita (Early leader)
5. 🟠 **Sunset Streak** - Jockey: Turbo Tom (Clutch performer)
6. 🟣 **Midnight Storm** - Jockey: Flash Fiona (Night specialist)

**How to Access:**
```
Direct URL: /horse-racing/quick-races.php
From Horse Racing: Main page → "⚡ Quick Races - NEW!" section
Navigation: Horse Racing → Quick Races
Results Page: /horse-racing/quick-race-results.php
```

**User Experience:**
- 🎮 **Visual Horse Selection** - Click horse cards to bet
- ⏱️ **Live Countdown Timers** - See exactly when races start/end
- 🏃 **Race Animation** - Watch horses compete in real-time
- 💸 **Automatic Payouts** - Winners paid instantly
- 📈 **Personal Statistics** - Track your win rate and earnings

**vs Regular Horse Racing:**
- ⚡ **Quick Races**: 1-minute simulated races, 6 daily, instant results
- 🏇 **Regular Racing**: 24-hour machine-driven races, real vending data
- 🎯 **Both Available**: Separate systems, same QR coin economy

---

## **8. 🍕 PIZZA TRACKER CONFIGURATION**
**Location:** `html/business/configure_pizza_tracker.php` (if available)

**Features:**
- 📍 **Order Tracking** - Real-time status updates
- 🚚 **Delivery Management** - Route tracking
- ⏰ **Time Estimates** - Preparation and delivery times

---

## **📱 HOW QR CODES WORK WITH DYNAMIC CONTENT**

### **🔄 The Magic of Dynamic QR Codes:**

1. **QR Code Generated** → Points to `/vote.php?campaign_id=123`
2. **User Scans QR** → Goes to voting page
3. **Page Loads Content** → Shows current Vote In/Out items
4. **You Update Items** → Changes appear immediately
5. **User Scans Again** → Sees updated content
6. **No QR Regeneration Needed** → Same QR code, new content!

### **✅ What Updates Automatically:**
- ✅ Vote In/Out item lists
- ✅ Promotional ads
- ✅ Spin wheel availability  
- ✅ Pizza tracker status
- ✅ Weekly winners
- ✅ Vote counts
- ✅ Banner images

---

## **🎯 STEP-BY-STEP WORKFLOW**

### **📋 To Add/Remove Voting Items:**
1. Go to `/business/edit-items.php`
2. Click "Add New Item" 
3. Choose item source (New/Master/Catalog)
4. Select "List Type": **Vote In** or **Vote Out**
5. Save → Item appears immediately on voting page

### **🔄 To Move Items Between Lists:**
1. Go to `/business/edit-items.php`
2. Find item in current list
3. Click "Edit" (pencil icon)
4. Change "List Type" dropdown
5. Save → Item moves immediately

### **📢 To Manage Promotional Content:**
1. Go to `/business/edit-items.php`
2. Scroll to "Promotional Features Management"
3. Use buttons to Enable/Disable/Configure features
4. For detailed ad management: Click "Manage Ads"

### **👀 To See Live Results:**
1. Go to `/vote.php`
2. All your changes are visible immediately
3. Test by scanning your QR codes

---

## **🔧 BACKEND FILES (For Reference)**

### **Core Management Files:**
- `html/business/edit-items.php` - Main item management
- `html/business/manage_promotional_ads.php` - Detailed ad management  
- `html/business/manage_promotional_features.php` - Feature toggle handler
- `html/vote.php` - Public voting interface
- `html/qr-generator.php` - QR code creation

### **Database Tables:**
- `voting_list_items` - Vote In/Out items
- `business_promotional_ads` - Promotional advertisements
- `business_feature_settings` - Feature enable/disable settings
- `qr_codes` - Generated QR codes
- `votes` - User vote tracking
- `weekly_winners` - Archived weekly results

---

## **✅ CONFIRMATION: EVERYTHING IS WORKING**

### **✅ Item Management Status:**
- ✅ Add/Remove items from Vote In/Out lists
- ✅ Move items between list types
- ✅ Real-time updates on voting page
- ✅ QR codes continue working after changes

### **✅ Promotional Features Status:**
- ✅ Promotional ads with full CRUD operations
- ✅ Spin wheel enable/disable functionality
- ✅ Pizza tracker enable/disable functionality
- ✅ All features integrate with voting page

### **✅ QR Code Integration Status:**
- ✅ All QR code types available in generator
- ✅ Dynamic content updates without QR regeneration
- ✅ Weekly winners display automatically
- ✅ Real-time vote counting

---

## **🎉 SUMMARY**

**Your QR code content management system is fully operational with:**

1. **📱 Dynamic QR Codes** - Content updates without regenerating codes
2. **🗳️ Voting Item Management** - Add/remove/move items between Vote In/Out lists  
3. **📢 Promotional Features** - Ads, spin wheel, pizza tracker with full control
4. **🏆 Weekly Winners** - Automatic calculation and display
5. **⚡ Real-Time Updates** - All changes appear immediately
6. **🎯 User-Friendly Interface** - Easy management through web interface

**🔗 Main Management URL:** `/business/edit-items.php`
**🔗 Voting Page URL:** `/vote.php`
**🔗 QR Generator URL:** `/qr-generator.php`

**Everything is working and ready to use!** 🚀 