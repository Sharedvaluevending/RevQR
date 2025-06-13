<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../models/Machine.php';
require_once '../models/Winner.php';

// Check if user is logged in and is a business user
if (!isLoggedIn() || !isBusinessUser()) {
    header('Location: /login.php');
    exit;
}

$machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
$machine = new Machine($pdo);
$winner = new Winner($pdo);

// Get machine details
$machine_data = $machine->getById($machine_id);

// Verify machine belongs to this business
if (!$machine_data || $machine_data['business_id'] != $_SESSION['business_id']) {
    header('Location: /business/dashboard.php');
    exit;
}

// Get current week's winners
$current_winners = $winner->getCurrentWinners($machine_id);

// Get previous winners
$previous_winners = $winner->getPreviousWinners($machine_id);

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Winners for <?php echo htmlspecialchars($machine_data['name']); ?></h1>
        <a href="manage-machines.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
            Back to Machines
        </a>
    </div>

    <!-- Current Week Winners -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Current Week Winners</h2>
        <?php if (empty($current_winners)): ?>
            <p class="text-gray-500">No winners for the current week yet.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($current_winners as $winner): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-semibold"><?php echo htmlspecialchars($winner['item_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo ucfirst($winner['item_type']); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                    <?php echo $winner['votes_count']; ?> votes
                                </span>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php echo ucfirst($winner['vote_type']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Previous Winners -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Previous Winners</h2>
        <?php if (empty($previous_winners)): ?>
            <p class="text-gray-500">No previous winners found.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                $current_week = '';
                foreach ($previous_winners as $winner): 
                    $week = date('F j, Y', strtotime($winner['week_start']));
                    if ($week !== $current_week):
                        $current_week = $week;
                ?>
                    <h3 class="font-semibold text-lg mt-4 mb-2"><?php echo $week; ?></h3>
                <?php endif; ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-semibold"><?php echo htmlspecialchars($winner['item_name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo ucfirst($winner['item_type']); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                    <?php echo $winner['votes_count']; ?> votes
                                </span>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php echo ucfirst($winner['vote_type']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../templates/footer.php'; ?> 