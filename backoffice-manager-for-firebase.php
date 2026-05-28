<?php
/*
Plugin Name: BackOffice Manager for Firebase
Description: Manage Firebase Firestore collections and documents from your WordPress admin area.
Version: 0.2.0
Author: José Rosales Rosendo
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: backoffice-manager-for-firebase
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'BOMFF_PLUGIN_PATH' ) ) {
    define( 'BOMFF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'BOMFF_PLUGIN_URL' ) ) {
    define( 'BOMFF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * 1) Main menu and submenus
 */
function bomff_add_admin_menu() {
    add_menu_page(
        __( 'BOM Firebase', 'backoffice-manager-for-firebase' ),
        __( 'BOM Firebase', 'backoffice-manager-for-firebase' ),
        'manage_options',
        'bomff-admin-panel',
        'bomff_render_firestore_page',
        'dashicons-database',
        80
    );

    add_submenu_page(
        'bomff-admin-panel',
        __( 'Firestore', 'backoffice-manager-for-firebase' ),
        __( 'Firestore', 'backoffice-manager-for-firebase' ),
        'manage_options',
        'bomff-admin-panel',
        'bomff_render_firestore_page'
    );

    add_submenu_page(
        'bomff-admin-panel',
        __( 'Settings', 'backoffice-manager-for-firebase' ),
        __( 'Settings', 'backoffice-manager-for-firebase' ),
        'manage_options',
        'bomff-settings',
        'bomff_render_settings_page'
    );
}

add_action( 'admin_menu', 'bomff_add_admin_menu' );

/**
 * 2) Page callbacks
 */
function bomff_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    include BOMFF_PLUGIN_PATH . 'bomff-admin.php';
}

function bomff_render_firestore_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    include BOMFF_PLUGIN_PATH . 'pages/firestore.php';
}

function bomff_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    include BOMFF_PLUGIN_PATH . 'bomff-settings.php';
}

/**
 * 3) Settings API (Firebase config)
 */
function bomff_sanitize_firebase_config( $input ) {
    $out = array();

    $keys = array(
        'apiKey',
        'authDomain',
        'projectId',
        'storageBucket',
        'messagingSenderId',
        'appId',
    );

    foreach ( $keys as $key ) {
        $out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
    }

    return $out;
}

function bomff_register_settings() {

    register_setting(
        'bomff_settings_group',
        'bomff_firebase_config',
        array(
            'sanitize_callback' => 'bomff_sanitize_firebase_config',
            'default'           => array(),
        )
    );

    add_settings_section(
        'bomff_firebase_main_section',
        __( 'Firebase Project Credentials', 'backoffice-manager-for-firebase' ),
        'bomff_firebase_section_cb',
        'bomff-settings'
    );

    add_settings_field(
        'bomff_api_key',
        __( 'API Key', 'backoffice-manager-for-firebase' ),
        'bomff_field_api_key_cb',
        'bomff-settings',
        'bomff_firebase_main_section'
    );

    add_settings_field(
        'bomff_auth_domain',
        __( 'Auth Domain', 'backoffice-manager-for-firebase' ),
        'bomff_field_auth_domain_cb',
        'bomff-settings',
        'bomff_firebase_main_section'
    );

    add_settings_field(
        'bomff_project_id',
        __( 'Project ID', 'backoffice-manager-for-firebase' ),
        'bomff_field_project_id_cb',
        'bomff-settings',
        'bomff_firebase_main_section'
    );

    add_settings_field(
        'bomff_storage_bucket',
        __( 'Storage Bucket', 'backoffice-manager-for-firebase' ),
        'bomff_field_storage_bucket_cb',
        'bomff-settings',
        'bomff_firebase_main_section'
    );

    add_settings_field(
        'bomff_messaging_sender_id',
        __( 'Messaging Sender ID', 'backoffice-manager-for-firebase' ),
        'bomff_field_messaging_sender_id_cb',
        'bomff-settings',
        'bomff_firebase_main_section'
    );

    add_settings_field(
        'bomff_app_id',
        __( 'App ID', 'backoffice-manager-for-firebase' ),
        'bomff_field_app_id_cb',
        'bomff-settings',
        'bomff_firebase_main_section'
    );
}
add_action( 'admin_init', 'bomff_register_settings' );

function bomff_firebase_section_cb() {
    echo '<p>' . esc_html__( 'Enter your Firebase project credentials. You can find them in the Firebase Console → Project settings → Your apps → Web app SDK.', 'backoffice-manager-for-firebase' ) . '</p>';
}

function bomff_get_firebase_option( $key ) {
    $options = get_option( 'bomff_firebase_config', array() );
    return isset( $options[ $key ] ) ? esc_attr( $options[ $key ] ) : '';
}

function bomff_field_api_key_cb() {
    printf(
        '<input type="text" name="bomff_firebase_config[apiKey]" value="%s" class="regular-text" />',
        esc_attr( bomff_get_firebase_option( 'apiKey' ) )
    );
}

function bomff_field_auth_domain_cb() {
    printf(
        '<input type="text" name="bomff_firebase_config[authDomain]" value="%s" class="regular-text" />',
        esc_attr( bomff_get_firebase_option( 'authDomain' ) )
    );
}

function bomff_field_project_id_cb() {
    printf(
        '<input type="text" name="bomff_firebase_config[projectId]" value="%s" class="regular-text" />',
        esc_attr( bomff_get_firebase_option( 'projectId' ) )
    );
}

function bomff_field_storage_bucket_cb() {
    printf(
        '<input type="text" name="bomff_firebase_config[storageBucket]" value="%s" class="regular-text" />',
        esc_attr( bomff_get_firebase_option( 'storageBucket' ) )
    );
}

function bomff_field_messaging_sender_id_cb() {
    printf(
        '<input type="text" name="bomff_firebase_config[messagingSenderId]" value="%s" class="regular-text" />',
        esc_attr( bomff_get_firebase_option( 'messagingSenderId' ) )
    );
}

function bomff_field_app_id_cb() {
    printf(
        '<input type="text" name="bomff_firebase_config[appId]" value="%s" class="regular-text" />',
        esc_attr( bomff_get_firebase_option( 'appId' ) )
    );
}

/**
 * 4) Enqueue scripts and styles (Dashboard + Firestore + Settings)
 */
function bomff_enqueue_admin_scripts( $hook ) {

    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

    $allowed_pages = array(
        'bomff-admin-panel',
        'bomff-settings',
    );

    if ( ! in_array( $page, $allowed_pages, true ) ) {
        return;
    }
	
	$css_path = BOMFF_PLUGIN_PATH . 'assets/css/firebase-wp-style.css';
	$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';

	wp_enqueue_style(
		'bomff-admin-style',
		BOMFF_PLUGIN_URL . 'assets/css/firebase-wp-style.css',
		array(),
		$css_ver
	);

    $base = plugin_dir_url( __FILE__ ) . 'assets/vendor/firebasejs/10.12.3/';

    wp_enqueue_script(
        'firebase-app-sdk',
        $base . 'firebase-app-compat.js',
        array(),
        '10.12.3',
        true
    );

    wp_enqueue_script(
        'firebase-firestore-sdk',
        $base . 'firebase-firestore-compat.js',
        array( 'firebase-app-sdk' ),
        '10.12.3',
        true
    );	

    wp_enqueue_script(
        'firebase-auth-sdk',
        $base . 'firebase-auth-compat.js',
        array( 'firebase-app-sdk' ),
        '10.12.3',
        true
    );

    $js_path = BOMFF_PLUGIN_PATH . 'bomff-scripts.js';
    $js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0';

    wp_enqueue_script(
        'bomff-admin-js',
        BOMFF_PLUGIN_URL . 'bomff-scripts.js',
        array( 'firebase-app-sdk', 'firebase-firestore-sdk', 'firebase-auth-sdk' ),
        $js_ver,
        true
    );

    // Pass config to JS
    $firebase_options = get_option( 'bomff_firebase_config', array() );

    $firebase_config = array(
        'apiKey'            => isset( $firebase_options['apiKey'] ) ? $firebase_options['apiKey'] : '',
        'authDomain'        => isset( $firebase_options['authDomain'] ) ? $firebase_options['authDomain'] : '',
        'projectId'         => isset( $firebase_options['projectId'] ) ? $firebase_options['projectId'] : '',
        'storageBucket'     => isset( $firebase_options['storageBucket'] ) ? $firebase_options['storageBucket'] : '',
        'messagingSenderId' => isset( $firebase_options['messagingSenderId'] ) ? $firebase_options['messagingSenderId'] : '',
        'appId'             => isset( $firebase_options['appId'] ) ? $firebase_options['appId'] : '',
        'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
        'nonce'             => wp_create_nonce( 'bomff_ajax' ),
        'isPro'             => (bool) apply_filters( 'bomff_is_pro', false ),
    );

    wp_localize_script(
        'bomff-admin-js',
        'bomffFirebaseConfig',
        $firebase_config
    );
	
	do_action( 'bomff_admin_enqueue_after', $page, $hook );
}
add_action( 'admin_enqueue_scripts', 'bomff_enqueue_admin_scripts' );

/**
 * Active structure (only one). Stored as a WordPress option.
 */
function bomff_get_active_structure() {
    $opt = get_option( 'bomff_active_structure', array() );
    return is_array( $opt ) ? $opt : array();
}

function bomff_ajax_require_admin() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'backoffice-manager-for-firebase' ) ), 403 );
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bomff_ajax' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'backoffice-manager-for-firebase' ) ), 403 );
    }
}

function bomff_ajax_get_structure() {
    bomff_ajax_require_admin();
    $structure = bomff_get_active_structure();
    wp_send_json_success( array( 'structure' => $structure ) );
}
add_action( 'wp_ajax_bomff_get_structure', 'bomff_ajax_get_structure' );

function bomff_ajax_save_structure() {
    bomff_ajax_require_admin();

    $collection = isset( $_POST['collection'] ) ? sanitize_text_field( wp_unslash( $_POST['collection'] ) ) : '';
    $fields_raw = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : '';

    if ( empty( $collection ) ) {
        wp_send_json_error( array( 'message' => __( 'Empty collection.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $fields = json_decode( $fields_raw, true );
    if ( ! is_array( $fields ) || empty( $fields ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid fields.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    // Sanitize fields: { name, type, required?, auto? }
    $clean_fields = array();
    foreach ( $fields as $f ) {
        if ( ! is_array( $f ) ) {
            continue;
        }

        $name = isset( $f['name'] ) ? sanitize_text_field( $f['name'] ) : '';
        $type = isset( $f['type'] ) ? sanitize_text_field( $f['type'] ) : 'string';

        if ( empty( $name ) ) {
            continue;
        }

        $required = ! empty( $f['required'] );
        $auto     = ! empty( $f['auto'] );

        $clean_fields[] = array(
            'name'     => $name,
            'type'     => $type,
            'required' => $required,
            'auto'     => $auto,
        );
    }

    if ( empty( $clean_fields ) ) {
        wp_send_json_error( array( 'message' => __( 'No valid fields.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $structure = array(
        'collection' => $collection,
        'fields'     => $clean_fields,
        'updatedAt'  => time(),
    );

    update_option( 'bomff_active_structure', $structure, false );

    wp_send_json_success( array( 'structure' => $structure ) );
}
add_action( 'wp_ajax_bomff_save_structure', 'bomff_ajax_save_structure' );

function bomff_ajax_delete_structure() {
    bomff_ajax_require_admin();
    delete_option( 'bomff_active_structure' );
    wp_send_json_success( array( 'deleted' => true ) );
}
add_action( 'wp_ajax_bomff_delete_structure', 'bomff_ajax_delete_structure' );
