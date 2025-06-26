<?php
/**
 * Balance Validator and Fixer
 * Detects and corrects QR coin balance inconsistencies
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/qr_coin_manager.php';

class BalanceValidator {
    private $pdo;
    private $fixes_applied = [];
    private $errors_found = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Validate a user's balance consistency
     * 
     * @param int $user_id User ID to validate
     * @param bool $fix_issues Whether to automatically fix found issues
     * @return array Validation results
     */
    public function validateUserBalance($user_id, $fix_issues = false) {
        $results = [
            'user_id' => $user_id,
            'is_consistent' => true,
            'issues' => [],
            'fixes_applied' => [],
            'calculated_balance' => 0,
            'transaction_count' => 0
        ];
        
        try {
            // Get all transactions for user
            $stmt = $this->pdo->prepare("
                SELECT * FROM qr_coin_transactions 
                WHERE user_id = ? 
                ORDER BY created_at ASC, id ASC
            ");
            $stmt->execute([$user_id]);
            $transactions = $stmt->fetchAll();
            
            if (empty($transactions)) {
                $results['calculated_balance'] = 0;
                return $results;
            }
            
            $results['transaction_count'] = count($transactions);
            
            // Calculate running balance and detect issues
            $running_balance = 0;
            $negative_balance_transactions = [];
            $duplicate_references = [];
            $reference_map = [];
            
            foreach ($transactions as $transaction) {
                $running_balance += $transaction['amount'];
                
                // Check for negative balances after spending
                if ($running_balance < 0 && $transaction['amount'] < 0) {
                    $negative_balance_transactions[] = [
                        'transaction_id' => $transaction['id'],
                        'balance_after' => $running_balance,
                        'amount' => $transaction['amount'],
                        'description' => $transaction['description']
                    ];
                }
                
                // Check for duplicate reference IDs (potential double spending)
                $ref_key = $transaction['reference_type'] . ':' . $transaction['reference_id'];
                if ($transaction['reference_id'] && $transaction['reference_type']) {
                    if (isset($reference_map[$ref_key])) {
                        $duplicate_references[] = [
                            'reference' => $ref_key,
                            'transactions' => [$reference_map[$ref_key]['id'], $transaction['id']]
                        ];
                    } else {
                        $reference_map[$ref_key] = $transaction;
                    }
                }
            }
            
            $results['calculated_balance'] = $running_balance;
            
            // Report negative balance issues
            if (!empty($negative_balance_transactions)) {
                $results['is_consistent'] = false;
                $results['issues'][] = [
                    'type' => 'negative_balance',
                    'description' => 'User went into negative balance',
                    'transactions' => $negative_balance_transactions
                ];
                
                if ($fix_issues) {
                    $this->fixNegativeBalance($user_id, $negative_balance_transactions);
                    $results['fixes_applied'][] = 'negative_balance_correction';
                }
            }
            
            // Report duplicate reference issues
            if (!empty($duplicate_references)) {
                $results['is_consistent'] = false;
                $results['issues'][] = [
                    'type' => 'duplicate_references',
                    'description' => 'Multiple transactions with same reference ID',
                    'duplicates' => $duplicate_references
                ];
                
                if ($fix_issues) {
                    $this->fixDuplicateReferences($duplicate_references);
                    $results['fixes_applied'][] = 'duplicate_reference_removal';
                }
            }
            
            // Verify against QRCoinManager calculation
            $manager_balance = QRCoinManager::getBalance($user_id);
            if ($manager_balance !== $running_balance) {
                $results['is_consistent'] = false;
                $results['issues'][] = [
                    'type' => 'calculation_mismatch',
                    'description' => 'Balance mismatch between direct calculation and QRCoinManager',
                    'calculated' => $running_balance,
                    'manager_result' => $manager_balance
                ];
            }
            
        } catch (Exception $e) {
            $results['is_consistent'] = false;
            $results['issues'][] = [
                'type' => 'validation_error',
                'description' => 'Error during validation: ' . $e->getMessage()
            ];
        }
        
        return $results;
    }
    
    /**
     * Fix negative balance issues by adding corrective transactions
     */
    private function fixNegativeBalance($user_id, $negative_transactions) {
        foreach ($negative_transactions as $issue) {
            if ($issue['balance_after'] < 0) {
                $correction_amount = abs($issue['balance_after']);
                
                $result = QRCoinManager::addTransaction(
                    $user_id,
                    'adjustment',
                    'balance_correction',
                    $correction_amount,
                    "Balance correction for negative balance issue (Transaction ID: {$issue['transaction_id']})",
                    [
                        'correction_type' => 'negative_balance_fix',
                        'original_transaction_id' => $issue['transaction_id'],
                        'original_balance' => $issue['balance_after'],
                        'correction_amount' => $correction_amount
                    ]
                );
                
                if ($result['success']) {
                    $this->fixes_applied[] = "Added {$correction_amount} coins to correct negative balance";
                }
            }
        }
    }
    
    /**
     * Fix duplicate reference issues by removing duplicate transactions
     */
    private function fixDuplicateReferences($duplicates) {
        foreach ($duplicates as $duplicate) {
            // Keep the first transaction, remove subsequent ones
            $transactions_to_remove = array_slice($duplicate['transactions'], 1);
            
            foreach ($transactions_to_remove as $transaction_id) {
                // Get transaction details before removing
                $stmt = $this->pdo->prepare("SELECT * FROM qr_coin_transactions WHERE id = ?");
                $stmt->execute([$transaction_id]);
                $transaction = $stmt->fetch();
                
                if ($transaction) {
                    // Remove the duplicate transaction
                    $stmt = $this->pdo->prepare("DELETE FROM qr_coin_transactions WHERE id = ?");
                    $stmt->execute([$transaction_id]);
                    
                    $this->fixes_applied[] = "Removed duplicate transaction ID {$transaction_id} (Amount: {$transaction['amount']})";
                }
            }
        }
    }
    
    /**
     * Validate all users' balances
     * 
     * @param bool $fix_issues Whether to fix issues
     * @param int $limit Maximum users to check (0 = all)
     * @return array Summary of validation results
     */
    public function validateAllBalances($fix_issues = false, $limit = 100) {
        $summary = [
            'total_users_checked' => 0,
            'users_with_issues' => 0,
            'total_issues_found' => 0,
            'total_fixes_applied' => 0,
            'issue_types' => [],
            'users_with_problems' => []
        ];
        
        // Get users with transactions
        $sql = "
            SELECT DISTINCT user_id 
            FROM qr_coin_transactions 
            ORDER BY user_id
        ";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->query($sql);
        $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($user_ids as $user_id) {
            $validation = $this->validateUserBalance($user_id, $fix_issues);
            $summary['total_users_checked']++;
            
            if (!$validation['is_consistent']) {
                $summary['users_with_issues']++;
                $summary['total_issues_found'] += count($validation['issues']);
                $summary['total_fixes_applied'] += count($validation['fixes_applied']);
                
                $summary['users_with_problems'][] = [
                    'user_id' => $user_id,
                    'issues' => $validation['issues'],
                    'fixes' => $validation['fixes_applied']
                ];
                
                // Count issue types
                foreach ($validation['issues'] as $issue) {
                    $type = $issue['type'];
                    $summary['issue_types'][$type] = ($summary['issue_types'][$type] ?? 0) + 1;
                }
            }
        }
        
        return $summary;
    }
    
    /**
     * Emergency balance recovery for a user
     * Forces recalculation and correction of balance
     */
    public function emergencyBalanceRecovery($user_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Force recalculation from transactions
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as balance 
                FROM qr_coin_transactions 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $correct_balance = (int) $stmt->fetchColumn();
            
            // Get current manager balance
            $current_manager_balance = QRCoinManager::getBalance($user_id);
            $difference = $correct_balance - $current_manager_balance;
            
            if (abs($difference) > 0) {
                $result = QRCoinManager::addTransaction(
                    $user_id,
                    'adjustment',
                    'emergency_recovery',
                    $difference,
                    "Emergency balance recovery - correcting balance discrepancy",
                    [
                        'recovery_type' => 'emergency',
                        'calculated_balance' => $correct_balance,
                        'manager_balance' => $current_manager_balance,
                        'correction_amount' => $difference
                    ]
                );
                
                if (!$result['success']) {
                    throw new Exception('Failed to add recovery transaction: ' . $result['error']);
                }
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Balance recovery completed',
                'balance' => $correct_balance,
                'correction_applied' => $difference
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            return [
                'success' => false,
                'error' => 'Recovery failed: ' . $e->getMessage()
            ];
        }
    }
}
?> 