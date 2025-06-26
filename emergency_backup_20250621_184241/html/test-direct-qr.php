<?php
require_once __DIR__ . '/includes/QRGenerator.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

try {
    $generator = new QRGenerator();
    $result = $generator->generate($input);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?> 