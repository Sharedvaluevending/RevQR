class QRGeneratorV2 {
    constructor() {
        this.form = document.getElementById('qrGeneratorForm');
        this.preview = document.getElementById('qrPreview');
        this.downloadBtn = document.getElementById('downloadBtn');
        this.currentQRCode = null;
        this.previewTimeout = null;
        this.isInitialized = false;
        
        // Bind methods to preserve 'this' context
        this.updatePreview = this.updatePreview.bind(this);
        this.handleSubmit = this.handleSubmit.bind(this);
        this.handleDownload = this.handleDownload.bind(this);
    }

    init() {
        if (this.isInitialized) return;
        
        console.log('Initializing QR Generator V2...');
        
        this.setupEventListeners();
        this.setupFormValidation();
        this.setupAdvancedFeatures();
        this.setupRangeInputs();
        this.setupColorInputs();
        this.setupLogoHandlers();
        this.loadLogos();
        
        // Set initial QR type visibility
        this.updateQRTypeVisibility();
        
        // Enable live preview after short delay
        setTimeout(() => {
            this.enableLivePreview();
        }, 1000);
        
        this.isInitialized = true;
        console.log('QR Generator V2 initialized successfully');
    }

    setupEventListeners() {
        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', this.handleSubmit);
        }

        // Download button
        if (this.downloadBtn) {
            this.downloadBtn.addEventListener('click', this.handleDownload);
        }

        // QR type change
        const qrTypeSelect = document.getElementById('qrType');
        if (qrTypeSelect) {
            qrTypeSelect.addEventListener('change', () => {
                this.updateQRTypeVisibility();
                // Trigger preview update after field visibility changes
                setTimeout(() => {
                    this.schedulePreviewUpdate();
                }, 100);
            });
        }

        // Reset button
        const resetBtn = document.getElementById('resetBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetForm());
        }

        // Test preview button
        const testPreviewBtn = document.getElementById('testPreviewBtn');
        if (testPreviewBtn) {
            testPreviewBtn.addEventListener('click', () => this.fillTestData());
        }
    }

    setupFormValidation() {
        if (!this.form) return;
        
        this.form.addEventListener('input', (e) => {
            const input = e.target;
            if (input.hasAttribute('required') && !input.value.trim()) {
                input.setCustomValidity('This field is required');
            } else {
                input.setCustomValidity('');
            }
        });
    }

    setupAdvancedFeatures() {
        // Toggle switches for feature sections
        const featureToggles = [
            { switch: 'enableLabel', target: 'labelOptions', container: 'labelToggle' },
            { switch: 'enableBottomText', target: 'bottomTextOptions', container: 'bottomToggle' },
            { switch: 'enableModuleShape', target: 'moduleShapeOptions' },
            { switch: 'enableGradient', target: 'gradientOptions' },
            { switch: 'enableShadow', target: 'shadowOptions' }
        ];

        featureToggles.forEach(({ switch: switchId, target, container }) => {
            const switchEl = document.getElementById(switchId);
            const targetEl = document.getElementById(target);
            const containerEl = container ? document.getElementById(container) : null;

            if (switchEl && targetEl) {
                switchEl.addEventListener('change', () => {
                    targetEl.style.display = switchEl.checked ? 'block' : 'none';
                    if (containerEl) {
                        containerEl.classList.toggle('active', switchEl.checked);
                    }
                    
                    // Trigger preview update
                    this.schedulePreviewUpdate();
                });
            }
        });
    }

    setupRangeInputs() {
        const rangeInputs = [
            { input: 'sizeRange', display: 'sizeValue', suffix: '' },
            { input: 'labelSizeRange', display: 'labelSizeValue', suffix: '' },
            { input: 'bottomSizeRange', display: 'bottomSizeValue', suffix: '' },
            { input: 'moduleSizeRange', display: 'moduleSizeValue', suffix: '' },
            { input: 'gradientAngleRange', display: 'gradientAngleValue', suffix: '°' },
            { input: 'gradientOpacityRange', display: 'gradientOpacityValue', suffix: '%', multiplier: 100 },
            { input: 'shadowBlurRange', display: 'shadowBlurValue', suffix: '' },
            { input: 'shadowOffsetXRange', display: 'shadowOffsetXValue', suffix: '' },
            { input: 'shadowOffsetYRange', display: 'shadowOffsetYValue', suffix: '' }
        ];

        rangeInputs.forEach(({ input, display, suffix, multiplier = 1 }) => {
            const inputEl = document.getElementById(input);
            const displayEl = document.getElementById(display);

            if (inputEl && displayEl) {
                const updateDisplay = () => {
                    const value = parseFloat(inputEl.value) * multiplier;
                    displayEl.textContent = value + suffix;
                };

                inputEl.addEventListener('input', () => {
                    updateDisplay();
                    this.schedulePreviewUpdate();
                });

                // Set initial value
                updateDisplay();
            }
        });
    }

    setupColorInputs() {
        const colorPairs = [
            { color: 'foregroundColor', hex: 'foregroundHex' },
            { color: 'backgroundColor', hex: 'backgroundHex' }
        ];

        colorPairs.forEach(({ color, hex }) => {
            const colorInput = document.getElementById(color);
            const hexInput = document.getElementById(hex);

            if (colorInput && hexInput) {
                colorInput.addEventListener('change', () => {
                    hexInput.value = colorInput.value;
                    this.schedulePreviewUpdate();
                });

                hexInput.addEventListener('change', () => {
                    if (/^#[0-9A-F]{6}$/i.test(hexInput.value)) {
                        colorInput.value = hexInput.value;
                        this.schedulePreviewUpdate();
                    }
                });
            }
        });
    }

    setupLogoHandlers() {
        const uploadLogoBtn = document.getElementById('uploadLogoBtn');
        const logoUpload = document.getElementById('logoUpload');
        const logoPreview = document.getElementById('logoPreview');
        const logoSelect = document.getElementById('logoSelect');
        const deleteLogoBtn = document.getElementById('deleteLogoBtn');

        if (uploadLogoBtn && logoUpload) {
            uploadLogoBtn.addEventListener('click', async () => {
                const file = logoUpload.files[0];
                if (!file) {
                    this.showMessage('Please select a file to upload', 'warning');
                    return;
                }

                if (!file.type.match(/^image\/(png|jpeg|jpg)$/)) {
                    this.showMessage('Please select a PNG or JPEG image', 'warning');
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    this.showMessage('File size must be less than 2MB', 'warning');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('logo', file);

                    uploadLogoBtn.disabled = true;
                    uploadLogoBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';

                    const response = await fetch('/api/qr/logo.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Add to dropdown
                        const option = document.createElement('option');
                        option.value = result.filename;
                        option.textContent = result.filename;
                        logoSelect.appendChild(option);
                        logoSelect.value = result.filename;

                        // Show preview
                        if (logoPreview) {
                            const img = logoPreview.querySelector('img');
                            if (img) {
                                img.src = result.url;
                                logoPreview.style.display = 'block';
                            }
                        }

                        // Show delete button
                        if (deleteLogoBtn) {
                            deleteLogoBtn.style.display = 'inline-block';
                        }

                        // Clear file input
                        logoUpload.value = '';

                        // Trigger preview update
                        this.schedulePreviewUpdate();

                        this.showMessage('Logo uploaded successfully!', 'success');
                    } else {
                        throw new Error(result.message || 'Upload failed');
                    }
                } catch (error) {
                    console.error('Logo upload error:', error);
                    this.showMessage('Failed to upload logo: ' + error.message, 'danger');
                } finally {
                    uploadLogoBtn.disabled = false;
                    uploadLogoBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
                }
            });
        }

        // Logo selection change
        if (logoSelect) {
            logoSelect.addEventListener('change', () => {
                const selectedLogo = logoSelect.value;
                
                if (selectedLogo && logoPreview) {
                    const img = logoPreview.querySelector('img');
                    if (img) {
                        img.src = `/assets/img/logos/${selectedLogo}`;
                        logoPreview.style.display = 'block';
                    }
                    if (deleteLogoBtn) {
                        deleteLogoBtn.style.display = 'inline-block';
                    }
                } else {
                    if (logoPreview) logoPreview.style.display = 'none';
                    if (deleteLogoBtn) deleteLogoBtn.style.display = 'none';
                }

                this.schedulePreviewUpdate();
            });
        }

        // Delete logo
        if (deleteLogoBtn) {
            deleteLogoBtn.addEventListener('click', async () => {
                const selectedLogo = logoSelect.value;
                if (!selectedLogo) return;

                if (!confirm(`Are you sure you want to delete "${selectedLogo}"?`)) {
                    return;
                }

                try {
                    const response = await fetch('/api/qr/logo.php', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ filename: selectedLogo })
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Remove from dropdown
                        const option = logoSelect.querySelector(`option[value="${selectedLogo}"]`);
                        if (option) option.remove();

                        // Reset selection
                        logoSelect.value = '';
                        
                        // Hide preview and delete button
                        if (logoPreview) logoPreview.style.display = 'none';
                        if (deleteLogoBtn) deleteLogoBtn.style.display = 'none';

                        // Trigger preview update
                        this.schedulePreviewUpdate();

                        this.showMessage('Logo deleted successfully!', 'success');
                    } else {
                        throw new Error(result.message || 'Delete failed');
                    }
                } catch (error) {
                    console.error('Logo delete error:', error);
                    this.showMessage('Failed to delete logo: ' + error.message, 'danger');
                }
            });
        }
    }

    updateQRTypeVisibility() {
        const qrType = document.getElementById('qrType')?.value || 'static';
        
        // Check if this is a coming soon type
        const comingSoonTypes = ['promotion', 'cross_promo', 'stackable'];
        if (comingSoonTypes.includes(qrType)) {
            this.showEmptyState('This QR type is coming soon! Please select a different type.');
            return;
        }
        
        // Hide all dynamic fields first
        const fields = ['urlFields', 'campaignFields', 'machineFields', 'promotionFields', 'machinePromotionFields'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        // Clear required attributes
        const inputs = ['url', 'campaign_id', 'machine_name', 'promotion_id'];
        inputs.forEach(name => {
            const input = document.querySelector(`[name="${name}"]`);
            if (input) input.removeAttribute('required');
        });

        // Show appropriate fields and set required attributes
        switch (qrType) {
            case 'static':
            case 'dynamic':
                this.showField('urlFields');
                this.setRequired('url');
                break;
            
            case 'dynamic_voting':
                this.showField('campaignFields');
                this.setRequired('campaign_id');
                break;
            
            case 'dynamic_vending':
                this.showField('campaignFields');
                this.showField('machineFields');
                this.setRequired('campaign_id');
                this.setRequired('machine_name');
                break;
            
            case 'machine_sales':
                this.showField('promotionFields');
                // Set required for machine name field in promotionFields
                const machineNameSales = document.querySelector('input[name="machine_name_sales"]');
                if (machineNameSales) machineNameSales.setAttribute('required', 'required');
                break;
                
            case 'promotion':
                this.showField('promotionFields');
                // Set required for machine name field in promotionFields
                const machineNamePromo = document.querySelector('input[name="machine_name_sales"]');
                if (machineNamePromo) machineNamePromo.setAttribute('required', 'required');
                break;
        }

        // Trigger preview update
        this.schedulePreviewUpdate();
    }

    showField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) field.style.display = 'block';
    }

    setRequired(inputName) {
        const input = document.querySelector(`[name="${inputName}"]`);
        if (input) input.setAttribute('required', 'required');
    }

    enableLivePreview() {
        if (!this.form) return;

        // Add event listeners for live preview
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            ['change', 'input', 'keyup'].forEach(event => {
                input.addEventListener(event, () => {
                    this.schedulePreviewUpdate();
                });
            });
        });

        console.log('Live preview enabled');
    }

    schedulePreviewUpdate() {
        // Clear existing timeout
        if (this.previewTimeout) {
            clearTimeout(this.previewTimeout);
        }

        // Schedule new update
        this.previewTimeout = setTimeout(() => {
            this.updatePreview();
        }, 300); // 300ms debounce
    }

    async updatePreview() {
        if (!this.form) return;

        try {
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());

            // Validate basic requirements
            if (!this.validatePreviewData(data)) {
                this.showEmptyState();
                return;
            }

            // Generate content based on QR type
            const content = this.generateContentForType(data);
            if (!content) {
                this.showEmptyState('Please fill in all required fields');
                return;
            }

            // Add content to data
            data.content = content;

            // Include advanced features
            this.processAdvancedFeatures(data);

            console.log('Updating preview with data:', data);

            // Call preview API
            const response = await fetch('/api/qr/preview.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            if (!result.success) {
                if (result.message && result.message.includes('Authentication required')) {
                    this.showEmptyState('Please log in to see preview');
                    return;
                }
                throw new Error(result.message || 'Preview generation failed');
            }

            // Update preview with new image
            this.updatePreviewImage(result.preview_url || result.url);
            
            // Store for download
            this.currentQRCode = {
                qr_code_url: result.url || result.preview_url,
                preview_url: result.preview_url || result.url
            };

            // Show download button
            if (this.downloadBtn) {
                this.downloadBtn.style.display = 'inline-block';
            }

            // Update preview info
            this.updatePreviewInfo(data);

        } catch (error) {
            console.error('Preview error:', error);
            this.showEmptyState('Preview failed: ' + error.message);
        }
    }

    validatePreviewData(data) {
        const qrType = data.qr_type || 'static';
        
        // Type-specific validation
        switch (qrType) {
            case 'static':
            case 'dynamic':
                return !!data.url?.trim();
            
            case 'dynamic_voting':
                return !!data.campaign_id;
            
            case 'dynamic_vending':
                return !!(data.campaign_id && data.machine_name?.trim());
            
            case 'machine_sales':
                const machineNameSales = data.machine_name_sales || data.machine_name_promotion || data.machine_name;
                return !!(machineNameSales?.trim());
                
            case 'spin_wheel':
                return !!data.spin_wheel_id;
                
            case 'pizza_tracker':
                return !!data.pizza_tracker_id;
                
            default:
                return false;
        }
    }

    generateContentForType(data) {
        const qrType = data.qr_type || 'static';
        const APP_URL = window.APP_URL || 'https://revenueqr.sharedvaluevending.com';
        
        switch (qrType) {
            case 'static':
            case 'dynamic':
                return data.url;
            
            case 'dynamic_voting':
                return `${APP_URL}/vote.php?campaign_id=${data.campaign_id}`;
            
            case 'dynamic_vending':
                return `${APP_URL}/vote.php?campaign_id=${data.campaign_id}&machine=${encodeURIComponent(data.machine_name)}`;
            
            case 'machine_sales':
                var machineNameSales = data.machine_name_sales || data.machine_name_promotion || data.machine_name;
                return `${APP_URL}/public/promotions.php?machine=${encodeURIComponent(machineNameSales)}`;
                
            case 'promotion':
                var machineNamePromo = data.machine_name_sales || data.machine_name_promotion || data.machine_name;
                return `${APP_URL}/public/promotions.php?machine=${encodeURIComponent(machineNamePromo)}&view=promotions`;
                
            case 'spin_wheel':
                return `${APP_URL}/public/spin-wheel.php?wheel_id=${data.spin_wheel_id}`;
                
            case 'pizza_tracker':
                return `${APP_URL}/public/pizza-tracker.php?tracker_id=${data.pizza_tracker_id}&source=qr`;
                
            default:
                return null;
        }
    }

    processAdvancedFeatures(data) {
        // Process text features
        if (data.enable_label && data.label_text) {
            // Label options are already in the form data
        } else {
            delete data.label_text;
        }

        if (data.enable_bottom_text && data.bottom_text) {
            // Bottom text options are already in the form data
        } else {
            delete data.bottom_text;
        }

        // Process module shape
        if (!data.enable_module_shape) {
            delete data.module_shape;
            delete data.module_size;
        }

        // Process gradient
        if (data.enable_gradient) {
            data.gradient_type = data.gradient_type || 'linear';
        } else {
            data.gradient_type = 'none';
        }

        // Process shadow
        if (data.enable_shadow) {
            data.shadow = true;
        } else {
            delete data.shadow_color;
            delete data.shadow_blur;
            delete data.shadow_offset_x;
            delete data.shadow_offset_y;
        }
    }

    updatePreviewImage(imageUrl) {
        if (!this.preview) return;

        // Clear current content
        this.preview.innerHTML = '';

        // Create image element
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = 'QR Code Preview';
        img.style.maxWidth = '100%';
        img.style.maxHeight = '400px';
        img.style.borderRadius = '4px';
        img.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';

        // Add loading effect
        img.style.opacity = '0';
        img.onload = () => {
            img.style.transition = 'opacity 0.3s ease';
            img.style.opacity = '1';
        };

        this.preview.appendChild(img);
    }

    showEmptyState(message = null) {
        if (!this.preview) return;

        this.preview.innerHTML = `
            <div class="qr-empty-state">
                <i class="bi bi-qr-code"></i>
                <h6>QR Code Preview</h6>
                <p class="small mb-0">${message || 'Fill in the form to see a live preview'}</p>
            </div>
        `;

        // Hide download button
        if (this.downloadBtn) {
            this.downloadBtn.style.display = 'none';
        }
    }

    updatePreviewInfo(data) {
        const infoEl = document.getElementById('previewInfo');
        if (!infoEl) return;

        const qrType = data.qr_type || 'static';
        const size = data.size || 400;

        let info = `<strong>Type:</strong> ${qrType.replace('_', ' ').toUpperCase()}<br>`;
        info += `<strong>Size:</strong> ${size}×${size}px<br>`;
        info += `<strong>Error Correction:</strong> ${data.error_correction_level || 'H'}<br>`;
        
        if (data.content) {
            const shortContent = data.content.length > 50 ? 
                data.content.substring(0, 50) + '...' : 
                data.content;
            info += `<strong>Content:</strong> ${shortContent}`;
        }

        infoEl.innerHTML = info;
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

            // Generate content
            const content = this.generateContentForType(data);
            if (!content) {
                throw new Error('Please fill in all required fields');
            }

            data.content = content;
            this.processAdvancedFeatures(data);

            // Set preview to false for final generation
            data.preview = false;

            console.log('Generating final QR code with data:', data);

            const response = await fetch('/api/qr/generate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'QR code generation failed');
            }

            // Update preview with final QR code
            this.updatePreviewImage(result.data.qr_code_url);
            
            // Store for download
            this.currentQRCode = {
                qr_code_url: result.data.qr_code_url,
                preview_url: result.data.qr_code_url
            };

            // Show download button
            if (this.downloadBtn) {
                this.downloadBtn.style.display = 'inline-block';
            }

            this.showMessage('QR code generated successfully!', 'success');

        } catch (error) {
            console.error('Generation error:', error);
            this.showMessage(error.message, 'danger');
        }
    }

    async handleDownload() {
        if (!this.currentQRCode) {
            this.showMessage('No QR code to download', 'warning');
            return;
        }

        try {
            const qrUrl = this.currentQRCode.qr_code_url;

            if (qrUrl.startsWith('data:')) {
                // Handle data URI
                this.downloadDataUri(qrUrl, 'qr-code.png');
            } else {
                // Handle regular URL
                const response = await fetch(qrUrl);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const blob = await response.blob();
                this.downloadBlob(blob, 'qr-code.png');
            }

            this.showMessage('QR code downloaded successfully!', 'success');

        } catch (error) {
            console.error('Download error:', error);
            // Fallback: try to open in new tab
            if (this.currentQRCode.qr_code_url) {
                window.open(this.currentQRCode.qr_code_url, '_blank');
                this.showMessage('Download failed, but QR code opened in new tab. Right-click to save.', 'warning');
            } else {
                this.showMessage('Failed to download QR code: ' + error.message, 'danger');
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

    resetForm() {
        if (!this.form) return;

        this.form.reset();
        
        // Reset advanced feature toggles
        const toggles = ['enableLabel', 'enableBottomText', 'enableModuleShape', 'enableGradient', 'enableShadow'];
        toggles.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.checked = false;
                el.dispatchEvent(new Event('change'));
            }
        });

        // Reset range input displays
        const ranges = document.querySelectorAll('input[type="range"]');
        ranges.forEach(range => {
            range.dispatchEvent(new Event('input'));
        });

        // Reset color displays
        const colorInputs = ['foregroundColor', 'backgroundColor'];
        colorInputs.forEach(id => {
            const colorEl = document.getElementById(id);
            const hexEl = document.getElementById(id.replace('Color', 'Hex'));
            if (colorEl && hexEl) {
                hexEl.value = colorEl.value;
            }
        });

        // Clear preview
        this.showEmptyState();
        
        // Update field visibility
        this.updateQRTypeVisibility();

        this.showMessage('Form reset successfully', 'info');
    }

    fillTestData() {
        // Set basic data
        const qrTypeSelect = document.getElementById('qrType');
        const urlInput = document.querySelector('input[name="url"]');
        const locationInput = document.querySelector('input[name="location"]');

        if (qrTypeSelect) qrTypeSelect.value = 'static';
        if (urlInput) urlInput.value = 'https://example.com/test';
        if (locationInput) locationInput.value = 'Test Location';

        // Enable and add label text
        const enableLabel = document.getElementById('enableLabel');
        const labelText = document.getElementById('labelText');
        const labelSizeRange = document.getElementById('labelSizeRange');
        
        if (enableLabel && labelText) {
            enableLabel.checked = true;
            enableLabel.dispatchEvent(new Event('change'));
            labelText.value = 'Scan for Info';
            if (labelSizeRange) {
                labelSizeRange.value = 20;
                labelSizeRange.dispatchEvent(new Event('input'));
            }
        }

        // Enable and add bottom text
        const enableBottomText = document.getElementById('enableBottomText');
        const bottomText = document.getElementById('bottomText');
        const bottomSizeRange = document.getElementById('bottomSizeRange');
        
        if (enableBottomText && bottomText) {
            enableBottomText.checked = true;
            enableBottomText.dispatchEvent(new Event('change'));
            bottomText.value = 'example.com';
            if (bottomSizeRange) {
                bottomSizeRange.value = 16;
                bottomSizeRange.dispatchEvent(new Event('input'));
            }
        }

        // Enable gradient for demo
        const enableGradient = document.getElementById('enableGradient');
        const gradientStart = document.querySelector('input[name="gradient_start"]');
        const gradientEnd = document.querySelector('input[name="gradient_end"]');
        
        if (enableGradient) {
            enableGradient.checked = true;
            enableGradient.dispatchEvent(new Event('change'));
            if (gradientStart) gradientStart.value = '#2196F3';
            if (gradientEnd) gradientEnd.value = '#21CBF3';
        }

        // Enable shadow for demo
        const enableShadow = document.getElementById('enableShadow');
        if (enableShadow) {
            enableShadow.checked = true;
            enableShadow.dispatchEvent(new Event('change'));
        }

        // Update visibility and trigger preview
        this.updateQRTypeVisibility();
        this.schedulePreviewUpdate();

        this.showMessage('Test data filled with text and advanced features enabled', 'info');
    }

    async loadLogos() {
        try {
            const response = await fetch('/api/qr/logo.php');
            const result = await response.json();
            
            if (result.success && result.data && result.data.logos) {
                const logoSelect = document.getElementById('logoSelect');
                if (logoSelect) {
                    result.data.logos.forEach(logo => {
                        const option = document.createElement('option');
                        option.value = logo.filename;
                        option.textContent = logo.filename;
                        logoSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.warn('Failed to load logos:', error);
        }
    }

    showMessage(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert before the form
        if (this.form && this.form.parentNode) {
            this.form.parentNode.insertBefore(alertDiv, this.form);
        } else {
            document.body.appendChild(alertDiv);
        }

        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 3000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const qrGenerator = new QRGeneratorV2();
    qrGenerator.init();
    
    // Make globally accessible for debugging
    window.qrGeneratorV2 = qrGenerator;
    
    console.log('QR Generator V2 ready');
}); 