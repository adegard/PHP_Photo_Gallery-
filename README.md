# PHP_Photo_Gallery 

Basic photo gallery for Home server - no database

- Gallery visualization script (handles displaying images and browsing). 
- Cron job script (runs nightly to generate thumbnails). 
- Supports two-level folder scanning (images/YYYY/MM). 
- Optimized performance, usability, and smooth navigation, thanks to progressive Thumbnails loading when scroll down
- Responsive to be use on small screens

![Screenshot](screen.jpg?raw=true "Screenshot")

The full PHP scripts are made for:

- Displaying images in low res. thumbnails
- Open full screen in full res. with Delete button (both thumbnails and original)
- Generating thumbnails via cron job

## Installation

Require PHP and Apache

1. Place these scripts in your web server (/var/www).
2. Place your images with any sub-folder structure you want (eg. YYYY/MM ...) inside a sub-folder "/var/www/images" (or use a symlink in linux to your existing photo folder)
3. First time scanning [or after bulk upload]: `cd /var/www && nohup php generate_thumbnails.php > /var/log/thumbnails.log 2>&1` (background process to not overload cpu). This will create sub-folders "thumbnails" in every folders (up to 2nd level of images) with low-res pictures
4. Open index.php in a browser to view and manage images! 🎉

🛠 Thumbnail Generator (generate_thumbnails.php)

✅ Set the cron job to run nightly at 3 AM:

`crontab -e`

Add:

`0 2 * * * php /var/www/generate_thumbnails.php >> /var/www/log.txt 2>&1`


## [OPTIONAL] Optimize the picture sizes:

launch this script inside the root picture folder:

`find /var/www/images/2003 -type f -iname "*.jpg" -exec jpegoptim --max=95 --strip-all {} \;`


## Common issues:

Verify your server has write permissions to photos folders

`ls -l /path/to/images` 

if not, give access to user:group  

`sudo chown -R www-data:www-data /path/to/images` 


## TODO:

- integrate video thumbnails and play

This program is in low-development. If some bugs appear, please open an issue.
