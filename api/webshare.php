<?php
/**
 * API endpoint for managing Webshare API keys
 */

// Include database models
require_once '../models.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all Webshare API keys
        $keys = getWebshareKeys();
        
        // Mask keys for security
        $maskedKeys = array_map(function($key) {
            $apiKey = $key['api_key'];
            $maskedKey = substr($apiKey, 0, 4) . '****' . substr($apiKey, -4);
            
            return [
                'id' => $key['id'],
                'api_key' => $apiKey, // Full key
                'masked_key' => $maskedKey, // Masked key for display
                'created_at' => $key['created_at']
            ];
        }, $keys);
        
        echo json_encode(['success' => true, 'keys' => $maskedKeys]);
        break;
        
    case 'POST':
        // Add a new Webshare API key
        $requestData = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($requestData['api_key']) || empty($requestData['api_key'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'API key is required']);
            exit;
        }
        
        $apiKey = $requestData['api_key'];
        
        if (saveWebshareKey($apiKey)) {
            echo json_encode(['success' => true, 'message' => 'API key added successfully']);
        } else {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'API key already exists or could not be saved']);
        }
        break;
        
    case 'DELETE':
        // Delete a Webshare API key
        $requestData = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($requestData['api_key']) || empty($requestData['api_key'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'API key is required']);
            exit;
        }
        
        $apiKey = $requestData['api_key'];
        
        if (deleteWebshareKey($apiKey)) {
            echo json_encode(['success' => true, 'message' => 'API key deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'API key not found or could not be deleted']);
        }
        break;
        
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>