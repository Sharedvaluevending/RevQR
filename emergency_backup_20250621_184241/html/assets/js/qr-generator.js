class QRGenerator {
    constructor() {
        this.form = document.getElementById('qrGeneratorForm');
        this.preview = document.getElementById('qrPreview');
        this.downloadBtn = document.getElementById('downloadBtn');
        this.currentQRCode = null;
        this.previewTimeout = null;
        this.hasInteracted = false; // Flag to prevent preview on page load

        // If qrPreview doesn't exist in HTML, create it
        if (!this.preview) {
            this.preview = document.createElement('div');
            this.preview.id = 'qrPreview';
            this.preview.className = 'qr-preview-container';
            if (this.form) {
                this.form.parentNode.insertBefore(this.preview, this.form.nextSibling);
            } else {
                document.body.appendChild(this.preview);
            }
        }
    }

    init() {
        this.setupEventListeners();
        this.setupFormValidation();
    }

    setupEventListeners() {
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Download button
        this.downloadBtn.addEventListener('click', () => this.handleDownload());

        // Enable preview immediately - no need to wait for interaction
        this.hasInteracted = true;
        const previewInputs = this.form.querySelectorAll('input, select, textarea');
        previewInputs.forEach(input => {
            ['change', 'input', 'keyup'].forEach(eventType => {
                input.addEventListener(eventType, () => {
                    this.hasInteracted = true;
                    this.updatePreview();
                });
            });
        });

        // Handle QR type changes
        this.setupQRTypeVisibility();
    }

    setupFormValidation() {
        this.form.addEventListener('input', (e) => {
            const input = e.target;
            if (input.hasAttribute('required') && !input.value) {
                input.setCustomValidity('This field is required');
            } else {
                input.setCustomValidity('');
            }
        });
    }

    setupQRTypeVisibility() {
        const qrTypeSelect = this.form.querySelector('#qrType');
        console.log('setupQRTypeVisibility - QR Type Select element:', qrTypeSelect);
        
        if (!qrTypeSelect) {
            console.error('QR Type select element not found!');
            return;
        }
        
        // Function to update field visibility
        const updateFieldVisibility = (type) => {
            console.log('=== Updating field visibility for type:', type, '===');
            
            // Check if this is a coming soon type
            const comingSoonTypes = ['promotion', 'cross_promo', 'stackable'];
            if (comingSoonTypes.includes(type)) {
                this.showMessage('This QR type is coming soon! Please select a different type.', 'warning');
                return;
            }
            
            const urlFields = document.getElementById('urlFields');
            const campaignFields = document.getElementById('campaignFields');
            const machineFields = document.getElementById('machineFields');
            const promotionFields = document.getElementById('promotionFields');
            
            console.log('Field elements found:');
            console.log('- urlFields:', urlFields);
            console.log('- campaignFields:', campaignFields);
            console.log('- machineFields:', machineFields);
            console.log('- promotionFields:', promotionFields);
            
            // Get form inputs for setting required attributes
            const urlInput = document.querySelector('input[name="url"]');
            const campaignSelect = document.querySelector('select[name="campaign_id"]');
            const machineInput = document.querySelector('input[name="machine_name"]');
            const promotionSelect = document.querySelector('select[name="promotion_id"]');

            console.log('Input elements found:');
            console.log('- urlInput:', urlInput);
            console.log('- campaignSelect:', campaignSelect);
            console.log('- machineInput:', machineInput);
            console.log('- promotionSelect:', promotionSelect);

            // Hide all fields first and remove required attributes
            if (urlFields) {
                urlFields.style.display = 'none';
                console.log('- URL fields hidden');
            }
            if (campaignFields) {
                campaignFields.style.display = 'none';
                console.log('- Campaign fields hidden');
            }
            if (machineFields) {
                machineFields.style.display = 'none';
                console.log('- Machine fields hidden');
            }
            if (promotionFields) {
                promotionFields.style.display = 'none';
                console.log('- Promotion fields hidden');
            }
            
            // Also hide the combined machine promotion fields
            const machinePromotionFields = document.getElementById('machinePromotionFields');
            if (machinePromotionFields) {
                machinePromotionFields.style.display = 'none';
                console.log('- Machine promotion fields hidden');
            }
            
            if (urlInput) urlInput.removeAttribute('required');
            if (campaignSelect) campaignSelect.removeAttribute('required');
            if (machineInput) machineInput.removeAttribute('required');
            if (promotionSelect) promotionSelect.removeAttribute('required');

            // Show fields based on type and set required attributes
            if (type === 'static' || type === 'dynamic') {
                if (urlFields) {
                    urlFields.style.display = 'block';
                    console.log('✅ URL field should now be VISIBLE for', type);
                } else {
                    console.error('❌ URL fields element not found!');
                }
                if (urlInput) {
                    urlInput.setAttribute('required', 'required');
                    console.log('✅ URL input marked as required');
                }
            } else if (type === 'dynamic_voting') {
                if (campaignFields) {
                    campaignFields.style.display = 'block';
                    console.log('✅ Campaign fields should now be VISIBLE for', type);
                }
                if (campaignSelect) campaignSelect.setAttribute('required', 'required');
            } else if (type === 'dynamic_vending') {
                if (campaignFields) {
                    campaignFields.style.display = 'block';
                    console.log('✅ Campaign fields should now be VISIBLE for', type);
                }
                if (machineFields) {
                    machineFields.style.display = 'block';
                    console.log('✅ Machine fields should now be VISIBLE for', type);
                }
                if (campaignSelect) campaignSelect.setAttribute('required', 'required');
                if (machineInput) machineInput.setAttribute('required', 'required');
            } else if (type === 'machine_sales') {
                // Machine sales - needs machine name AND promotion dropdown
                const machinePromotionFields = document.getElementById('machinePromotionFields');
                if (machinePromotionFields) {
                    machinePromotionFields.style.display = 'block';
                    console.log('✅ Machine promotion fields should now be VISIBLE for', type);
                }
                const machinePromotionMachine = document.getElementById('machinePromotionMachine');
                const machinePromotionSelect = document.getElementById('machinePromotionSelect');
                if (machinePromotionMachine) machinePromotionMachine.setAttribute('required', 'required');
                if (machinePromotionSelect) machinePromotionSelect.setAttribute('required', 'required');
            }
            console.log('=== Field visibility update complete ===');
        };
        
        const self = this;
        qrTypeSelect.addEventListener('change', function() {
            console.log('QR Type changed to:', this.value);
            updateFieldVisibility(this.value);
            // Trigger preview update after field visibility changes
            setTimeout(() => {
                if (self.hasInteracted) {
                    self.updatePreview();
                }
            }, 100);
        });
        
        // Set initial field visibility for static QR (default)
        console.log('Setting initial field visibility for static QR...');
        updateFieldVisibility('static');
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            return;
        }

        try {
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());
            
            // Set content based on QR type
            let content = '';
            if (data.qr_type === 'static' || data.qr_type === 'dynamic') {
                content = data.url || '';
            } else if (data.qr_type === 'dynamic_voting') {
                content = `/vote.php?campaign_id=${data.campaign_id || ''}`;
            } else if (data.qr_type === 'dynamic_vending') {
                content = `/vote.php?campaign_id=${data.campaign_id || ''}&machine=${encodeURIComponent(data.machine_name || '')}`;
            } else if (data.qr_type === 'machine_sales') {
                content = `/machine-sales.php?machine=${encodeURIComponent(data.machine_name || '')}&promotion_id=${data.promotion_id || ''}`;
            }

            // Add content to data
            data.content = content;

            // Include all form fields in the request
            const response = await fetch('/api/qr/generate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Failed to generate QR code');
            }

            // Update QR code preview
            const qrPreview = document.getElementById('qrPreview');
            if (qrPreview) {
                let previewImg = qrPreview.querySelector('img');
                if (!previewImg) {
                    previewImg = document.createElement('img');
                    previewImg.alt = 'QR Code Generated';
                    previewImg.style.display = 'block';
                    previewImg.style.maxWidth = '100%';
                    previewImg.className = 'img-fluid';
                    qrPreview.appendChild(previewImg);
                }
                previewImg.src = result.data.qr_code_url;
                qrPreview.style.display = 'block';
            }

            // Store current QR code data
            this.currentQRCode = result.data;

            // Show download button
            if (this.downloadBtn) {
                this.downloadBtn.style.display = 'block';
            }

            // Show success message
            this.showMessage('QR code generated successfully!', 'success');
        } catch (error) {
            console.error('Error:', error);
            this.showMessage(error.message, 'error');
        }
    }

    async updatePreview() {
        if (!this.hasInteracted) return;
        // Clear any existing timeout
        if (this.previewTimeout) {
            clearTimeout(this.previewTimeout);
        }

        // Clear any existing messages first
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        const formData = new FormData(this.form);
        const data = Object.fromEntries(formData.entries());

        // Ensure we have a QR type (default to static if missing)
        if (!data.qr_type) {
            data.qr_type = 'static';
        }

        // Debug logging for raw form data
        console.log('[QR Preview Debug] Raw form data:', data);
        console.log('[QR Preview Debug] QR Type detected:', data.qr_type);

        // Set content based on QR type
        let content = '';
        if (data.qr_type === 'static' || data.qr_type === 'dynamic') {
            // Static and dynamic use the URL field
            content = data.url || '';
        } else if (data.qr_type === 'dynamic_voting') {
            content = `/vote.php?campaign_id=${data.campaign_id || ''}`;
        } else if (data.qr_type === 'dynamic_vending') {
            content = `/vote.php?campaign_id=${data.campaign_id || ''}&machine=${encodeURIComponent(data.machine_name || '')}`;
        } else if (data.qr_type === 'machine_sales') {
            content = `/machine-sales.php?machine=${encodeURIComponent(data.machine_name || '')}&promotion_id=${data.promotion_id || ''}`;
        }
        data.content = content;

        // Debug logging for content generation
        console.log('[QR Preview Debug] Generated content:', content);

        // Check for specific missing fields and show helpful messages
        if (data.qr_type === 'static' || data.qr_type === 'dynamic') {
            if (!data.url || data.url.trim() === '') {
                this.showMessage('Please enter a URL to see preview.', 'warning');
                return;
            }
        } else if (data.qr_type === 'dynamic_voting') {
            if (!data.campaign_id || data.campaign_id.trim() === '') {
                this.showMessage('Please select a campaign to see preview.', 'warning');
                return;
            }
        } else if (data.qr_type === 'dynamic_vending') {
            if (!data.campaign_id || data.campaign_id.trim() === '') {
                this.showMessage('Please select a campaign to see preview.', 'warning');
                return;
            }
            if (!data.machine_name || data.machine_name.trim() === '') {
                this.showMessage('Please enter a machine name to see preview.', 'warning');
                return;
            }
        } else if (data.qr_type === 'machine_sales') {
            const machinePromotionMachine = document.getElementById('machinePromotionMachine');
            const machinePromotionSelect = document.getElementById('machinePromotionSelect');
            if (!machinePromotionMachine?.value || machinePromotionMachine.value.trim() === '') {
                this.showMessage('Please enter a machine name to see preview.', 'warning');
                return;
            }
            if (!machinePromotionSelect?.value || machinePromotionSelect.value.trim() === '') {
                this.showMessage('Please select a promotion to see preview.', 'warning');
                return;
            }
        }

        // Final check for content
        if (!content || content.trim() === '' || /[?&][a-z_]+=($|&)/.test(content)) {
            this.showMessage('Please fill all required fields to see preview.', 'warning');
            return;
        }

        console.log('[QR Preview Debug] Validation passed - making API call with content:', data.content);

        // Now debounce and call the API
        this.previewTimeout = setTimeout(async () => {
            try {
                console.log('[QR Preview Debug] Sending data to API:', JSON.stringify(data, null, 2));
                
                const response = await fetch('/api/qr/preview.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                console.log('Response Status:', response.status);
                console.log('Response Headers:', Object.fromEntries(response.headers.entries()));
                
                const rawResponse = await response.text();
                console.log('Raw Response:', rawResponse);
                
                const result = JSON.parse(rawResponse);
                console.log('Parsed Response:', result);

                if (!result.success) {
                    // Check if it's an authentication error
                    if (result.message && (result.message.includes('Authentication required') || result.message.includes('Unauthorized'))) {
                        this.showMessage('Please log in to use QR preview. <a href="/login.php">Login here</a>', 'warning');
                        return;
                    }
                    throw new Error(`Failed to generate preview: ${rawResponse}`);
                }

                // Ensure preview element exists - use existing HTML element
                if (!this.preview) {
                    this.preview = document.getElementById('qrPreview');
                    if (!this.preview) {
                        // Fallback: create new element if HTML element doesn't exist
                        this.preview = document.createElement('div');
                        this.preview.id = 'qrPreview';
                        this.preview.className = 'qr-preview-container';
                        if (this.form) {
                            this.form.parentNode.insertBefore(this.preview, this.form.nextSibling);
                        } else {
                            document.body.appendChild(this.preview);
                        }
                    }
                }

                // Create or update preview image
                let previewImg = this.preview.querySelector('img');
                if (!previewImg) {
                    previewImg = document.createElement('img');
                    previewImg.alt = 'QR Code Preview';
                    previewImg.style.display = 'block';
                    previewImg.style.maxWidth = '100%';
                    previewImg.style.height = 'auto';
                    this.preview.appendChild(previewImg);
                    console.log('Created new preview image element');
                }
                
                previewImg.src = result.preview_url;
                console.log('Updated preview image src:', result.preview_url.substring(0, 50) + '...');
                
                // Ensure preview container is visible
                this.preview.style.display = 'block';
                console.log('Preview container made visible');

                // Store preview data for potential download
                this.currentQRCode = {
                    qr_code_url: result.url || result.preview_url,
                    preview_url: result.preview_url
                };

                // Update download button if it exists
                if (this.downloadBtn) {
                    this.downloadBtn.style.display = 'block';
                }

            } catch (error) {
                console.error('Error:', error);
                
                // Check if it's an authentication error
                if (error.message.includes('Authentication required') || error.message.includes('Unauthorized')) {
                    this.showMessage('Please log in to use QR preview. <a href="/login.php">Login here</a>', 'warning');
                } else {
                    this.showMessage(error.message, 'error');
                }
            }
        }, 500); // 500ms debounce
    }

    async handleDownload() {
        if (!this.currentQRCode) {
            this.showMessage('No QR code to download', 'error');
            return;
        }

        try {
            const qrUrl = this.currentQRCode.qr_code_url;
            
            // Check if it's a data URI (base64 encoded)
            if (qrUrl.startsWith('data:')) {
                // Handle data URI directly without fetch to avoid CSP violation
                this.downloadDataUri(qrUrl, 'qr-code.png');
                this.showMessage('QR code downloaded successfully!', 'success');
            } else {
                // Handle regular URL with fetch
                const response = await fetch(qrUrl);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const blob = await response.blob();
                this.downloadBlob(blob, 'qr-code.png');
                this.showMessage('QR code downloaded successfully!', 'success');
            }
        } catch (error) {
            console.error('Download Error:', error);
            // Fallback: try to open the image in a new tab
            if (this.currentQRCode.qr_code_url) {
                window.open(this.currentQRCode.qr_code_url, '_blank');
                this.showMessage('Download failed, but QR code opened in new tab. Right-click to save.', 'warning');
            } else {
                this.showMessage('Failed to download QR code: ' + error.message, 'error');
            }
        }
    }

    downloadDataUri(dataUri, filename) {
        const a = document.createElement('a');
        a.href = dataUri;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    downloadBlob(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    showMessage(message, type = 'info') {
        // Remove any existing alerts first
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        console.log(`[QR Message] ${type.toUpperCase()}: ${message}`);
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert before the form instead of preview
        if (this.form && this.form.parentNode) {
            this.form.parentNode.insertBefore(alertDiv, this.form);
        } else {
            document.body.appendChild(alertDiv);
        }
        
        // Auto-remove after 3 seconds
        setTimeout(() => alertDiv.remove(), 3000);
    }
}

// Initialize QR generator when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const qrGenerator = new QRGenerator();
    qrGenerator.init();
}); 