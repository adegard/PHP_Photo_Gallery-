# PHP_Photo_Gallery
Basic photo gallery for Home server - no database

✅ Gallery visualization script (handles displaying images and browsing). 
✅ Cron job script (runs nightly to generate thumbnails). 
✅ Supports two-level folder scanning (images/YYYY/MM). 
✅ Optimized performance, usability, and smooth navigation.

I'll now write the full PHP scripts for:

- Displaying images

- Generating thumbnails via cron job

## Installation

Require PHP and Apache

1️⃣ Place these scripts in your web server (/var/www/html).
2️⃣ Set up cron jobs for thumbnail generation and cleanup at night.
3️⃣ Open index.php in a browser to view and manage images! 🎉

🛠 2. Thumbnail Generator (generate_thumbnails.php)

✅ Set the cron job to run nightly at 3 AM:

bash
crontab -e
Add:

0 3 * * * /usr/bin/php /path/to/generate_thumbnails.php
