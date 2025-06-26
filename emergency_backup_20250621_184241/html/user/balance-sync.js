/**
 * QR Coin Balance Sync Manager
 * Ensures consistent balance updates across all user pages
 */

class QRBalanceManager {
    constructor() {
        this.currentBalance = 0;
        this.updateInterval = null;
        this.isUpdating = false;
        this.authenticationFailed = false;
        this.consecutiveFailures = 0;
        this.maxRetries = 5; // Increased retry limit
        this.lastSuccessfulUpdate = Date.now();
        
        // Initialize on page load
        this.init();
        
        // Track user activity to keep session alive
        this.setupActivityTracking();
    }
    
    /**
     * Initialize the balance manager
     */
    init() {
        // Wait a bit to ensure session is established
        setTimeout(() => {
            // Get initial balance
            this.updateBalance();
            
            // Set up automatic refresh every 30 seconds only if authenticated
            if (!this.authenticationFailed) {
                this.startAutoRefresh();
            }
        }, 1000);
        
        // Listen for focus events to refresh when user comes back to tab
        window.addEventListener('focus', () => {
            if (!this.authenticationFailed) {
                this.updateBalance();
            }
        });
        
        // Listen for custom balance update events
        window.addEventListener('qr-balance-changed', (event) => {
            if (event.detail && typeof event.detail.newBalance === 'number') {
                this.setBalance(event.detail.newBalance);
            } else {
                this.updateBalance();
            }
        });
    }
    
    /**
     * Update balance from server
     */
    async updateBalance() {
        if (this.isUpdating || this.authenticationFailed) return;
        this.isUpdating = true;
        
        try {
            const response = await fetch('/user/balance-check.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                // Handle authentication errors gracefully
                if (response.status === 401 || response.status === 403) {
                    console.warn('User not authenticated - stopping balance updates');
                    this.handleAuthenticationFailure();
                    return;
                }
                throw new Error(`HTTP ${response.status}`);
            }
            
            // Check if response is actually JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response received:', text.substring(0, 100));
                
                // If we receive HTML, it's likely a login page
                if (text.trim().startsWith('<!DOCTYPE html>') || text.includes('<html')) {
                    console.warn('Received HTML response - user may not be authenticated');
                    this.handleAuthenticationFailure();
                    return;
                }
                
                throw new Error('Response is not JSON - user may not be authenticated');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.setBalance(data.balance);
                this.consecutiveFailures = 0; // Reset failure count on success
                this.authenticationFailed = false; // Reset auth failure flag
                this.lastSuccessfulUpdate = Date.now();
                
                // Handle resync flag from enhanced responses
                if (data.should_resync) {
                    console.log('Server requested balance resync');
                    this.forceBalanceResync();
                }
            } else {
                console.warn('Balance update failed:', data.error || data.message);
                
                // Check for authentication/authorization errors
                if (data.error === 'User not authenticated' || 
                    data.error === 'Insufficient permissions' ||
                    data.code === 401 || data.code === 403 ||
                    data.action === 'redirect_login') {
                    
                    // Only treat as auth failure if it's been a while since last success
                    const timeSinceLastSuccess = Date.now() - this.lastSuccessfulUpdate;
                    if (timeSinceLastSuccess > 60000) { // Only after 1 minute of failures
                        this.handleAuthenticationFailure();
                    } else {
                        this.consecutiveFailures++;
                    }
                } else {
                    // Other errors - increment failure count
                    this.consecutiveFailures++;
                    
                    // If server explicitly requests resync, do it regardless of error
                    if (data.should_resync) {
                        console.log('Server requested balance resync after error');
                        this.forceBalanceResync();
                    }
                }
            }
            
        } catch (error) {
            this.consecutiveFailures++;
            console.error('Failed to update QR balance:', error);
            
            // If we have too many consecutive failures, assume authentication issue
            if (this.consecutiveFailures >= this.maxRetries) {
                console.warn('Too many consecutive failures - assuming authentication issue');
                this.handleAuthenticationFailure();
            }
        } finally {
            this.isUpdating = false;
        }
    }
    
    /**
     * Handle authentication failure
     */
    handleAuthenticationFailure() {
        // Try to refresh session first before showing popup
        this.trySessionRefresh().then(refreshed => {
            if (!refreshed) {
                this.authenticationFailed = true;
                this.stopAutoRefresh();
                
                // Only show warning after a delay to avoid false positives
                setTimeout(() => {
                    if (this.authenticationFailed) {
                        this.showAuthenticationWarning();
                    }
                }, 2000); // Wait 2 seconds before showing popup
            }
        });
    }
    
    /**
     * Try to refresh the session
     */
    async trySessionRefresh() {
        try {
            console.log('Attempting to refresh session...');
            
            // Try the dedicated session refresh endpoint first
            let response = await fetch('/user/session-refresh.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            // If that fails, try the simple keep-alive endpoint
            if (!response.ok) {
                response = await fetch('/user/keep-alive.php', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            }
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log('Session refreshed successfully');
                    this.consecutiveFailures = 0;
                    this.authenticationFailed = false;
                    this.lastSuccessfulUpdate = Date.now();
                    // Restart balance updates
                    this.startAutoRefresh();
                    return true;
                }
            }
        } catch (error) {
            console.warn('Session refresh failed:', error);
        }
        
        return false;
    }
    
    /**
     * Show authentication warning to user
     */
    showAuthenticationWarning() {
        // Only show once
        if (document.querySelector('.auth-warning-toast')) return;
        
        const toast = document.createElement('div');
        toast.className = 'auth-warning-toast position-fixed top-0 end-0 m-3 alert alert-info alert-dismissible fade show';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <i class="bi bi-info-circle me-2"></i>
            <strong>Session Inactive</strong><br>
            <small>Your session has been inactive. <a href="javascript:window.location.reload()" class="alert-link fw-bold">Refresh</a> to continue or <button type="button" class="btn btn-link btn-sm p-0 align-baseline" onclick="this.closest('.alert').remove()">dismiss</button>.</small>
            <button type="button" class="btn-close btn-close-sm" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-dismiss after 10 seconds (no auto-refresh)
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 10000);
    }
    
    /**
     * Set balance and update all displays
     */
    setBalance(newBalance) {
        const oldBalance = this.currentBalance;
        this.currentBalance = newBalance;
        
        // Update all balance displays on the page
        this.updateBalanceDisplays(newBalance);
        
        // Trigger change event if balance changed
        if (oldBalance !== newBalance) {
            this.notifyBalanceChange(oldBalance, newBalance);
        }
    }
    
    /**
     * Update all balance display elements
     */
    updateBalanceDisplays(balance) {
        const formattedBalance = this.formatBalance(balance);
        
        // Common selectors for balance displays
        const selectors = [
            '[data-balance-display]',
            '#navbarQRBalance',
            '.balance-amount',
            '.qr-balance',
            '.user-balance',
            '.current-balance'
        ];
        
        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (element) {
                    element.textContent = formattedBalance;
                    
                    // Add visual feedback for balance changes
                    this.addUpdateAnimation(element);
                }
            });
        });
        
        // Update any input fields that might contain balance
        const balanceInputs = document.querySelectorAll('input[data-balance-field]');
        balanceInputs.forEach(input => {
            input.value = balance;
        });
    }
    
    /**
     * Format balance for display
     */
    formatBalance(balance) {
        return new Intl.NumberFormat().format(balance);
    }
    
    /**
     * Add visual feedback animation
     */
    addUpdateAnimation(element) {
        // Remove existing animation classes
        element.classList.remove('balance-updated', 'balance-increase', 'balance-decrease');
        
        // Add update animation
        element.classList.add('balance-updated');
        
        // Remove animation class after animation completes
        setTimeout(() => {
            element.classList.remove('balance-updated');
        }, 1000);
    }
    
    /**
     * Notify other parts of the app about balance changes
     */
    notifyBalanceChange(oldBalance, newBalance) {
        const changeEvent = new CustomEvent('balance-updated', {
            detail: {
                oldBalance: oldBalance,
                newBalance: newBalance,
                change: newBalance - oldBalance
            }
        });
        
        window.dispatchEvent(changeEvent);
        
        // Console log for debugging
        console.log(`QR Balance updated: ${oldBalance} → ${newBalance} (${newBalance - oldBalance > 0 ? '+' : ''}${newBalance - oldBalance})`);
    }
    
    /**
     * Start automatic refresh
     */
    startAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        this.updateInterval = setInterval(() => {
            this.updateBalance();
        }, 60000); // 60 seconds (reduced frequency)
    }
    
    /**
     * Stop automatic refresh
     */
    stopAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }
    
    /**
     * Manually trigger balance update (for after purchases, spins, etc.)
     */
    refresh() {
        this.updateBalance();
    }
    
    /**
     * Get current balance
     */
    getBalance() {
        return this.currentBalance;
    }
    
    /**
     * Force a complete balance resync from multiple endpoints
     */
    async forceBalanceResync() {
        console.log('🔄 Forcing complete balance resync...');
        
        // Try multiple balance endpoints for reliability, including emergency sync
        const endpoints = [
            '/user/api/emergency-balance-sync.php',
            '/user/api/get-balance.php',
            '/user/balance-check.php',
            '/api/get-balance.php'
        ];
        
        for (const endpoint of endpoints) {
            try {
                const response = await fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && typeof data.balance === 'number') {
                        console.log(`✅ Balance resynced from ${endpoint}: ${data.balance}`);
                        this.setBalance(data.balance);
                        
                        // Check if emergency recovery was performed or is needed
                        if (data.needs_recovery && endpoint !== '/user/api/emergency-balance-sync.php') {
                            console.log('🚨 Balance inconsistency detected, triggering emergency recovery...');
                            await this.performEmergencyRecovery();
                            return true;
                        }
                        
                        if (data.recovery_performed) {
                            console.log(`🔧 Emergency recovery completed: ${data.correction_applied} coins adjusted`);
                            this.showBalanceWarning(`Balance corrected: ${data.correction_applied > 0 ? '+' : ''}${data.correction_applied} coins applied`);
                        }
                        
                        // Update localStorage for other tabs
                        localStorage.setItem('qr_balance_update', JSON.stringify({
                            balance: data.balance,
                            timestamp: Date.now(),
                            source: 'resync'
                        }));
                        
                        return true;
                    }
                }
            } catch (error) {
                console.warn(`Failed to resync from ${endpoint}:`, error);
            }
        }
        
        console.error('❌ All balance resync attempts failed');
        this.showBalanceWarning('Unable to sync balance. Please refresh the page.');
        return false;
    }
    
    /**
     * Perform emergency balance recovery
     */
    async performEmergencyRecovery() {
        try {
            console.log('🚨 Performing emergency balance recovery...');
            
            const response = await fetch('/user/api/emergency-balance-sync.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    force_recovery: true
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.setBalance(data.balance);
                    
                    if (data.correction_applied !== 0) {
                        this.showBalanceWarning(`Emergency recovery completed: ${data.correction_applied > 0 ? '+' : ''}${data.correction_applied} coins corrected`);
                    }
                    
                    return true;
                } else {
                    throw new Error(data.error || 'Emergency recovery failed');
                }
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Emergency recovery failed:', error);
            this.showBalanceWarning('Emergency balance recovery failed. Please refresh the page.');
            return false;
        }
    }
    
    /**
     * Show balance-specific warning to user
     */
    showBalanceWarning(message) {
        // Create or update balance warning
        let warning = document.getElementById('balance-warning');
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'balance-warning';
            warning.className = 'alert alert-warning balance-warning';
            warning.style.cssText = `
                position: fixed;
                top: 70px;
                right: 20px;
                z-index: 9999;
                max-width: 300px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            document.body.appendChild(warning);
        }
        
        warning.innerHTML = `
            <strong>⚠️ Balance Sync Issue</strong><br>
            ${message}
            <button type="button" class="btn-close ms-2" onclick="this.parentElement.remove()"></button>
        `;
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (warning.parentElement) {
                warning.remove();
            }
        }, 10000);
    }
    
    /**
     * Setup user activity tracking to keep session alive
     */
    setupActivityTracking() {
        let activityTimer;
        const keepAliveInterval = 300000; // 5 minutes
        
        const resetActivityTimer = () => {
            clearTimeout(activityTimer);
            activityTimer = setTimeout(() => {
                this.keepSessionAlive();
            }, keepAliveInterval);
        };
        
        // Track user interactions
        ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetActivityTimer, { passive: true });
        });
        
        // Initial timer
        resetActivityTimer();
    }
    
    /**
     * Send keep-alive request to extend session
     */
    async keepSessionAlive() {
        try {
            const response = await fetch('/user/keep-alive.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                console.log('Session keep-alive:', data.message);
            }
        } catch (error) {
            console.warn('Keep-alive failed:', error);
        }
    }
}

// Global instance
window.qrBalanceManager = new QRBalanceManager();

// CSS for animations
const style = document.createElement('style');
style.textContent = `
    .balance-updated {
        animation: balanceGlow 1s ease-in-out;
    }
    
    @keyframes balanceGlow {
        0%, 100% { 
            transform: scale(1); 
            box-shadow: none;
        }
        50% { 
            transform: scale(1.05); 
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
        }
    }
    
    .balance-increase {
        color: #28a745 !important;
        animation: balanceIncrease 0.8s ease-out;
    }
    
    .balance-decrease {
        color: #dc3545 !important;
        animation: balanceDecrease 0.8s ease-out;
    }
    
    @keyframes balanceIncrease {
        0% { transform: translateY(0px); }
        30% { transform: translateY(-5px); }
        100% { transform: translateY(0px); }
    }
    
    @keyframes balanceDecrease {
        0% { transform: translateY(0px); }
        30% { transform: translateY(5px); }
        100% { transform: translateY(0px); }
    }
`;

document.head.appendChild(style);

// Helper functions for other scripts
window.updateQRBalance = () => {
    if (window.qrBalanceManager) {
        window.qrBalanceManager.refresh();
    }
};

window.triggerBalanceChange = (newBalance) => {
    const event = new CustomEvent('qr-balance-changed', {
        detail: { newBalance: newBalance }
    });
    window.dispatchEvent(event);
};

// Auto-update after common actions
document.addEventListener('DOMContentLoaded', function() {
    // Listen for purchase completions
    document.addEventListener('purchase-completed', function() {
        setTimeout(() => {
            window.updateQRBalance();
        }, 1000);
    });
    
    // Listen for spin completions  
    document.addEventListener('spin-completed', function() {
        setTimeout(() => {
            window.updateQRBalance();
        }, 1000);
    });
    
    // Listen for vote completions
    document.addEventListener('vote-completed', function() {
        setTimeout(() => {
            window.updateQRBalance();
        }, 1000);
    });
}); 