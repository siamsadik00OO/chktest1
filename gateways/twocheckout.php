<?php
/**
 * 2Checkout Payment Gateway Checker
 * This gateway uses the 2Checkout API to validate cards
 */

// Function to check card with 2Checkout
function checkCardWithTwoCheckout($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // Merchant Code should be in apiKey, Public Key can be in secretKey
    $merchantCode = $apiKey;
    $publicKey = $secretKey;
    
    // Check if we have the required keys
    if (empty($merchantCode)) {
        return "❌ 2CHECKOUT ERROR - Missing Merchant Code";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Generate reference ID
        $referenceId = "ref_" . time() . "_" . mt_rand(1000, 9999);
        
        // Prepare checkout data
        $checkoutData = [
            "merchantCode" => $merchantCode,
            "clientIP" => $_SERVER['REMOTE_ADDR'] ?? "127.0.0.1",
            "orderReference" => $referenceId,
            "currency" => "USD",
            "grandTotal" => mt_rand(100, 500) / 100,
            "billingDetails" => [
                "firstName" => $user_data['first_name'],
                "lastName" => $user_data['last_name'],
                "email" => $user_data['email'],
                "address1" => $user_data['street'],
                "zipCode" => $user_data['postcode'],
                "city" => $user_data['city'],
                "state" => $user_data['state'],
                "countryCode" => $user_data['country'],
                "phoneNumber" => $user_data['phone']
            ],
            "items" => [
                [
                    "name" => "Card Validation",
                    "description" => "Testing card validity",
                    "quantity" => 1,
                    "price" => mt_rand(100, 500) / 100,
                    "type" => "PRODUCT"
                ]
            ],
            "payment" => [
                "type" => "CARD",
                "cardDetails" => [
                    "cardNumber" => $cc,
                    "cardType" => (isset($bin_data['scheme']) ? strtoupper($bin_data['scheme']) : null),
                    "cardExpirationMonth" => $formattedMonth,
                    "cardExpirationYear" => $formattedYear,
                    "cardCVV" => $cvv,
                    "cardHolderName" => $user_data['name']
                ]
            ]
        ];
        
        // Add public key if available
        if (!empty($publicKey)) {
            $checkoutData["publicKey"] = $publicKey;
        }
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, "https://secure.2checkout.com/checkout/api/1/" . $merchantCode . "/validate");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkoutData));
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
            return "❌ 2CHECKOUT ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Check validation result
        if ($httpcode === 200 && isset($result['validationResults']) && $result['validationResults']['status'] === 'APPROVED') {
            // Card is valid
            return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ 2CHECKOUT: Card Validated Successfully ]{$bin_info}";
        } elseif ($httpcode === 200 && isset($result['validationResults']) && $result['validationResults']['status'] === 'PENDING') {
            // Card requires additional verification
            return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ 2CHECKOUT: Additional Verification Required ]{$bin_info}";
        } elseif ($httpcode === 200 && isset($result['validationResults']) && $result['validationResults']['status'] === 'DECLINED') {
            // Card was declined
            $errorMessage = "Card Declined";
            
            if (isset($result['validationResults']['errorMessage'])) {
                $errorMessage = $result['validationResults']['errorMessage'];
            } elseif (isset($result['validationResults']['errorCode'])) {
                $errorMessage = "Error Code: " . $result['validationResults']['errorCode'];
            }
            
            // Check for specific error messages that might indicate the card is valid but has issues
            if (strpos($errorMessage, 'insufficient funds') !== false || strpos($errorMessage, 'CVV') !== false) {
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ 2CHECKOUT: {$errorMessage} ]{$bin_info}";
            } else {
                return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ 2CHECKOUT: {$errorMessage} ]{$bin_info}";
            }
        } else {
            // Request failed
            $errorMessage = "Invalid Merchant Code or Data";
            
            if (isset($result['error'])) {
                $errorMessage = $result['error']['message'] ?? "Unknown Error";
            } elseif (isset($result['errorMsg'])) {
                $errorMessage = $result['errorMsg'];
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ 2CHECKOUT: {$errorMessage} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ 2CHECKOUT ERROR - Exception: " . $e->getMessage();
    }
}
?>