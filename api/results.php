<?php
/**
 * API endpoint for retrieving check results
 */

// Include database models
require_once '../models.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get recent results
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

// Validate limit
if ($limit <= 0 || $limit > 500) {
    $limit = 100; // Default to 100 if invalid
}

$results = getRecentResults($limit);

echo json_encode(['success' => true, 'results' => $results, 'count' => count($results)]);
?>