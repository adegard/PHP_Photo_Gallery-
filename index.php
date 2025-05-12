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
		return is_file($folder . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|mp4)$/i', $file);
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

# upload pictures
$logFile = "log.txt"; // Debug log storage

function writeLog($message) {
    file_put_contents($GLOBALS['logFile'], date("[Y-m-d H:i:s] ") . $message . PHP_EOL, FILE_APPEND);
}

function uploadImage($baseFolder) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
        return ['success' => false, 'error' => 'Error uploading image'];
    }

    // Extract the target folder from the request
    $targetFolder = isset($_POST['targetFolder']) ? realpath($_POST['targetFolder']) : $baseFolder;

    if (!$targetFolder || !is_dir($targetFolder)) {
        return ['success' => false, 'error' => 'Invalid target folder'];
    }

    $imageFile = $_FILES['image'];
    $imageName = basename($imageFile['name']);
    $imagePath = $targetFolder . DIRECTORY_SEPARATOR . $imageName;

    if (!move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
        return ['success' => false, 'error' => 'Failed to save image'];
    }

    // Ensure thumbnail folder exists
    $thumbnailFolder = $targetFolder . DIRECTORY_SEPARATOR . 'thumbnails';
    if (!is_dir($thumbnailFolder)) {
        mkdir($thumbnailFolder, 0777, true);
    }

    createThumbnail($imagePath, $thumbnailFolder . DIRECTORY_SEPARATOR . $imageName);
    return ['success' => true, 'image' => $imagePath];
}


function createThumbnail($imagePath, $thumbPath, $thumbWidth = 150) {
    writeLog("Creating thumbnail for: " . $imagePath);

    $sourceImage = imagecreatefromjpeg($imagePath);
    if (!$sourceImage) {
        writeLog("Error loading image for thumbnail creation.");
        return;
    }

    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    $thumbHeight = ($thumbWidth / $width) * $height;
    $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
    imagecopyresized($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
    imagejpeg($thumbnail, $thumbPath);
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);

    writeLog("Thumbnail created successfully at: " . $thumbPath);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadResult = uploadImage($mainFolder);
    echo json_encode($uploadResult);
    exit;
}

######

$folderStructure = getFolderStructure($mainFolder);
$currentFolder = isset($_GET['folder']) ? realpath($_GET['folder']) : $mainFolder;
$initialImages = getImagesFromFolder($currentFolder, 0, $imagesPerLoad);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Gallery</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background: #333; display: flex; margin: 0; }
        .sidebar { width: 90px; height: 100vh; background: #333; color: white; padding: 5px; overflow-y: auto; position: fixed; left: 0; top: 0; transition: all 0.3s; }
        .sidebar.hidden { left: -220px; } /* Collapsed State */
        .toggle-sidebar { position: fixed; top: 10px; left: 50px; background: #007bff; color: white; padding: 10px; cursor: pointer; border: none; }
        .year { cursor: pointer; padding: 10px; background: #444; margin: 5px; border-radius: 5px; }
        .months { display: none; padding-left: 15px; }
        .sidebar a { color: white; text-decoration: none; font-size: 16px; display: block; padding: 5px; }
        .main-content { margin-left: 70px; background: #333;  padding: 20px; flex-grow: 1; transition: margin-left 0.3s; }
        .main-content.expanded { margin-left: 10px; } /* Adjust when sidebar is hidden */
        .gallery { display: flex; flex-wrap: wrap; justify-content: center; gap: 3px; }
        .loading { display: none; text-align: center; margin-top: 20px; font-size: 18px; color: gray; }
        
        .gallery img {
			max-height: 200px;
			max-width: 100%;
			object-fit: contain; /* Keeps aspect ratio without distortion */
			border-radius: 5px;
			cursor: pointer;
			transition: opacity 0.3s ease-in-out;
		}


        /* Fullscreen Image with Fade-In Effect */
        .fullscreen-container { 
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.8); 
            justify-content: center; align-items: center; opacity: 0; transition: opacity 0.5s ease-in-out;
        }
        .fullscreen-container.show { opacity: 1; }
				
		.fullscreen-container img {
			max-width: 90vw;
			max-height: 90vh;
			opacity: 0;
			transition: opacity 0.5s ease-in-out;
			will-change: opacity; /* Optimized for mobile smooth rendering */
		}



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
		
		/* Video css */
		.video-thumbnail {
			position: relative;
			display: inline-block;
		}

		.video-thumbnail img {
			max-height: 150px;
			border-radius: 5px;
			cursor: pointer;
		}

		.play-icon {
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			width: 50px;
			height: 50px;
			background: rgba(0, 0, 0, 0.7);
			color: white;
			font-size: 24px;
			text-align: center;
			line-height: 50px;
			border-radius: 50%;
			pointer-events: none;
		}


    </style>
</head>
<body>

    <!-- Collapsible Sidebar -->
    <div class="sidebar" id="sidebar">
		<form id="uploadForm" enctype="multipart/form-data">
			<input type="file" name="image" id="imageInput">
			<button type="button" onclick="uploadPicture()">Upload Picture</button>
		</form>

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
        <h1>      Gallery - <?= basename($currentFolder) ?></h1>
        <!-- output for uploading ENABLE FOR DEBUGING
    		<div id="logOutput" style="border: 1px solid #ccc; padding: 10px; max-height: 150px; overflow-y: auto;"></div>
    	-->	

        <div class="gallery" id="gallery">
			<?php foreach ($initialImages as $media): ?>
				<?php if ($media['thumbnail']): ?>	
					<?php if (strpos($media['original'], '.mp4') !== false): ?>
  						<!-- Video Thumbnail -->
						<div class="video-thumbnail" onclick="playVideo('<?= htmlspecialchars($media['original']) ?>')">
							<img src="<?= htmlspecialchars($media['thumbnail']) ?>" onerror="this.style.display='none';">
							<div class="play-icon">▶</div>
						</div>
					<?php else: ?>
						<!-- Image Thumbnail -->
						<img src="<?= htmlspecialchars($media['thumbnail']) ?>" 
							 data-original="<?= htmlspecialchars($media['original']) ?>" 
							 onclick="openFullscreen(this)">
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

        
        
        <div class="loading" id="loading">Loading more images...</div>
    </div>

    <!-- Fullscreen Views -->
    <div class="fullscreen-container" id="fullscreen">
		<button class="delete-btn" onclick="deleteImage()">🗑 Delete</button>
        <img id="full-image">
        <button class="close-btn" onclick="closeFullscreen()">X</button>
    </div>
    
    <div class="fullscreen-container" id="fullscreen-video">
		<video id="video-player" controls>
			<source id="video-source" src="" type="video/mp4">
			Your browser does not support the video tag.
		</video>
		<button class="close-btn" onclick="closeVideo()">X</button>
	</div>


<script>
	    function playVideo(videoSrc) {
			let videoPlayer = document.getElementById('video-player');
			let fullscreenVideo = document.getElementById('fullscreen-video');

			if (!videoPlayer || !fullscreenVideo) {
				console.error("Error: Video elements not found.");
				return;
			}

			document.getElementById('video-source').src = videoSrc;
			videoPlayer.load();
			fullscreenVideo.style.display = "flex";
		}

		function closeVideo() {
			let fullscreenVideo = document.getElementById('fullscreen-video');
			fullscreenVideo.style.display = "none";
		}

 
	    
	    let images = Array.from(document.querySelectorAll('.gallery img'));
        let currentIndex = 0;

		
        function uploadPicture() {
			let formData = new FormData(document.getElementById('uploadForm'));
			formData.append("targetFolder", "<?= htmlspecialchars($currentFolder) ?>"); // Sends correct folder

			fetch('', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				console.log("Upload response:", data);

				if (data.success) {
					console.log("Image uploaded successfully:", data.image);
					location.reload();
				} else {
					console.error("Upload error:", data.error);
					alert("Error: " + data.error);
				}
			})
			.catch(error => console.error("Upload failed:", error));
		}


        function displayLog(message) {
            let logBox = document.getElementById('logOutput');
            let logEntry = document.createElement('div');
            logEntry.innerText = message;
            logBox.appendChild(logEntry);
        }

        function fetchServerLogs() {
            fetch('log.txt')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('logOutput').innerText = data;
                });
        }

        setInterval(fetchServerLogs, 5000); // Update logs every 5 seconds
        
		
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

			// Reset fullscreen container before loading a new image
			fullscreenContainer.style.display = "flex";
			fullscreenContainer.classList.add("show");
			fullImage.src = ""; // Clear previous image
			fullImage.style.opacity = "0"; // Reset fade effect
			fullImage.style.width = "90vw"; // Ensure fullscreen size
			// fullImage.style.height = "90vh";
			
			
			// Load the thumbnail first
			let thumbnailSrc = imageElement.src;
			let originalSrc = imageElement.getAttribute("data-original");

			fullImage.src = thumbnailSrc; // Start with thumbnail
			fullImage.style.opacity = "1"; // Make sure it's fully visible

			// Once the original image loads, fade into it
			let tempImage = new Image();
			tempImage.src = originalSrc;
			tempImage.onload = () => {
				setTimeout(() => {
					fullImage.src = originalSrc; // Switch to original
					fullImage.style.transition = "opacity 0.5s ease-in-out";
					fullImage.style.opacity = "1"; // Apply fade effect
				}, 200);
			};
		}


/*
		function openFullscreen(imageElement) {
			let fullImage = document.getElementById('full-image');
			let fullscreenContainer = document.getElementById('fullscreen');

			if (!fullImage || !fullscreenContainer) {
				console.error("Error: Fullscreen elements not found.");
				return;
			}
			// Reset previous image and hide it before loading new one
			fullImage.src = '';
			fullImage.style.opacity = "0"; 
			fullscreenContainer.style.display = "flex";
			fullscreenContainer.classList.add("show");

			// Show thumbnail first with full opacity
			let thumbnailSrc = imageElement.src;
			let originalSrc = imageElement.getAttribute("data-original");

			fullImage.src = thumbnailSrc; // Set thumbnail first
			fullImage.style.opacity = "1"; // Ensure the thumbnail is visible

			// Fade-in effect after original image loads
			fullImage.onload = () => {
				setTimeout(() => {
					fullImage.style.transition = "opacity 0.5s ease-in-out";
					fullImage.src = originalSrc; // Replace with full image
				}, 100); // Slight delay to smooth transition
			};
		}
*/
/*
		function openFullscreen(imageElement) {
			let fullImage = document.getElementById('full-image');
			let fullscreenContainer = document.getElementById('fullscreen');

			if (!fullImage || !fullscreenContainer) {
				console.error("Error: Fullscreen elements not found.");
				return;
			}

			// Reset previous image and hide it before loading new one
			fullImage.src = '';
			fullImage.style.opacity = "0"; 
			fullscreenContainer.style.display = "flex";
			fullscreenContainer.classList.add("show");

			let originalSrc = imageElement.getAttribute("data-original");

			// Load the new image and trigger fade effect on load
			fullImage.onload = () => {
				fullImage.style.transition = "opacity 0.5s ease-in-out";
				fullImage.style.opacity = "1";
			};
			
			fullImage.src = originalSrc; // Assign source AFTER setting onload handler
		}

*/
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

					data.forEach((image, index) => {
						if (image.thumbnail) {
							setTimeout(() => { // Staggered loading improves rendering
								let img = document.createElement("img");
								img.src = image.thumbnail;
								img.setAttribute("data-original", image.original);
								img.onclick = () => openFullscreen(img);
								gallery.appendChild(img);
							}, index * 50); // Adjust delay if needed
						}
					});

					offset += <?= $imagesPerLoad ?>;
					document.getElementById("loading").style.display = "none";
				})
				.catch(error => console.error("Error loading images:", error));
		}

/*
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
*/				
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

