<?php
require_once __DIR__ . '/../core/config.php';

try {
    // Update the Ghost Pepper Chips category
    $stmt = $pdo->prepare("
        UPDATE voting_list_items 
        SET item_category = 'snacks' 
        WHERE item_name LIKE '%Ghost Pepper%'
    ");
    
    $result = $stmt->execute();
    
    if ($result) {
        echo "Successfully updated Ghost Pepper Chips category to 'snacks'";
    } else {
        echo "Failed to update category";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 