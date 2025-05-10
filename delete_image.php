<?php
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$original = $data["original"] ?? "";
$thumbnail = $data["thumbnail"] ?? "";

// Debugging: Log received data
error_log("Request received: " . json_encode($data));
error_log("Original file path: " . $originalPath);
error_log("Thumbnail file path: " . $thumbnailPath);


if (!$original || !$thumbnail) {
    echo json_encode(["success" => false, "message" => "Invalid image paths"]);
    exit;
}

// Convert URL paths back to real file system paths
$originalPath = realpath($_SERVER['DOCUMENT_ROOT'] . $original);
$thumbnailPath = realpath($_SERVER['DOCUMENT_ROOT'] . $thumbnail);

// Debugging: Log resolved paths
error_log("Resolved Original Path: " . $originalPath);
error_log("Resolved Thumbnail Path: " . $thumbnailPath);

$deletedOriginal = file_exists($originalPath) ? unlink($originalPath) : false;
$deletedThumbnail = file_exists($thumbnailPath) ? unlink($thumbnailPath) : false;

if ($deletedOriginal || $deletedThumbnail) {
    echo json_encode(["success" => true]);
} else {
    error_log("Failed to delete files");
    echo json_encode(["success" => false, "message" => "Failed to delete files"]);
}


?>
