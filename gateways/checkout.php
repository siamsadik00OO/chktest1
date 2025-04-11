<?php
/**
 * Checkout.com Payment Gateway Checker
 * This gateway uses the Checkout.com API to validate cards
 */

// Function to check card with Checkout.com
function checkCardWithCheckout($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // We use apiKey as the public key (pk_*) and secretKey is optional
    
    // Check if we have the required key
    if (empty($apiKey)) {
        return "❌ CHECKOUT.COM ERROR - Missing API Key";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Generate a random amount between 0.5 and 5.0
        $amount = mt_rand(50, 500);
        
        // Generate token first
        $tokenData = json_encode([
            "type" => "card",
            "number" => $cc,
            "expiry_month" => intval($formattedMonth),
            "expiry_year" => intval($formattedYear),
            "cvv" => $cvv
        ]);
        
        curl_setopt($ch, CURLOPT_URL, "https://api.checkout.com/tokens");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $tokenData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$apiKey}",
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
        
        // Execute token request
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check if the request failed
        if (curl_errno($ch)) {
            curl_close($ch);
            return "❌ CHECKOUT.COM ERROR - Connection Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Parse token response
        $tokenResult = json_decode($response, true);
        
        // Check if token generation failed
        if ($httpcode !== 201 || !isset($tokenResult['token'])) {
            $error_message = "Invalid card";
            
            if (isset($tokenResult['error_type'])) {
                $error_message = $tokenResult['error_type'];
                if (isset($tokenResult['error_codes'])) {
                    $error_message .= ": " . implode(", ", $tokenResult['error_codes']);
                }
            }
            
            // Format BIN information
            $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ CHECKOUT.COM: {$error_message} ]{$bin_info}";
        }
        
        $token = $tokenResult['token'];
        
        // If we have a token and also a secret key, proceed with a payment request
        if (!empty($secretKey)) {
            $ch = curl_init();
            
            // Prepare payment request data
            $paymentData = json_encode([
                "source" => [
                    "type" => "token",
                    "token" => $token
                ],
                "amount" => $amount,
                "currency" => "USD",
                "reference" => "ORDER-" . time() . "-" . mt_rand(1000, 9999),
                "customer" => [
                    "email" => $user_data['email'],
                    "name" => $user_data['name']
                ],
                "description" => "Card Verification Payment",
                "capture" => false // Auth only, no capture
            ]);
            
            curl_setopt($ch, CURLOPT_URL, "https://api.checkout.com/payments");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: {$secretKey}",
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
            
            // Execute payment request
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Check if the request failed
            if (curl_errno($ch)) {
                curl_close($ch);
                // Format BIN information
                $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ CHECKOUT.COM: Token Valid, Payment Failed - {$token} ]{$bin_info}";
            }
            
            curl_close($ch);
            
            // Parse payment response
            $paymentResult = json_decode($response, true);
            
            // Format BIN information
            $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
            
            // Check payment status
            if ($httpcode === 201 && isset($paymentResult['approved']) && $paymentResult['approved'] === true) {
                // Payment was approved
                return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ CHECKOUT.COM: Payment Approved - ID: {$paymentResult['id']} ]{$bin_info}";
            } elseif (isset($paymentResult['status']) && $paymentResult['status'] === 'Pending') {
                // Payment requires 3DS or additional verification
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ CHECKOUT.COM: Additional Verification Required - {$paymentResult['status']} ]{$bin_info}";
            } else {
                // Payment was declined
                $error_message = "Declined";
                
                if (isset($paymentResult['response_summary'])) {
                    $error_message = $paymentResult['response_summary'];
                } elseif (isset($paymentResult['status'])) {
                    $error_message = $paymentResult['status'];
                }
                
                return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ CHECKOUT.COM: {$error_message} ]{$bin_info}";
            }
        } else {
            // If we only have a token (no secret key for payment)
            // Format BIN information
            $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
            return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ CHECKOUT.COM: Token Valid - {$token} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ CHECKOUT.COM ERROR - Exception: " . $e->getMessage();
    }
}
?>