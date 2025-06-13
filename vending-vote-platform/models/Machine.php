<?php

class VotingList {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($data) {
        $sql = "INSERT INTO voting_lists (business_id, name, description, created_at) 
                VALUES (:business_id, :name, :description, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'business_id' => $data['business_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null
        ]);
        return $this->pdo->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE voting_lists 
                SET name = :name,
                    description = :description
                WHERE id = :id AND business_id = :business_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'business_id' => $data['business_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null
        ]);
    }
    
    public function delete($id, $business_id) {
        // First delete all associated items
        $sql = "DELETE FROM voting_list_items WHERE voting_list_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        // Finally delete the voting list
        $sql = "DELETE FROM voting_lists WHERE id = :id AND business_id = :business_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id, 'business_id' => $business_id]);
    }
    
    public function getById($id, $business_id) {
        $sql = "SELECT * FROM voting_lists WHERE id = :id AND business_id = :business_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'business_id' => $business_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getByBusiness($business_id) {
        $sql = "SELECT * FROM voting_lists WHERE business_id = :business_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['business_id' => $business_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getItems($voting_list_id) {
        $sql = "SELECT * FROM voting_list_items WHERE voting_list_id = :voting_list_id ORDER BY item_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['voting_list_id' => $voting_list_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // The QR code and campaign methods are not migrated, as they depend on other tables/logic.
    // Add similar methods here if you migrate QR code logic to the new schema.
} 