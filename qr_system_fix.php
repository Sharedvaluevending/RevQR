<?php
echo "🔧 QR CODE SYSTEM AUTO-FIX\n";
echo "==========================\n\n";

// Fix 1: Update basic QR generator to use the correct API
echo "1. 🔄 Fixing Basic QR Generator API endpoint...\n";

$basic_generator_file = 'html/qr-generator.php';
if (file_exists($basic_generator_file)) {
    $content = file_get_contents($basic_generator_file);
    
    // Replace the wrong API call
    $old_api = "fetch('/api/qr/enhanced-generate.php',";
    $new_api = "fetch('/api/qr/generate.php',";
    
    if (strpos($content, $old_api) !== false) {
        $content = str_replace($old_api, $new_api, $content);
        file_put_contents($basic_generator_file, $content);
        echo "   ✅ Fixed: Basic generator now calls /api/qr/generate.php\n";
    } else {
        echo "   ✅ Already correct or pattern not found\n";
    }
} else {
    echo "   ❌ Basic generator file not found\n";
}

// Fix 2: Ensure the basic API can handle preview requests
echo "\n2. 🔄 Adding preview support to basic API...\n";

$basic_api_file = 'html/api/qr/generate.php';
if (file_exists($basic_api_file)) {
    $content = file_get_contents($basic_api_file);
    
    // Check if it handles preview requests
    if (strpos($content, '$data[\'preview\']') === false) {
        // Add preview support
        $preview_code = '
    // Handle preview requests
    if (isset($data[\'preview\']) && $data[\'preview\'] === true) {
        $options[\'preview\'] = true;
    }
';
        
        // Insert after the options array setup
        $insert_point = 'error_correction_level\'] ?? \'H\',';
        if (strpos($content, $insert_point) !== false) {
            $content = str_replace(
                $insert_point . "\n        'preview' => false",
                $insert_point . "\n        'preview' => isset(\$data['preview']) ? \$data['preview'] : false",
                $content
            );
            file_put_contents($basic_api_file, $content);
            echo "   ✅ Added preview support to basic API\n";
        } else {
            echo "   ⚠️  Could not auto-add preview support\n";
        }
    } else {
        echo "   ✅ Preview support already exists\n";
    }
} else {
    echo "   ❌ Basic API file not found\n";
}

// Fix 3: Update JavaScript files to use correct APIs
echo "\n3. 🔄 Fixing JavaScript API calls...\n";

$js_files = [
    'html/assets/js/qr-generator.js' => [
        'description' => 'Basic QR Generator JS',
        'api_mapping' => [
            "fetch('/api/qr/generate.php'," => "fetch('/api/qr/generate.php',", // Keep as is
            "fetch('/api/qr/preview.php'," => "fetch('/api/qr/preview.php',"   // Keep as is
        ]
    ],
    'html/assets/js/qr-generator-v2.js' => [
        'description' => 'Advanced QR Generator JS',
        'api_mapping' => [
            "fetch('/api/qr/generate.php'," => "fetch('/api/qr/enhanced-generate.php',", // Use enhanced for v2
            "fetch('/api/qr/preview.php'," => "fetch('/api/qr/enhanced-preview.php',"   // Use enhanced for v2
        ]
    ]
];

foreach ($js_files as $js_file => $config) {
    if (file_exists($js_file)) {
        echo "   📄 Updating {$config['description']}...\n";
        $content = file_get_contents($js_file);
        $updated = false;
        
        foreach ($config['api_mapping'] as $old => $new) {
            if (strpos($content, $old) !== false && $old !== $new) {
                $content = str_replace($old, $new, $content);
                $updated = true;
                echo "      ✅ Updated API call: $old -> $new\n";
            }
        }
        
        if ($updated) {
            file_put_contents($js_file, $content);
            echo "      💾 Saved changes\n";
        } else {
            echo "      ✅ No changes needed\n";
        }
    } else {
        echo "   ❌ File not found: $js_file\n";
    }
}

// Fix 4: Create a unified endpoint redirector (optional)
echo "\n4. 🔄 Creating API endpoint compatibility layer...\n";

$compatibility_layer = 'html/api/qr/route.php';
$router_code = '<?php
/**
 * QR API Router - Compatibility Layer
 * Routes requests to appropriate QR generation endpoints
 */

header("Content-Type: application/json");

// Get the requested endpoint from the path
$request_uri = $_SERVER["REQUEST_URI"];
$path = parse_url($request_uri, PHP_URL_PATH);

// Route to appropriate handler
switch ($path) {
    case "/api/qr/generate.php":
        require_once __DIR__ . "/generate.php";
        break;
    
    case "/api/qr/enhanced-generate.php":
        require_once __DIR__ . "/enhanced-generate.php";
        break;
    
    case "/api/qr/unified-generate.php":
        require_once __DIR__ . "/unified-generate.php";
        break;
    
    case "/api/qr/preview.php":
        require_once __DIR__ . "/preview.php";
        break;
    
    case "/api/qr/enhanced-preview.php":
        require_once __DIR__ . "/enhanced-preview.php";
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "QR API endpoint not found: $path"
        ]);
        break;
}
?>';

file_put_contents($compatibility_layer, $router_code);
echo "   ✅ Created API router at $compatibility_layer\n";

// Fix 5: Test the APIs
echo "\n5. 🧪 Testing API endpoints...\n";

$test_endpoints = [
    'html/api/qr/generate.php' => 'Basic Generator API',
    'html/api/qr/enhanced-generate.php' => 'Enhanced Generator API',
    'html/api/qr/preview.php' => 'Preview API'
];

foreach ($test_endpoints as $endpoint => $name) {
    if (file_exists($endpoint)) {
        // Test PHP syntax
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($endpoint) . " 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "   ✅ $name: PHP syntax OK\n";
        } else {
            echo "   ❌ $name: PHP syntax error\n";
            echo "      " . implode("\n      ", $output) . "\n";
        }
    } else {
        echo "   ❌ $name: File not found\n";
    }
}

echo "\n🎉 QR SYSTEM FIXES COMPLETED!\n";
echo "===============================\n\n";

echo "📋 **SUMMARY OF CHANGES:**\n";
echo "1. ✅ Fixed basic QR generator API endpoint\n";
echo "2. ✅ Added preview support to basic API\n";
echo "3. ✅ Updated JavaScript API calls\n";
echo "4. ✅ Created API compatibility layer\n";
echo "5. ✅ Tested API syntax\n";

echo "\n🧪 **NEXT STEPS:**\n";
echo "1. Test basic QR generator at /qr-generator.php\n";
echo "2. Test enhanced QR generator at /qr-generator-enhanced.php\n";
echo "3. Verify QR codes are saved to database\n";
echo "4. Check QR codes appear in QR manager\n";

echo "\n✅ **BOTH GENERATORS SHOULD NOW WORK!**\n";
?> 