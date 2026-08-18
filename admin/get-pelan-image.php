<?php
require_once '../config/database.php';

// Get kebun ID from query parameter
$kebun_id = (int)($_GET['id'] ?? 0);

if ($kebun_id <= 0) {
    http_response_code(400);
    die('Invalid kebun ID');
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT pelan_lot FROM kebun WHERE id = ?");
$stmt->execute([$kebun_id]);
$kebun = $stmt->fetch();

if (!$kebun || empty($kebun['pelan_lot'])) {
    http_response_code(404);
    die('Image not found');
}

$image_data = $kebun['pelan_lot'];

// Detect MIME type from binary data
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_buffer($finfo, $image_data);
finfo_close($finfo);

// If detection fails or returns generic octet-stream, inspect magic bytes
if (!$mime_type || $mime_type === 'application/octet-stream') {
    if (substr($image_data, 0, 4) === '%PDF') {
        $mime_type = 'application/pdf';
    } elseif (substr($image_data, 0, 8) === "\x89PNG\x0d\x0a\x1a\x0a") {
        $mime_type = 'image/png';
    } elseif (substr($image_data, 0, 3) === 'GIF') {
        $mime_type = 'image/gif';
    } elseif (substr($image_data, 0, 4) === 'RIFF' && substr($image_data, 8, 4) === 'WEBP') {
        $mime_type = 'image/webp';
    } else {
        $mime_type = 'image/jpeg';
    }
}

// Set proper headers for image/document output and prevent stale browser caching
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . strlen($image_data));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($mime_type === 'application/pdf') {
    header('Content-Disposition: inline; filename="pelan_lot_' . $kebun_id . '.pdf"');
}

echo $image_data;
exit();
?>
