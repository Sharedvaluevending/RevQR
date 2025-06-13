-- Fix QR Code URL Storage Inconsistency
-- This migration standardizes URL storage by populating the url field from meta.content

USE revenueqr;

-- Start transaction for safety
START TRANSACTION;

-- Update existing QR codes to populate url field from meta.content
UPDATE qr_codes 
SET url = JSON_UNQUOTE(JSON_EXTRACT(meta, '$.content'))
WHERE url IS NULL 
  AND meta IS NOT NULL 
  AND JSON_EXTRACT(meta, '$.content') IS NOT NULL;

-- Add index for better performance on URL lookups
-- Check if index exists first
SET @index_exists = (SELECT COUNT(*) FROM information_schema.statistics 
                     WHERE table_schema = 'revenueqr' 
                     AND table_name = 'qr_codes' 
                     AND index_name = 'idx_qr_codes_url');

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_qr_codes_url ON qr_codes(url)',
    'SELECT "Index already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the update worked
SELECT 
    COUNT(*) as total_qr_codes,
    SUM(CASE WHEN url IS NOT NULL THEN 1 ELSE 0 END) as codes_with_url,
    SUM(CASE WHEN url IS NULL THEN 1 ELSE 0 END) as codes_without_url
FROM qr_codes;

-- Show sample of updated records
SELECT id, qr_type, machine_name, 
       SUBSTRING(url, 1, 80) as url_preview,
       status
FROM qr_codes 
WHERE url IS NOT NULL 
ORDER BY created_at DESC 
LIMIT 5;

COMMIT;

-- Log this migration
INSERT INTO migration_log (phase, step, status, message) 
VALUES ('qr_url_fix', 1, 'success', 'URL storage standardized - populated url field from meta.content'); 