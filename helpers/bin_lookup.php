<?php
/**
 * BIN Lookup Functions
 * For retrieving card information based on BIN
 */

function getBinInfo($cc) {
    $bin = substr($cc, 0, 6);
    $binInfo = [
        'brand' => 'Unknown',
        'type' => 'Unknown',
        'bank' => 'Unknown',
        'country' => 'Unknown',
        'emoji' => '',
        'bin' => $bin
    ];
    
    // Try first API: binlist.net
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://lookup.binlist.net/'.$bin);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $fim = curl_exec($ch);
    curl_close($ch);
    
    if ($fim) {
        $binInfo['emoji'] = GetStr($fim, '"emoji":"', '"');
        
        // Extract more info if available
        if (strpos($fim, '"type":"') !== false) {
            $binInfo['type'] = GetStr($fim, '"type":"', '"');
        }
        
        if (strpos($fim, '"brand":"') !== false) {
            $binInfo['brand'] = GetStr($fim, '"brand":"', '"');
        }
        
        if (strpos($fim, '"bank":{"name":"') !== false) {
            $binInfo['bank'] = GetStr($fim, '"bank":{"name":"', '"');
        }
        
        if (strpos($fim, '"country":{"name":"') !== false) {
            $binInfo['country'] = GetStr($fim, '"country":{"name":"', '"');
        }
    }
    
    // Try second API: binlist.io
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://binlist.io/lookup/'.$bin.'/');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $bindata = curl_exec($ch);
    curl_close($ch);
    
    if ($bindata) {
        $binna = json_decode($bindata, true);
        
        if (isset($binna['scheme']) && !empty($binna['scheme'])) {
            $binInfo['brand'] = $binna['scheme'];
        }
        
        if (isset($binna['type']) && !empty($binna['type'])) {
            $binInfo['type'] = $binna['type'];
        }
        
        if (isset($binna['bank']['name']) && !empty($binna['bank']['name'])) {
            $binInfo['bank'] = $binna['bank']['name'];
        }
        
        if (isset($binna['country']['name']) && !empty($binna['country']['name'])) {
            $binInfo['country'] = $binna['country']['name'];
        }
    }
    
    return $binInfo;
}

// Format BIN information for display
function formatBinInfo($bin_data) {
    return "Card Type: " . $bin_data['type'] . 
           " | Level: " . $bin_data['brand'] . 
           " | Bank: " . $bin_data['bank'] . 
           " | Country: " . $bin_data['country'] . 
           " " . $bin_data['emoji'] . 
           " | Bin: " . $bin_data['bin'];
}
?>