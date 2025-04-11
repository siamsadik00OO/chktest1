<?php
/**
 * API endpoint for managing gateway API keys
 */

// Include database models
require_once '../models.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get API keys for a specific gateway
        if (!isset($_GET['gateway']) || empty($_GET['gateway'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Gateway parameter is required']);
            exit;
        }
        
        $gateway = $_GET['gateway'];
        $keys = getGatewayApiKeys($gateway);
        
        // Mask keys for security
        $maskedKeys = array_map(function($key) {
            $apiKey = $key['api_key'];
            $secretKey = $key['secret_key'];
            
            $maskedApiKey = substr($apiKey, 0, 4) . '****' . substr($apiKey, -4);
            $maskedSecretKey = $secretKey ? substr($secretKey, 0, 4) . '****' . substr($secretKey, -4) : null;
            
            return [
                'id' => $key['id'],
                'api_key' => $apiKey, // Full key
                'secret_key' => $secretKey, // Full secret key
                'masked_api_key' => $maskedApiKey, // Masked API key for display
                'masked_secret_key' => $maskedSecretKey, // Masked secret key for display
                'created_at' => $key['created_at']
            ];
        }, $keys);
        
        echo json_encode(['success' => true, 'keys' => $maskedKeys]);
        break;
        
    case 'POST':
        // Add a new gateway API key
        $requestData = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($requestData['gateway']) || empty($requestData['gateway'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Gateway is required']);
            exit;
        }
        
        if (!isset($requestData['api_key']) || empty($requestData['api_key'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'API key is required']);
            exit;
        }
        
        $gateway = $requestData['gateway'];
        $apiKey = $requestData['api_key'];
        $secretKey = $requestData['secret_key'] ?? null;
        
        if (saveGatewayApiKey($gateway, $apiKey, $secretKey)) {
            echo json_encode(['success' => true, 'message' => 'API key added successfully']);
        } else {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'API key already exists or could not be saved']);
        }
        break;
        
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>