# 🏇 Item-Specific Jockey Assignment System

## **IMPLEMENTED: Item-Specific Jockey Assignments**

The horse racing system now supports **item-specific jockey assignments**, allowing businesses to customize jockeys for individual vending machine items (not just item types).

---

## **Business Capabilities** 🏢

### **What Businesses CAN Now Do:**

✅ **Assign Custom Jockeys to Specific Items**
- Each individual item (e.g., "Coke in slot A1", "Pepsi in slot B2") can have its own jockey
- No longer limited to item-type assignments (all drinks getting same jockey)

✅ **Full Jockey Customization**
- **Custom Jockey Names**: "Lightning Larry", "Speed Demon Sarah", etc.
- **Custom Colors**: Choose any hex color for jockey themes
- **Custom Avatar URLs**: Upload or link to custom jockey images
- **Quick Select Defaults**: Choose from existing system jockeys as starting points

✅ **Comprehensive Management Interface**
- View all vending machine items across all machines
- See current jockey assignments (custom vs default)
- Edit/remove assignments with simple interface
- Access via "Manage Jockeys" button in Horse Racing dashboard

✅ **Race Impact**
- Custom jockeys appear in all race interfaces (betting, live racing, results)
- Personalized experience for customers
- Better branding and engagement opportunities

### **Business Interface Location:**
```
Business Dashboard → Horse Racing → "Manage Jockeys" button
Direct URL: /business/horse-racing/jockey-assignments.php
```

---

## **Admin Capabilities** 🔧

### **What Admins CAN Do:**

✅ **System-Wide Control**
- **Database Access**: Full control over `item_jockey_assignments` table
- **Override Custom Assignments**: Can modify/remove business assignments
- **Monitor Usage**: Track which businesses use custom jockeys

✅ **Default Jockey Management**
- **Global Defaults**: Manage the 5 default jockeys (Splash Rodriguez, Crunch Thompson, etc.)
- **System Settings**: Control jockey assignment rules and limits
- **Fallback System**: Ensure all items have jockey assignments

✅ **Race Oversight**
- **Visual Verification**: See custom jockeys in admin race monitoring
- **Quality Control**: Monitor for inappropriate jockey names/images
- **Emergency Override**: Can modify assignments during live races if needed

### **Admin Database Access:**
```sql
-- View all custom jockey assignments
SELECT ija.*, vli.item_name, b.name as business_name 
FROM item_jockey_assignments ija
JOIN voting_list_items vli ON ija.item_id = vli.id
JOIN voting_lists vl ON vli.voting_list_id = vl.id
JOIN businesses b ON vl.business_id = b.id;

-- Remove specific assignment
DELETE FROM item_jockey_assignments 
WHERE business_id = ? AND item_id = ?;
```

---

## **Technical Implementation** ⚙️

### **Database Schema:**
```sql
CREATE TABLE item_jockey_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    item_id INT NOT NULL,              -- Specific voting_list_item
    custom_jockey_name VARCHAR(100),
    custom_jockey_avatar_url VARCHAR(255),
    custom_jockey_color VARCHAR(7),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_business_item (business_id, item_id)
);
```

### **Priority System:**
1. **Custom Assignment** (item_jockey_assignments) - Highest priority
2. **Default by Type** (jockey_assignments) - Medium priority  
3. **System Fallback** ("Wild Card Willie") - Lowest priority

### **Updated Race Queries:**
All race-related queries now use:
```sql
COALESCE(ija.custom_jockey_name, ja.jockey_name, 'Wild Card Willie') as jockey_name
```

---

## **Examples** 💡

### **Business Use Cases:**
- **Themed Machines**: "Campus Snack Attack" with custom college mascot jockeys
- **Seasonal Events**: Halloween jockeys for October races
- **Branding**: Company-specific jockeys matching business colors
- **Location-Specific**: Different jockeys for different building locations

### **Item-Specific Examples:**
- **Slot A1 (Coke)**: "Cola Champion Charlie" (Red theme)
- **Slot B2 (Pepsi)**: "Pepsi Powerhouse Pete" (Blue theme)  
- **Slot C3 (Doritos)**: "Chip Champion Chuck" (Orange theme)
- **Slot D4 (Pizza)**: "Pepperoni Racing Rick" (Italian theme)

---

## **System Benefits** 🎯

### **For Businesses:**
- **Increased Engagement**: Personalized racing experience
- **Brand Consistency**: Jockeys match business themes
- **Marketing Tool**: Custom jockeys as promotional elements
- **Competitive Edge**: Unique racing experiences vs competitors

### **For Users:**
- **Better Experience**: More diverse and interesting races
- **Memorable Characters**: Easier to remember favorite jockeys
- **Betting Strategy**: Can bet based on jockey preferences
- **Visual Appeal**: More colorful and engaging race displays

### **For System:**
- **Scalability**: Unlimited jockey combinations
- **Flexibility**: Businesses control their own customization
- **Fallback Safety**: Always has default assignments
- **Performance**: Efficient query system with proper indexing

---

## **Current Status** ✅

**FULLY IMPLEMENTED:**
- ✅ Database schema created and deployed
- ✅ Business management interface active
- ✅ All race displays updated (betting, live, results)
- ✅ Admin oversight capabilities in place
- ✅ Fallback system ensures no missing jockeys

**READY FOR USE:**
Businesses can immediately start assigning custom jockeys to their vending machine items through the "Manage Jockeys" interface in their Horse Racing dashboard.

The system maintains full backward compatibility - existing races continue to work with default jockeys until businesses choose to customize. 