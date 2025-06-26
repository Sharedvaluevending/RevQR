# 🏗️ Voting System - Backend & Frontend Architecture

## 🔧 **Backend Architecture**

### **Core System Files**

#### **1. Configuration & Database**
```
html/core/config.php
├── Database connection (MySQL/PDO)
├── Application constants
├── QR code settings
├── Session configuration
└── Error reporting setup
```

#### **2. Core Managers**
```
html/core/qr_coin_manager.php (598 lines)
├── QR coin balance management
├── Transaction logging
├── Vote rewards (30 coins per vote)
├── Spin rewards
├── Spending validation
└── Economic calculations

html/core/promotional_ads_manager.php (163 lines)
├── Ad rotation system
├── View/click tracking
├── Performance analytics
├── Business-specific ads
└── Daily view limits

html/core/functions.php (567 lines)
├── Utility functions
├── Data validation
├── Security helpers
└── Common operations
```

#### **3. Voting System Core**
```
html/core/get-vote-counts.php (42 lines)
├── Real-time vote count API
├── JSON responses
├── Error handling
└── Unified voting service

html/core/services/VotingService.php
├── Vote processing logic
├── Campaign management
├── Analytics integration
└── Data validation
```

#### **4. Session & Authentication**
```
html/core/session.php (96 lines)
├── Session management
├── Security headers
├── CSRF protection
└── User authentication

html/core/auth.php (136 lines)
├── User authentication
├── Permission checking
├── Login/logout logic
└── Security validation
```

### **Database Schema**

#### **Voting Tables**
```sql
-- Main voting table
votes
├── id (Primary Key)
├── user_id (User who voted)
├── item_id (Item being voted on)
├── vote_type (vote_in/vote_out)
├── campaign_id (Campaign context)
├── voter_ip (IP address)
├── machine_id (Vending machine)
├── user_agent (Browser info)
└── created_at (Timestamp)

-- Campaign management
campaigns
├── id (Primary Key)
├── business_id (Business owner)
├── name (Campaign name)
├── description (Campaign details)
├── status (active/inactive)
└── created_at (Timestamp)

-- Campaign items
campaign_items
├── campaign_id (Campaign reference)
├── item_id (Item reference)
└── added_at (Timestamp)
```

#### **QR Coin Economy**
```sql
-- QR coin transactions
qr_coin_transactions
├── id (Primary Key)
├── user_id (User)
├── transaction_type (earning/spending)
├── category (voting/spinning/purchase)
├── amount (Positive/negative)
├── description (Transaction details)
├── metadata (JSON data)
├── reference_id (Related record)
├── reference_type (vote/spin/purchase)
└── created_at (Timestamp)
```

#### **Promotional Ads**
```sql
-- Business promotional ads
business_promotional_ads
├── id (Primary Key)
├── business_id (Business owner)
├── feature_type (casino/spin_wheel/general)
├── ad_title (Ad headline)
├── ad_description (Ad content)
├── ad_cta_text (Call-to-action)
├── ad_cta_url (Link URL)
├── background_color (Hex color)
├── text_color (Hex color)
├── show_on_vote_page (Boolean)
├── show_on_dashboard (Boolean)
├── priority (Display order)
├── max_daily_views (View limit)
├── daily_views_count (Current views)
├── total_views (Lifetime views)
├── total_clicks (Lifetime clicks)
└── is_active (Boolean)
```

### **API Endpoints**

#### **Voting APIs**
```
GET /core/get-vote-counts.php
├── Parameters: item_id, campaign_id, machine_id
├── Returns: JSON with vote counts
└── Used for: Real-time vote count updates

POST /html/vote.php
├── Parameters: item_id, vote_type, campaign_id
├── Returns: Success/error message
└── Used for: Vote submission

POST /html/public/vote.php
├── Parameters: item_id, vote_type, campaign_id
├── Returns: Success/error message
└── Used for: Public vote submission
```

#### **QR Coin APIs**
```
QRCoinManager::getBalance($user_id)
├── Returns: Current QR coin balance
└── Used for: Balance display

QRCoinManager::addTransaction($user_id, $type, $category, $amount)
├── Returns: Success status
└── Used for: Awarding/spending coins
```

## 🎨 **Frontend Architecture**

### **Core Frontend Files**

#### **1. Main Voting Pages**
```
html/vote.php (1,618 lines)
├── Main voting interface
├── AJAX voting system
├── Real-time updates
├── QR coin integration
├── Promotional ads
└── Modern responsive design

html/public/vote.php (834 lines)
├── Public voting interface
├── Same functionality as main page
├── QR code scanning
├── Guest user support
└── Mobile-optimized
```

#### **2. JavaScript Components**
```
html/assets/js/
├── balance-sync-enhanced.js (309 lines)
│   ├── Real-time balance updates
│   ├── QR coin synchronization
│   └── Transaction notifications
│
├── user-behavior-tracker.js (498 lines)
│   ├── User interaction tracking
│   ├── Analytics data collection
│   └── Performance monitoring
│
├── qr-generator-v2.js (955 lines)
│   ├── QR code generation
│   ├── Custom styling
│   └── Download functionality
│
└── custom.js (262 lines)
    ├── Global JavaScript functions
    ├── UI interactions
    └── Utility functions
```

#### **3. CSS Styling**
```
html/assets/css/
├── enhanced-gradients.css (577 lines)
│   ├── Modern gradient designs
│   ├── Card layouts
│   └── Responsive components
│
├── custom.css (145 lines)
│   ├── Custom styling overrides
│   ├── Component-specific styles
│   └── Theme customizations
│
├── critical.css (68 lines)
│   ├── Critical path CSS
│   ├── Above-the-fold styles
│   └── Performance optimization
│
└── global-table-fix.css (89 lines)
    ├── Table styling fixes
    ├── Data display improvements
    └── Responsive tables
```

### **Frontend Features**

#### **1. AJAX Voting System**
```javascript
// Real-time vote submission
async function handleVoteSubmission(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        // Show toast notification
        showVoteToast(alertText, alertType);
        
        // Update vote counts
        updateAllVoteCounts();
        
    } catch (error) {
        console.error('Vote submission error:', error);
    }
}
```

#### **2. Real-time Updates**
```javascript
// Update vote counts every 5 seconds
setInterval(updateAllVoteCounts, 5000);

async function updateAllVoteCounts() {
    const items = document.querySelectorAll('.item-card');
    
    items.forEach(async (card) => {
        const itemId = card.querySelector('input[name="item_id"]')?.value;
        const campaignId = card.querySelector('input[name="campaign_id"]')?.value;
        
        if (itemId) {
            await updateItemVoteCount(card, itemId, campaignId);
        }
    });
}
```

#### **3. Toast Notifications**
```javascript
function showVoteToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
    
    toast.innerHTML = `
        <i class="bi bi-${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 4000);
}
```

#### **4. Modern UI Components**
```css
/* Card-based design */
.voting-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* Vote buttons */
.btn-vote-in {
    background: linear-gradient(135deg, #00ff88 0%, #00d084 100%);
    border: none;
    color: #000;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-vote-out {
    background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
    border: none;
    color: #fff;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}
```

### **Responsive Design**

#### **Mobile Optimization**
```css
/* Mobile-first approach */
@media (max-width: 768px) {
    .voting-container {
        padding: 1rem;
    }
    
    .item-card {
        padding: 1rem;
    }
    
    .btn-vote-in,
    .btn-vote-out {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}
```

#### **Progressive Enhancement**
- **Base functionality**: Works without JavaScript
- **Enhanced experience**: AJAX voting, real-time updates
- **Modern features**: Toast notifications, animations

## 🔄 **Data Flow**

### **Vote Submission Flow**
```
1. User clicks vote button
   ↓
2. JavaScript prevents form submission
   ↓
3. AJAX request sent to PHP backend
   ↓
4. Backend validates vote (limits, duplicates)
   ↓
5. Vote recorded in database
   ↓
6. QR coins awarded (if logged in)
   ↓
7. Success response sent to frontend
   ↓
8. Toast notification shown
   ↓
9. Vote counts updated in real-time
```

### **Real-time Update Flow**
```
1. JavaScript timer triggers every 5 seconds
   ↓
2. AJAX request to get-vote-counts.php
   ↓
3. Backend queries database for current counts
   ↓
4. JSON response with updated counts
   ↓
5. Frontend updates display with animation
   ↓
6. Visual feedback for changed counts
```

## 🚀 **Performance Optimizations**

### **Backend Optimizations**
- **Database indexing** on frequently queried columns
- **Prepared statements** for SQL injection prevention
- **Connection pooling** for database efficiency
- **Caching** for frequently accessed data
- **Error logging** for debugging and monitoring

### **Frontend Optimizations**
- **Critical CSS** for above-the-fold content
- **Lazy loading** for images and non-critical content
- **Minified assets** for faster loading
- **CDN integration** for static assets
- **Progressive enhancement** for accessibility

## 🔒 **Security Features**

### **Backend Security**
- **CSRF protection** on all forms
- **SQL injection prevention** with prepared statements
- **XSS protection** with proper output escaping
- **Session security** with secure cookies
- **Input validation** on all user data

### **Frontend Security**
- **Content Security Policy** headers
- **HTTPS enforcement** for all connections
- **Secure cookie settings** for sessions
- **Input sanitization** on client-side
- **Rate limiting** for API endpoints

## 📊 **Analytics Integration**

### **Tracking Points**
- **Vote submissions** with user and item data
- **QR coin transactions** for economic analysis
- **Ad views and clicks** for marketing insights
- **User behavior** patterns and engagement
- **Performance metrics** for optimization

### **Data Collection**
```php
// Vote tracking
$stmt = $pdo->prepare("
    INSERT INTO votes (item_id, vote_type, voter_ip, user_id, campaign_id, machine_id, user_agent, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");

// QR coin tracking
QRCoinManager::addTransaction($user_id, 'earning', 'voting', 30, 'Vote cast for item', 'vote');

// Ad tracking
$adsManager->trackView($ad_id, $user_id, 'vote');
```

## 🎯 **Key Features Summary**

### **Backend Features**
- ✅ **Unified voting system** across all interfaces
- ✅ **QR coin economy** with transaction logging
- ✅ **Promotional ads** with rotation and tracking
- ✅ **Real-time APIs** for live updates
- ✅ **Comprehensive analytics** and reporting
- ✅ **Security hardening** and validation

### **Frontend Features**
- ✅ **AJAX voting** with no page reloads
- ✅ **Real-time updates** every 5 seconds
- ✅ **Toast notifications** for user feedback
- ✅ **Modern responsive design** with animations
- ✅ **Progressive enhancement** for accessibility
- ✅ **Mobile optimization** for all devices

**The voting system now provides a modern, secure, and engaging user experience with comprehensive backend functionality and polished frontend design!** 🎉 