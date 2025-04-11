<?php
/**
 * Braintree Payment Gateway Checker
 * This gateway uses the Braintree Client SDK to validate cards
 */

// Function to check card with Braintree
function checkCardWithBraintree($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // Api key should be merchant ID and secret key should be public key
    $merchantId = $apiKey;
    $publicKey = $secretKey;
    
    // Check if we have the required keys
    if (empty($merchantId)) {
        return "❌ BRAINTREE ERROR - Missing Merchant ID";
    }
    
    if (empty($publicKey)) {
        return "❌ BRAINTREE ERROR - Missing Public Key";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Set cURL options for client token generation
        curl_setopt($ch, CURLOPT_URL, "https://api.braintreegateway.com/merchants/{$merchantId}/client_token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$publicKey}",
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
        
        // Execute the request for client token
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check if the request failed
        if (curl_errno($ch)) {
            curl_close($ch);
            return "❌ BRAINTREE ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the response to get client token
        $json = json_decode($response, true);
        
        if ($httpcode !== 200 || !isset($json['clientToken'])) {
            return "❌ BRAINTREE ERROR - Invalid Merchant ID or Public Key";
        }
        
        $clientToken = $json['clientToken'];
        
        // Now use client token to make a payment method request
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Prepare payment method data
        $paymentMethodData = json_encode([
            "creditCard" => [
                "number" => $cc,
                "expirationMonth" => $formattedMonth,
                "expirationYear" => $formattedYear,
                "cvv" => $cvv
            ],
            "clientToken" => $clientToken
        ]);
        
        curl_setopt($ch, CURLOPT_URL, "https://api.braintreegateway.com/merchants/{$merchantId}/payment_methods/credit_cards");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentMethodData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$publicKey}",
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
        
        // Execute the validation request
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check if the request failed
        if (curl_errno($ch)) {
            curl_close($ch);
            return "❌ BRAINTREE ERROR - Validation Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the validation response
        $validationResult = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Format the result based on response
        if ($httpcode === 201 && isset($validationResult['creditCard']) && isset($validationResult['creditCard']['token'])) {
            // Card is valid and has been tokenized
            return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ BRAINTREE: Card Approved - Token: " . substr($validationResult['creditCard']['token'], 0, 10) . "... ]{$bin_info}";
        } elseif (isset($validationResult['verification']) && $validationResult['verification']['status'] === 'verified') {
            // Card is verified but requires additional authentication
            return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ BRAINTREE: Verification Required ]{$bin_info}";
        } else {
            // Card is declined
            $error_message = "Declined";
            
            if (isset($validationResult['error'])) {
                $error_message = $validationResult['error']['message'] ?? "Unknown Error";
            } elseif (isset($validationResult['verification']) && isset($validationResult['verification']['message'])) {
                $error_message = $validationResult['verification']['message'];
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ BRAINTREE: {$error_message} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ BRAINTREE ERROR - Exception: " . $e->getMessage();
    }
}
?>