/**
 * Enhanced QR Balance Synchronization System
 * Ensures real-time balance updates across all pages and components
 */

class EnhancedQRBalanceManager {
    constructor() {
        this.currentBalance = 0;
        this.isUpdating = false;
        this.updateQueue = [];
        this.refreshInterval = null;
        
        this.init();
    }
    
    init() {
        // Initial balance load
        this.refresh();
        
        // Set up periodic refresh (every 30 seconds)
        this.refreshInterval = setInterval(() => {
            this.refresh();
        }, 30000);
        
        // Listen for balance change events
        this.setupEventListeners();
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refresh(); // Refresh when user returns to tab
            }
        });
        
        console.log('🪙 Enhanced QR Balance Manager initialized');
    }
    
    setupEventListeners() {
        // Listen for various balance-affecting events
        const events = [
            'vote-completed',
            'spin-completed', 
            'purchase-completed',
            'casino-win',
            'casino-loss',
            'balance-update-needed'
        ];
        
        events.forEach(eventName => {
            document.addEventListener(eventName, (e) => {
                console.log(`🔔 Received ${eventName} event`, e.detail);
                setTimeout(() => this.refresh(), 500); // Small delay to ensure server processing
            });
        });
        
        // Listen for manual refresh requests
        window.addEventListener('refresh-balance', () => {
            this.refresh();
        });
    }
    
    async refresh() {
        if (this.isUpdating) {
            return; // Prevent concurrent updates
        }
        
        this.isUpdating = true;
        
        try {
            const response = await fetch('/html/user/api/get-balance.php', {
                method: 'GET',
                cache: 'no-cache',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const newBalance = parseInt(data.balance);
                const previousBalance = this.currentBalance;
                
                if (newBalance !== previousBalance) {
                    this.currentBalance = newBalance;
                    this.updateAllDisplays(newBalance);
                    this.triggerBalanceChangeEvent(newBalance, previousBalance);
                    
                    console.log(`💰 Balance updated: ${previousBalance} → ${newBalance}`);
                }
            } else {
                console.warn('⚠️ Balance API returned error:', data.error);
            }
            
        } catch (error) {
            console.error('❌ Balance refresh failed:', error);
            // Don't show user errors for background refreshes
        } finally {
            this.isUpdating = false;
        }
    }
    
    updateAllDisplays(balance) {
        const formattedBalance = this.formatBalance(balance);
        
        // Common balance display selectors
        const selectors = [
            '[data-balance-display]',
            '#navbarQRBalance',
            '.balance-amount',
            '.qr-balance',
            '.user-balance',
            '.current-balance',
            '.qr-coin-balance'
        ];
        
        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (element) {
                    // Update text content
                    element.textContent = formattedBalance;
                    
                    // Add visual feedback
                    this.addUpdateAnimation(element);
                    
                    // Update any data attributes
                    element.setAttribute('data-balance', balance);
                }
            });
        });
        
        // Update form inputs that might contain balance
        const balanceInputs = document.querySelectorAll('input[data-balance-field]');
        balanceInputs.forEach(input => {
            input.value = balance;
        });
        
        // Update any progress bars based on balance
        this.updateProgressBars(balance);
    }
    
    updateProgressBars(balance) {
        const progressBars = document.querySelectorAll('[data-balance-progress]');
        progressBars.forEach(bar => {
            const maxValue = parseInt(bar.getAttribute('data-max') || 10000);
            const percentage = Math.min((balance / maxValue) * 100, 100);
            
            if (bar.classList.contains('progress-bar')) {
                bar.style.width = percentage + '%';
            } else if (bar.style.setProperty) {
                bar.style.setProperty('--progress', percentage + '%');
            }
        });
    }
    
    addUpdateAnimation(element) {
        element.classList.remove('balance-updated', 'balance-increase', 'balance-decrease');
        
        // Force reflow
        element.offsetHeight;
        
        element.classList.add('balance-updated');
        
        // Remove animation class after animation completes
        setTimeout(() => {
            element.classList.remove('balance-updated');
        }, 1000);
    }
    
    formatBalance(balance) {
        return new Intl.NumberFormat().format(balance);
    }
    
    triggerBalanceChangeEvent(newBalance, previousBalance) {
        const changeEvent = new CustomEvent('qr-balance-changed', {
            detail: {
                newBalance: newBalance,
                previousBalance: previousBalance,
                change: newBalance - previousBalance
            }
        });
        
        window.dispatchEvent(changeEvent);
        
        // Also dispatch the old event format for compatibility
        const legacyEvent = new CustomEvent('balanceUpdate', {
            detail: { balance: newBalance }
        });
        window.dispatchEvent(legacyEvent);
    }
    
    // Manual update method for immediate use
    async updateAfterAction(actionType = 'unknown') {
        console.log(`🔄 Manual balance update triggered by: ${actionType}`);
        await this.refresh();
    }
    
    // Get current balance without API call
    getCurrentBalance() {
        return this.currentBalance;
    }
    
    // Cleanup method
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize the enhanced balance manager
let enhancedBalanceManager;

document.addEventListener('DOMContentLoaded', function() {
    enhancedBalanceManager = new EnhancedQRBalanceManager();
    
    // Make it globally accessible
    window.qrBalanceManager = enhancedBalanceManager;
    
    // Legacy compatibility functions
    window.updateQRBalance = () => {
        if (enhancedBalanceManager) {
            enhancedBalanceManager.refresh();
        }
    };
    
    window.triggerBalanceChange = (newBalance) => {
        const event = new CustomEvent('qr-balance-changed', {
            detail: { newBalance: newBalance }
        });
        window.dispatchEvent(event);
    };
});

// Enhanced CSS for animations
const enhancedStyles = document.createElement('style');
enhancedStyles.textContent = `
    .balance-updated {
        animation: balanceGlow 0.8s ease-in-out;
        transform-origin: center;
    }
    
    @keyframes balanceGlow {
        0% { 
            transform: scale(1);
            box-shadow: none;
        }
        50% { 
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.6);
            color: #ffc107;
        }
        100% { 
            transform: scale(1);
            box-shadow: none;
        }
    }
    
    .balance-increase {
        color: #28a745 !important;
        animation: balanceIncrease 0.6s ease-out;
    }
    
    .balance-decrease {
        color: #dc3545 !important;
        animation: balanceDecrease 0.6s ease-out;
    }
    
    @keyframes balanceIncrease {
        0% { transform: translateY(0px) scale(1); }
        50% { transform: translateY(-8px) scale(1.1); }
        100% { transform: translateY(0px) scale(1); }
    }
    
    @keyframes balanceDecrease {
        0% { transform: translateY(0px) scale(1); }
        50% { transform: translateY(8px) scale(0.95); }
        100% { transform: translateY(0px) scale(1); }
    }
    
    /* Loading states */
    .balance-loading {
        opacity: 0.6;
        position: relative;
    }
    
    .balance-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: balanceLoading 1.5s infinite;
    }
    
    @keyframes balanceLoading {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
`;

document.head.appendChild(enhancedStyles); 