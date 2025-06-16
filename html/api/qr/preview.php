<?php
// QR Preview API - Returns base64 encoded image
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

try {
    require_once __DIR__ . "/../../includes/QRGenerator.php";
    
    $generator = new QRGenerator();
    
    $options = [
        "type" => $input["qr_type"] ?? "static",
        "content" => $input["content"] ?? "https://example.com",
        "size" => intval($input["size"] ?? 300),
        "foreground_color" => $input["foreground_color"] ?? "#000000",
        "background_color" => $input["background_color"] ?? "#FFFFFF",
        "error_correction_level" => $input["error_correction_level"] ?? "H",
        "preview" => true  // Enable preview mode
    ];
    
    // Add logo if specified
    if (!empty($input["logo"])) {
        $options["logo"] = $input["logo"];
    }
    
    // Add text labels
    if (!empty($input["enable_label"]) && !empty($input["label_text"])) {
        $options["enable_label"] = true;
        $options["label_text"] = $input["label_text"];
        $options["label_font"] = $input["label_font"] ?? "Arial";
        $options["label_size"] = intval($input["label_size"] ?? 16);
        $options["label_color"] = $input["label_color"] ?? "#000000";
        $options["label_alignment"] = $input["label_alignment"] ?? "center";
        $options["label_bold"] = !empty($input["label_bold"]);
        $options["label_underline"] = !empty($input["label_underline"]);
        $options["label_shadow"] = !empty($input["label_shadow"]);
        $options["label_outline"] = !empty($input["label_outline"]);
        $options["label_shadow_color"] = $input["label_shadow_color"] ?? "#000000";
        $options["label_outline_color"] = $input["label_outline_color"] ?? "#000000";
    }
    
    // Add bottom text
    if (!empty($input["enable_bottom_text"]) && !empty($input["bottom_text"])) {
        $options["enable_bottom_text"] = true;
        $options["bottom_text"] = $input["bottom_text"];
        $options["bottom_font"] = $input["bottom_font"] ?? "Arial";
        $options["bottom_size"] = intval($input["bottom_size"] ?? 14);
        $options["bottom_color"] = $input["bottom_color"] ?? "#666666";
        $options["bottom_alignment"] = $input["bottom_alignment"] ?? "center";
        $options["bottom_bold"] = !empty($input["bottom_bold"]);
        $options["bottom_underline"] = !empty($input["bottom_underline"]);
        $options["bottom_shadow"] = !empty($input["bottom_shadow"]);
        $options["bottom_outline"] = !empty($input["bottom_outline"]);
        $options["bottom_shadow_color"] = $input["bottom_shadow_color"] ?? "#000000";
        $options["bottom_outline_color"] = $input["bottom_outline_color"] ?? "#000000";
    }
    
    $result = $generator->generate($options);
    
    if ($result["success"]) {
        // Extract base64 data from data URL (remove "data:image/png;base64," prefix)
        $dataUrl = $result["url"] ?? $result["preview_url"] ?? "";
        $base64Data = "";
        
        if (strpos($dataUrl, "data:image/png;base64,") === 0) {
            $base64Data = substr($dataUrl, strlen("data:image/png;base64,"));
        } elseif (strpos($dataUrl, "data:image/jpeg;base64,") === 0) {
            $base64Data = substr($dataUrl, strlen("data:image/jpeg;base64,"));
        } else {
            $base64Data = $dataUrl; // Assume it's already base64
        }
        
        echo json_encode([
            "success" => true,
            "preview_data" => $base64Data,
            "data_url" => $dataUrl,
            "content" => $options["content"]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => $result["message"] ?? "Preview generation failed"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>