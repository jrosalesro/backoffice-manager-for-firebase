=== Firebase Integration – Firestore & Auth ===
Contributors: jrosalesdev
Tags: firebase, firestore, firebase auth, authentication, database, admin, wordpress, crud, backend, demo
Requires at least: 6.0
Tested up to: 7.0.2
Requires PHP: 8.0
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage your Firebase project directly from the WordPress dashboard. Browse Firestore collections, edit documents, manage Firebase Authentication users and safely explore every feature with Demo Mode.

== Description ==

Stop building custom Firebase admin panels.

Manage Firestore collections, edit documents and administer Firebase Authentication users directly from your familiar WordPress dashboard.

Firebase Integration – Firestore & Auth brings Firebase administration into WordPress, providing a clean, native interface for managing your Firebase project without building custom backoffice tools.

Whether you're developing client websites, SaaS applications or internal dashboards, the plugin lets you browse Firestore data, perform CRUD operations, inspect Authentication users and securely connect your Firebase project using a Service Account.

This plugin is an independent project and is not affiliated with, endorsed by or sponsored by Google LLC or Firebase.

= Firestore =

* Browse Firestore collections.
* View documents in a sortable table.
* Create, edit, duplicate and delete documents.
* Guided document creation.
* Import structures from existing collections.
* Inline editing.
* JSON editor.
* Collection history.

= Firebase Authentication =

* Browse Authentication users.
* Search by email, UID or display name.
* View providers, verification status and account details.
* Review creation and last sign-in dates.
* Manage Authentication users directly from WordPress.

= Demo Mode =

* Explore the plugin without Firebase.
* Realistic Firestore sample collections.
* Sample Authentication users.
* Isolated per-user demo data.
* Perfect for evaluating the plugin safely.

= Why use this plugin? =

* Native WordPress interface.
* No custom admin panel required.
* No coding required for everyday administration.
* Secure Service Account authentication.
* Designed specifically for Firebase administration.

Note: Production usage requires your own Firebase project and Service Account JSON credentials. Demo Mode works without Firebase.

== Installation ==

1. Upload the plugin or install it from the WordPress Plugins screen.
2. Activate the plugin.
3. Open **Firebase Integration → Settings**.
4. Upload your Firebase Service Account JSON file.
5. Start managing your Firebase project.

== Frequently Asked Questions ==

= Can I try the plugin without Firebase? =

Yes. Demo Mode lets you explore Firestore collections, Authentication users and the complete interface without connecting a Firebase project.

= Does Demo Mode modify my Firebase project? =

No. Demo Mode is completely isolated and never communicates with Firebase.

= How do I connect Firebase? =

Upload your Firebase Service Account JSON file from Firebase Console → Project Settings → Service Accounts.

= Does the plugin modify Firebase Security Rules? =

No. The plugin respects your existing Firebase configuration and only performs the actions you explicitly execute.

= Does it support Firebase Authentication? =

Yes. Browse Authentication users, inspect account details and search by email or UID.

= Does it support Realtime Database? =

Not yet. Current versions support Cloud Firestore and Firebase Authentication.

= Is this plugin production-ready? =

Yes. It is suitable for production administration. As every Firebase project has its own data model, always test new workflows before using them in critical environments.

== Screenshots ==

1. Manage Firestore collections visually — Browse, filter, edit, duplicate and delete documents directly from your WordPress dashboard.
2. Manage Firebase Authentication — Search users, inspect account details and perform common administration tasks in seconds.
3. Secure Firebase connection — Connect your project using a Service Account JSON file with an intuitive configuration screen.
4. Demo Mode — Explore the complete plugin using realistic sample data before connecting your own Firebase project.
5. Native WordPress experience — A clean interface designed to make Firebase management simple for developers and site administrators.

== Changelog ==

= 0.5.0 =

* Public branding updated to Firebase Integration – Firestore & Auth.
* Added Firebase Authentication management.
* Improved Authentication user details and provider labels.
* Hardened Service Account JSON uploads.
* Improved admin security and sanitization.
* Improved packaging for WordPress.org.
* Various UI and usability improvements.

= 0.4.0 =

* Added Demo Mode.
* Added isolated per-user demo data.
* Added onboarding wizard.
* Added demo collections.
* Improved Firestore AJAX handling.

= 0.3.0 =

* Added document duplication.
* Added guided document creation.
* Added structure import.
* Added collection history.

= 0.2.0 =

* Added inline editing.
* Improved array editing.
* Improved table actions.

= 0.1.0 =

* Initial public release.

== Upgrade Notice ==

= 0.5.0 =

Introduces Firebase Authentication management, Demo Mode improvements, stronger security and a refreshed public interface.
