/**
 * Emergency Balance Sync - Add to any page with balance issues
 * This provides a fallback when the main balance sync fails
 */

function emergencyBalanceSync() {
    console.log('🚨 Running emergency balance sync...');
    
    fetch('/html/user/api/get-balance.php', {
        method: 'GET',
        cache: 'no-cache',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update all balance displays
            const balanceElements = document.querySelectorAll([
                '[data-balance-display]',
                '.qr-balance',
                '.balance-amount',
                '.user-balance',
                '.current-balance',
                '#navbarQRBalance'
            ].join(', '));
            
            const formattedBalance = new Intl.NumberFormat().format(data.balance);
            
            balanceElements.forEach(el => {
                el.textContent = formattedBalance;
                el.setAttribute('data-balance', data.balance);
            });
            
            console.log('✅ Emergency balance sync completed:', data.balance);
            
            // Trigger event for other components
            window.dispatchEvent(new CustomEvent('emergency-balance-updated', {
                detail: { balance: data.balance }
            }));
            
        } else {
            console.error('❌ Emergency balance sync failed:', data.message);
        }
    })
    .catch(error => {
        console.error('❌ Emergency balance sync network error:', error);
    });
}

// Auto-run if balance appears to be 0 or missing
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const balanceEl = document.querySelector('[data-balance-display], .qr-balance');
        if (balanceEl) {
            const balanceText = balanceEl.textContent.trim();
            if (balanceText === '0' || balanceText === '' || balanceText === 'NaN') {
                console.log('⚠️ Detected potentially incorrect balance, running emergency sync...');
                emergencyBalanceSync();
            }
        }
    }, 2000); // Wait 2 seconds for normal sync to complete
});

// Make available globally for manual troubleshooting
window.emergencyBalanceSync = emergencyBalanceSync;

console.log('🛡️ Emergency balance sync loaded'); 