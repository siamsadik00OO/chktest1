<?php
// Helper functions for card checking

/**
 * Splits a string using multiple delimiters
 * 
 * @param array $delimiters Array of delimiters
 * @param string $string String to split
 * @return array Split string
 */
function multiexplode($delimiters, $string) {
    $one = str_replace($delimiters, $delimiters[0], $string);
    $two = explode($delimiters[0], $one);
    return $two;
}

/**
 * Extracts a substring between two markers
 * 
 * @param string $string The source string
 * @param string $start The starting marker
 * @param string $end The ending marker
 * @return string The extracted substring
 */
function GetStr($string, $start, $end) {
    if (!strpos($string, $start)) return '';
    $str = explode($start, $string);
    if (!isset($str[1])) return '';
    $str = explode($end, $str[1]);
    return $str[0];
}

/**
 * Calculates modulus for Luhn algorithm
 */
function mod($dividendo, $divisor) {
    return round($dividendo - (floor($dividendo/$divisor)*$divisor));
}

/**
 * Generates a random alphanumeric string of specified length
 * 
 * @param int $data Length of string to generate
 * @return string Random string
 */
function AllinOne($data = 42) {
    return substr(strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X%04X%04X', 
        mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), 
        mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(1, 65535), 
        mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535))), 0, $data);
}

/**
 * Gets BIN (Bank Identification Number) information
 * 
 * @param string $cc Credit card number
 * @return array BIN information
 */
function getBinInfo($cc) {
    $bin = substr($cc, 0, 6);
    $result = array();
    
    // First attempt using binlist.net
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://lookup.binlist.net/'.$bin);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Host: lookup.binlist.net',
        'Cookie: _ga=GA1.2.549903363.1545240628; _gid=GA1.2.82939664.1545240628',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8'
    ));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $fim = curl_exec($ch);
    $emoji = GetStr($fim, '"emoji":"', '"');
    curl_close($ch);
    
    // Second attempt using binlist.io
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://binlist.io/lookup/'.$bin.'/');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $bindata = curl_exec($ch);
    curl_close($ch);
    
    // Parse BIN data
    $binna = json_decode($bindata, true);
    
    if (isset($binna['scheme'])) {
        $result['brand'] = $binna['scheme'];
    } else {
        $result['brand'] = 'Unknown';
    }
    
    if (isset($binna['country']['name'])) {
        $result['country'] = $binna['country']['name'];
    } else {
        $result['country'] = 'Unknown';
    }
    
    if (isset($binna['type'])) {
        $result['type'] = $binna['type'];
    } else {
        $result['type'] = 'Unknown';
    }
    
    if (isset($binna['bank']['name'])) {
        $result['bank'] = $binna['bank']['name'];
    } else {
        $result['bank'] = 'Unknown';
    }
    
    $result['emoji'] = $emoji;
    $result['bindata'] = "{$result['type']} - {$result['brand']} - {$result['country']} {$emoji}";
    
    return $result;
}

/**
 * Generates random user information for testing
 * 
 * @return array User information
 */
function generateRandomUserInfo() {
    $get = file_get_contents('https://randomuser.me/api/1.2/?nat=us');
    $user = array();
    
    // Extract first name
    preg_match_all("(\"first\":\"(.*)\")siU", $get, $matches1);
    $user['first'] = isset($matches1[1][0]) ? $matches1[1][0] : 'John';
    
    // Extract last name
    preg_match_all("(\"last\":\"(.*)\")siU", $get, $matches1);
    $user['last'] = isset($matches1[1][0]) ? $matches1[1][0] : 'Doe';
    
    // Extract and modify email
    preg_match_all("(\"email\":\"(.*)\")siU", $get, $matches1);
    $user['email'] = isset($matches1[1][0]) ? $matches1[1][0] : 'johndoe@gmail.com';
    $serve_arr = array("gmail.com", "hotmail.com", "yahoo.com", "outlook.com");
    $serv_rnd = $serve_arr[array_rand($serve_arr)];
    $user['email'] = str_replace("example.com", $serv_rnd, $user['email']);
    
    // Extract street
    preg_match_all("(\"street\":\"(.*)\")siU", $get, $matches1);
    $user['street'] = isset($matches1[1][0]) ? $matches1[1][0] : '123 Main St';
    
    // Extract city
    preg_match_all("(\"city\":\"(.*)\")siU", $get, $matches1);
    $user['city'] = isset($matches1[1][0]) ? $matches1[1][0] : 'Anytown';
    
    // Extract state
    preg_match_all("(\"state\":\"(.*)\")siU", $get, $matches1);
    $user['state'] = isset($matches1[1][0]) ? $matches1[1][0] : 'CA';
    
    // Extract phone
    preg_match_all("(\"phone\":\"(.*)\")siU", $get, $matches1);
    $user['phone'] = isset($matches1[1][0]) ? $matches1[1][0] : '555-123-4567';
    
    // Extract postcode/zip
    preg_match_all("(\"postcode\":(.*),\")siU", $get, $matches1);
    $user['postcode'] = isset($matches1[1][0]) ? $matches1[1][0] : '90210';
    $user['zip'] = $user['postcode'];
    
    return $user;
}

/**
 * Sets up cURL with proxy configuration
 * 
 * @param resource $ch cURL handle
 * @param string $proxy Proxy address
 * @param string $credentials Proxy credentials
 * @param string $useragent User-Agent string
 */
function setupCurlWithProxy($ch, $proxy, $credentials, $useragent) {
    // Set connection timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Set up proxy
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $credentials);
    
    // Set user agent
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
}
?>
