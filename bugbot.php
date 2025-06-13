<?php
echo "🤖 BUG BOT - AUTOMATED ISSUE DETECTION\n";
echo "=====================================\n\n";

require_once 'html/core/config.php';

class BugBot {
    private $pdo;
    private $issues = [];
    private $fixes = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function runFullAnalysis() {
        echo "🔍 Starting comprehensive bug analysis...\n\n";
        
        $this->checkPhpSyntax();
        $this->analyzeLogs();
        $this->checkDatabaseIssues();
        $this->checkCommonBugs();
        $this->generateReport();
    }
    
    private function checkPhpSyntax() {
        echo "📝 CHECKING PHP SYNTAX ERRORS\n";
        echo "==============================\n";
        
        $php_files = [];
        $this->findPhpFiles('html', $php_files);
        $this->findPhpFiles('api', $php_files);
        
        $syntax_errors = 0;
        foreach ($php_files as $file) {
            $output = [];
            $return_var = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
            
            if ($return_var !== 0) {
                $syntax_errors++;
                $this->issues[] = [
                    'type' => 'SYNTAX_ERROR',
                    'file' => $file,
                    'message' => implode("\n", $output),
                    'severity' => 'HIGH'
                ];
                echo "❌ SYNTAX ERROR: $file\n";
                echo "   " . implode("\n   ", $output) . "\n\n";
            }
        }
        
        if ($syntax_errors === 0) {
            echo "✅ No PHP syntax errors found\n\n";
        } else {
            echo "🚨 Found $syntax_errors PHP syntax errors\n\n";
        }
    }
    
    private function findPhpFiles($dir, &$files) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->findPhpFiles($path, $files);
            } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                $files[] = $path;
            }
        }
    }
    
    private function analyzeLogs() {
        echo "📋 ANALYZING ERROR LOGS\n";
        echo "=======================\n";
        
        $log_files = [
            'html/logs/php-error.log',
            'html/logs/cron-weekly-reset.log',
            'logs/cron-weekly-reset.log'
        ];
        
        $error_patterns = [
            'SQLSTATE\[42000\].*sql_mode=only_full_group_by' => 'SQL GROUP BY Error',
            'Undefined array key "user_id"' => 'Missing User ID Check',
            'Column not found.*i\.name' => 'Missing Table Column',
            'Call to undefined function' => 'Missing Function',
            'Fatal error' => 'Fatal PHP Error'
        ];
        
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                echo "📄 Checking $log_file...\n";
                $content = file_get_contents($log_file);
                
                foreach ($error_patterns as $pattern => $description) {
                    if (preg_match_all("/$pattern/i", $content, $matches)) {
                        $count = count($matches[0]);
                        echo "   ⚠️  $description: $count occurrences\n";
                        
                        $this->issues[] = [
                            'type' => 'LOG_ERROR',
                            'file' => $log_file,
                            'pattern' => $description,
                            'count' => $count,
                            'severity' => ($count > 10) ? 'HIGH' : 'MEDIUM'
                        ];
                    }
                }
            }
        }
        echo "\n";
    }
    
    private function checkDatabaseIssues() {
        echo "🗄️  CHECKING DATABASE ISSUES\n";
        echo "============================\n";
        
        // Check for common SQL issues
        $common_queries = [
            'Community Activity Query' => "
                SELECT u.username, COUNT(*) as activity_count
                FROM users u
                LEFT JOIN votes v ON u.id = v.user_id
                GROUP BY u.id, u.username
                LIMIT 1
            ",
            'Weekly Reset Query' => "
                SELECT i.name, COUNT(*)
                FROM items i
                WHERE i.id = 1
                GROUP BY i.id
                LIMIT 1
            "
        ];
        
        foreach ($common_queries as $name => $query) {
            try {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute();
                echo "✅ $name: OK\n";
            } catch (Exception $e) {
                echo "❌ $name: " . $e->getMessage() . "\n";
                $this->issues[] = [
                    'type' => 'DATABASE_QUERY',
                    'query' => $name,
                    'error' => $e->getMessage(),
                    'severity' => 'HIGH'
                ];
            }
        }
        echo "\n";
    }
    
    private function checkCommonBugs() {
        echo "🐛 CHECKING COMMON CODE PATTERNS\n";
        echo "================================\n";
        
        // Check for common bug patterns in key files
        $files_to_check = [
            'html/user/dashboard.php',
            'html/api/community-activity.php',
            'html/cron/weekly-reset.php'
        ];
        
        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Check for undefined array access
                if (preg_match('/\$_SESSION\[[\'"]\w+[\'\"]\]/', $content)) {
                    if (!preg_match('/isset\(\$_SESSION\[[\'"]\w+[\'\"]\]\)/', $content)) {
                        echo "⚠️  $file: Potential undefined array access\n";
                        $this->issues[] = [
                            'type' => 'CODE_PATTERN',
                            'file' => $file,
                            'issue' => 'Undefined array access',
                            'severity' => 'MEDIUM'
                        ];
                    }
                }
                
                // Check for SQL injection vulnerabilities
                if (preg_match('/\$pdo->query\([^?].*\$/', $content)) {
                    echo "⚠️  $file: Potential SQL injection vulnerability\n";
                    $this->issues[] = [
                        'type' => 'SECURITY',
                        'file' => $file,
                        'issue' => 'Potential SQL injection',
                        'severity' => 'HIGH'
                    ];
                }
            }
        }
        echo "\n";
    }
    
    private function generateReport() {
        echo "📊 BUG BOT ANALYSIS REPORT\n";
        echo "==========================\n\n";
        
        $high_priority = array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'HIGH';
        });
        
        $medium_priority = array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'MEDIUM';
        });
        
        echo "🚨 HIGH PRIORITY ISSUES: " . count($high_priority) . "\n";
        foreach ($high_priority as $issue) {
            echo "   • {$issue['type']}: ";
            if (isset($issue['file'])) echo $issue['file'] . " - ";
            if (isset($issue['message'])) echo $issue['message'];
            if (isset($issue['pattern'])) echo $issue['pattern'] . " ({$issue['count']} times)";
            if (isset($issue['issue'])) echo $issue['issue'];
            echo "\n";
        }
        
        echo "\n⚠️  MEDIUM PRIORITY ISSUES: " . count($medium_priority) . "\n";
        foreach ($medium_priority as $issue) {
            echo "   • {$issue['type']}: ";
            if (isset($issue['file'])) echo $issue['file'] . " - ";
            if (isset($issue['issue'])) echo $issue['issue'];
            if (isset($issue['pattern'])) echo $issue['pattern'] . " ({$issue['count']} times)";
            echo "\n";
        }
        
        echo "\n💡 SUGGESTED FIXES:\n";
        echo "==================\n";
        
        if (count($high_priority) > 0) {
            echo "1. Fix SQL GROUP BY errors by adding proper GROUP BY clauses\n";
            echo "2. Add isset() checks before accessing \$_SESSION variables\n";
            echo "3. Update database schema to include missing columns\n";
            echo "4. Use prepared statements for all SQL queries\n";
        }
        
        if (count($medium_priority) > 0) {
            echo "5. Add error handling for array access\n";
            echo "6. Implement logging for debugging\n";
        }
        
        echo "\n🎯 TOTAL ISSUES FOUND: " . count($this->issues) . "\n";
        
        if (count($this->issues) === 0) {
            echo "🎉 CONGRATULATIONS! No major issues detected!\n";
        }
    }
}

// Run the bug bot
try {
    $bugBot = new BugBot($pdo);
    $bugBot->runFullAnalysis();
} catch (Exception $e) {
    echo "❌ BugBot Error: " . $e->getMessage() . "\n";
}

echo "\n🤖 BugBot analysis complete!\n";
echo "💾 Run this script regularly to keep your code healthy.\n";
?> 