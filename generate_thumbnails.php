<?php
$mainFolder = realpath('images');

function logMessage($message) {
    echo $message . PHP_EOL; // Print to CLI
}

// Recursive function to generate thumbnails up to 3 levels deep
function generateThumbnailsRecursively($folder, $currentDepth = 1, $maxDepth = 3) {
    if ($currentDepth > $maxDepth) return; // Stop recursion beyond 3 levels

    logMessage("Processing folder: " . $folder);
    $thumbFolder = $folder . DIRECTORY_SEPARATOR . 'thumbnails';

    // Ensure thumbnail folder exists
    if (!file_exists($thumbFolder)) {
        mkdir($thumbFolder, 0755, true);
        logMessage("Created folder: " . $thumbFolder);
    }

    // Process images in the current folder
    foreach (scandir($folder) as $file) {
        $filePath = realpath($folder . DIRECTORY_SEPARATOR . $file);
        if ($filePath && preg_match('/\.(jpg|jpeg)$/i', $file)) {
            generateThumbnail($filePath, $thumbFolder);
        }
    }

    // Process subfolders (next level)
    foreach (scandir($folder) as $subfolder) {
        $subfolderPath = realpath($folder . DIRECTORY_SEPARATOR . $subfolder);
        if (is_dir($subfolderPath) && $subfolder !== '.' && $subfolder !== '..') {
            generateThumbnailsRecursively($subfolderPath, $currentDepth + 1, $maxDepth);
        }
    }
}

// Function to create a thumbnail if it does not exist
function generateThumbnail($imagePath, $thumbFolder) {
    $thumbPath = $thumbFolder . DIRECTORY_SEPARATOR . basename($imagePath);

    if (!file_exists($thumbPath)) {
        list($width, $height) = getimagesize($imagePath);
        $newWidth = 200;
        $newHeight = ($height / $width) * $newWidth;

        logMessage("Creating thumbnail for: " . $imagePath);

        $source = imagecreatefromjpeg($imagePath);
        if (!$source) {
            logMessage("Error: Unable to process image: " . $imagePath);
            return;
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagejpeg($thumb, $thumbPath, 75);

        imagedestroy($source);
        imagedestroy($thumb);

        logMessage("Thumbnail created: " . $thumbPath);
    } else {
        logMessage("Thumbnail already exists: " . $thumbPath);
    }
}

// Start thumbnail generation
logMessage("Starting thumbnail generation...");
generateThumbnailsRecursively($mainFolder, 1, 3);
logMessage("Thumbnail generation completed.");
?>
