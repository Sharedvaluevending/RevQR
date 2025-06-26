/**
 * Unified Spinwheel Component
 * Handles all spinwheel functionality across the application
 */

class SpinWheel {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            console.error(`Canvas with id '${canvasId}' not found`);
            return;
        }
        
        this.ctx = this.canvas.getContext('2d');
        this.centerX = this.canvas.width / 2;
        this.centerY = this.canvas.height / 2;
        this.radius = Math.min(this.centerX, this.centerY) - 20;
        
        // Configuration
        this.options = {
            rewards: [],
            colors: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF9F40', '#C9CBCF'],
            textColor: '#fff',
            textFont: 'bold 14px Arial',
            spinDuration: 4000,
            spinRotations: 8,
            onSpinComplete: null,
            interactive: true,
            ...options
        };
        
        this.rotation = 0;
        this.spinning = false;
        this.animationId = null;
        
        this.init();
    }
    
    init() {
        this.setRewards(this.options.rewards);
        this.draw();
        
        if (this.options.interactive) {
            this.setupInteraction();
        }
    }
    
    setRewards(rewards) {
        this.rewards = rewards || [];
        this.sliceAngle = this.rewards.length > 0 ? (2 * Math.PI) / this.rewards.length : 0;
    }
    
    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        if (this.rewards.length === 0) {
            this.drawEmptyWheel();
            return;
        }
        
        // Draw wheel segments
        this.rewards.forEach((reward, index) => {
            this.drawSegment(reward, index);
        });
        
        // Draw center circle
        this.drawCenter();
        
        // Draw pointer
        this.drawPointer();
    }
    
    drawSegment(reward, index) {
        const startAngle = index * this.sliceAngle + this.rotation;
        const endAngle = startAngle + this.sliceAngle;
        
        // Draw segment
        this.ctx.beginPath();
        this.ctx.moveTo(this.centerX, this.centerY);
        this.ctx.arc(this.centerX, this.centerY, this.radius, startAngle, endAngle);
        this.ctx.closePath();
        
        // Fill with color
        const colorIndex = index % this.options.colors.length;
        this.ctx.fillStyle = this.options.colors[colorIndex];
        this.ctx.fill();
        
        // Add border
        this.ctx.strokeStyle = '#fff';
        this.ctx.lineWidth = 2;
        this.ctx.stroke();
        
        // Draw text
        this.drawSegmentText(reward, startAngle, endAngle);
    }
    
    drawSegmentText(reward, startAngle, endAngle) {
        const textAngle = startAngle + this.sliceAngle / 2;
        const textRadius = this.radius * 0.7;
        
        this.ctx.save();
        this.ctx.translate(this.centerX, this.centerY);
        this.ctx.rotate(textAngle);
        
        this.ctx.fillStyle = this.options.textColor;
        this.ctx.font = this.options.textFont;
        this.ctx.textAlign = 'right';
        this.ctx.textBaseline = 'middle';
        
        // Truncate text if too long
        let text = reward.name || reward.prize_won || 'Prize';
        if (text.length > 12) {
            text = text.substring(0, 10) + '...';
        }
        
        this.ctx.fillText(text, textRadius, 0);
        this.ctx.restore();
    }
    
    drawCenter() {
        this.ctx.beginPath();
        this.ctx.arc(this.centerX, this.centerY, 20, 0, 2 * Math.PI);
        this.ctx.fillStyle = '#fff';
        this.ctx.fill();
        this.ctx.strokeStyle = '#333';
        this.ctx.lineWidth = 2;
        this.ctx.stroke();
    }
    
    drawPointer() {
        const pointerSize = 15;
        this.ctx.beginPath();
        this.ctx.moveTo(this.centerX, this.centerY - this.radius - 10);
        this.ctx.lineTo(this.centerX - pointerSize, this.centerY - this.radius + 10);
        this.ctx.lineTo(this.centerX + pointerSize, this.centerY - this.radius + 10);
        this.ctx.closePath();
        
        this.ctx.fillStyle = '#FFD700';
        this.ctx.fill();
        this.ctx.strokeStyle = '#333';
        this.ctx.lineWidth = 2;
        this.ctx.stroke();
    }
    
    drawEmptyWheel() {
        // Draw empty circle
        this.ctx.beginPath();
        this.ctx.arc(this.centerX, this.centerY, this.radius, 0, 2 * Math.PI);
        this.ctx.fillStyle = '#f8f9fa';
        this.ctx.fill();
        this.ctx.strokeStyle = '#dee2e6';
        this.ctx.lineWidth = 2;
        this.ctx.stroke();
        
        // Draw "No Prizes" text
        this.ctx.fillStyle = '#6c757d';
        this.ctx.font = 'bold 16px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        this.ctx.fillText('No Prizes Available', this.centerX, this.centerY);
        
        this.drawCenter();
    }
    
    setupInteraction() {
        this.canvas.addEventListener('click', () => {
            if (!this.spinning && this.rewards.length > 0) {
                this.spin();
            }
        });
        
        // Add hover effect
        this.canvas.style.cursor = 'pointer';
        this.canvas.addEventListener('mouseenter', () => {
            if (!this.spinning) {
                this.canvas.style.transform = 'scale(1.02)';
                this.canvas.style.transition = 'transform 0.2s ease';
            }
        });
        
        this.canvas.addEventListener('mouseleave', () => {
            this.canvas.style.transform = 'scale(1)';
        });
    }
    
    spin(targetReward = null) {
        if (this.spinning || this.rewards.length === 0) return;
        
        this.spinning = true;
        this.canvas.style.cursor = 'wait';
        
        const startTime = Date.now();
        const startRotation = this.rotation;
        
        // Calculate target angle if specific reward is provided
        let targetAngle = this.options.spinRotations * 2 * Math.PI;
        if (targetReward !== null) {
            const rewardIndex = this.rewards.findIndex(r => 
                r.name === targetReward || r.id === targetReward
            );
            if (rewardIndex !== -1) {
                const rewardAngle = rewardIndex * this.sliceAngle + this.sliceAngle / 2;
                targetAngle += (2 * Math.PI - rewardAngle);
            }
        } else {
            targetAngle += Math.random() * 2 * Math.PI;
        }
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / this.options.spinDuration, 1);
            
            // Easing function for smooth deceleration
            const easeOut = (t) => 1 - Math.pow(1 - t, 3);
            
            this.rotation = startRotation + (targetAngle * easeOut(progress));
            this.draw();
            
            if (progress < 1) {
                this.animationId = requestAnimationFrame(animate);
            } else {
                this.onSpinComplete();
            }
        };
        
        this.animationId = requestAnimationFrame(animate);
    }
    
    onSpinComplete() {
        this.spinning = false;
        this.canvas.style.cursor = 'pointer';
        
        // Calculate winning segment
        const normalizedRotation = (this.rotation % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI);
        const pointerAngle = (2 * Math.PI - normalizedRotation) % (2 * Math.PI);
        const winningIndex = Math.floor(pointerAngle / this.sliceAngle) % this.rewards.length;
        const winningReward = this.rewards[winningIndex];
        
        if (this.options.onSpinComplete && typeof this.options.onSpinComplete === 'function') {
            this.options.onSpinComplete(winningReward, winningIndex);
        }
        
        // Highlight winning segment briefly
        this.highlightWinningSegment(winningIndex);
    }
    
    highlightWinningSegment(index) {
        const originalColors = [...this.options.colors];
        
        // Flash the winning segment
        let flashCount = 0;
        const flashInterval = setInterval(() => {
            if (flashCount % 2 === 0) {
                this.options.colors[index % this.options.colors.length] = '#FFD700';
            } else {
                this.options.colors[index % this.options.colors.length] = originalColors[index % originalColors.length];
            }
            
            this.draw();
            flashCount++;
            
            if (flashCount >= 6) {
                clearInterval(flashInterval);
                this.options.colors = originalColors;
                this.draw();
            }
        }, 200);
    }
    
    updateRewards(newRewards) {
        this.setRewards(newRewards);
        this.draw();
    }
    
    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        
        // Remove event listeners
        this.canvas.replaceWith(this.canvas.cloneNode(true));
    }
}

// Utility function to create a spin wheel
window.createSpinWheel = function(canvasId, rewards, options = {}) {
    return new SpinWheel(canvasId, {
        rewards: rewards,
        ...options
    });
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SpinWheel;
} 