=== GDrive to Post ===
Contributors: mma
Tags: google drive, google docs, import, sync, automation
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sync Google Docs from a shared Drive folder into WordPress draft posts with clean HTML, images, and one-click publish links.

== Description ==

GDrive to Post connects to a Google Drive folder via Service Account, automatically syncs Google Docs on a daily schedule, creates draft posts from each document, and emails a notification with one-click publish links.

= Features =

* **Service Account Auth** - Secure connection using Google Cloud Service Account JSON key
* **Folder Browser** - Browse and select your Google Drive source folder from the admin UI
* **Auto Sync** - Daily automatic sync via WP-Cron with manual "Sync Now" button
* **Clean HTML** - Converts Google Docs to clean WordPress HTML with headings, bold, italic, links, lists, images, and tables
* **Image Import** - Downloads images into the WordPress Media Library
* **Duplicate Detection** - Already-imported documents are automatically skipped
* **Subfolder Support** - Recursively scans subfolders up to 10 levels deep
* **Email Notifications** - Configurable email notifications with one-click publish links
* **Admin Dashboard** - Connection status, sync status, recent imports, and quick stats
* **Import Log** - Filterable, paginated history of all imports

= Requirements =

* PHP 7.4 or higher
* PHP OpenSSL extension
* Google Cloud Service Account with Drive API enabled
* Google Drive folder shared with the Service Account email

== Installation ==

1. Upload the `gdrive-to-post` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to GDrive to Post > Settings
4. Upload your Google Cloud Service Account JSON key file
5. Share your Google Drive folder with the Service Account email address
6. Browse and select the source folder
7. Configure sync frequency and notification settings
8. Click "Sync Now" or wait for the scheduled sync

== Frequently Asked Questions ==

= How do I create a Service Account? =

1. Go to the Google Cloud Console (console.cloud.google.com)
2. Create a new project or select an existing one
3. Enable the Google Drive API
4. Go to IAM & Admin > Service Accounts
5. Create a new Service Account
6. Create and download a JSON key

= How do I share a folder with the Service Account? =

Right-click the folder in Google Drive, click "Share", and add the Service Account email address (found in the JSON key file as `client_email`).

= Will it re-import documents that have been updated? =

No. Once a document is imported, it will not be re-imported even if the source document is modified. Only new documents are imported.

= What happens if I delete the WordPress post? =

The import record remains in the log, so the document will not be re-imported. You would need to manually re-import it.

= Can I change the post status from draft? =

Yes. In Settings, you can set the default post status to Draft, Pending Review, or Private.

== Changelog ==

= 1.0.0 =
* Initial release
* Service Account authentication with JWT
* Google Drive folder browsing and selection
* Automatic and manual sync
* HTML cleaning and image import
* Email notifications with one-click publish links
* Admin dashboard and import log
