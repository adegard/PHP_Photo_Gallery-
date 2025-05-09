<?php
$mainFolder = realpath('images');

function logMessage($message) {
    echo $message . PHP_EOL;
}

// Function to remove nested "thumbnails/thumbnails" folders
function removeNestedThumbnails($folder, $currentDepth = 1, $maxDepth = 3) {
    if ($currentDepth > $maxDepth) return;

    logMessage("Scanning: " . $folder);

    foreach (scandir($folder) as $subfolder) {
        $subfolderPath = realpath($folder . DIRECTORY_SEPARATOR . $subfolder);
        
        // Check if this subfolder is a "thumbnails" directory
        if (is_dir($subfolderPath) && $subfolder === 'thumbnails') {
            // Check for nested thumbnails inside
            $nestedThumbPath = $subfolderPath . DIRECTORY_SEPARATOR . 'thumbnails';
            if (is_dir($nestedThumbPath)) {
                logMessage("Deleting nested thumbnails folder: " . $nestedThumbPath);
                rmdir($nestedThumbPath);
            }
        }

        // Recursively scan subdirectories
        if (is_dir($subfolderPath) && $subfolder !== '.' && $subfolder !== '..') {
            removeNestedThumbnails($subfolderPath, $currentDepth + 1, $maxDepth);
        }
    }
}

logMessage("Starting cleanup...");
removeNestedThumbnails($mainFolder);
logMessage("Cleanup completed!");
?>
