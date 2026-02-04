# Lenco Payment Integration

This document describes the Lenco payment integration for the WD Parcel System.

## Overview

Lenco is integrated as a payment gateway for handling card and mobile money payments during parcel registration.

## Configuration

### Environment Settings

The configuration file is located at:
```
outlet-app/api/payments/lenco_config.php
```

#### Sandbox (Testing) Mode
- **Environment**: `sandbox`
- **Public Key**: `pub-88dd921c0ecd73590459a1dd5a9343c77db0f3c344f222b9`
- **Secret Key**: `993bed87f9d592566a6cce2cefd79363d1b7e95af3e1e6642b294ce5fc8c59f6`
- **Widget URL**: `https://pay.sandbox.lenco.co/js/v1/inline.js`
- **API Base URL**: `https://sandbox.lenco.co/access/v2`

#### Production Mode
To switch to production:
1. Open `lenco_config.php`
2. Change `LENCO_ENV` from `'sandbox'` to `'live'`
3. Update `LENCO_PUBLIC_KEY_LIVE` and `LENCO_SECRET_KEY_LIVE` with your production credentials

## Files Created

1. **lenco_config.php** - Configuration and helper functions
2. **verify_lenco.php** - Payment verification API endpoint
3. **lenco_webhook.php** - Webhook handler for payment notifications
4. **lenco_payment.js** - Frontend JavaScript handler

## Test Accounts

### Mobile Money (Sandbox)

| Phone | Operator | Response |
|-------|----------|----------|
| 0961111111 | MTN | Successful |
| 0962222222 | MTN | Failed - Not enough funds |
| 0963333333 | MTN | Failed - Limit exceeded |
| 0971111111 | Airtel (ZM) | Successful |
| 0972222222 | Airtel (ZM) | Failed - Incorrect Pin |
| 0975555555 | Airtel (ZM) | Failed - Not enough funds |

### Test Cards (Sandbox)

| Type | Number | CVV | Expiry |
|------|--------|-----|--------|
| Visa | 4622 9431 2701 3705 | 838 | Any future date |
| Visa | 4622 9431 2701 3747 | 370 | Any future date |
| Mastercard | 5555 5555 5555 4444 | Any 3 digits | Any future date |

## Payment Flow

1. User selects "Mobile Money" or "Card Payment" from payment method dropdown
2. User fills in required details (sender info, delivery fee)
3. User clicks "Pay with Mobile Money" or "Pay with Card"
4. Lenco popup widget appears for secure payment
5. User completes payment in the popup
6. `onSuccess` callback triggers payment verification
7. Backend verifies payment with Lenco API
8. On success, form submission is enabled
9. Parcel is registered with payment reference stored

## API Endpoints

### Verify Payment
```
GET /api/payments/verify_lenco.php?reference={reference}
```

### Webhook (POST)
```
POST /api/payments/lenco_webhook.php
```

Configure this URL in your Lenco dashboard under webhook settings.

## Webhook Events

- `collection.successful` - Payment completed successfully
- `collection.failed` - Payment failed
- `collection.pending` - Payment is pending

## Security Notes

1. **Never expose the Secret Key** on the frontend
2. Always verify payments on the backend before processing orders
3. Use HTTPS in production
4. Implement webhook signature verification for production

## Documentation

- [Lenco API Documentation](https://lenco-api.readme.io/v2.0/reference/introduction)
- [Accept Payments Guide](https://lenco-api.readme.io/v2.0/reference/accept-payments)

## Support

For Lenco-specific issues, contact Lenco Support Team.
