<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Spin Wheel Management</h1>
            <p class="text-muted">Preview, test, and manage your engagement spin wheel and rewards.</p>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card gradient-card-primary shadow-lg h-100">
                <div class="card-body text-center position-relative">
                    <h4 class="mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Live Wheel Preview</h4>
                    <div id="business-wheel-container" class="spin-wheel-container mb-3 mx-auto position-relative" style="max-width:320px;">
                        <canvas id="business-prize-wheel" width="300" height="300"></canvas>
                        <div id="wheel-pointer" style="position:absolute; left:50%; top:0; transform:translate(-50%,-30%); z-index:2;">
                            <svg width="40" height="40" viewBox="0 0 40 40">
                                <polygon points="20,0 30,30 10,30" fill="#FFD700" stroke="#333" stroke-width="2"/>
                            </svg>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-lg" id="test-spin-btn">
                        <i class="bi bi-play-circle me-2"></i>Test Spin (Simulate)
                    </button>
                    <div id="test-spin-result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Place these scripts just before the closing </body> or at the end of the file -->
<script src="https://cdn.jsdelivr.net/npm/winwheel@2.8.0/dist/Winwheel.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script>
const rewards = <?php echo json_encode($rewards); ?>;
const sliceColors = [
    ['#232526', '#414345'], // Gradient 1
    ['#2c3e50', '#4b6cb7'], // Gradient 2
    ['#434343', '#232526'], // Gradient 3
    ['#232526', '#2c3e50'], // Gradient 4
    ['#1a2980', '#26d0ce'], // Gradient 5
    ['#283e51', '#485563'], // Gradient 6
];

// Build segments for Winwheel
const segments = rewards.map((reward, i) => ({
    fillStyle: sliceColors[i % sliceColors.length][0], // Use first color for now
    text: reward.name,
    textFillStyle: '#fff',
    textFontSize: 16,
    // Optionally, add image: reward.image_url
}));

// Create the wheel
const theWheel = new Winwheel({
    'canvasId': 'business-prize-wheel',
    'numSegments': segments.length,
    'segments': segments,
    'outerRadius': 130,
    'innerRadius': 30,
    'textAlignment': 'outer',
    'animation': {
        'type': 'spinToStop',
        'duration': 5,
        'spins': 8,
        'callbackFinished': displayResult
    }
});

// Spin button logic
const spinBtn = document.getElementById('test-spin-btn');
spinBtn.addEventListener('click', function() {
    theWheel.stopAnimation(false);
    theWheel.rotationAngle = 0;
    theWheel.draw();
    theWheel.startAnimation();
});

function displayResult(indicatedSegment) {
    document.getElementById('test-spin-result').innerHTML =
        `<div class='alert alert-success mt-3'><i class='bi bi-gift-fill me-2'></i>Simulated Prize: <strong>${indicatedSegment.text}</strong></div>`;
}
</script> 