# Payment Gateway API Key Guide

This guide provides detailed instructions on how to find API keys and other necessary credentials for all payment gateways supported by the Multi-Gateway Card Checker. The focus is on capturing these credentials from HTTP requests during live checkout processes.

## Capturing API Keys from HTTP Requests

### Prerequisites
- Chrome, Firefox, or Edge browser
- Basic understanding of developer tools

### General Process
1. **Prepare Developer Tools**:
   - Open your browser and press **F12** (or right-click and select "Inspect")
   - Navigate to the **Network** tab
   - Enable "Preserve log" (to keep requests even when navigating between pages)
   - Clear any existing requests (click the 🗑️ icon)

2. **Start the Checkout Process**:
   - Add a product to cart on a site using the gateway you want to capture
   - Begin the checkout process
   - Enter test card details when prompted (use test cards from the list at the bottom)
   - DO NOT complete the purchase if using real card details

3. **Capture the API Credentials**:
   - Look for requests to the payment gateway's domain
   - Usually occurs when you enter card details or click "Pay"
   - Filter requests by the gateway name (e.g., type "stripe" in the filter box)
   - Examine both the **Request Headers** and **Request Payload**

4. **Find the Credential Format**:
   - API keys often begin with specific prefixes (like `pk_` for Stripe public keys)
   - Tokens typically appear as long alphanumeric strings
   - Merchant IDs may be shorter numeric or alphanumeric codes

## Detailed Gateway-Specific Instructions

### Stripe
**Keys needed**: Publishable Key (pk_...) and optionally a Payment Intent (pi_...)

**Detailed HTTP Request Capture Method**:
1. Start a checkout process on any site using Stripe
2. In the Network tab, filter for "stripe.com"
3. Look for requests to `https://api.stripe.com/v1/payment_intents` or `https://api.stripe.com/v1/tokens`
4. **Publishable Key**: Found in the request headers under `Authorization: Bearer pk_...` or as a URL parameter `?key=pk_...`
5. **Payment Intent ID**: In the response, look for `"id": "pi_..."` - this is used for testing specific payment intents

**Example Request URL**:
```
https://api.stripe.com/v1/payment_methods?key=pk_live_51aBcDeFgHiJkLmNo0PqRsTuVwXyZ1234567890abcdefghijklmno
```

**Example Request Headers**:
```
Authorization: Bearer pk_live_51aBcDeFgHiJkLmNo0PqRsTuVwXyZ1234567890abcdefghijklmno
```

### PayPal
**Keys needed**: Client ID and optional Client Secret

**Detailed HTTP Request Capture Method**:
1. Filter network requests for "paypal.com"
2. Look for requests to `https://www.paypal.com/sdk/js`
3. **Client ID**: Found as a URL parameter `client-id=...`
4. If using PayPal Checkout, the Client ID is also visible in the source code of pages integrating PayPal buttons

**Example Request URL**:
```
https://www.paypal.com/sdk/js?client-id=AaBbCcDdEeFfGgHh-12345XyZ_abcdefghijklmno&currency=USD
```

**In JavaScript**:
```javascript
paypal.Buttons({
  createOrder: function() {
    // Client ID is used here
  }
}).render('#paypal-button-container');
```

### Braintree
**Keys needed**: Merchant ID and Public Key

**Detailed HTTP Request Capture Method**:
1. Filter network requests for "braintreegateway.com" or "braintree-api.com"
2. Look for requests to `https://api.braintreegateway.com/merchants/[merchant_id]/client_token`
3. **Merchant ID**: Appears in the URL path as a unique identifier
4. **Public Key**: Found in the Authorization header or in client configuration

**Example URL Structure**:
```
https://api.braintreegateway.com/merchants/abc123def456ghi/client_token
```

**In JavaScript Configuration**:
```javascript
braintree.create({
  authorization: 'sandbox_abcdef_merchantidxyz',
  // or
  merchantId: 'abc123def456ghi',
  publicKey: 'sandbox_abcdefg_publickeyhijklmno'
});
```

### Adyen
**Keys needed**: API Key and Merchant Account

**Detailed HTTP Request Capture Method**:
1. Filter for "adyen.com" in network requests
2. Look for calls to `https://checkoutshopper-live.adyen.com/checkoutshopper/` or `https://checkout-live.adyen.com/checkout/`
3. **API Key**: In request headers as `X-API-Key` or in the request configuration
4. **Merchant Account**: In the request payload as `merchantAccount` parameter

**Example Request Headers**:
```
X-API-Key: AQEmhmfuXNWTK0Qc+iSTkYZZQiJaxxSF123456abc...
```

**Example Request Payload**:
```json
{
  "merchantAccount": "ExampleMerchantECOM",
  "amount": {
    "value": 1000,
    "currency": "USD"
  }
}
```

### Authorize.net
**Keys needed**: API Login ID and Transaction Key

**Detailed HTTP Request Capture Method**:
1. Filter network requests for "authorize.net"
2. Look for POST requests to `https://api.authorize.net/xml/v1/request.api`
3. **API Login ID**: In request payload as `merchantAuthentication.name`
4. **Transaction Key**: In request payload as `merchantAuthentication.transactionKey`

**Example Request Payload**:
```json
{
  "createTransactionRequest": {
    "merchantAuthentication": {
      "name": "5KP3u95bQpv",
      "transactionKey": "346HZ32z3fP4hTG2"
    },
    "refId": "123456",
    "transactionRequest": {
      // transaction details
    }
  }
}
```

### Checkout.com
**Keys needed**: Public Key (pk_...) and optional Secret Key

**Detailed HTTP Request Capture Method**:
1. Filter for "checkout.com" in network requests
2. **Public Key**: In requests to `https://api.checkout.com/tokens` in the Authorization header or as a parameter
3. Look for `public_key` in request parameters or `Authorization: pk_...` in headers

**Example Request Headers**:
```
Authorization: pk_a1b2c3d4-5678-90ab-cdef-ghijklmnopqr
```

**Example Request URL**:
```
https://api.checkout.com/tokens?public_key=pk_a1b2c3d4-5678-90ab-cdef-ghijklmnopqr
```

### Worldpay
**Keys needed**: Client Key and Merchant ID

**Detailed HTTP Request Capture Method**:
1. Filter for "worldpay.com" in network requests
2. Look for requests to `https://api.worldpay.com/` or `https://secure.worldpay.com/`
3. **Client Key**: Found in Authorization headers or as a URL parameter `client_key`
4. **Merchant ID**: In the request payload or as part of the URL path

**Example Request Headers**:
```
Authorization: Bearer [client_key_here]
```

**URL Structure**:
```
https://api.worldpay.com/v1/orders/[merchant_id]/...
```

### Square
**Keys needed**: Application ID and Location ID

**Detailed HTTP Request Capture Method**:
1. Filter for "squareup.com" in network requests
2. Look for requests to `https://connect.squareup.com/v2/` or `https://web.squarecdn.com/`
3. **Application ID**: Found in request headers as `Authorization: Bearer [app_id]` or in the JavaScript source
4. **Location ID**: In the request payload when creating payments or as a URL parameter

**Example Request Headers**:
```
Square-Version: 2023-05-17
Authorization: Bearer [application_id_here]
```

**Example Request Payload**:
```json
{
  "source_id": "cnon:card-nonce-ok",
  "idempotency_key": "4935a656-a929-4792-b97c-8848be85c27c",
  "amount_money": {
    "amount": 500,
    "currency": "USD"
  },
  "location_id": "L4MYQFEZS8STC"
}
```

### Shopify Payments
**Keys needed**: Shop ID and optional Checkout Token

**Detailed HTTP Request Capture Method**:
1. Filter for "shopify.com" in network requests
2. Look for requests to `https://checkout.shopify.com/` or `https://deposit.us.shopifycs.com/`
3. **Shop ID**: Found in request headers as `X-Shopify-Shop-Id` or as a parameter
4. **Checkout Token**: In request headers as `X-Shopify-Checkout-Token` when processing a payment

**Example Request Headers**:
```
X-Shopify-Shop-Id: 12345678
X-Shopify-Checkout-Token: 0123456789abcdefghijklmnopqrstuvwxyz
```

### Klarna
**Keys needed**: API Key (Username:Password format)

**Detailed HTTP Request Capture Method**:
1. Filter for "klarna.com" in network requests
2. Look for requests to `https://api.klarna.com/`
3. **API Key**: Found in the Authorization header in Basic format (base64 encoded)
4. The format is typically `username:password` encoded in base64

**Example Request Headers**:
```
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=  <!-- Base64 encoded -->
```

**Example decoded format**: `PK12345:shared_secret`

### 2Checkout
**Keys needed**: Merchant Code and Public Key

**Detailed HTTP Request Capture Method**:
1. Filter for "2checkout.com" in network requests
2. Look for requests to `https://secure.2checkout.com/checkout/`
3. **Merchant Code**: In request parameters or URL as `merchant` or `merchant_code`
4. **Public Key**: In JavaScript configuration objects or request parameters as `publicKey`

**Example Request URL**:
```
https://secure.2checkout.com/checkout/buy?merchant=123456&publicKey=6A39AC478933BC2E...
```

### Xsolla
**Keys needed**: Project ID and API Key

**Detailed HTTP Request Capture Method**:
1. Filter for "xsolla.com" in network requests
2. Look for requests to `https://secure.xsolla.com/api/`
3. **Project ID**: Found in the URL path or request parameters
4. **API Key**: In Authorization headers

**Example Request URL**:
```
https://secure.xsolla.com/api/v2/project/123456/payment/token
```

**Example Request Headers**:
```
Authorization: Basic [base64_encoded_credentials]
```

### NordVPN
**Keys needed**: Service ID and Token

**Detailed HTTP Request Capture Method**:
1. Filter for "nordvpn.com" in network requests, particularly "payment.nordvpn.com"
2. **Service ID**: Found in request parameters when initiating a payment
3. **Token**: In Authorization headers or in payment initiation parameters

**Example Request URL or Payload**:
```
https://payment.nordvpn.com/api/payment/...?service_id=12345
```

### Patreon
**Keys needed**: Client ID and Client Secret

**Detailed HTTP Request Capture Method**:
1. Filter for "patreon.com" in network requests
2. Look for OAuth authentication requests or API calls
3. **Client ID**: Found in request parameters as `client_id`
4. **Client Secret**: May appear in OAuth token exchanges

**Example OAuth Request**:
```
https://www.patreon.com/api/oauth2/token?client_id=abcdefg12345&client_secret=xyz...
```

## Test Card Numbers

Use these test card numbers for safe testing (do not use real cards):

### Visa Test Cards
- **General Success**: 4242 4242 4242 4242
- **Requires Authentication**: 4000 0025 0000 3155
- **Declined**: 4000 0000 0000 0002
- **Insufficient Funds**: 4000 0000 0000 9995

### Mastercard Test Cards
- **General Success**: 5555 5555 5555 4444
- **Requires Authentication**: 5200 0000 0000 0007
- **Declined**: 5105 1051 0510 5100

### American Express Test Cards
- **Success**: 3782 822463 10005
- **Declined**: 3714 496353 98431

### Discover Test Cards
- **Success**: 6011 1111 1111 1117
- **Declined**: 6011 0000 0000 0004

## Security and Ethical Considerations

- **Never use these techniques on websites without permission**
- Use sandbox/test environments whenever possible
- Only capture API keys for legitimate testing purposes
- Follow each gateway's terms of service
- Do not perform excessive requests (can trigger anti-fraud systems)
- Data may be tied to IP addresses - use with caution

## Troubleshooting Common Issues

### "Invalid API Key" Errors
- Check if you're using a test key in production mode or vice versa
- Verify the key format (no extra spaces, complete key copied)
- Some keys expire - make sure your key is current

### "Unauthorized" Errors
- Check if the merchant account or project is associated with the key
- Verify you have permissions for the operation you're attempting

### Gateway Connection Issues
- Some gateways restrict access by IP or region
- Check for anti-automation protections that block scripted requests
- Try using different proxies if connection is consistently refused