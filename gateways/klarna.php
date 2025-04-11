<?php
/**
 * Klarna Payment Gateway Checker
 * This gateway uses the Klarna API to validate cards
 */

// Function to check card with Klarna
function checkCardWithKlarna($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // API Key should be in apiKey
    $klarnaApiKey = $apiKey;
    
    // Check if we have the required API key
    if (empty($klarnaApiKey)) {
        return "❌ KLARNA ERROR - Missing API Key";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Generate a random order ID
        $orderId = "ORDER-" . time() . "-" . mt_rand(1000, 9999);
        
        // Generate random amount (10-100)
        $amount = mt_rand(1000, 10000);
        
        // Prepare session creation data
        $sessionData = json_encode([
            "purchase_country" => $user_data['country'],
            "purchase_currency" => "USD",
            "locale" => "en-US",
            "order_amount" => $amount,
            "order_tax_amount" => 0,
            "order_lines" => [
                [
                    "type" => "digital",
                    "reference" => "Test-Product",
                    "name" => "Card Validation",
                    "quantity" => 1,
                    "unit_price" => $amount,
                    "tax_rate" => 0,
                    "total_amount" => $amount,
                    "total_tax_amount" => 0
                ]
            ],
            "billing_address" => [
                "given_name" => $user_data['first_name'],
                "family_name" => $user_data['last_name'],
                "email" => $user_data['email'],
                "street_address" => $user_data['street'],
                "postal_code" => $user_data['postcode'],
                "city" => $user_data['city'],
                "region" => $user_data['state'],
                "phone" => $user_data['phone'],
                "country" => $user_data['country']
            ],
            "shipping_address" => [
                "given_name" => $user_data['first_name'],
                "family_name" => $user_data['last_name'],
                "email" => $user_data['email'],
                "street_address" => $user_data['street'],
                "postal_code" => $user_data['postcode'],
                "city" => $user_data['city'],
                "region" => $user_data['state'],
                "phone" => $user_data['phone'],
                "country" => $user_data['country']
            ],
            "merchant_reference1" => $orderId
        ]);
        
        // Set cURL options for session creation
        curl_setopt($ch, CURLOPT_URL, "https://api.klarna.com/payments/v1/sessions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sessionData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: Basic " . base64_encode($klarnaApiKey),
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        ]);
        
        // Set proxy if configured
        if ($proxyConfig) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyConfig['ip'] . ':' . $proxyConfig['port']);
            
            if (!empty($proxyConfig['username']) && !empty($proxyConfig['password'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyConfig['username'] . ':' . $proxyConfig['password']);
            }
            
            if ($proxyConfig['type'] === 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
        }
        
        // Execute the request
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check if the request failed
        if (curl_errno($ch)) {
            curl_close($ch);
            return "❌ KLARNA ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Check if session was created successfully
        if ($httpcode === 200 && isset($result['session_id'])) {
            $sessionId = $result['session_id'];
            $clientToken = $result['client_token'];
            
            // Now try to authorize a payment using this session
            $ch = curl_init();
            
            // Prepare authorization data
            $authData = json_encode([
                "session_id" => $sessionId,
                "auto_capture" => false,
                "merchant_reference1" => $orderId,
                "payment_method_categories" => ["credit_card"],
                "billing_address" => [
                    "given_name" => $user_data['first_name'],
                    "family_name" => $user_data['last_name'],
                    "email" => $user_data['email'],
                    "street_address" => $user_data['street'],
                    "postal_code" => $user_data['postcode'],
                    "city" => $user_data['city'],
                    "region" => $user_data['state'],
                    "phone" => $user_data['phone'],
                    "country" => $user_data['country']
                ],
                "purchase_country" => $user_data['country'],
                "purchase_currency" => "USD",
                "locale" => "en-US",
                "order_amount" => $amount,
                "order_tax_amount" => 0,
                "order_lines" => [
                    [
                        "type" => "digital",
                        "reference" => "Test-Product",
                        "name" => "Card Validation",
                        "quantity" => 1,
                        "unit_price" => $amount,
                        "tax_rate" => 0,
                        "total_amount" => $amount,
                        "total_tax_amount" => 0
                    ]
                ]
            ]);
            
            // Set cURL options for authorization
            curl_setopt($ch, CURLOPT_URL, "https://api.klarna.com/payments/v1/authorizations");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $authData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: Basic " . base64_encode($klarnaApiKey),
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
            ]);
            
            // Set proxy if configured
            if ($proxyConfig) {
                curl_setopt($ch, CURLOPT_PROXY, $proxyConfig['ip'] . ':' . $proxyConfig['port']);
                
                if (!empty($proxyConfig['username']) && !empty($proxyConfig['password'])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyConfig['username'] . ':' . $proxyConfig['password']);
                }
                
                if ($proxyConfig['type'] === 'socks5') {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                }
            }
            
            // Execute the authorization request
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Check if the request failed
            if (curl_errno($ch)) {
                curl_close($ch);
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ KLARNA: Session Created but Auth Failed - {$sessionId} ]{$bin_info}";
            }
            
            curl_close($ch);
            
            // Parse the authorization response
            $authResult = json_decode($response, true);
            
            if ($httpcode === 200 && isset($authResult['authorization_token'])) {
                // Authorization was successful
                return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ KLARNA: Authorization Approved - Token: " . substr($authResult['authorization_token'], 0, 10) . "... ]{$bin_info}";
            } elseif ($httpcode === 202 || ($httpcode === 200 && isset($authResult['redirect_url']))) {
                // Authorization requires additional steps
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ KLARNA: Additional Verification Required ]{$bin_info}";
            } else {
                // Authorization failed
                $errorMessage = "Authorization Failed";
                
                if (isset($authResult['error_messages']) && is_array($authResult['error_messages'])) {
                    $errorMessage = implode(", ", $authResult['error_messages']);
                } elseif (isset($authResult['error_message'])) {
                    $errorMessage = $authResult['error_message'];
                }
                
                // Check for specific error messages that might indicate the card is valid
                if (strpos($errorMessage, 'insufficient funds') !== false || strpos($errorMessage, 'balance') !== false) {
                    return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ KLARNA: {$errorMessage} ]{$bin_info}";
                } else {
                    return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ KLARNA: {$errorMessage} ]{$bin_info}";
                }
            }
        } else {
            // Session creation failed
            $errorMessage = "Invalid API Key or Data";
            
            if (isset($result['error_messages']) && is_array($result['error_messages'])) {
                $errorMessage = implode(", ", $result['error_messages']);
            } elseif (isset($result['error_message'])) {
                $errorMessage = $result['error_message'];
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ KLARNA: {$errorMessage} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ KLARNA ERROR - Exception: " . $e->getMessage();
    }
}
?>