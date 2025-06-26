/**
 * MODAL FIX SCRIPT - Inject this to fix the "page goes dark" issue
 * Copy and paste this into browser console or add to page
 */

// Emergency modal fix functions
window.modalFix = {
    // Force close any stuck modals
    forceClose: function() {
        console.log('🚨 Force closing stuck modals...');
        
        // Remove all modal backdrops
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.remove();
        });
        
        // Close all Bootstrap modals
        document.querySelectorAll('.modal').forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
            modal.style.display = 'none';
            modal.classList.remove('show');
        });
        
        // Reset body styles
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        console.log('✅ Modals cleared');
    },
    
    // Add emergency close button
    addEmergencyButton: function() {
        if (document.getElementById('emergencyModalClose')) return;
        
        const button = document.createElement('button');
        button.id = 'emergencyModalClose';
        button.innerHTML = '✕';
        button.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 24px;
            cursor: pointer;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        `;
        
        button.onclick = this.forceClose;
        document.body.appendChild(button);
        
        // Show button when modal appears
        const observer = new MutationObserver(() => {
            const hasModal = document.querySelector('.modal.show') || document.querySelector('.modal-backdrop');
            button.style.display = hasModal ? 'block' : 'none';
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    },
    
    // Fix the purchase function
    fixPurchaseFunction: function() {
        // Override the existing purchase function with a safer version
        window.purchaseDiscount = async function(itemId, itemName, price) {
            const button = event.target.closest('.purchase-btn');
            if (!button || button.disabled) return;
            
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Purchasing...';
            button.disabled = true;
            
            try {
                const response = await fetch('/html/api/purchase-discount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        machine_id: new URLSearchParams(window.location.search).get('machine_id'),
                        source: new URLSearchParams(window.location.search).get('source') || 'direct'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // Use alert instead of modal for now (more reliable)
                    const message = `✅ Purchase Successful!\n\n` +
                                  `Item: ${result.item_name}\n` +
                                  `Discount Code: ${result.discount_code}\n` +
                                  `Discount: ${result.discount_percent}%\n` +
                                  `Expires: ${new Date(result.expires_at).toLocaleDateString()}`;
                    
                    alert(message);
                    
                    // Update button
                    button.innerHTML = '<i class="bi bi-check"></i> Purchased';
                    button.classList.add('btn-success');
                    button.disabled = true;
                    
                    // Update balance if function exists
                    if (typeof updateUserBalance === 'function') {
                        updateUserBalance();
                    }
                } else {
                    throw new Error(result.error || 'Purchase failed');
                }
                
            } catch (error) {
                console.error('Purchase error:', error);
                alert('❌ Purchase failed: ' + error.message);
                
                // Restore button
                button.innerHTML = originalText;
                button.disabled = false;
            }
        };
        
        console.log('✅ Purchase function fixed - using alerts instead of modals');
    },
    
    // Initialize all fixes
    init: function() {
        this.addEmergencyButton();
        this.fixPurchaseFunction();
        
        // Add keyboard shortcut (Ctrl+Shift+X) to force close
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'X') {
                this.forceClose();
            }
        });
        
        console.log('🔧 Modal fix initialized. Press Ctrl+Shift+X to force close modals.');
    }
};

// Auto-initialize
modalFix.init();

// Add to window for easy access
window.forceCloseModal = modalFix.forceClose; 