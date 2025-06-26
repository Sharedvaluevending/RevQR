<?php
/**
 * Race Results Entry Interface
 * Drag and drop interface for setting finishing order
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require admin role
require_role('admin');

$race_id = intval($_GET['race_id'] ?? 0);

if (!$race_id) {
    header('Location: process-race-results.php');
    exit;
}

// Get race details
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    WHERE br.id = ? AND br.status = 'active'
");
$stmt->execute([$race_id]);
$race = $stmt->fetch();

if (!$race) {
    header('Location: process-race-results.php?error=race_not_found');
    exit;
}

// Get horses in this race with custom or default jockey assignments
$stmt = $pdo->prepare("
    SELECT rh.*, vli.item_name, 
           COALESCE(ija.custom_jockey_name, ja.jockey_name, 'Wild Card Willie') as jockey_name,
           COALESCE(ija.custom_jockey_avatar_url, ja.jockey_avatar_url, '/horse-racing/assets/img/jockeys/jockey-other.png') as jockey_avatar_url,
           COALESCE(ija.custom_jockey_color, ja.jockey_color, '#6f42c1') as jockey_color,
           COALESCE(hpc.performance_score, 50) as performance_score,
           COALESCE(hpc.units_sold_24h, 0) as sales_24h
    FROM race_horses rh
    JOIN voting_list_items vli ON rh.item_id = vli.id
    LEFT JOIN horse_performance_cache hpc ON vli.id = hpc.item_id 
        AND hpc.cache_date = CURDATE()
    LEFT JOIN item_jockey_assignments ija ON vli.id = ija.item_id 
        AND ija.business_id = ?
    LEFT JOIN jockey_assignments ja ON LOWER(vli.item_category) = ja.item_type
    WHERE rh.race_id = ?
    ORDER BY rh.id ASC
");
$stmt->execute([$race['business_id'], $race_id]);
$horses = $stmt->fetchAll();

// Get total bets for this race
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bets,
        SUM(bet_amount_qr_coins) as total_wagered,
        COUNT(DISTINCT user_id) as unique_bettors,
        bet_type,
        COUNT(*) as type_count
    FROM race_bets 
    WHERE race_id = ? AND status = 'pending'
    GROUP BY bet_type
");
$stmt->execute([$race_id]);
$betting_stats = $stmt->fetchAll();

$total_bets = 0;
$total_wagered = 0;
$unique_bettors = 0;

foreach ($betting_stats as $stat) {
    $total_bets += $stat['type_count'];
    $total_wagered += $stat['total_wagered'];
    if ($stat['unique_bettors'] > $unique_bettors) {
        $unique_bettors = $stat['unique_bettors'];
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.results-header {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.horse-item {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
    cursor: grab;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.horse-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

.horse-item.dragging {
    opacity: 0.5;
    cursor: grabbing;
}

.finish-zone {
    background: rgba(40, 167, 69, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 2px dashed rgba(40, 167, 69, 0.3) !important;
    border-radius: 16px !important;
    min-height: 400px;
    color: #ffffff;
    transition: all 0.3s ease;
}

.finish-zone.drag-over {
    border-color: rgba(40, 167, 69, 0.8) !important;
    background: rgba(40, 167, 69, 0.2) !important;
}

.position-slot {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    min-height: 80px;
    transition: all 0.3s ease;
}

.position-slot.occupied {
    background: rgba(40, 167, 69, 0.2) !important;
    border-color: rgba(40, 167, 69, 0.5) !important;
}

.position-1 .position-number { color: #ffd700; }
.position-2 .position-number { color: #c0c0c0; }
.position-3 .position-number { color: #cd7f32; }

.betting-summary {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
}

.jockey-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid white;
    background-size: cover;
    background-position: center;
}

.performance-indicator {
    height: 6px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    overflow: hidden;
}

.performance-fill {
    height: 100%;
    background: linear-gradient(45deg, #28a745, #20c997);
}
</style>

<div class="results-header text-center">
    <div class="container">
        <h1 class="display-4 mb-3">🏆 Enter Race Results</h1>
        <p class="lead"><?php echo htmlspecialchars($race['race_name']); ?></p>
        <small class="text-white-50"><?php echo htmlspecialchars($race['business_name']); ?></small>
    </div>
</div>

<div class="container">
    <!-- Betting Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="betting-summary p-4">
                <h5 class="mb-3">📊 Race Betting Summary</h5>
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="mb-1 text-warning"><?php echo $total_bets; ?></h3>
                        <small>Total Bets</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-1 text-warning"><?php echo number_format($total_wagered); ?></h3>
                        <small>QR Coins Wagered</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-1 text-warning"><?php echo $unique_bettors; ?></h3>
                        <small>Unique Bettors</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-1 text-warning"><?php echo count($horses); ?></h3>
                        <small>Horses Racing</small>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Bet Types:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($betting_stats as $stat): ?>
                                <span class="badge bg-info">
                                    <?php echo ucfirst($stat['bet_type']); ?>: <?php echo $stat['type_count']; ?> bets
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Available Horses -->
        <div class="col-md-6">
            <h3 style="color: #fff;" class="mb-4">🐎 Racing Horses</h3>
            <div id="horses-pool">
                <?php foreach ($horses as $horse): ?>
                    <div class="horse-item p-3" draggable="true" data-horse-id="<?php echo $horse['id']; ?>">
                        <div class="d-flex align-items-center">
                            <div class="jockey-avatar me-3" 
                                 style="background-image: url('<?php echo $horse['jockey_avatar_url'] ?? '../assets/img/jockey-default.png'; ?>'); border-color: <?php echo $horse['jockey_color'] ?? '#007bff'; ?>">
                            </div>
                            
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($horse['horse_name']); ?></h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($horse['machine_location'] ?? $horse['item_name']); ?>
                                    • Jockey: <?php echo htmlspecialchars($horse['jockey_name'] ?? 'TBD'); ?>
                                </small>
                                
                                <div class="performance-indicator mt-2">
                                    <div class="performance-fill" 
                                         style="width: <?php echo min(100, $horse['performance_score']); ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    Performance: <?php echo round($horse['performance_score']); ?>% 
                                    • Sales: <?php echo $horse['sales_24h']; ?> units
                                </small>
                            </div>
                            
                            <div class="text-end">
                                <i class="bi bi-grip-vertical text-muted"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Finish Order -->
        <div class="col-md-6">
            <h3 style="color: #fff;" class="mb-4">🏁 Finishing Order</h3>
            <div class="finish-zone p-4" id="finish-zone">
                <div class="text-center mb-4">
                    <i class="bi bi-trophy display-4 text-muted"></i>
                    <p class="text-muted">Drag horses here in finishing order</p>
                </div>
                
                <div id="finish-positions">
                    <?php for ($i = 1; $i <= count($horses); $i++): ?>
                        <div class="position-slot position-<?php echo $i; ?>" data-position="<?php echo $i; ?>">
                            <div class="d-flex align-items-center">
                                <div class="position-number fw-bold fs-4 me-3"><?php echo $i; ?></div>
                                <div class="position-content flex-grow-1">
                                    <div class="text-muted">Position <?php echo $i; ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="mt-4">
                <button id="process-results" class="btn btn-success btn-lg w-100" disabled>
                    <i class="bi bi-trophy"></i> Process Race Results & Calculate Payouts
                </button>
                <small class="text-muted d-block mt-2">
                    This will calculate payouts for Win, Place, Show, Exacta, Quinella, Trifecta, and Superfecta bets
                </small>
            </div>
        </div>
    </div>
</div>

<script>
let finishingOrder = [];
let draggedHorse = null;

// Drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
    const horsesPool = document.getElementById('horses-pool');
    const finishZone = document.getElementById('finish-zone');
    const processButton = document.getElementById('process-results');
    
    // Horse dragging
    document.querySelectorAll('.horse-item').forEach(horse => {
        horse.addEventListener('dragstart', function(e) {
            draggedHorse = this;
            this.classList.add('dragging');
        });
        
        horse.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            draggedHorse = null;
        });
    });
    
    // Position slot drop zones
    document.querySelectorAll('.position-slot').forEach(slot => {
        slot.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        slot.addEventListener('drop', function(e) {
            e.preventDefault();
            if (draggedHorse && !this.classList.contains('occupied')) {
                const position = parseInt(this.dataset.position);
                const horseId = draggedHorse.dataset.horseId;
                const horseName = draggedHorse.querySelector('h6').textContent;
                
                // Add horse to position
                this.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="position-number fw-bold fs-4 me-3">${position}</div>
                        <div class="position-content flex-grow-1">
                            <div class="fw-bold">${horseName}</div>
                            <small class="text-muted">Position ${position}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-light remove-horse" onclick="removeHorse(${position})">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                `;
                this.classList.add('occupied');
                
                // Hide from horses pool
                draggedHorse.style.display = 'none';
                
                // Update finishing order
                finishingOrder[position - 1] = horseId;
                updateProcessButton();
            }
        });
    });
    
    // Finish zone for general dropping
    finishZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    
    finishZone.addEventListener('dragleave', function(e) {
        this.classList.remove('drag-over');
    });
    
    finishZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        // Find next available position
        const availableSlot = document.querySelector('.position-slot:not(.occupied)');
        if (availableSlot && draggedHorse) {
            availableSlot.dispatchEvent(new Event('drop'));
        }
    });
    
    // Process results
    processButton.addEventListener('click', function() {
        if (finishingOrder.filter(id => id !== null && id !== undefined).length === <?php echo count($horses); ?>) {
            const confirmMsg = `Are you sure you want to process the race results?\n\nThis will:\n- Calculate payouts for all bet types\n- Transfer winnings to users\n- Mark the race as completed\n\nThis action cannot be undone.`;
            
            if (confirm(confirmMsg)) {
                processRaceResults();
            }
        }
    });
});

function removeHorse(position) {
    const slot = document.querySelector(`[data-position="${position}"]`);
    const horseId = finishingOrder[position - 1];
    
    // Reset slot
    slot.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="position-number fw-bold fs-4 me-3">${position}</div>
            <div class="position-content flex-grow-1">
                <div class="text-muted">Position ${position}</div>
            </div>
        </div>
    `;
    slot.classList.remove('occupied');
    
    // Show horse back in pool
    const horse = document.querySelector(`[data-horse-id="${horseId}"]`);
    if (horse) {
        horse.style.display = 'block';
    }
    
    // Remove from finishing order
    finishingOrder[position - 1] = null;
    updateProcessButton();
}

function updateProcessButton() {
    const processButton = document.getElementById('process-results');
    const completedPositions = finishingOrder.filter(id => id !== null && id !== undefined).length;
    const totalHorses = <?php echo count($horses); ?>;
    
    if (completedPositions === totalHorses) {
        processButton.disabled = false;
        processButton.innerHTML = '<i class="bi bi-trophy"></i> Process Race Results & Calculate Payouts';
    } else {
        processButton.disabled = true;
        processButton.innerHTML = `<i class="bi bi-clock"></i> Place ${totalHorses - completedPositions} more horses`;
    }
}

function processRaceResults() {
    const processButton = document.getElementById('process-results');
    processButton.disabled = true;
    processButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    
    // Filter out null values and ensure order is correct
    const cleanFinishingOrder = finishingOrder.filter(id => id !== null && id !== undefined);
    
    fetch('process-race-results.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `race_id=<?php echo $race_id; ?>&finishing_order=${JSON.stringify(cleanFinishingOrder)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Race results processed successfully!\n\nPayouts have been calculated and distributed.');
            window.location.href = '../admin/horse-racing/index.php';
        } else {
            alert('❌ Error processing results: ' + data.message);
            processButton.disabled = false;
            processButton.innerHTML = '<i class="bi bi-trophy"></i> Process Race Results & Calculate Payouts';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error occurred. Please try again.');
        processButton.disabled = false;
        processButton.innerHTML = '<i class="bi bi-trophy"></i> Process Race Results & Calculate Payouts';
    });
}

// Auto-save finishing order to localStorage
function saveFinishingOrder() {
    localStorage.setItem(`race_${<?php echo $race_id; ?>}_finishing_order`, JSON.stringify(finishingOrder));
}

// Load finishing order from localStorage
function loadFinishingOrder() {
    const saved = localStorage.getItem(`race_${<?php echo $race_id; ?>}_finishing_order`);
    if (saved) {
        finishingOrder = JSON.parse(saved);
        // Restore UI state if needed
    }
}

// Initialize
loadFinishingOrder();
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 