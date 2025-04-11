<?php
// Format checker results for display

/**
 * Formats the response for an approved (CVV match) card
 * 
 * @param string $card_info Card number and details
 * @param array $bin_info BIN information
 * @param array $user_info User information
 * @param string $status Status message
 * @param string $message Response message
 * @param float $time Execution time
 * @return string Formatted response
 */
function formatApprovedCVVResponse($card_info, $bin_info = array(), $user_info = array(), $status = "", $message = "", $time = 0) {
    // Extract card details
    $parts = multiexplode(array(":", "|", " "), $card_info);
    $cc = isset($parts[0]) ? $parts[0] : '';
    $mes = isset($parts[1]) ? $parts[1] : '';
    $ano = isset($parts[2]) ? $parts[2] : '';
    $cvv = isset($parts[3]) ? $parts[3] : '';
    
    // Format bin information
    $bin = substr($cc, 0, 6);
    $brand = isset($bin_info['brand']) ? $bin_info['brand'] : 'Unknown';
    $type = isset($bin_info['type']) ? $bin_info['type'] : 'Unknown';
    $bank = isset($bin_info['bank']) ? $bin_info['bank'] : 'Unknown';
    $country = isset($bin_info['country']) ? $bin_info['country'] : 'Unknown';
    $emoji = isset($bin_info['emoji']) ? $bin_info['emoji'] : '';
    
    // Format card number for display
    $first_six = substr($cc, 0, 6);
    $last_four = substr($cc, -4);
    $masked_cc = $first_six . str_repeat("x", strlen($cc) - 10) . $last_four;
    
    // Create HTML response
    $response = '<div class="result-card success-card">';
    $response .= "#CVV - APPROVED ✅<br>";
    $response .= "Card: {$masked_cc}|{$mes}|{$ano}|{$cvv}<br>";
    $response .= "Status: {$status}<br>";
    $response .= "Response: {$message}<br>";
    $response .= "Card Type: {$type} | Brand: {$brand} | Level: LIVE<br>";
    $response .= "Bank: {$bank} | Country: {$country} {$emoji}<br>";
    
    if ($time > 0) {
        $response .= "Time: {$time}s<br>";
    }
    
    $response .= '</div>';
    
    return $response;
}

/**
 * Formats the response for an approved (CCN match) card
 * 
 * @param string $card_info Card number and details
 * @param array $bin_info BIN information
 * @param array $user_info User information
 * @param string $status Status message
 * @param string $message Response message
 * @param float $time Execution time
 * @return string Formatted response
 */
function formatApprovedCCNResponse($card_info, $bin_info = array(), $user_info = array(), $status = "", $message = "", $time = 0) {
    // Extract card details
    $parts = multiexplode(array(":", "|", " "), $card_info);
    $cc = isset($parts[0]) ? $parts[0] : '';
    $mes = isset($parts[1]) ? $parts[1] : '';
    $ano = isset($parts[2]) ? $parts[2] : '';
    $cvv = isset($parts[3]) ? $parts[3] : '';
    
    // Format bin information
    $bin = substr($cc, 0, 6);
    $brand = isset($bin_info['brand']) ? $bin_info['brand'] : 'Unknown';
    $type = isset($bin_info['type']) ? $bin_info['type'] : 'Unknown';
    $bank = isset($bin_info['bank']) ? $bin_info['bank'] : 'Unknown';
    $country = isset($bin_info['country']) ? $bin_info['country'] : 'Unknown';
    $emoji = isset($bin_info['emoji']) ? $bin_info['emoji'] : '';
    
    // Format card number for display
    $first_six = substr($cc, 0, 6);
    $last_four = substr($cc, -4);
    $masked_cc = $first_six . str_repeat("x", strlen($cc) - 10) . $last_four;
    
    // Create HTML response
    $response = '<div class="result-card warning-card">';
    $response .= "#CCN - APPROVED ⚠️<br>";
    $response .= "Card: {$masked_cc}|{$mes}|{$ano}|{$cvv}<br>";
    $response .= "Status: {$status}<br>";
    $response .= "Response: {$message}<br>";
    $response .= "Card Type: {$type} | Brand: {$brand} | Level: CCN<br>";
    $response .= "Bank: {$bank} | Country: {$country} {$emoji}<br>";
    
    if ($time > 0) {
        $response .= "Time: {$time}s<br>";
    }
    
    $response .= '</div>';
    
    return $response;
}

/**
 * Formats the response for a declined card
 * 
 * @param string $card_info Card number and details
 * @param string $message Error message
 * @param string $code Error code
 * @param array $bin_info BIN information
 * @param array $user_info User information
 * @param float $time Execution time
 * @return string Formatted response
 */
function formatDeclinedResponse($card_info, $message = "", $code = "", $bin_info = array(), $user_info = array(), $time = 0) {
    // Extract card details
    $parts = multiexplode(array(":", "|", " "), $card_info);
    $cc = isset($parts[0]) ? $parts[0] : '';
    $mes = isset($parts[1]) ? $parts[1] : '';
    $ano = isset($parts[2]) ? $parts[2] : '';
    $cvv = isset($parts[3]) ? $parts[3] : '';
    
    // Format bin information if available
    $bin_details = "";
    if (!empty($bin_info)) {
        $bin = substr($cc, 0, 6);
        $brand = isset($bin_info['brand']) ? $bin_info['brand'] : 'Unknown';
        $type = isset($bin_info['type']) ? $bin_info['type'] : 'Unknown';
        $bank = isset($bin_info['bank']) ? $bin_info['bank'] : 'Unknown';
        $country = isset($bin_info['country']) ? $bin_info['country'] : 'Unknown';
        $emoji = isset($bin_info['emoji']) ? $bin_info['emoji'] : '';
        
        $bin_details = "Card Type: {$type} | Brand: {$brand}<br>";
        $bin_details .= "Bank: {$bank} | Country: {$country} {$emoji}<br>";
    }
    
    // Format card number for display
    if (strlen($cc) >= 10) {
        $first_six = substr($cc, 0, 6);
        $last_four = substr($cc, -4);
        $masked_cc = $first_six . str_repeat("x", strlen($cc) - 10) . $last_four;
    } else {
        $masked_cc = $cc;
    }
    
    // Create HTML response
    $response = '<div class="result-card danger-card">';
    $response .= "#DECLINED ❌<br>";
    $response .= "Card: {$masked_cc}|{$mes}|{$ano}|{$cvv}<br>";
    $response .= "Error: {$message}<br>";
    
    if (!empty($code)) {
        $response .= "Code: {$code}<br>";
    }
    
    $response .= $bin_details;
    
    if ($time > 0) {
        $response .= "Time: {$time}s<br>";
    }
    
    $response .= '</div>';
    
    return $response;
}
?>
