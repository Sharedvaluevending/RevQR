<?php
require_once __DIR__ . '/../core/config.php';

try {
    // Update Ghost Pepper Chips to Odd or Unique Items
    $stmt = $pdo->prepare("
        UPDATE voting_list_items 
        SET item_category = 'Odd or Unique Items (Novelty & Imports)' 
        WHERE item_name LIKE '%Ghost Pepper%'
    ");
    
    $result = $stmt->execute();
    
    if ($result) {
        echo "Successfully updated Ghost Pepper Chips category to 'Odd or Unique Items (Novelty & Imports)'\n";
    } else {
        echo "Failed to update Ghost Pepper Chips category\n";
    }

    // Update 3 Musketeers Bar to Candy and Chocolate Bars
    $stmt = $pdo->prepare("
        UPDATE voting_list_items 
        SET item_category = 'Candy and Chocolate Bars' 
        WHERE item_name LIKE '%3 Musketeers%'
    ");
    
    $result = $stmt->execute();
    
    if ($result) {
        echo "Successfully updated 3 Musketeers Bar category to 'Candy and Chocolate Bars'\n";
    } else {
        echo "Failed to update 3 Musketeers Bar category\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 