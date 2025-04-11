<?php
/**
 * Stripe Gateway Handler
 * Supports direct API validation or Python script execution
 */

// Check a card with Stripe gateway
function checkCardWithStripe($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig) {
    // Start timing
    $start_time = microtime(true);
    
    // Choose method based on API key availability
    if (!empty($apiKey) && !empty($secretKey)) {
        // Use Python implementation with custom API keys
        return checkWithStripePython($cc, $mes, $ano, $cvv, $apiKey, $secretKey, $proxyConfig);
    } else {
        // Use PHP implementation with default API key
        return checkWithStripePHP($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $proxyConfig);
    }
}

// Check using Python script with custom API keys
function checkWithStripePython($cc, $mes, $ano, $cvv, $pk, $pi, $proxyConfig) {
    // Build command
    $cmd = "python3 stripe_validator.py " . escapeshellarg($cc) . " " . 
           escapeshellarg($mes) . " " . 
           escapeshellarg($ano) . " " . 
           escapeshellarg($cvv) . " " . 
           escapeshellarg($pk) . " " . 
           escapeshellarg($pi);
    
    // Add proxy if configured
    if (!empty($proxyConfig)) {
        $proxy_str = $proxyConfig['ip'] . ':' . $proxyConfig['port'];
        if (!empty($proxyConfig['username']) && !empty($proxyConfig['password'])) {
            $proxy_str = $proxyConfig['username'] . ':' . $proxyConfig['password'] . '@' . $proxy_str;
        }
        $cmd .= " " . escapeshellarg($proxy_str);
    }
    
    // Execute the command
    $output = shell_exec($cmd);
    return $output ?: "❌ ERROR - $cc|$mes|$ano|$cvv - [ Python execution failed ]";
}

// Check with PHP implementation
function checkWithStripePHP($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $proxyConfig) {
    $start_time = microtime(true);
    
    // Create cURL session
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
    
    // First request to get Stripe session
    curl_setopt($ch, CURLOPT_URL, "https://m.stripe.com/6");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
        "Accept: */*",
        "Accept-Language: en-US,en;q=0.9",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    
    $result1 = curl_exec($ch);
    
    // Check for successful first request
    if (!$result1 || curl_errno($ch)) {
        $time_taken = number_format(microtime(true) - $start_time, 2);
        return "❌ ERROR: Connection failed | Time: " . $time_taken . "s";
    }
    
    $stripe_session = json_decode($result1, true);
    
    // Default Stripe publishable key - use this if no custom key provided
    $stripe_key = "pk_live_51HPtzNJlkQw87gXSsvRTF4Ox0k3YoLXhdiuBGRxUUaqqz9sxSKBnXQTHr5QiN2AxLOBgSMMGu8hJOARhHT1PSnH800SsRuEXWn";
    
    // Second request to validate card
    $data = http_build_query([
        'type' => 'card',
        'owner[name]' => $user_data['first_name'] . ' ' . $user_data['last_name'],
        'owner[email]' => $user_data['email'],
        'owner[address][line1]' => $user_data['street'],
        'owner[address][city]' => $user_data['city'],
        'owner[address][state]' => $user_data['state'],
        'owner[address][postal_code]' => $user_data['postcode'],
        'owner[address][country]' => 'US',
        'card[number]' => $cc,
        'card[cvc]' => $cvv,
        'card[exp_month]' => $mes,
        'card[exp_year]' => $ano,
        'guid' => isset($stripe_session['guid']) ? $stripe_session['guid'] : $session_data['guid'],
        'muid' => isset($stripe_session['muid']) ? $stripe_session['muid'] : $session_data['muid'],
        'sid' => isset($stripe_session['sid']) ? $stripe_session['sid'] : $session_data['sid'],
        'key' => $stripe_key,
        'payment_user_agent' => 'stripe.js/7315d41'
    ]);
    
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    $result2 = curl_exec($ch);
    curl_close($ch);
    
    // Calculate time taken
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Format bin info for display
    $bin_info_formatted = formatBinInfo($bin_data);
    
    // Process result
    if ($result2) {
        if (strpos($result2, '"id": "pm_') !== false) {
            if (strpos($result2, '"cvc_check": "pass"') !== false) {
                return "✅ #CVV - $cc|$mes|$ano|$cvv - [ $bin_info_formatted | Time: " . $time_taken . "s ]";
            } elseif (strpos($result2, '"cvc_check": "unavailable"') !== false) {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ $bin_info_formatted | Time: " . $time_taken . "s ]";
            } else {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ $bin_info_formatted | Time: " . $time_taken . "s ]";
            }
        } elseif (strpos($result2, 'rate_limit') !== false) {
            return "❌ RATE LIMIT - $cc|$mes|$ano|$cvv - [ Try again later | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'insufficient_funds') !== false) {
            return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Insufficient Funds | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'incorrect_cvc') !== false) {
            return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Incorrect CVC | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'lost_card') !== false || strpos($result2, 'stolen_card') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Lost/Stolen Card | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'card_decline') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Card Declined | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'Your card does not support this type of purchase') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Card Doesn't Support This Purchase | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'do_not_honor') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Do Not Honor | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'generic_decline') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Generic Decline | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'expired_card') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Expired Card | $bin_info_formatted | Time: " . $time_taken . "s ]";
        } else {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ " . GetStr($result2, '"decline_code":"', '"') . " | $bin_info_formatted | Time: " . $time_taken . "s ]";
        }
    } else {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Connection Error | Time: " . $time_taken . "s ]";
    }
}
?>