#!/usr/bin/env python3
"""
Stripe Card Validator
Validates credit cards against Stripe's API
"""

import sys
import json
import time
import requests
from urllib.parse import urlencode

# Create a session for reusability
session = requests.Session()

def setup_proxy(proxy_string):
    """Set up proxy configuration"""
    if proxy_string and '@' in proxy_string:
        # Format: username:password@ip:port
        auth, proxy = proxy_string.split('@')
        username, password = auth.split(':')
        proxy_dict = {
            "http": f"http://{auth}@{proxy}",
            "https": f"https://{auth}@{proxy}"
        }
    elif proxy_string:
        # Format: ip:port
        proxy_dict = {
            "http": f"http://{proxy_string}",
            "https": f"https://{proxy_string}"
        }
    else:
        proxy_dict = None
    
    return proxy_dict

def get_stripe_tokens(proxies=None):
    """Get Stripe session tokens"""
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
        "Accept": "*/*",
        "Accept-Language": "en-US,en;q=0.9"
    }
    
    try:
        response = session.post(
            "https://m.stripe.com/6",
            headers=headers,
            proxies=proxies,
            timeout=10
        )
        
        if response.status_code == 200:
            return response.json()
        else:
            return {"muid": "", "guid": "", "sid": ""}
    except Exception as e:
        print(f"Error getting Stripe tokens: {str(e)}", file=sys.stderr)
        return {"muid": "", "guid": "", "sid": ""}

def validate_payment_intent(cc, month, year, cvv, pk, pi, proxies=None):
    """Validate a card using Stripe's payment intent API"""
    start_time = time.time()
    
    # Get Stripe tokens
    tokens = get_stripe_tokens(proxies)
    muid = tokens.get("muid", "")
    guid = tokens.get("guid", "")
    sid = tokens.get("sid", "")
    
    # Extract the actual pi value from full intent string if needed
    if '_secret_' in pi:
        pi_value = pi.split('_secret_')[0]
    else:
        pi_value = pi
    
    # Prepare headers
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
        "Accept": "application/json",
        "Accept-Language": "en-US,en;q=0.9",
        "Content-Type": "application/x-www-form-urlencoded",
        "Origin": "https://js.stripe.com",
        "Referer": "https://js.stripe.com/"
    }
    
    # Prepare data
    data = {
        'payment_method_data[type]': 'card',
        'payment_method_data[billing_details][name]': 'John Smith',
        'payment_method_data[card][number]': cc,
        'payment_method_data[card][exp_month]': month,
        'payment_method_data[card][exp_year]': year,
        'payment_method_data[card][cvc]': cvv,
        'payment_method_data[guid]': guid,
        'payment_method_data[muid]': muid,
        'payment_method_data[sid]': sid,
        'payment_method_data[pasted_fields]': 'number',
        'payment_method_data[payment_user_agent]': 'stripe.js/987e19c84; stripe-js-v3/987e19c84',
        'expected_payment_method_type': 'card',
        'use_stripe_sdk': 'true',
        'key': pk,
        'client_secret': pi
    }
    
    # Make the request
    try:
        response = session.post(
            f"https://api.stripe.com/v1/payment_intents/{pi_value}/confirm",
            headers=headers,
            data=urlencode(data),
            proxies=proxies,
            timeout=30
        )
        
        # Calculate time taken
        time_taken = round(time.time() - start_time, 2)
        
        # Process response
        if response.status_code == 200:
            response_json = response.json()
            
            if response_json.get('status') == 'succeeded':
                return f"✅ #CVV - {cc}|{month}|{year}|{cvv} - [ Payment successful | Time: {time_taken}s ]"
            elif response_json.get('status') == 'requires_action' or response_json.get('status') == 'requires_source_action':
                return f"⚠️ #CCN - {cc}|{month}|{year}|{cvv} - [ 3D Secure Required | Time: {time_taken}s ]"
            else:
                return f"⚠️ #CCN - {cc}|{month}|{year}|{cvv} - [ Status: {response_json.get('status', 'Unknown')} | Time: {time_taken}s ]"
        else:
            response_json = response.json()
            error = response_json.get('error', {})
            code = error.get('code', 'unknown_error')
            decline_code = error.get('decline_code', '')
            message = error.get('message', 'Unknown error')
            
            if code == 'card_declined':
                return f"❌ DECLINED - {cc}|{month}|{year}|{cvv} - [ {decline_code}: {message} | Time: {time_taken}s ]"
            elif code == 'incorrect_cvc':
                return f"⚠️ #CCN - {cc}|{month}|{year}|{cvv} - [ Incorrect CVC | Time: {time_taken}s ]"
            elif code == 'insufficient_funds':
                return f"✅ #CVV - {cc}|{month}|{year}|{cvv} - [ Insufficient Funds | Time: {time_taken}s ]"
            else:
                return f"❌ DECLINED - {cc}|{month}|{year}|{cvv} - [ {code}: {message} | Time: {time_taken}s ]"
    
    except Exception as e:
        time_taken = round(time.time() - start_time, 2)
        return f"❌ ERROR - {cc}|{month}|{year}|{cvv} - [ {str(e)} | Time: {time_taken}s ]"

def main():
    """Main function for command line usage"""
    # Check arguments
    if len(sys.argv) < 7:
        print("Usage: python stripe_validator.py <cc> <month> <year> <cvv> <pk> <pi> [proxy]")
        sys.exit(1)
    
    cc = sys.argv[1]
    month = sys.argv[2]
    year = sys.argv[3]
    cvv = sys.argv[4]
    pk = sys.argv[5]
    pi = sys.argv[6]
    
    # Set up proxy if provided
    proxies = None
    if len(sys.argv) >= 8:
        proxy_string = sys.argv[7]
        proxies = setup_proxy(proxy_string)
    
    # Validate card
    result = validate_payment_intent(cc, month, year, cvv, pk, pi, proxies)
    print(result)

if __name__ == "__main__":
    main()