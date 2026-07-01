=== Firebase Integration for WordPress – Firestore & Auth ===
Contributors: jrosalesdev
Tags: firebase, firestore, admin, backoffice, database
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Firebase Firestore and Authentication. Browse collections, manage documents, administer Firebase Auth users, import structures, and test everything with Demo Mode — no Firebase configuration required.

== Description ==

Firebase Integration for WordPress – Firestore & Auth allows you to manage data stored in Firebase Firestore and inspect Firebase Authentication users directly from your WordPress admin dashboard.

The plugin provides a lightweight Firestore administration interface focused on browsing collections, managing documents, and performing common CRUD operations without leaving WordPress.

Designed for developers, administrators, and technical users working with Firebase projects, it offers a familiar WordPress experience for managing Firestore data.

This plugin is an independent tool and is not affiliated with or endorsed by Google or Firebase.

Features include:

* Browse Firestore collections and documents.
* View document data in a structured table format.
* Inline editing of document fields directly from the table.
* Create, edit, duplicate and delete Firestore documents.
* Import document structures from existing collections.
* Guided document creation using imported structures.
* Demo Mode with sample collections and documents.
* Per-user isolated demo data for safe testing.
* Collection history for quick access.
* JSON document editing for advanced users.

Note: This plugin requires your own Firebase project and credentials for production use. Demo Mode is available for evaluation without Firebase configuration.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Open "Firebase Integration" from the WordPress admin menu.
4. Configure your Firebase credentials under Settings or start with Demo Mode.

== Frequently Asked Questions ==

= Is this plugin production-ready? =

The plugin is actively maintained and suitable for development, testing and administration tasks. As Firestore projects vary greatly in structure, it is recommended to test thoroughly before using it in critical environments.

= Can I try the plugin without Firebase? =

Yes. Demo Mode allows you to explore the interface and test all major features without connecting to a Firebase project.

= Does this plugin create Firestore collections automatically? =

Currently, collections are created through normal Firestore operations. The plugin focuses on document management and structure-based document creation.

= Do I need a Firebase account? =

Only for production usage. Demo Mode works without Firebase.

= Does it support Realtime Database? =

Not currently. Firestore is the only supported Firebase database.

== Screenshots ==

1. Dashboard with Firebase connection status and quick access links.
2. Firebase settings screen.
3. Firestore collection explorer with CRUD operations.
4. Inline editing directly from the document table.
5. JSON document editor.
6. Structure import from existing Firestore collections.
7. Guided document creation form.
8. Demo Mode with sample collections and documents.

== Changelog ==

= 0.4.0 =

* Added Demo Mode for testing without Firebase credentials.
* Added isolated per-user demo collections and documents.
* Added dedicated Demo Mode admin screen.
* Added guided onboarding for first-time users.
* Added demo collection shortcuts.
* Improved Firestore AJAX handling.
* Improved user experience for unconfigured installations.

= 0.3.0 =

* Added document duplication.
* Added collection structure import.
* Added guided document creation.
* Added collection history.
* Various UI improvements.

= 0.2.0 =

* Added inline field editing.
* Added stable column ordering.
* Added field-specific actions.
* Improved array editing controls.

= 0.1.0 =

* Initial public release.

== Upgrade Notice ==

= 0.4.0 =

Introduces Demo Mode and onboarding improvements, allowing users to evaluate the plugin before configuring Firebase.
