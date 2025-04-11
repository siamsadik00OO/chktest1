<?php
/**
 * API endpoint for retrieving statistics
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

// Get statistics for a specific date range
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$stats = getStatistics($startDate, $endDate);

// Format for charting
$formattedStats = [
    'labels' => [],
    'total' => [],
    'approved' => [],
    'declined' => [],
    'error' => []
];

foreach ($stats as $day) {
    $formattedStats['labels'][] = $day['date'];
    $formattedStats['total'][] = intval($day['total_checks']);
    $formattedStats['approved'][] = intval($day['approved_checks']);
    $formattedStats['declined'][] = intval($day['declined_checks']);
    $formattedStats['error'][] = intval($day['error_checks']);
}

echo json_encode([
    'success' => true, 
    'stats' => $stats,
    'chart_data' => $formattedStats
]);
?>