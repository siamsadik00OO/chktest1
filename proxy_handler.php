<?php
/**
 * Proxy Handler Class
 * 
 * Handles proxy rotation and verification for the card checker
 */

class ProxyHandler {
    private $webshare_token;
    private $proxy_list = [];
    private $current_proxy = null;
    
    /**
     * Constructor
     * 
     * @param string $token Webshare API token
     */
    public function __construct($token = null) {
        $this->webshare_token = $token ?: '2a952592de542903da4c865330b95795db1ffdcb';
        $this->fetchProxyList();
    }
    
    /**
     * Fetch proxy list from Webshare API
     */
    private function fetchProxyList() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://proxy.webshare.io/api/proxy/list/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        
        $headers = array();
        $headers[] = 'Authorization: Token ' . $this->webshare_token;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['results']) && is_array($data['results'])) {
                $this->proxy_list = $data['results'];
            }
        }
    }
    
    /**
     * Get a random proxy from the list
     * 
     * @return array|null Proxy details or null if none available
     */
    public function getRandomProxy() {
        if (empty($this->proxy_list)) {
            return null;
        }
        
        $random = array_rand($this->proxy_list);
        $proxy = $this->proxy_list[$random];
        
        $this->current_proxy = [
            'ip' => $proxy['proxy_address'],
            'port' => $proxy['ports']['socks5'],
            'username' => $proxy['username'],
            'password' => $proxy['password']
        ];
        
        return $this->current_proxy;
    }
    
    /**
     * Get the current proxy
     * 
     * @return array|null Current proxy or null if none set
     */
    public function getCurrentProxy() {
        return $this->current_proxy;
    }
    
    /**
     * Get proxy as URL with authentication
     * 
     * @return string Proxy URL
     */
    public function getProxyUrl() {
        if (!$this->current_proxy) {
            $this->getRandomProxy();
        }
        
        if ($this->current_proxy) {
            return $this->current_proxy['username'] . ':' . 
                   $this->current_proxy['password'] . '@' . 
                   $this->current_proxy['ip'] . ':' . 
                   $this->current_proxy['port'];
        }
        
        return '';
    }
    
    /**
     * Test if current proxy is working
     * 
     * @return bool Whether proxy is working
     */
    public function testProxy() {
        if (!$this->current_proxy) {
            $this->getRandomProxy();
        }
        
        if (!$this->current_proxy) {
            return false;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXY, $this->current_proxy['ip'] . ':' . $this->current_proxy['port']);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->current_proxy['username'] . ':' . $this->current_proxy['password']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $result = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        
        return !$error && !empty($result);
    }
    
    /**
     * Get a working proxy (tests and rotates as needed)
     * 
     * @param int $max_attempts Maximum number of proxies to try
     * @return array|null Working proxy or null if none found
     */
    public function getWorkingProxy($max_attempts = 3) {
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            $this->getRandomProxy();
            
            if ($this->testProxy()) {
                return $this->current_proxy;
            }
            
            $attempts++;
        }
        
        return null;
    }
    
    /**
     * Configure a cURL handle with the current proxy
     * 
     * @param resource $ch cURL handle
     * @return resource Configured cURL handle
     */
    public function configureCurl($ch) {
        if (!$this->current_proxy) {
            $this->getWorkingProxy();
        }
        
        if ($this->current_proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->current_proxy['ip'] . ':' . $this->current_proxy['port']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->current_proxy['username'] . ':' . $this->current_proxy['password']);
        }
        
        return $ch;
    }
    
    /**
     * Parse a proxy string into components
     * 
     * @param string $proxy_string Proxy string (ip:port:username:password)
     * @return array|null Proxy components or null if invalid
     */
    public static function parseProxyString($proxy_string) {
        $components = explode(':', $proxy_string);
        
        if (count($components) === 4) {
            return [
                'ip' => $components[0],
                'port' => $components[1],
                'username' => $components[2],
                'password' => $components[3]
            ];
        } elseif (count($components) === 2) {
            return [
                'ip' => $components[0],
                'port' => $components[1],
                'username' => null,
                'password' => null
            ];
        }
        
        return null;
    }
}
?>
