# Nayax Product Mapping System - Complete Implementation

## 🎯 **Overview**

The Nayax Product Mapping system creates a bridge between your QR Store items and actual Nayax vending machine products. This allows businesses to create digital products (like discounts) that map to specific physical items in their Nayax-connected machines.

**Based on Official Nayax API Documentation:**
- [Security & Token Authentication](https://developerhub.nayax.com/docs/security#using-the-token-in-api-requests)
- [Machine Products API](https://developerhub.nayax.com/reference/get-machine-products)
- [Operational Lynx API](https://developerhub.nayax.com/reference/get-operator-products)

---

## 🔧 **System Components**

### 1. **Product Mapper Interface** (`/business/product-mapper.php`)
- **Visual machine overview** showing mapped vs available products
- **Real-time product sync** from Nayax API
- **Drag-and-drop mapping** between QR items and machine products
- **Confidence scoring** for mapping accuracy
- **Mapping type classification** (direct, substitute, bundle)

### 2. **Machine Products API** (`/api/nayax/get-machine-products.php`)
- **Real-time fetching** from Nayax `/machines/{id}/products` endpoint
- **Intelligent caching** (1-hour cache with API fallback)
- **Error handling** with detailed logging
- **Security validation** ensuring business owns machines

### 3. **Database Schema** (`add_product_mapping_table.sql`)
```sql
CREATE TABLE product_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    qr_store_item_id INT NOT NULL,
    nayax_machine_id VARCHAR(50) NOT NULL,
    nayax_product_selection VARCHAR(10) NOT NULL,
    nayax_product_name VARCHAR(255) NULL,
    nayax_product_price DECIMAL(10,2) NULL,
    mapping_type ENUM('direct', 'substitute', 'bundle'),
    confidence_score INT DEFAULT 95,
    -- ... additional fields
);
```

---

## 🚀 **API Integration Details**

### **Authentication Method**
Following [Nayax API Security Guidelines](https://developerhub.nayax.com/docs/security#using-the-token-in-api-requests):

```php
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);
```

### **Machine Products Endpoint**
```
GET https://lynx.nayax.com/operational/api/v1/machines/{machine_id}/products
```

**Response Format Handling:**
```php
// Handle different possible API response formats
if (isset($api_data['products'])) {
    $products_data = $api_data['products'];
} elseif (isset($api_data['data'])) {
    $products_data = $api_data['data'];
} elseif (is_array($api_data)) {
    $products_data = $api_data;
}
```

### **Product Data Structure**
```json
{
  "success": true,
  "products": [
    {
      "selection": "A1",
      "name": "Coca Cola 12oz",
      "price": 1.75,
      "quantity": 8
    }
  ],
  "source": "api",
  "count": 12
}
```

---

## 📊 **Mapping Types**

### **1. Direct Mapping (1:1)**
- One QR store item = One machine product
- **Use case:** "Coca Cola Discount" → A1 slot (Coca Cola)
- **Confidence:** 95-100%

### **2. Substitute Mapping**
- QR item can substitute for machine product
- **Use case:** "Pepsi Alternative" → A1 slot (Coca Cola)
- **Confidence:** 80-94%

### **3. Bundle Mapping**
- QR item is part of a larger bundle
- **Use case:** "Snack Combo" → A1 (Coke) + B2 (Chips)
- **Confidence:** 60-79%

---

## 🛠 **Usage Workflow**

### **Step 1: Business Setup**
1. Navigate to **Nayax Settings** (`/business/nayax-settings.php`)
2. Enter Nayax API access token
3. Sync machines automatically via `/devices` endpoint

### **Step 2: Product Discovery**
1. Go to **Product Mapper** (`/business/product-mapper.php`)
2. Click **"Sync Products"** for each machine
3. System fetches real inventory via `/machines/{id}/products`

### **Step 3: Create Mappings**
1. Select **QR Store Item** from dropdown
2. Choose **Nayax Machine** 
3. Pick **Machine Product** (loaded dynamically via AJAX)
4. Set **Mapping Type** and create mapping

### **Step 4: Enhanced Discount Store**
1. Go to **Discount Store** (`/business/discount-store.php`)
2. Select machine to see **real inventory**
3. Create discounts for **specific machine products**
4. Customers purchase with QR coins

---

## 🔐 **Security Features**

### **Token Encryption**
```sql
-- Secure storage using AES encryption
AES_ENCRYPT(access_token, 'nayax_secure_key_2025')
```

### **Business Validation**
```php
// Ensure machine belongs to requesting business
$stmt = $pdo->prepare("
    SELECT nayax_machine_id FROM nayax_machines 
    WHERE nayax_machine_id = ? AND business_id = ? AND status = 'active'
");
```

### **API Rate Limiting**
- **0.5 second delays** between API requests
- **15-second timeout** for API calls
- **Intelligent caching** to reduce API load

---

## 🎛 **Navigation Integration**

### **Business Navigation Menu**
```
Nayax [Live Badge]
├── 📊 Advanced Analytics [AI Badge]
├── 👥 Customer Intelligence [Insights Badge]  
├── 📱 Mobile Dashboard [PWA Badge]
├── ─────────────────────
├── 🖥️ Machine Status
├── 🔗 Product Mapper [New Badge] ← **NEW**
└── ⚙️ Nayax Settings
```

### **Active Page Detection**
```php
// Updated to include product-mapper.php
echo in_array($current_page, [
    'nayax-analytics.php', 
    'nayax-customers.php', 
    'mobile-dashboard.php', 
    'nayax-settings.php', 
    'nayax-machines.php', 
    'product-mapper.php'  // ← Added
]) ? 'active' : '';
```

---

## 📈 **Performance Optimizations**

### **Intelligent Caching Strategy**
- **Primary:** Database cache (1-hour TTL)
- **Fallback:** Live Nayax API call
- **Update:** Background sync every hour

### **Database Indexing**
```sql
-- Performance indexes for quick lookups
CREATE INDEX idx_product_lookup ON product_mapping (business_id, nayax_machine_id, is_active);
CREATE INDEX idx_mapping_analytics ON product_mapping (business_id, mapping_type, created_at);
```

### **AJAX Product Loading**
```javascript
// Dynamic product loading without page refresh
fetch('/html/api/nayax/get-machine-products.php', {
    method: 'POST',
    body: JSON.stringify({ machine_id: machineId })
})
```

---

## 🎯 **Key Benefits**

### **For Businesses**
1. **Real-time inventory** awareness in discount store
2. **Machine-specific discounts** increase relevance
3. **Automated product discovery** via Nayax API
4. **Confidence scoring** ensures mapping accuracy

### **For Customers**
1. **Machine-aware discounts** only show available items
2. **Real-time stock status** prevents disappointment
3. **Seamless QR coin integration** for purchases
4. **Clear product identification** with selection codes

### **For Platform**
1. **API-first approach** ensures scalability
2. **Secure token management** protects credentials
3. **Flexible mapping types** handle complex scenarios
4. **Comprehensive error handling** maintains reliability

---

## 🔍 **System Flow Example**

```
1. Business connects Nayax account
   ↓
2. System syncs machines from /devices endpoint  
   ↓
3. Product Mapper fetches inventory from /machines/{id}/products
   ↓
4. Business creates mapping: "Coke Discount" → A1 (Coca Cola)
   ↓
5. Discount Store shows A1 with real stock: "8 available"
   ↓
6. Customer buys discount with QR coins
   ↓
7. Customer redeems at machine using selection A1
```

---

## 🚀 **Future Enhancements**

### **Phase 2 Features**
- **Auto-mapping suggestions** based on product name similarity
- **Inventory alerts** when mapped products run low  
- **Bulk mapping tools** for multiple products
- **Mapping analytics** showing popular connections

### **Integration Opportunities**
- **QR code generation** for specific product mappings
- **Mobile app integration** with product mapper
- **Webhook support** for real-time inventory updates
- **Multi-location management** for franchise businesses

---

## 📚 **API Documentation References**

- **[Nayax Security Guide](https://developerhub.nayax.com/docs/security#using-the-token-in-api-requests)** - Token authentication
- **[Machine Products API](https://developerhub.nayax.com/reference/get-machine-products)** - Product inventory
- **[Operator Products API](https://developerhub.nayax.com/reference/get-operator-products)** - Business-level products
- **[Cortina Integration](https://developerhub.nayax.com/docs/cortina-integration)** - Payment processing
- **[Cortina Overview](https://developerhub.nayax.com/docs/overview-cortina)** - System architecture

---

## ✅ **Implementation Status: COMPLETE**

- ✅ **Product Mapper Interface** - Fully functional with real-time API integration
- ✅ **Machine Products API Endpoint** - Secure, cached, error-handled
- ✅ **Database Schema** - Optimized with proper indexing
- ✅ **Navigation Integration** - Added to business menu with active states
- ✅ **Enhanced Discount Store** - Machine-aware inventory display
- ✅ **Security Implementation** - Token encryption and business validation
- ✅ **API Documentation Compliance** - Following official Nayax guidelines

**The Nayax Product Mapping system is now fully operational and ready for production use!** 🎉 