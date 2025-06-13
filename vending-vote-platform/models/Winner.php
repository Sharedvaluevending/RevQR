<?php

class Winner {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($data) {
        $sql = "INSERT INTO winners (list_id, item_id, vote_type, week_start, week_end, votes_count)
                VALUES (:list_id, :item_id, :vote_type, :week_start, :week_end, :votes_count)";
                
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'list_id' => $data['list_id'],
            'item_id' => $data['item_id'],
            'vote_type' => $data['vote_type'],
            'week_start' => $data['week_start'],
            'week_end' => $data['week_end'],
            'votes_count' => $data['votes_count']
        ]);
    }
    
    public function getByList($list_id) {
        $sql = "SELECT w.*, i.name as item_name, i.type as item_type
                FROM winners w
                JOIN voting_list_items i ON w.item_id = i.id
                WHERE w.list_id = :list_id
                ORDER BY w.week_start DESC";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['list_id' => $list_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getByWeek($list_id, $week_start) {
        $sql = "SELECT w.*, i.name as item_name, i.type as item_type
                FROM winners w
                JOIN voting_list_items i ON w.item_id = i.id
                WHERE w.list_id = :list_id
                AND w.week_start = :week_start
                ORDER BY w.votes_count DESC";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'list_id' => $list_id,
            'week_start' => $week_start
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCurrentWinners($list_id) {
        $week_start = date('Y-m-d', strtotime('monday this week'));
        return $this->getByWeek($list_id, $week_start);
    }
    
    public function getPreviousWinners($list_id, $weeks = 4) {
        $sql = "SELECT w.*, i.name as item_name, i.type as item_type
                FROM winners w
                JOIN voting_list_items i ON w.item_id = i.id
                WHERE w.list_id = :list_id
                AND w.week_start < CURDATE()
                ORDER BY w.week_start DESC
                LIMIT :weeks";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':list_id', $list_id, PDO::PARAM_INT);
        $stmt->bindValue(':weeks', $weeks, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function deleteByList($list_id) {
        $sql = "DELETE FROM winners WHERE list_id = :list_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['list_id' => $list_id]);
    }
    
    public function deleteByWeek($list_id, $week_start) {
        $sql = "DELETE FROM winners 
                WHERE list_id = :list_id 
                AND week_start = :week_start";
                
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'list_id' => $list_id,
            'week_start' => $week_start
        ]);
    }
} 