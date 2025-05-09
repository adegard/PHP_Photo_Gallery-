<?php
$mainFolder = realpath('images');
$folder = isset($_GET['folder']) ? realpath($_GET['folder']) : $mainFolder;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 40;

function getImagesFromFolder($folder, $offset, $limit) {
    $images = [];
    $thumbFolder = $folder . DIRECTORY_SEPARATOR . 'thumbnails';

    $files = array_filter(scandir($folder), fn($file) => preg_match('/\.(jpg|jpeg)$/i', $file));
    $files = array_slice($files, $offset, $limit);

    foreach ($files as $file) {
        $filePath = realpath($folder . DIRECTORY_SEPARATOR . $file);
        $thumbPath = $thumbFolder . DIRECTORY_SEPARATOR . basename($file);
        $images[] = [
            'original' => str_replace(realpath('images'), '/images', $filePath),
            'thumbnail' => file_exists($thumbPath) ? str_replace(realpath('images'), '/images', $thumbPath) : null
        ];
    }

    return $images;
}

header('Content-Type: application/json');
echo json_encode(getImagesFromFolder($folder, $offset, $limit));
