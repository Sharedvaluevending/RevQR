<?php
require_once __DIR__ . '/../core/config.php';

try {
    // First, let's see what we're working with
    $check = $pdo->query("SELECT id, name, category FROM master_items WHERE name LIKE '%Ghost Pepper%'");
    $item = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        echo "Found item: " . $item['name'] . " (ID: " . $item['id'] . ") with category: " . $item['category'] . "\n";
        
        // Update with exact ID
        $stmt = $pdo->prepare("
            UPDATE master_items 
            SET category = 'Odd or Unique Items (Novelty & Imports)' 
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$item['id']]);
        
        if ($result) {
            echo "Successfully updated category to 'Odd or Unique Items (Novelty & Imports)'\n";
            
            // Verify the update
            $verify_stmt = $pdo->prepare("SELECT category FROM master_items WHERE id = ?");
            $verify_stmt->execute([$item['id']]);
            $new_category = $verify_stmt->fetchColumn();
            echo "New category: " . $new_category . "\n";
        } else {
            echo "Failed to update category\n";
        }
    } else {
        echo "Could not find Ghost Pepper item\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 