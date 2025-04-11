<?php
/**
 * Authorize.net Gateway Handler
 * Validates cards against Authorize.net's payment processing system
 */

// Check a card with Authorize.net gateway
function checkCardWithAuthorize($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig) {
    // Start timing
    $start_time = microtime(true);
    
    // Format month for Authorize.net (requires 2 digits)
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
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    
    // Set API credentials
    $api_login_id = !empty($apiKey) ? $apiKey : '5KP3u95bQpv';  // Test API Login ID
    $transaction_key = !empty($secretKey) ? $secretKey : '346HZ32z3fP4hTG2'; // Test Transaction Key
    
    // Create a random reference
    $ref_id = 'REF' . time() . rand(1000, 9999);
    $amount = rand(100, 500) / 100; // Random amount between $1.00 and $5.00
    
    // Build auth request
    $request = [
        'createTransactionRequest' => [
            'merchantAuthentication' => [
                'name' => $api_login_id,
                'transactionKey' => $transaction_key
            ],
            'refId' => $ref_id,
            'transactionRequest' => [
                'transactionType' => 'authOnlyTransaction',
                'amount' => number_format($amount, 2, '.', ''),
                'payment' => [
                    'creditCard' => [
                        'cardNumber' => $cc,
                        'expirationDate' => $mes . $ano,
                        'cardCode' => $cvv
                    ]
                ],
                'billTo' => [
                    'firstName' => $user_data['first_name'],
                    'lastName' => $user_data['last_name'],
                    'address' => $user_data['street'],
                    'city' => $user_data['city'],
                    'state' => $user_data['state'],
                    'zip' => $user_data['postcode'],
                    'country' => 'US',
                    'phoneNumber' => $user_data['phone']
                ],
                'customerIP' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]
        ]
    ];
    
    // Set request options
    curl_setopt($ch, CURLOPT_URL, "https://apitest.authorize.net/xml/v1/request.api");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
    
    $result = curl_exec($ch);
    
    // Get error if request failed
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    // Calculate time taken
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Format bin info for display
    $bin_info_formatted = formatBinInfo($bin_data);
    
    // Check for connection issues
    if (!$result || $error) {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Authorize.net Connection Error: $error | Time: " . $time_taken . "s ]";
    }
    
    // Process response
    $response = json_decode($result, true);
    
    if (isset($response['transactionResponse'])) {
        $transaction = $response['transactionResponse'];
        
        if (isset($transaction['responseCode'])) {
            $response_code = $transaction['responseCode'];
            
            if ($response_code === '1') {
                // Approved
                return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Authorize.net: Approved | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif ($response_code === '2') {
                // Declined
                $reason = isset($transaction['messages'][0]['description']) ? $transaction['messages'][0]['description'] : 'Card Declined';
                
                // Special cases for specific decline reasons
                if (strpos(strtolower($reason), 'cvv') !== false) {
                    return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Authorize.net: CVV Issue | $bin_info_formatted | Time: " . $time_taken . "s ]";
                } elseif (strpos(strtolower($reason), 'insufficient') !== false) {
                    return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Authorize.net: Insufficient Funds | $bin_info_formatted | Time: " . $time_taken . "s ]";
                } else {
                    return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Authorize.net: $reason | $bin_info_formatted | Time: " . $time_taken . "s ]";
                }
            } elseif ($response_code === '4') {
                // Held for review
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Authorize.net: Held for Review | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } else {
                // Other response code
                $reason = isset($transaction['messages'][0]['description']) ? $transaction['messages'][0]['description'] : 'Unknown Error';
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Authorize.net: $reason | $bin_info_formatted | Time: " . $time_taken . "s ]";
            }
        } elseif (isset($transaction['errors'])) {
            // Error in transaction
            $error_message = isset($transaction['errors'][0]['errorText']) ? $transaction['errors'][0]['errorText'] : 'Transaction Error';
            
            if (strpos(strtolower($error_message), 'invalid card') !== false) {
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Authorize.net: Invalid Card | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif (strpos(strtolower($error_message), 'card number is invalid') !== false) {
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Authorize.net: Invalid Card Number | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif (strpos(strtolower($error_message), 'card code') !== false) {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Authorize.net: Card Code Issue | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } else {
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Authorize.net: $error_message | $bin_info_formatted | Time: " . $time_taken . "s ]";
            }
        }
    } elseif (isset($response['messages']) && isset($response['messages']['resultCode'])) {
        // Check for general errors
        if ($response['messages']['resultCode'] === 'Error') {
            $error_message = isset($response['messages']['message'][0]['text']) ? $response['messages']['message'][0]['text'] : 'API Error';
            return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Authorize.net: $error_message | Time: " . $time_taken . "s ]";
        }
    }
    
    // Default error response
    return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Authorize.net: Unknown Response | Time: " . $time_taken . "s ]";
}
?>