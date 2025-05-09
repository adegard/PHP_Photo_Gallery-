<?php
header('Content-Type: application/json');
error_log("Received request to delete.");
error_log("Original Path: " . $original);
error_log("Thumbnail Path: " . $thumbnail);


$data = json_decode(file_get_contents("php://input"), true);
$original = realpath($_SERVER['DOCUMENT_ROOT'] . $data['original']);
$thumbnail = realpath($_SERVER['DOCUMENT_ROOT'] . $data['thumbnail']);

$response = ["success" => false];

// Check if files exist and delete them
/*
if (file_exists($original) && file_exists($thumbnail)) {
    unlink($original); // Delete original
    unlink($thumbnail); // Delete thumbnail
    $response["success"] = true;
}
*/

if (file_exists($original)) {
    unlink($original);
} else {
    error_log("Original file not found: " . $original);
}

if (file_exists($thumbnail)) {
    unlink($thumbnail);
} else {
    error_log("Thumbnail file not found: " . $thumbnail);
}

echo json_encode($response);
?>
