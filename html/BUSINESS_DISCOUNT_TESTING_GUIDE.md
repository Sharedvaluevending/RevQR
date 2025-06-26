# Business Discount Purchase Testing Guide

## Overview
This guide explains how to test the business discount purchasing system from both frontend and backend perspectives.

## Test File Location
- **Test Interface**: `/html/test_business_discount_purchase.php`
- **Frontend API**: `/html/user/purchase-business-item.php`
- **Backend Logic**: `/html/core/store_manager.php`
- **Business Store Management**: `/html/business/store.php`

## What the System Tests

### 1. Frontend Purchase Flow
- **File**: `test_business_discount_purchase.php`
- **Tests**: Complete user purchase experience
- **Validates**:
  - User authentication
  - Item availability and validation
  - QR coin balance checking
  - Purchase API endpoint response
  - HTTP status codes
  - JSON response structure

### 2. Backend Purchase Logic
- **File**: `user/purchase-business-item.php`
- **Tests**: Server-side purchase processing
- **Validates**:
  - Item retrieval from database
  - Balance validation
  - Transaction processing
  - Purchase record creation
  - QR code generation
  - Business wallet crediting
  - Error handling

### 3. QR Cost Calculation
- **Function**: `BusinessQRManager::calculateQRCoinCost()`
- **Tests**: Pricing algorithm
- **Validates**:
  - Correct QR coin cost calculation
  - Discount percentage application
  - User base size consideration
  - Economic formula accuracy

## Key Components Tested

### Database Tables
- `business_store_items` - Discount items
- `business_purchases` - Purchase records
- `qr_transactions` - QR coin transactions
- `business_wallets` - Business earnings
- `qr_codes` - Generated QR codes

### API Endpoints
- `POST /user/purchase-business-item.php` - Purchase processing
- `POST /business/store.php` - Store management
- `GET /user/qr-store.php` - Store display

### Core Functions
- `QRCoinManager::spendCoins()` - Deduct user balance
- `QRCodeManager::generateDiscountQRCode()` - Create QR codes
- `BusinessWalletManager::addCoins()` - Credit business
- `StoreManager::purchaseBusinessItem()` - Main purchase logic

## Testing Scenarios

### Success Cases
1. **Valid Purchase**: User has sufficient balance, item is active
2. **QR Code Generation**: Successfully creates scannable QR code
3. **Business Credit**: 90% of payment goes to business wallet
4. **Transaction Recording**: All database records created correctly

### Error Cases
1. **Insufficient Balance**: User doesn't have enough QR coins
2. **Inactive Item**: Item is disabled or deleted
3. **Invalid Item ID**: Non-existent item requested
4. **Authentication**: Unauthenticated user attempts purchase

### Edge Cases
1. **Concurrent Purchases**: Multiple users buying same item
2. **Expired Items**: Time-limited discount items
3. **Usage Limits**: Max purchases per user restrictions
4. **Network Failures**: API timeout handling

## Expected Response Format

### Successful Purchase
```json
{
  "success": true,
  "message": "Purchase successful! QR code generated for easy redemption.",
  "purchase_code": "ABC12345",
  "expires_at": "2025-01-21 12:00:00",
  "discount_percentage": 20,
  "business_name": "Test Business",
  "item_name": "Test Discount",
  "purchase_id": 123,
  "qr_code_generated": true,
  "business_credited": true,
  "business_earning": 18,
  "qr_coins_spent": 20
}
```

### Failed Purchase
```json
{
  "success": false,
  "message": "Insufficient QR coins. You need 20 but only have 15"
}
```

## How to Run Tests

1. **Access Test Interface**: Navigate to `/html/test_business_discount_purchase.php`
2. **View Available Items**: See all active discount items
3. **Test Purchase**: Click "Test Purchase" button for any item
4. **Review Results**: Check JSON response in test results section
5. **Verify Database**: Check purchase records in admin panel

## Monitoring and Debugging

### Log Files
- Purchase errors logged to PHP error log
- QR code generation logs in `/html/logs/`
- Business wallet transactions tracked

### Database Verification
```sql
-- Check recent purchases
SELECT * FROM business_purchases ORDER BY created_at DESC LIMIT 10;

-- Verify QR transactions
SELECT * FROM qr_transactions WHERE transaction_type = 'business_discount_purchase' ORDER BY created_at DESC LIMIT 10;

-- Check business wallet credits
SELECT * FROM business_wallet_transactions WHERE transaction_type = 'store_sale' ORDER BY created_at DESC LIMIT 10;
```

## Common Issues and Solutions

### Issue: "Item not found"
- **Cause**: Item ID invalid or item is inactive
- **Solution**: Check `business_store_items` table for active items

### Issue: "Insufficient QR coins"
- **Cause**: User balance too low
- **Solution**: Add QR coins to user account or test with cheaper item

### Issue: "QR code generation failed"
- **Cause**: QR code library issues or file permissions
- **Solution**: Check `/html/uploads/qr/` directory permissions

### Issue: "Business wallet not credited"
- **Cause**: Business wallet manager error
- **Solution**: Check business wallet configuration and logs

## Security Considerations

### Validation Checks
- User authentication required
- Item ownership validation
- Balance verification before and after transaction
- Purchase code uniqueness
- SQL injection prevention

### Transaction Safety
- Database transactions for consistency
- Rollback on errors
- Duplicate purchase prevention
- Rate limiting consideration

This testing system ensures the business discount purchasing functionality works correctly across all components and handles both success and error scenarios appropriately. 