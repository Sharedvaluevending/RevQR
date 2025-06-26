// Custom JavaScript for RevenueQR

// Utility Functions
const utils = {
    // Show loading spinner
    showLoading: function(element) {
        const spinner = document.createElement('div');
        spinner.className = 'spinner-border loading-spinner text-primary';
        spinner.setAttribute('role', 'status');
        spinner.innerHTML = '<span class="visually-hidden">Loading...</span>';
        element.appendChild(spinner);
    },

    // Hide loading spinner
    hideLoading: function(element) {
        const spinner = element.querySelector('.spinner-border');
        if (spinner) {
            spinner.remove();
        }
    },

    // Show alert message
    showAlert: function(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    },

    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    },

    // Format date
    formatDate: function(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }
};

// Voting System
const votingSystem = {
    selectedItems: new Set(),

    init: function() {
        const voteCards = document.querySelectorAll('.vote-card');
        voteCards.forEach(card => {
            card.addEventListener('click', () => this.toggleVote(card));
        });
    },

    toggleVote: function(card) {
        const itemId = card.dataset.itemId;
        if (this.selectedItems.has(itemId)) {
            this.selectedItems.delete(itemId);
            card.classList.remove('selected');
        } else {
            this.selectedItems.add(itemId);
            card.classList.add('selected');
        }
    },

    submitVotes: async function() {
        if (this.selectedItems.size === 0) {
            utils.showAlert('Please select at least one item to vote for.', 'warning');
            return;
        }

        try {
            const response = await fetch('/user/submit-votes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    items: Array.from(this.selectedItems)
                })
            });

            const data = await response.json();
            if (data.success) {
                utils.showAlert('Votes submitted successfully!', 'success');
                this.selectedItems.clear();
                document.querySelectorAll('.vote-card.selected').forEach(card => {
                    card.classList.remove('selected');
                });
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            utils.showAlert(error.message || 'Failed to submit votes. Please try again.', 'danger');
        }
    }
};

// Spin Wheel System
const spinWheel = {
    isSpinning: false,
    currentRotation: 0,

    init: function() {
        this.wheel = document.querySelector('.spin-wheel');
        this.spinButton = document.querySelector('.spin-button');
        if (this.spinButton) {
            this.spinButton.addEventListener('click', () => this.spin());
        }
    },

    spin: function() {
        if (this.isSpinning) return;

        this.isSpinning = true;
        this.spinButton.disabled = true;

        // Random number of full rotations (3-5) plus random angle
        const rotations = 3 + Math.floor(Math.random() * 3);
        const extraDegrees = Math.floor(Math.random() * 360);
        const totalDegrees = (rotations * 360) + extraDegrees;

        this.currentRotation += totalDegrees;
        this.wheel.style.transform = `rotate(${this.currentRotation}deg)`;

        // After spin completes
        setTimeout(() => {
            this.isSpinning = false;
            this.spinButton.disabled = false;
            this.checkPrize(extraDegrees);
        }, 4000);
    },

    checkPrize: function(finalAngle) {
        // Calculate which prize was won based on final angle
        const prizeIndex = Math.floor(finalAngle / (360 / 8)); // Assuming 8 prizes
        const prize = this.getPrizeForIndex(prizeIndex);
        
        if (prize) {
            utils.showAlert(`Congratulations! You won: ${prize.name}`, 'success');
        }
    },

    getPrizeForIndex: function(index) {
        // This would typically come from the server
        const prizes = [
            { name: 'Free Item', probability: 0.4 },
            { name: 'BOGO Coupon', probability: 0.3 },
            { name: 'Hoodie', probability: 0.1 },
            { name: 'Nothing', probability: 0.2 }
        ];
        return prizes[index % prizes.length];
    }
};

// QR Code Generator
const qrGenerator = {
    init: function() {
        this.form = document.querySelector('#qr-generator-form');
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.generateQR(e));
        }
    },

    generateQR: async function(e) {
        e.preventDefault();
        const formData = new FormData(this.form);
        
        try {
            const response = await fetch('/business/generate-qr.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.displayQR(data.qrCode);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            utils.showAlert(error.message || 'Failed to generate QR code.', 'danger');
        }
    },

    displayQR: function(qrCodeUrl) {
        const container = document.querySelector('.qr-code-container');
        container.innerHTML = `
            <img src="${qrCodeUrl}" alt="Generated QR Code" class="img-fluid">
            <div class="mt-3">
                <button class="btn btn-primary" onclick="qrGenerator.downloadQR('${qrCodeUrl}')">
                    <i class="bi bi-download me-2"></i>Download
                </button>
            </div>
        `;
    },

    downloadQR: function(qrCodeUrl) {
        const link = document.createElement('a');
        link.href = qrCodeUrl;
        link.download = 'qr-code.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};

// Touch event handling for better mobile performance
document.addEventListener('touchstart', function() {}, {passive: true});

// Mobile menu handling
document.addEventListener('DOMContentLoaded', function() {
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const navbar = document.querySelector('.navbar-collapse');
        const navbarToggler = document.querySelector('.navbar-toggler');
        
        if (navbar && navbar.classList.contains('show') && 
            !navbar.contains(event.target) && 
            !navbarToggler.contains(event.target)) {
            navbar.classList.remove('show');
        }
    });

    // Prevent double-tap zoom on buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.click();
        });
    });

    // Initialize voting system if on voting page
    if (document.querySelector('.vote-card')) {
        votingSystem.init();
    }

    // Initialize spin wheel if on spin page
    if (document.querySelector('.spin-wheel')) {
        spinWheel.init();
    }

    // Initialize QR generator if on QR generator page
    if (document.querySelector('#qr-generator-form')) {
        qrGenerator.init();
    }
}); 