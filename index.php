<?php
$mainFolder = realpath('images');
$imagesPerLoad = 40;
$deletedImages = []; // Store deleted images temporarily for undo functionality

// Function to fetch images from a folder
function getImagesFromFolder($folder, $offset = 0, $limit = 40) {
    $images = [];
    $thumbFolder = $folder . DIRECTORY_SEPARATOR . 'thumbnails';

    if (!is_dir($folder)) {
        return [];
    }

    $files = array_filter(scandir($folder), function($file) use ($folder) {
        return is_file($folder . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg)$/i', $file);
    });

    $files = array_slice($files, $offset, $limit);

    foreach ($files as $file) {
        $filePath = realpath($folder . DIRECTORY_SEPARATOR . $file);
        $thumbPath = $thumbFolder . DIRECTORY_SEPARATOR . basename($file);

        $images[] = [
            'original' => file_exists($filePath) ? str_replace(realpath('images'), '/images', $filePath) : null,
            'thumbnail' => file_exists($thumbPath) ? str_replace(realpath('images'), '/images', $thumbPath) : null
        ];
    }

    return $images;
}

// Function to get folders and subfolders, excluding "thumbnails"
function getFolderStructure($folder) {
    $structure = [];

    foreach (scandir($folder) as $subfolder) {
        $subfolderPath = realpath($folder . DIRECTORY_SEPARATOR . $subfolder);
        if (is_dir($subfolderPath) && $subfolder !== '.' && $subfolder !== '..' && $subfolder !== 'thumbnails') {
            $structure[$subfolder] = [];

            foreach (scandir($subfolderPath) as $nestedFolder) {
                $nestedPath = realpath($subfolderPath . DIRECTORY_SEPARATOR . $nestedFolder);
                if (is_dir($nestedPath) && $nestedFolder !== '.' && $nestedFolder !== '..' && $nestedFolder !== 'thumbnails') {
                    $structure[$subfolder][] = $nestedFolder;
                }
            }
        }
    }
    return $structure;
}

$folderStructure = getFolderStructure($mainFolder);
$currentFolder = isset($_GET['folder']) ? realpath($_GET['folder']) : $mainFolder;
$initialImages = getImagesFromFolder($currentFolder, 0, $imagesPerLoad);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Gallery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; display: flex; margin: 0; }
        .sidebar { width: 200px; height: 100vh; background: #333; color: white; padding: 15px; overflow-y: auto; position: fixed; left: 0; top: 0; transition: all 0.3s; }
        .sidebar.hidden { left: -220px; } /* Collapsed State */
        .toggle-sidebar { position: fixed; top: 10px; left: 220px; background: #007bff; color: white; padding: 10px; cursor: pointer; border: none; }
        .year { cursor: pointer; padding: 10px; background: #444; margin: 5px; border-radius: 5px; }
        .months { display: none; padding-left: 15px; }
        .sidebar a { color: white; text-decoration: none; font-size: 16px; display: block; padding: 5px; }
        .main-content { margin-left: 220px; padding: 20px; flex-grow: 1; transition: margin-left 0.3s; }
        .main-content.expanded { margin-left: 10px; } /* Adjust when sidebar is hidden */
        .gallery { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .gallery img { max-height: 150px; border-radius: 5px; cursor: pointer; transition: opacity 0.3s ease-in-out; }
        .loading { display: none; text-align: center; margin-top: 20px; font-size: 18px; color: gray; }

        /* Fullscreen Image with Fade-In Effect */
        .fullscreen-container { 
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.8); 
            justify-content: center; align-items: center; opacity: 0; transition: opacity 0.5s ease-in-out;
        }
        .fullscreen-container.show { opacity: 1; }
        .fullscreen-container img { max-width: 90vw; max-height: 90vh; }
        .close-btn, .delete-btn, .undo-btn { position: absolute; font-size: 18px; padding: 10px; cursor: pointer; border: none; border-radius: 5px; }
        .close-btn { top: 20px; right: 30px; background: red; color: white; }
        .delete-btn { top: 20px; left: 20px; background: darkred; color: white; }
        .undo-btn { bottom: 20px; left: 50%; transform: translateX(-50%); background: green; color: white; display: none; }
    </style>
</head>
<body>

    <!-- Collapsible Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="?folder=<?= urlencode($mainFolder) ?>">All Years</a>
        <?php foreach ($folderStructure as $year => $months): ?>
            <div class="year" onclick="toggleAccordion('<?= $year ?>')"><?= htmlspecialchars($year) ?></div>
            <div id="months-<?= $year ?>" class="months">
                <?php foreach ($months as $month): ?>
                    <a href="?folder=<?= urlencode($mainFolder . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month) ?>">
                        <?= htmlspecialchars($month) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" onclick="toggleSidebar()">☰ Menu</button>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <h1>Image Gallery - <?= basename($currentFolder) ?></h1>
        <div class="gallery" id="gallery">
            <?php foreach ($initialImages as $image): ?>
                <?php if ($image['thumbnail']): ?>
                    <img src="<?= htmlspecialchars($image['thumbnail']) ?>" 
                         data-original="<?= htmlspecialchars($image['original']) ?>" 
                         onclick="openFullscreen(this)">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="loading" id="loading">Loading more images...</div>
    </div>

    <!-- Fullscreen View -->
    <div class="fullscreen-container" id="fullscreen">
        <img id="full-image">
        <button class="close-btn" onclick="closeFullscreen()">X</button>
    </div>

<script>

		function toggleAccordion(year) {
            let months = document.getElementById("months-" + year);
            months.style.display = months.style.display === "none" ? "block" : "none";
        }

        function toggleSidebar() {
            let sidebar = document.getElementById("sidebar");
            let mainContent = document.getElementById("main-content");
            sidebar.classList.toggle("hidden");
            mainContent.classList.toggle("expanded");
        }

        function openFullscreen(imageElement) {
            let fullImage = document.getElementById('full-image');
            let fullscreenContainer = document.getElementById('fullscreen');

            if (!fullImage || !fullscreenContainer) {
                console.error("Error: Fullscreen elements not found.");
                return;
            }

            fullImage.src = imageElement.getAttribute("data-original");
            fullscreenContainer.classList.add("show");
            fullscreenContainer.style.display = "flex";
        }

        function closeFullscreen() {
            let fullscreenContainer = document.getElementById('fullscreen');
            fullscreenContainer.classList.remove("show");
            setTimeout(() => { fullscreenContainer.style.display = "none"; }, 500);
        }
</script>

</body>
</html>

