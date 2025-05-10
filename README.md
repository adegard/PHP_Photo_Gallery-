# PHP_Photo_Gallery 
Basic photo gallery for Home server - no database

- Gallery visualization script (handles displaying images and browsing). 
- Cron job script (runs nightly to generate thumbnails). 
- Supports two-level folder scanning (images/YYYY/MM). 
- Optimized performance, usability, and smooth navigation, thanks to progressive Thumbnails loading when scroll down
- Responsive to be use on small screens

![Screenshot](screen.jpg?raw=true "Screenshot")

I'll now write the full PHP scripts for:

- Displaying images

- Generating thumbnails via cron job

## Installation

Require PHP and Apache

1. Place these scripts in your web server (/var/www).
2. Place your images inside a sub-folder named "images"
3. First time scanning: `php /var/www/generate_thumbnails.php >> /var/www/log.txt 2>&1.` (background process to not overload cpu)
4. Open index.php in a browser to view and manage images! 🎉

🛠 Thumbnail Generator (generate_thumbnails.php)

✅ Set the cron job to run nightly at 3 AM:

`crontab -e`

Add:

`0 2 * * * php /var/www/generate_thumbnails.php >> /var/www/log.txt 2>&1`
