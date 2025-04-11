<?php
/**
 * Multi-Gateway Credit Card Checker
 * Supports numerous payment processors for comprehensive card validation
 */

// Error handling and security
error_reporting(0);
set_time_limit(0);
date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Include helper libraries
require_once 'helpers/proxy_handler.php';
require_once 'helpers/bin_lookup.php';

// Include major gateway handlers
require_once 'gateways/stripe.php';
require_once 'gateways/paypal.php';
require_once 'gateways/adyen.php';
require_once 'gateways/authorize.php';
require_once 'gateways/braintree.php';
require_once 'gateways/checkout.php';
require_once 'gateways/worldpay.php';
require_once 'gateways/square.php';

// Include e-commerce & platform gateways
require_once 'gateways/shopify.php';
require_once 'gateways/klarna.php';
require_once 'gateways/twocheckout.php';

// Include subscription service gateways
require_once 'gateways/nordvpn.php';
require_once 'gateways/patreon.php';
require_once 'gateways/xsolla.php';

// Function to parse card details
function multiexplode($delimiters, $string) {
    $one = str_replace($delimiters, $delimiters[0], $string);
    $two = explode($delimiters[0], $one);
    return $two;
}

// Function to extract data from response
function GetStr($string, $start, $end) {
    $str = explode($start, $string);
    if(count($str) > 1) {
        $str = explode($end, $str[1]);
        return $str[0];
    }
    return '';
}

// Generate random IDs for tracking
function generateUUID($data = 42) {
    return substr(strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X%04X%04X', 
        mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(16384, 20479), 
        mt_rand(32768, 49151), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), 
        mt_rand(1, 65535), mt_rand(1, 65535))), 0, $data);
}

// Setup proxy with WebShare API
function getWebshareProxy($apiKey) {
    // Initialize cURL for API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://proxy.webshare.io/api/proxy/list/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    // Set API token in header
    $headers = array();
    $headers[] = 'Authorization: Token ' . $apiKey;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Execute request
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Check for errors
    if ($error) {
        error_log("Error fetching Webshare proxies: " . $error);
        return null;
    }
    
    // Parse response
    $data = json_decode($response, true);
    
    // Check if we got a valid response with proxies
    if (!isset($data['results']) || empty($data['results'])) {
        error_log("No proxies found or invalid Webshare API key");
        return null;
    }
    
    // Select a random proxy from the results
    $random_index = array_rand($data['results']);
    $proxy = $data['results'][$random_index];
    
    // Create and return the proxy configuration
    return [
        'ip' => $proxy['proxy_address'],
        'port' => $proxy['ports']['socks5'],
        'username' => $proxy['username'],
        'password' => $proxy['password'],
        'url' => $proxy['username'] . ':' . $proxy['password'] . '@' . $proxy['proxy_address'] . ':' . $proxy['ports']['socks5'],
        'type' => 'socks5'
    ];
}

// Setup proxy from direct details (legacy support)
function setupProxy($proxyDetails) {
    // Parse proxy string
    $components = explode(':', $proxyDetails);
    
    if (count($components) === 4) {
        return [
            'ip' => $components[0],
            'port' => $components[1],
            'username' => $components[2],
            'password' => $components[3],
            'url' => $components[2] . ':' . $components[3] . '@' . $components[0] . ':' . $components[1],
            'type' => 'socks5'
        ];
    } elseif (count($components) === 2) {
        return [
            'ip' => $components[0],
            'port' => $components[1],
            'username' => null,
            'password' => null,
            'url' => $components[0] . ':' . $components[1],
            'type' => 'socks5'
        ];
    }
    
    return null;
}

// Generate random user details
function generateUserData() {
    $get = file_get_contents('https://randomuser.me/api/1.2/?nat=us');
    
    preg_match_all("(\"first\":\"(.*)\")siU", $get, $matches1);
    $first = $matches1[1][0] ?? 'John';
    
    preg_match_all("(\"last\":\"(.*)\")siU", $get, $matches1);
    $last = $matches1[1][0] ?? 'Doe';
    
    preg_match_all("(\"email\":\"(.*)\")siU", $get, $matches1);
    $email = $matches1[1][0] ?? 'example@gmail.com';
    $serve_arr = array("gmail.com","hotmail.com","yahoo.com","outlook.com");
    $serv_rnd = $serve_arr[array_rand($serve_arr)];
    $email = str_replace("example.com", $serv_rnd, $email);
    
    preg_match_all("(\"street\":\"(.*)\")siU", $get, $matches1);
    $street = $matches1[1][0] ?? '123 Main St';
    
    preg_match_all("(\"city\":\"(.*)\")siU", $get, $matches1);
    $city = $matches1[1][0] ?? 'New York';
    
    preg_match_all("(\"state\":\"(.*)\")siU", $get, $matches1);
    $state = $matches1[1][0] ?? 'NY';
    
    preg_match_all("(\"phone\":\"(.*)\")siU", $get, $matches1);
    $phone = $matches1[1][0] ?? '555-123-4567';
    
    preg_match_all("(\"postcode\":(.*),\")siU", $get, $matches1);
    $postcode = $matches1[1][0] ?? '10001';
    
    return [
        'first_name' => $first,
        'last_name' => $last,
        'name' => "$first $last",
        'email' => $email,
        'street' => $street,
        'city' => $city,
        'state' => $state,
        'phone' => $phone,
        'postcode' => $postcode,
        'country' => 'US'
    ];
}

// Main card checking function
function checkCard($cc, $mes, $ano, $cvv, $gateway, $apiKey, $secretKey, $proxyConfig = null) {
    // Start timing
    $start_time = microtime(true);
    
    // Get BIN information
    $bin_data = getBinInfo($cc);
    
    // Generate user data
    $user_data = generateUserData();
    
    // Generate session IDs
    $session_data = [
        'guid' => generateUUID(),
        'muid' => generateUUID(),
        'sid' => generateUUID()
    ];
    
    // Check card using appropriate gateway
    switch($gateway) {
        // Major payment gateways
        case 'stripe':
            $result = checkCardWithStripe($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'paypal':
            $result = checkCardWithPaypal($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'adyen':
            $result = checkCardWithAdyen($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'authorize':
            $result = checkCardWithAuthorize($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'braintree':
            $result = checkCardWithBraintree($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'checkout':
            $result = checkCardWithCheckout($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'worldpay':
            $result = checkCardWithWorldpay($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'square':
            $result = checkCardWithSquare($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
            
        // E-commerce & platforms
        case 'shopify':
            $result = checkCardWithShopify($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'klarna':
            $result = checkCardWithKlarna($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'twocheckout':
            $result = checkCardWithTwoCheckout($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'bluesnap':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'BlueSnap');
            break;
        case 'razorpay':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'Razorpay');
            break;
        case 'airwallex':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'Airwallex');
            break;
        case 'mollie':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'Mollie');
            break;
            
        // Subscription services
        case 'nordvpn':
            $result = checkCardWithNordVPN($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'patreon':
            $result = checkCardWithPatreon($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'xsolla':
            $result = checkCardWithXsolla($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
            break;
        case 'gocardless':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'GoCardless');
            break;
        case 'midtrans':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'Midtrans');
            break;
            
        // Regional gateways
        case 'payu':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'PayU');
            break;
        case 'cybersource':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'CyberSource');
            break;
        case 'micropayment':
            $result = gatewayNotImplemented($cc, $mes, $ano, $cvv, 'Micropayment');
            break;
            
        default:
            // Default to Stripe if an unknown gateway is selected
            $result = checkCardWithStripe($cc, $mes, $ano, $cvv, $bin_data, $user_data, $session_data, $apiKey, $secretKey, $proxyConfig);
    }
    
    // Calculate processing time
    $time_taken = number_format(microtime(true) - $start_time, 2);
    
    // Add time to result if not already included
    if (strpos($result, "Time: ") === false) {
        $result .= " | Time: " . $time_taken . "s";
    }
    
    return $result;
}

// Fallback function for gateways that are not implemented yet
function gatewayNotImplemented($cc, $mes, $ano, $cvv, $gateway) {
    return "❌ GATEWAY NOT IMPLEMENTED - $cc|$mes|$ano|$cvv - [ Gateway: $gateway is not fully implemented yet ]";
}

// Process incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST parameters
    $lista = $_POST['lista'] ?? '';
    $gateway = $_POST['gateway'] ?? 'stripe';
    $useProxy = isset($_POST['useProxy']) && $_POST['useProxy'] == 1;
    $proxyDetails = $_POST['proxyDetails'] ?? '';
    $webshareApiKey = $_POST['webshareApiKey'] ?? '';
    $apiKey = $_POST['apiKey'] ?? '';
    $secretKey = $_POST['secretKey'] ?? '';
    $threads = intval($_POST['threads'] ?? 1);
    
    // Parse the card details
    $details = multiexplode(array(":", "|", " "), $lista);
    
    // Check if we have a valid card format
    if (count($details) >= 4) {
        $cc = $details[0];
        $mes = $details[1];
        $ano = $details[2];
        $cvv = $details[3];
        
        // Basic validation
        if (!preg_match('/^[0-9]{13,19}$/', $cc)) {
            echo "❌ INVALID FORMAT - Please check the card number";
            exit;
        }
        
        // Setup proxy if requested
        $proxyConfig = null;
        if ($useProxy) {
            if (!empty($webshareApiKey)) {
                // Use Webshare API to get proxy
                $proxyConfig = getWebshareProxy($webshareApiKey);
                
                // Fallback to direct proxy if Webshare API fails
                if ($proxyConfig === null && !empty($proxyDetails)) {
                    $proxyConfig = setupProxy($proxyDetails);
                }
            } else if (!empty($proxyDetails)) {
                // Use direct proxy details
                $proxyConfig = setupProxy($proxyDetails);
            }
        }
        
        // Include database models
        require_once 'models.php';

        // Get client IP
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

        // Process the card and output result
        $result = checkCard($cc, $mes, $ano, $cvv, $gateway, $apiKey, $secretKey, $proxyConfig);

        // Determine status
        $status = 'ERROR';
        if (strpos($result, "#CVV") !== false) {
            $status = 'APPROVED';
        } elseif (strpos($result, "#CCN") !== false) {
            $status = 'APPROVED_CCN';
        } elseif (strpos($result, "DECLINED") !== false) {
            $status = 'DECLINED';
        }

        // Save result to database
        $cardString = $cc . '|' . $mes . '|' . $ano . '|' . $cvv;
        saveCheckResult($cardString, $status, $result, $gateway, $bin_data, $clientIp);

        // If using Webshare proxy, record usage
        if ($proxyConfig && !empty($webshareApiKey)) {
            // Get Webshare key ID
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT id FROM webshare_keys WHERE api_key = :api_key");
            $stmt->execute([':api_key' => $webshareApiKey]);
            $keyId = $stmt->fetchColumn();
            
            if ($keyId) {
                recordProxyUsage(
                    $proxyConfig['ip'],
                    $proxyConfig['port'],
                    $proxyConfig['username'] ?? '',
                    $keyId,
                    $status === 'APPROVED' || $status === 'APPROVED_CCN'
                );
            }
        }

        // Output the result
        echo $result;
    } else {
        echo "❌ INVALID FORMAT - Use format: XXXXXXXXXXXXXXXX|MM|YYYY|CVV";
    }
} else {
    // If accessed directly without POST
    echo "❌ Invalid access method";
}
?>