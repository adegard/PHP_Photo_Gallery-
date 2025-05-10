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

    foreach (scandir($folder, SCANDIR_SORT_DESCENDING) as $subfolder) {
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
        .sidebar { width: 90px; height: 100vh; background: #333; color: white; padding: 5px; overflow-y: auto; position: fixed; left: 0; top: 0; transition: all 0.3s; }
        .sidebar.hidden { left: -220px; } /* Collapsed State */
        .toggle-sidebar { position: fixed; top: 10px; left: 70px; background: #007bff; color: white; padding: 10px; cursor: pointer; border: none; }
        .year { cursor: pointer; padding: 10px; background: #444; margin: 5px; border-radius: 5px; }
        .months { display: none; padding-left: 15px; }
        .sidebar a { color: white; text-decoration: none; font-size: 16px; display: block; padding: 5px; }
        .main-content { margin-left: 120px; padding: 20px; flex-grow: 1; transition: margin-left 0.3s; }
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

        .delete-btn {
			position: absolute;
			top: 20px;
			left: 20px;
			background: red;
			color: white;
			padding: 10px 15px;
			font-size: 18px;
			cursor: pointer;
			border: none;
			border-radius: 5px;
		}

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
		<button class="delete-btn" onclick="deleteImage()">🗑 Delete</button>
        <img id="full-image">
        <button class="close-btn" onclick="closeFullscreen()">X</button>
    </div>

<script>
	    let images = Array.from(document.querySelectorAll('.gallery img'));
        let currentIndex = 0;

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
        
        let offset = <?= $imagesPerLoad ?>;
        let folder = "<?= htmlspecialchars($_GET['folder'] ?? 'images') ?>";
        
        function loadMoreImages() {
			document.getElementById("loading").style.display = "block";

			fetch(`load_images.php?folder=${encodeURIComponent(folder)}&offset=${offset}`)
				.then(response => response.json())
				.then(data => {
					let gallery = document.getElementById("gallery");

					data.forEach(image => {
						if (image.thumbnail) {
							let img = document.createElement("img");
							img.src = image.thumbnail;
							img.setAttribute("data-original", image.original);
							img.onclick = () => openFullscreen(img);
							gallery.appendChild(img);
						}
					});

					offset += <?= $imagesPerLoad ?>;
					document.getElementById("loading").style.display = "none";
				})
				.catch(error => console.error("Error loading images:", error));
		}
				
		function deleteImage() {
			let fullImageElement = document.getElementById('full-image');

			if (!fullImageElement || !fullImageElement.src) {
				alert("Error: No image is selected for deletion!");
				return;
			}

			// Define imageSrc **before** using it
			let imageSrc = fullImageElement.src;

			// Normalize path to match `data-original`
			let normalizedImageSrc = imageSrc.replace("http://dietpi", "");

			console.log("Trying to delete image:", normalizedImageSrc);

			let thumbnailElement = Array.from(document.querySelectorAll('.gallery img'))
				.find(img => img.getAttribute("data-original") === normalizedImageSrc);

			if (!thumbnailElement) {
				console.error("Thumbnail not found! Available images:");
				document.querySelectorAll('.gallery img').forEach(img => console.log(img.getAttribute("data-original")));

				alert("Error: Unable to find the thumbnail!");
				return;
			}

			let thumbnailSrc = thumbnailElement.getAttribute("src");

			console.log("Found thumbnail:", thumbnailSrc);

			fetch("delete_image.php", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ original: normalizedImageSrc, thumbnail: thumbnailSrc })
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					thumbnailElement.parentNode.removeChild(thumbnailElement);
					closeFullscreen();
				} else {
					alert("Error deleting image! Please refresh the page.");
				}
			})
			.catch(error => console.error("Error:", error));
		}



		        
		window.addEventListener("scroll", () => {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 50) {
                loadMoreImages();
            }
        });
</script>

</body>
</html>

