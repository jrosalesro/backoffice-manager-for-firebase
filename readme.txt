=== BackOffice Manager for Firebase ===
Contributors: jrosalesdev
Tags: firebase, firestore, admin, backoffice, database
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Firebase Firestore collections and documents directly from your WordPress admin.

== Description ==

BackOffice Manager for Firebase  for WordPress allows you to manage data stored in Firebase Firestore directly from the WordPress admin area.

This plugin is designed as a lightweight back office for Firestore, focused on browsing collections, inspecting documents, and performing basic CRUD operations without leaving WordPress.

This is an early release (0.1.x), intended for developers, administrators, and technical users who already work with Firebase and want a simple way to manage Firestore data from WordPress.

This plugin is an independent tool and is not affiliated with or endorsed by Google or the Firebase project.

Features include:
* Browse Firestore collections and documents.
* View document data in a structured table format.
* Import a document structure from an existing Firestore collection.
* Create documents using a guided form generated from the imported structure.
* Edit documents using direct JSON editing.
* Delete documents from Firestore.

Note: This plugin requires your own Firebase project and credentials. It does not create Firebase projects or modify Firebase billing settings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to "BackOffice Manager for Firebase " in the WordPress admin menu.
4. Open "Settings" and add your Firebase Web App credentials.

== Frequently Asked Questions ==

= Is this plugin production-ready? =
This is an early version (0.1.x). It is functional but still evolving and may receive breaking changes in future releases.

= Does this plugin create Firestore collections automatically? =
No. You can import the structure from an existing Firestore collection and create documents based on that structure.

= Do I need a Firebase account? =
Yes. You need a Firebase project with Firestore enabled and a configured Web App.

= Does it support Realtime Database? =
Not in this version.

== Screenshots ==

1. BackOffice Manager for Firebase  dashboard showing connection status, authentication, and quick access to official Firebase usage and billing pages.
2. Firebase settings screen where the Firebase Web App credentials are configured to connect WordPress with a Firebase project.
3. Firestore collection explorer with document listing and full CRUD actions.
4. Firestore document edit screen allowing direct JSON editing with save actions.
5. Guided structure import from an existing Firestore collection, automatically detecting fields and data types.
6. Assisted Firestore document creation using a guided form generated from an existing collection structure.

== Changelog ==

= 0.2.0 =
* Simplified admin menu: Firestore and Settings only.
* Added configuration warning on Firestore screen.
* Replaced View JSON row action with Duplicate document.
* Improved quick array editing with add/remove item controls.

= 0.2.0 =
* Stable alphabetical table columns.
* Added quick field editing by double-clicking table cells.
* Added field-specific actions for strings, numbers and booleans.


= 0.1.0 =
* Initial public release.

== Upgrade Notice ==

= 0.1.0 =
Initial public release.
