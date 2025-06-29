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