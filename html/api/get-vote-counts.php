<?php
/**
 * Real-time Vote Count API
 * Returns current vote counts for immediate display updates
 */

require_once __DIR__ . "/../core/config.php";

header("Content-Type: application/json");

try {
    $item_id = $_GET["item_id"] ?? null;
    $campaign_id = $_GET["campaign_id"] ?? null;
    
    if (!$item_id) {
        throw new Exception("Item ID required");
    }
    
    // Get current vote counts
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN vote_type = 'vote_in' THEN 1 END) as votes_in,
            COUNT(CASE WHEN vote_type = 'vote_out' THEN 1 END) as votes_out,
            COUNT(*) as total_votes
        FROM votes 
        WHERE item_id = ?
        " . ($campaign_id ? " AND campaign_id = ?" : "") . "
    ");
    
    $params = [$item_id];
    if ($campaign_id) $params[] = $campaign_id;
    
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    echo json_encode([
        "success" => true,
        "item_id" => $item_id,
        "votes_in" => (int) $result["votes_in"],
        "votes_out" => (int) $result["votes_out"], 
        "total_votes" => (int) $result["total_votes"],
        "timestamp" => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>