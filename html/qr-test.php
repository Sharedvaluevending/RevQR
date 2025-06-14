<?php
require_once __DIR__ . "/core/config.php";
require_once __DIR__ . "/core/session.php";
require_once __DIR__ . "/core/auth.php";

if (!is_logged_in()) {
    header("Location: /login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Generator Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1>🧪 QR Generator Test</h1>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Generate QR Code</h3>
                <form id="testForm">
                    <div class="mb-3">
                        <label class="form-label">QR Type</label>
                        <select class="form-select" id="qrType">
                            <option value="static">Static QR Code</option>
                            <option value="dynamic">Dynamic QR Code</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL</label>
                        <input type="url" class="form-control" id="url" value="https://example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Size</label>
                        <input type="range" class="form-range" id="size" min="200" max="600" value="400">
                        <span id="sizeValue">400px</span>
                    </div>
                    <button type="button" onclick="testGenerate()" class="btn btn-primary">
                        Generate QR Code
                    </button>
                    <button type="button" onclick="testPreview()" class="btn btn-secondary">
                        Preview Only
                    </button>
                </form>
            </div>
            <div class="col-md-6">
                <h3>Preview</h3>
                <div id="preview" class="border p-4 text-center">
                    <p class="text-muted">Click preview to see QR code</p>
                </div>
                <div id="results" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById("size").addEventListener("input", function() {
        document.getElementById("sizeValue").textContent = this.value + "px";
    });

    function testPreview() {
        const url = document.getElementById("url").value;
        const size = parseInt(document.getElementById("size").value);
        
        if (!url) {
            alert("Please enter a URL");
            return;
        }
        
        const preview = document.getElementById("preview");
        preview.innerHTML = "<p>Generating preview...</p>";
        
        QRCode.toCanvas(url, {
            width: size,
            height: size,
            margin: 2,
            errorCorrectionLevel: "H"
        }, function (error, canvas) {
            if (error) {
                preview.innerHTML = "<p class=\"text-danger\">Error: " + error.message + "</p>";
                return;
            }
            
            preview.innerHTML = "";
            preview.appendChild(canvas);
        });
    }

    function testGenerate() {
        const formData = {
            qr_type: document.getElementById("qrType").value,
            content: document.getElementById("url").value,
            size: parseInt(document.getElementById("size").value),
            foreground_color: "#000000",
            background_color: "#FFFFFF",
            error_correction_level: "H"
        };
        
        const results = document.getElementById("results");
        results.innerHTML = "<p>Generating QR code...</p>";
        
        fetch("/api/qr/generate.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                results.innerHTML = `
                    <div class="alert alert-success">
                        <h5>✅ Success!</h5>
                        <p>QR Code generated successfully.</p>
                        <a href="${result.data.qr_code_url}" target="_blank" class="btn btn-primary btn-sm">View QR Code</a>
                        <a href="${result.data.qr_code_url}" download="qr-code.png" class="btn btn-secondary btn-sm">Download</a>
                    </div>
                `;
            } else {
                results.innerHTML = `<div class="alert alert-danger">❌ Error: ${result.message}</div>`;
            }
        })
        .catch(error => {
            results.innerHTML = `<div class="alert alert-danger">❌ Error: ${error.message}</div>`;
        });
    }
    </script>
</body>
</html>