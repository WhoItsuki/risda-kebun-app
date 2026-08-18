<?php
require_once 'config/database.php';

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

// Default to jpeg if detection fails
if (!$mime_type || strpos($mime_type, 'image') === false) {
    $mime_type = 'image/jpeg';
}

// Set proper headers for image output
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . strlen($image_data));
header('Cache-Control: public, max-age=3600');
header('Pragma: public');

echo $image_data;
exit();
?>
