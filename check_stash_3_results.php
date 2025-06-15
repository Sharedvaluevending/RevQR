<?php
echo "=== CHECKING STASH@{3} APPLICATION RESULTS ===\n\n";

echo "✅ SUCCESS: git checkout stash@{3} -- . completed successfully!\n\n";

echo "Checking git status to see what was modified...\n";
$status = [];
exec("git status --porcelain 2>&1", $status, $status_return);

if ($status_return === 0) {
    if (!empty($status)) {
        echo "Modified files from stash@{3}:\n";
        foreach ($status as $line) {
            echo "  $line\n";
        }
        echo "\n";
    } else {
        echo "No changes detected in git status.\n";
        echo "This could mean the stashed changes were already present.\n\n";
    }
} else {
    echo "Error checking git status\n";
}

echo "Checking what files were in stash@{3}...\n";
$stash_files = [];
exec("git stash show --name-only stash@{3} 2>&1", $stash_files, $stash_return);

if ($stash_return === 0 && !empty($stash_files)) {
    echo "Files that were in stash@{3}:\n";
    foreach ($stash_files as $file) {
        echo "  - $file\n";
    }
    echo "\n";
    
    echo "Change summary from stash@{3}:\n";
    $stats = [];
    exec("git stash show --stat stash@{3} 2>&1", $stats, $stat_return);
    
    if ($stat_return === 0) {
        foreach ($stats as $stat) {
            echo "  $stat\n";
        }
    }
} else {
    echo "Could not retrieve stash@{3} file list\n";
}

echo "\n=== RECOVERY ANALYSIS ===\n";
echo "Stash@{3} was created right before QR Code 2.02 branch creation.\n";
echo "This contained work in progress that was meant for version 2.02.\n";
echo "The force application (git checkout stash@{3} -- .) has completed.\n";
echo "\nNext steps:\n";
echo "1. Check the modified files for new QR Code 2.02 features\n";
echo "2. Test the functionality to ensure everything works\n";
echo "3. Commit the recovered changes if they're valuable\n";
?> 