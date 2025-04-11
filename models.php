<?php
/**
 * Database models for the card checker application
 */

// Create connection to PostgreSQL database
function getDbConnection() {
    $dbUrl = getenv('DATABASE_URL');
    
    if (!$dbUrl) {
        throw new Exception("DATABASE_URL environment variable not set");
    }
    
    // Parse DATABASE_URL
    $dbParams = parse_url($dbUrl);
    $dbUser = $dbParams['user'];
    $dbPassword = $dbParams['pass'];
    $dbHost = $dbParams['host'];
    $dbPort = $dbParams['port'];
    $dbName = ltrim($dbParams['path'], '/');
    
    try {
        $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;user=$dbUser;password=$dbPassword";
        $conn = new PDO($dsn);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
}

// Initialize database tables if they don't exist
function initializeDatabase() {
    try {
        $conn = getDbConnection();
        
        // Create check_results table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS check_results (
                id SERIAL PRIMARY KEY,
                card_number VARCHAR(255),
                card_bin VARCHAR(10),
                expiry_month VARCHAR(2),
                expiry_year VARCHAR(4),
                cvv VARCHAR(4),
                card_type VARCHAR(50),
                card_brand VARCHAR(50),
                bank_name VARCHAR(100),
                country_code VARCHAR(5),
                status VARCHAR(20),
                response_message TEXT,
                gateway VARCHAR(50),
                ip_address VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create api_keys table for storing gateway API keys
        $conn->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id SERIAL PRIMARY KEY,
                gateway VARCHAR(50) NOT NULL,
                api_key TEXT NOT NULL,
                secret_key TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create webshare_keys table for storing Webshare API keys
        $conn->exec("
            CREATE TABLE IF NOT EXISTS webshare_keys (
                id SERIAL PRIMARY KEY,
                api_key TEXT NOT NULL UNIQUE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create proxy_usage table to track proxy usage
        $conn->exec("
            CREATE TABLE IF NOT EXISTS proxy_usage (
                id SERIAL PRIMARY KEY,
                proxy_ip VARCHAR(50),
                proxy_port VARCHAR(10),
                proxy_username VARCHAR(100),
                last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                successful_checks INTEGER DEFAULT 0,
                failed_checks INTEGER DEFAULT 0,
                webshare_key_id INTEGER REFERENCES webshare_keys(id) ON DELETE CASCADE
            )
        ");
        
        // Create statistics table to track usage
        $conn->exec("
            CREATE TABLE IF NOT EXISTS statistics (
                id SERIAL PRIMARY KEY,
                date DATE DEFAULT CURRENT_DATE,
                total_checks INTEGER DEFAULT 0,
                approved_checks INTEGER DEFAULT 0,
                declined_checks INTEGER DEFAULT 0,
                error_checks INTEGER DEFAULT 0
            )
        ");
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}

// Save a card check result to the database
function saveCheckResult($card, $status, $response, $gateway, $bin_data, $ip_address = null) {
    try {
        $conn = getDbConnection();
        
        // Parse card details
        $details = explode('|', $card);
        $cc = $details[0] ?? '';
        $month = $details[1] ?? '';
        $year = $details[2] ?? '';
        $cvv = $details[3] ?? '';
        
        // Get BIN info
        $cardBin = substr($cc, 0, 6);
        $cardType = $bin_data['type'] ?? '';
        $cardBrand = $bin_data['scheme'] ?? '';
        $bankName = $bin_data['bank'] ?? '';
        $countryCode = $bin_data['country'] ?? '';
        
        // Prepare statement
        $stmt = $conn->prepare("
            INSERT INTO check_results 
                (card_number, card_bin, expiry_month, expiry_year, cvv, 
                card_type, card_brand, bank_name, country_code, 
                status, response_message, gateway, ip_address)
            VALUES 
                (:card_number, :card_bin, :expiry_month, :expiry_year, :cvv, 
                :card_type, :card_brand, :bank_name, :country_code, 
                :status, :response_message, :gateway, :ip_address)
        ");
        
        // Mask card number for storage (save only first 6 and last 4 digits)
        $maskedCC = '';
        if (strlen($cc) >= 10) {
            $maskedCC = substr($cc, 0, 6) . str_repeat('*', strlen($cc) - 10) . substr($cc, -4);
        } else {
            $maskedCC = $cc; // Shouldn't happen but just in case
        }
        
        // Execute with parameters
        $stmt->execute([
            ':card_number' => $maskedCC,
            ':card_bin' => $cardBin,
            ':expiry_month' => $month,
            ':expiry_year' => $year,
            ':cvv' => $cvv,
            ':card_type' => $cardType,
            ':card_brand' => $cardBrand,
            ':bank_name' => $bankName, 
            ':country_code' => $countryCode,
            ':status' => $status,
            ':response_message' => $response,
            ':gateway' => $gateway,
            ':ip_address' => $ip_address
        ]);
        
        // Update statistics
        updateStatistics($status);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error saving check result: " . $e->getMessage());
        return false;
    }
}

// Update daily statistics
function updateStatistics($status) {
    try {
        $conn = getDbConnection();
        
        // Get today's date in YYYY-MM-DD format
        $today = date('Y-m-d');
        
        // Check if we have statistics for today
        $stmt = $conn->prepare("SELECT id FROM statistics WHERE date = :date");
        $stmt->execute([':date' => $today]);
        $statsId = $stmt->fetchColumn();
        
        if (!$statsId) {
            // Create new statistics record for today
            $stmt = $conn->prepare("
                INSERT INTO statistics (date, total_checks, approved_checks, declined_checks, error_checks)
                VALUES (:date, 1, :approved, :declined, :error)
            ");
            
            $approved = ($status === 'APPROVED') ? 1 : 0;
            $declined = ($status === 'DECLINED') ? 1 : 0;
            $error = ($status === 'ERROR') ? 1 : 0;
            
            $stmt->execute([
                ':date' => $today,
                ':approved' => $approved,
                ':declined' => $declined,
                ':error' => $error
            ]);
        } else {
            // Update existing statistics
            $updateField = 'total_checks';
            
            if ($status === 'APPROVED') {
                $updateField = 'approved_checks';
            } elseif ($status === 'DECLINED') {
                $updateField = 'declined_checks';
            } elseif ($status === 'ERROR') {
                $updateField = 'error_checks';
            }
            
            // Update the specific counter and total
            $stmt = $conn->prepare("
                UPDATE statistics 
                SET $updateField = $updateField + 1, total_checks = total_checks + 1
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $statsId]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating statistics: " . $e->getMessage());
        return false;
    }
}

// Save Webshare API key to database
function saveWebshareKey($apiKey) {
    try {
        $conn = getDbConnection();
        
        // Check if key already exists
        $stmt = $conn->prepare("SELECT id FROM webshare_keys WHERE api_key = :api_key");
        $stmt->execute([':api_key' => $apiKey]);
        
        if (!$stmt->fetchColumn()) {
            // Insert new key
            $stmt = $conn->prepare("
                INSERT INTO webshare_keys (api_key, is_active)
                VALUES (:api_key, TRUE)
            ");
            
            $stmt->execute([':api_key' => $apiKey]);
            return true;
        }
        
        return false; // Key already exists
        
    } catch (PDOException $e) {
        error_log("Error saving Webshare key: " . $e->getMessage());
        return false;
    }
}

// Get all Webshare API keys
function getWebshareKeys() {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->query("
            SELECT id, api_key, is_active, created_at
            FROM webshare_keys
            WHERE is_active = TRUE
            ORDER BY created_at DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error retrieving Webshare keys: " . $e->getMessage());
        return [];
    }
}

// Delete a Webshare API key
function deleteWebshareKey($apiKey) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            DELETE FROM webshare_keys
            WHERE api_key = :api_key
        ");
        
        $stmt->execute([':api_key' => $apiKey]);
        return $stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        error_log("Error deleting Webshare key: " . $e->getMessage());
        return false;
    }
}

// Save API key for a gateway
function saveGatewayApiKey($gateway, $apiKey, $secretKey = null) {
    try {
        $conn = getDbConnection();
        
        // Check if key already exists for this gateway
        $stmt = $conn->prepare("
            SELECT id FROM api_keys 
            WHERE gateway = :gateway AND api_key = :api_key
        ");
        
        $stmt->execute([
            ':gateway' => $gateway,
            ':api_key' => $apiKey
        ]);
        
        if (!$stmt->fetchColumn()) {
            // Insert new key
            $stmt = $conn->prepare("
                INSERT INTO api_keys (gateway, api_key, secret_key)
                VALUES (:gateway, :api_key, :secret_key)
            ");
            
            $stmt->execute([
                ':gateway' => $gateway,
                ':api_key' => $apiKey,
                ':secret_key' => $secretKey
            ]);
            
            return true;
        }
        
        return false; // Key already exists
        
    } catch (PDOException $e) {
        error_log("Error saving gateway API key: " . $e->getMessage());
        return false;
    }
}

// Get API keys for a specific gateway
function getGatewayApiKeys($gateway) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT id, api_key, secret_key, created_at
            FROM api_keys
            WHERE gateway = :gateway AND is_active = TRUE
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([':gateway' => $gateway]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error retrieving gateway API keys: " . $e->getMessage());
        return [];
    }
}

// Record proxy usage
function recordProxyUsage($proxyIp, $proxyPort, $proxyUsername, $webshareKeyId, $successful = true) {
    try {
        $conn = getDbConnection();
        
        // Check if proxy exists
        $stmt = $conn->prepare("
            SELECT id FROM proxy_usage 
            WHERE proxy_ip = :proxy_ip AND proxy_port = :proxy_port AND proxy_username = :proxy_username
        ");
        
        $stmt->execute([
            ':proxy_ip' => $proxyIp,
            ':proxy_port' => $proxyPort,
            ':proxy_username' => $proxyUsername
        ]);
        
        $proxyId = $stmt->fetchColumn();
        
        if (!$proxyId) {
            // Insert new proxy
            $stmt = $conn->prepare("
                INSERT INTO proxy_usage 
                    (proxy_ip, proxy_port, proxy_username, successful_checks, failed_checks, webshare_key_id)
                VALUES 
                    (:proxy_ip, :proxy_port, :proxy_username, :successful, :failed, :webshare_key_id)
            ");
            
            $stmt->execute([
                ':proxy_ip' => $proxyIp,
                ':proxy_port' => $proxyPort,
                ':proxy_username' => $proxyUsername,
                ':successful' => $successful ? 1 : 0,
                ':failed' => $successful ? 0 : 1,
                ':webshare_key_id' => $webshareKeyId
            ]);
        } else {
            // Update existing proxy
            if ($successful) {
                $stmt = $conn->prepare("
                    UPDATE proxy_usage 
                    SET successful_checks = successful_checks + 1, last_used = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
            } else {
                $stmt = $conn->prepare("
                    UPDATE proxy_usage 
                    SET failed_checks = failed_checks + 1, last_used = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
            }
            
            $stmt->execute([':id' => $proxyId]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error recording proxy usage: " . $e->getMessage());
        return false;
    }
}

// Get statistics for a specific date range
function getStatistics($startDate = null, $endDate = null) {
    try {
        $conn = getDbConnection();
        
        if (!$startDate) {
            // Default to last 7 days
            $startDate = date('Y-m-d', strtotime('-7 days'));
        }
        
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        
        $stmt = $conn->prepare("
            SELECT 
                date, total_checks, approved_checks, declined_checks, error_checks
            FROM 
                statistics
            WHERE 
                date BETWEEN :start_date AND :end_date
            ORDER BY 
                date ASC
        ");
        
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error retrieving statistics: " . $e->getMessage());
        return [];
    }
}

// Get most recent check results
function getRecentResults($limit = 100) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                id, card_number, card_bin, expiry_month, expiry_year,
                card_type, card_brand, bank_name, country_code,
                status, response_message, gateway, created_at
            FROM 
                check_results
            ORDER BY 
                created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error retrieving recent results: " . $e->getMessage());
        return [];
    }
}

// Initialize database on first load
try {
    initializeDatabase();
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}
?>