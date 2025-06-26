<?php
require_once __DIR__ . '/../../core/config.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Read and execute migration file
    $migration_sql = file_get_contents(__DIR__ . '/update_schema.sql');
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', 
            explode(';', $migration_sql)
        )
    );
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement) && !str_starts_with($statement, '--')) {
            echo "Executing: " . substr($statement, 0, 100) . "...\n";
            $pdo->exec($statement);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    // Only rollback if transaction is active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
} 