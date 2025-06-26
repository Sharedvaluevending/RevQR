/* MINIMAL MODAL FIX - Only fix actual modal interaction issues */

(function() {
    'use strict';

    // Ensure modal elements have proper pointer events when modal is shown
    document.addEventListener('shown.bs.modal', function(event) {
        const modal = event.target;
        const modalContent = modal.querySelector('.modal-content');
        const modalBackdrop = document.querySelector('.modal-backdrop');
        
        if (modalContent) {
            modalContent.style.pointerEvents = 'auto';
        }
        
        if (modalBackdrop) {
            modalBackdrop.style.pointerEvents = 'none';
        }
        
        // Ensure all buttons in modal are clickable
        modal.querySelectorAll('button, .btn, [data-bs-dismiss="modal"]').forEach(function(btn) {
            btn.style.pointerEvents = 'auto';
        });
    });

    // Clean up when modal is hidden
    document.addEventListener('hidden.bs.modal', function(event) {
        // Modal cleanup is handled by Bootstrap, just ensure no stuck states
        document.body.classList.remove('modal-open');
        const remainingBackdrops = document.querySelectorAll('.modal-backdrop');
        if (remainingBackdrops.length === 0) {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    });

})(); 