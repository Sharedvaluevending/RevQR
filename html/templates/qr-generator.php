<!-- QR Code Generator Form -->
<div class="qr-generator-container">
    <form id="qrGeneratorForm" class="qr-form">
        <!-- Basic Settings -->
        <div class="form-section">
            <h3>Basic Settings</h3>
            <div class="form-group">
                <label for="qrType">QR Code Type</label>
                <select id="qrType" name="qr_type" required>
                    <option value="static">Static QR Code</option>
                    <option value="dynamic">Dynamic QR Code</option>
                    <option value="dynamic_voting">Dynamic Voting QR Code</option>
                            <option value="promotion">Promotion QR Code</option>
        <option value="vending_discount_store">Vending Machine Discount Store QR Code</option>
        <option value="machine_sales">Vending Machine Sales QR Code</option>
                    <option value="cross_promo" disabled>Cross-Promotion QR Code (Coming Soon)</option>
                    <option value="stackable" disabled>Stackable QR Code (Coming Soon)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="qrContent">Content</label>
                <input type="text" id="qrContent" name="content" required>
            </div>
            
            <!-- Dynamic Fields -->
            <div id="urlFields" class="form-group" style="display: none;">
                <label for="urlInput">URL</label>
                <input type="url" id="urlInput" name="url" placeholder="https://example.com">
            </div>

            <div id="campaignFields" class="form-group" style="display: none;">
                <label for="campaignSelect">Campaign</label>
                <select id="campaignSelect" name="campaign_id">
                    <option value="">Select a campaign</option>
                    <!-- Campaign options would be populated by PHP -->
                </select>
            </div>

            <div id="machineFields" class="form-group" style="display: none;">
                <label for="machineInput">Machine Name</label>
                <input type="text" id="machineInput" name="machine_name" placeholder="Enter machine name">
            </div>

            <div id="promotionFields" class="form-group" style="display: none;">
                <label for="promotionSelect">Promotion</label>
                <select id="promotionSelect" name="promotion_id">
                    <option value="">Select a promotion</option>
                    <!-- Promotion options would be populated by PHP -->
                </select>
            </div>

            <div id="machinePromotionFields" class="form-group" style="display: none;">
                <label for="machinePromotionMachine">Machine Name</label>
                <input type="text" id="machinePromotionMachine" name="machine_name" placeholder="Enter machine name">
                <label for="machinePromotionSelect">Promotion</label>
                <select id="machinePromotionSelect" name="promotion_id">
                    <option value="">Select a promotion</option>
                    <!-- Promotion options would be populated by PHP -->
                </select>
            </div>

            <div class="form-group">
                <label for="qrSize">Size</label>
                <input type="number" id="qrSize" name="size" min="100" max="1000" value="300">
            </div>
        </div>

        <!-- Module Customization -->
        <div class="form-section">
            <h3>Module Customization</h3>
            <div class="form-group">
                <label for="moduleShape">Module Shape</label>
                <select id="moduleShape" name="module_shape">
                    <option value="square">Square</option>
                    <option value="circle">Circle</option>
                    <option value="rounded">Rounded</option>
                    <option value="diamond">Diamond</option>
                </select>
            </div>
            <div class="form-group">
                <label for="moduleSize">Module Size</label>
                <input type="range" id="moduleSize" name="module_size" min="1" max="5" value="1">
            </div>
            <div class="form-group">
                <label for="moduleSpacing">Module Spacing</label>
                <input type="range" id="moduleSpacing" name="module_spacing" min="0" max="10" value="0">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="moduleGlow" name="module_glow">
                    Enable Module Glow
                </label>
            </div>
            <div class="form-group module-glow-options" style="display: none;">
                <label for="moduleGlowColor">Glow Color</label>
                <input type="color" id="moduleGlowColor" name="module_glow_color" value="#000000">
                <label for="moduleGlowIntensity">Glow Intensity</label>
                <input type="range" id="moduleGlowIntensity" name="module_glow_intensity" min="1" max="10" value="5">
            </div>
        </div>

        <!-- Gradient Options -->
        <div class="form-section">
            <h3>Gradient Options</h3>
            <div class="form-group">
                <label for="gradientType">Gradient Type</label>
                <select id="gradientType" name="gradient_type">
                    <option value="none">None</option>
                    <option value="linear">Linear</option>
                    <option value="radial">Radial</option>
                    <option value="conic">Conic</option>
                </select>
            </div>
            <div class="form-group gradient-options" style="display: none;">
                <label for="gradientStart">Start Color</label>
                <input type="color" id="gradientStart" name="gradient_start" value="#000000">
                <label for="gradientEnd">End Color</label>
                <input type="color" id="gradientEnd" name="gradient_end" value="#0000FF">
                <label for="gradientAngle">Angle</label>
                <input type="range" id="gradientAngle" name="gradient_angle" min="0" max="360" value="45">
                <label for="gradientOpacity">Opacity</label>
                <input type="range" id="gradientOpacity" name="gradient_opacity" min="0" max="1" step="0.1" value="1">
            </div>
        </div>

        <!-- Eye Customization -->
        <div class="form-section">
            <h3>Eye Customization</h3>
            <div class="form-group">
                <label for="eyeStyle">Eye Style</label>
                <select id="eyeStyle" name="eye_style">
                    <option value="square">Square</option>
                    <option value="circle">Circle</option>
                    <option value="rounded">Rounded</option>
                    <option value="diamond">Diamond</option>
                </select>
            </div>
            <div class="form-group">
                <label for="eyeColor">Eye Color</label>
                <input type="color" id="eyeColor" name="eye_color" value="#000000">
            </div>
            <div class="form-group">
                <label for="eyeSize">Eye Size</label>
                <input type="range" id="eyeSize" name="eye_size" min="1" max="5" value="1">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="eyeBorder" name="eye_border">
                    Enable Eye Border
                </label>
            </div>
            <div class="form-group eye-border-options" style="display: none;">
                <label for="eyeBorderColor">Border Color</label>
                <input type="color" id="eyeBorderColor" name="eye_border_color" value="#000000">
                <label for="eyeBorderWidth">Border Width</label>
                <input type="range" id="eyeBorderWidth" name="eye_border_width" min="1" max="5" value="1">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="eyeGlow" name="eye_glow">
                    Enable Eye Glow
                </label>
            </div>
            <div class="form-group eye-glow-options" style="display: none;">
                <label for="eyeGlowColor">Glow Color</label>
                <input type="color" id="eyeGlowColor" name="eye_glow_color" value="#000000">
                <label for="eyeGlowIntensity">Glow Intensity</label>
                <input type="range" id="eyeGlowIntensity" name="eye_glow_intensity" min="1" max="10" value="5">
            </div>
        </div>

        <!-- Frame Customization -->
        <div class="form-section">
            <h3>Frame Customization</h3>
            <div class="form-group">
                <label for="frameStyle">Frame Style</label>
                <select id="frameStyle" name="frame_style">
                    <option value="none">None</option>
                    <option value="solid">Solid</option>
                    <option value="dashed">Dashed</option>
                    <option value="dotted">Dotted</option>
                </select>
            </div>
            <div class="form-group frame-options" style="display: none;">
                <label for="frameColor">Frame Color</label>
                <input type="color" id="frameColor" name="frame_color" value="#000000">
                <label for="frameWidth">Frame Width</label>
                <input type="range" id="frameWidth" name="frame_width" min="1" max="10" value="2">
                <label for="frameRadius">Frame Radius</label>
                <input type="range" id="frameRadius" name="frame_radius" min="0" max="20" value="5">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="frameGlow" name="frame_glow">
                    Enable Frame Glow
                </label>
            </div>
            <div class="form-group frame-glow-options" style="display: none;">
                <label for="frameGlowColor">Glow Color</label>
                <input type="color" id="frameGlowColor" name="frame_glow_color" value="#000000">
                <label for="frameGlowIntensity">Glow Intensity</label>
                <input type="range" id="frameGlowIntensity" name="frame_glow_intensity" min="1" max="10" value="5">
            </div>
        </div>

        <!-- Text Options -->
        <div class="form-section">
            <h3>Text Options</h3>
            <div class="form-group">
                <label for="labelText">Label Text</label>
                <input type="text" id="labelText" name="label_text">
            </div>
            <div class="form-group label-options" style="display: none;">
                <label for="labelFont">Font</label>
                <select id="labelFont" name="label_font">
                    <option value="Arial">Arial</option>
                    <option value="Helvetica">Helvetica</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Courier New">Courier New</option>
                </select>
                <label for="labelSize">Size</label>
                <input type="number" id="labelSize" name="label_size" min="8" max="72" value="12">
                <label for="labelColor">Color</label>
                <input type="color" id="labelColor" name="label_color" value="#000000">
                <label for="labelAlignment">Alignment</label>
                <select id="labelAlignment" name="label_alignment">
                    <option value="left">Left</option>
                    <option value="center">Center</option>
                    <option value="right">Right</option>
                </select>
                <label for="labelRotation">Rotation</label>
                <input type="range" id="labelRotation" name="label_rotation" min="-180" max="180" value="0">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="labelGlow" name="label_glow">
                    Enable Label Glow
                </label>
            </div>
            <div class="form-group label-glow-options" style="display: none;">
                <label for="labelGlowColor">Glow Color</label>
                <input type="color" id="labelGlowColor" name="label_glow_color" value="#000000">
                <label for="labelGlowIntensity">Glow Intensity</label>
                <input type="range" id="labelGlowIntensity" name="label_glow_intensity" min="1" max="10" value="5">
            </div>
        </div>

        <!-- Bottom Text Options -->
        <div class="form-section">
            <h3>Bottom Text Options</h3>
            <div class="form-group">
                <label for="bottomText">Bottom Text</label>
                <input type="text" id="bottomText" name="bottom_text">
            </div>
            <div class="form-group bottom-text-options" style="display: none;">
                <label for="bottomFont">Font</label>
                <select id="bottomFont" name="bottom_font">
                    <option value="Arial">Arial</option>
                    <option value="Helvetica">Helvetica</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Courier New">Courier New</option>
                </select>
                <label for="bottomSize">Size</label>
                <input type="number" id="bottomSize" name="bottom_size" min="8" max="72" value="12">
                <label for="bottomColor">Color</label>
                <input type="color" id="bottomColor" name="bottom_color" value="#000000">
                <label for="bottomAlignment">Alignment</label>
                <select id="bottomAlignment" name="bottom_alignment">
                    <option value="left">Left</option>
                    <option value="center">Center</option>
                    <option value="right">Right</option>
                </select>
                <label for="bottomRotation">Rotation</label>
                <input type="range" id="bottomRotation" name="bottom_rotation" min="-180" max="180" value="0">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="bottomGlow" name="bottom_glow">
                    Enable Bottom Text Glow
                </label>
            </div>
            <div class="form-group bottom-glow-options" style="display: none;">
                <label for="bottomGlowColor">Glow Color</label>
                <input type="color" id="bottomGlowColor" name="bottom_glow_color" value="#000000">
                <label for="bottomGlowIntensity">Glow Intensity</label>
                <input type="range" id="bottomGlowIntensity" name="bottom_glow_intensity" min="1" max="10" value="5">
            </div>
        </div>

        <!-- Effects -->
        <div class="form-section">
            <h3>Effects</h3>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="shadow" name="shadow">
                    Enable Shadow
                </label>
            </div>
            <div class="form-group shadow-options" style="display: none;">
                <label for="shadowColor">Shadow Color</label>
                <input type="color" id="shadowColor" name="shadow_color" value="#000000">
                <label for="shadowBlur">Shadow Blur</label>
                <input type="range" id="shadowBlur" name="shadow_blur" min="0" max="20" value="5">
                <label for="shadowOffsetX">Shadow Offset X</label>
                <input type="range" id="shadowOffsetX" name="shadow_offset_x" min="-20" max="20" value="2">
                <label for="shadowOffsetY">Shadow Offset Y</label>
                <input type="range" id="shadowOffsetY" name="shadow_offset_y" min="-20" max="20" value="2">
                <label for="shadowOpacity">Shadow Opacity</label>
                <input type="range" id="shadowOpacity" name="shadow_opacity" min="0" max="1" step="0.1" value="0.5">
            </div>
        </div>

        <!-- Statistics -->
        <div class="form-section">
            <h3>Statistics</h3>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableStats" name="enable_stats">
                    Enable Statistics
                </label>
            </div>
            <div class="form-group stats-options" style="display: none;">
                <label for="statsDisplay">Display Type</label>
                <select id="statsDisplay" name="stats_display">
                    <option value="none">None</option>
                    <option value="basic">Basic</option>
                    <option value="detailed">Detailed</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
        </div>

        <!-- Preview and Generate -->
        <div class="form-section">
            <div class="preview-container">
                <div id="qrPreview" class="qr-preview"></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Generate QR Code</button>
                <button type="button" id="downloadBtn" class="btn btn-secondary" disabled>Download</button>
            </div>
        </div>
    </form>
</div>

<!-- Add necessary JavaScript -->
<script src="/assets/js/qr-generator.js?v=<?php echo time(); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form visibility toggles
    const toggles = {
        'moduleGlow': '.module-glow-options',
        'gradientType': '.gradient-options',
        'eyeBorder': '.eye-border-options',
        'eyeGlow': '.eye-glow-options',
        'frameStyle': '.frame-options',
        'frameGlow': '.frame-glow-options',
        'labelText': '.label-options',
        'labelGlow': '.label-glow-options',
        'bottomText': '.bottom-text-options',
        'bottomGlow': '.bottom-glow-options',
        'shadow': '.shadow-options',
        'enableStats': '.stats-options'
    };

    // Set up toggle handlers
    Object.entries(toggles).forEach(([id, targetClass]) => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', function() {
                const target = document.querySelector(targetClass);
                if (target) {
                    target.style.display = this.type === 'checkbox' ? 
                        (this.checked ? 'block' : 'none') :
                        (this.value !== 'none' ? 'block' : 'none');
                }
            });
        }
    });

    // Initialize QR generator
    const qrGenerator = new QRGenerator();
    qrGenerator.init();
});
</script>

<!-- Add necessary CSS -->
<style>
.qr-generator-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.form-section {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #666;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-group input[type="range"] {
    width: 100%;
}

.form-group input[type="color"] {
    width: 50px;
    height: 30px;
    padding: 0;
    border: none;
    border-radius: 4px;
}

.preview-container {
    text-align: center;
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.qr-preview {
    display: inline-block;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-actions {
    text-align: center;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    margin: 0 10px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive design */
@media (max-width: 768px) {
    .qr-generator-container {
        padding: 10px;
    }

    .form-section {
        padding: 15px;
    }

    .btn {
        width: 100%;
        margin: 10px 0;
    }
}
</style> 