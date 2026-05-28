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

if ( ! defined( 'BOMFF_CAPABILITY' ) ) {
    define( 'BOMFF_CAPABILITY', 'manage_options' );
}

function bomff_add_admin_menu() {
    add_menu_page(
        __( 'BOM Firebase', 'backoffice-manager-for-firebase' ),
        __( 'BOM Firebase', 'backoffice-manager-for-firebase' ),
        BOMFF_CAPABILITY,
        'bomff-admin-panel',
        'bomff_render_firestore_page',
        'dashicons-database',
        80
    );

    add_submenu_page(
        'bomff-admin-panel',
        __( 'Firestore', 'backoffice-manager-for-firebase' ),
        __( 'Firestore', 'backoffice-manager-for-firebase' ),
        BOMFF_CAPABILITY,
        'bomff-admin-panel',
        'bomff_render_firestore_page'
    );

    add_submenu_page(
        'bomff-admin-panel',
        __( 'Settings', 'backoffice-manager-for-firebase' ),
        __( 'Settings', 'backoffice-manager-for-firebase' ),
        BOMFF_CAPABILITY,
        'bomff-settings',
        'bomff_render_settings_page'
    );
}
add_action( 'admin_menu', 'bomff_add_admin_menu' );

function bomff_render_firestore_page() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    include BOMFF_PLUGIN_PATH . 'pages/firestore.php';
}

function bomff_render_settings_page() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    include BOMFF_PLUGIN_PATH . 'bomff-settings.php';
}

function bomff_enqueue_admin_scripts( $hook ) {
    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

    if ( ! in_array( $page, array( 'bomff-admin-panel', 'bomff-settings' ), true ) ) {
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

    $js_path = BOMFF_PLUGIN_PATH . 'bomff-scripts.js';
    $js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0';

    wp_enqueue_script(
        'bomff-admin-js',
        BOMFF_PLUGIN_URL . 'bomff-scripts.js',
        array(),
        $js_ver,
        true
    );

    $service_account = bomff_get_service_account();

    wp_localize_script(
        'bomff-admin-js',
        'bomffFirebaseConfig',
        array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'settingsUrl' => admin_url( 'admin.php?page=bomff-settings' ),
            'nonce'       => wp_create_nonce( 'bomff_ajax' ),
            'isPro'       => (bool) apply_filters( 'bomff_is_pro', false ),
            'configured'  => (bool) $service_account,
            'projectId'   => is_array( $service_account ) && ! empty( $service_account['project_id'] ) ? $service_account['project_id'] : '',
        )
    );

    do_action( 'bomff_admin_enqueue_after', $page, $hook );
}
add_action( 'admin_enqueue_scripts', 'bomff_enqueue_admin_scripts' );

function bomff_crypto_key() {
    $material = '';
    foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
        if ( defined( $constant ) ) {
            $material .= constant( $constant );
        }
    }

    if ( empty( $material ) ) {
        $material = wp_salt( 'auth' );
    }

    return hash( 'sha256', $material, true );
}

function bomff_encrypt_secret( $plain_text ) {
    if ( ! function_exists( 'openssl_encrypt' ) ) {
        return new WP_Error( 'openssl_missing', __( 'OpenSSL is required to encrypt Firebase credentials.', 'backoffice-manager-for-firebase' ) );
    }

    $iv     = random_bytes( 16 );
    $cipher = openssl_encrypt( $plain_text, 'AES-256-CBC', bomff_crypto_key(), OPENSSL_RAW_DATA, $iv );

    if ( false === $cipher ) {
        return new WP_Error( 'encrypt_failed', __( 'Could not encrypt Firebase credentials.', 'backoffice-manager-for-firebase' ) );
    }

    return base64_encode(
        wp_json_encode(
            array(
                'iv'   => base64_encode( $iv ),
                'data' => base64_encode( $cipher ),
            )
        )
    );
}

function bomff_decrypt_secret( $stored ) {
    if ( empty( $stored ) || ! function_exists( 'openssl_decrypt' ) ) {
        return '';
    }

    $decoded = json_decode( base64_decode( $stored ), true );
    if ( ! is_array( $decoded ) || empty( $decoded['iv'] ) || empty( $decoded['data'] ) ) {
        return '';
    }

    $plain = openssl_decrypt(
        base64_decode( $decoded['data'] ),
        'AES-256-CBC',
        bomff_crypto_key(),
        OPENSSL_RAW_DATA,
        base64_decode( $decoded['iv'] )
    );

    return false === $plain ? '' : $plain;
}

function bomff_validate_service_account( $json ) {
    $data = json_decode( $json, true );

    if ( ! is_array( $data ) ) {
        return new WP_Error( 'invalid_json', __( 'Invalid JSON file.', 'backoffice-manager-for-firebase' ) );
    }

    foreach ( array( 'type', 'project_id', 'private_key', 'client_email', 'token_uri' ) as $key ) {
        if ( empty( $data[ $key ] ) ) {
            return new WP_Error( 'missing_key', sprintf( __( 'Missing service account field: %s', 'backoffice-manager-for-firebase' ), $key ) );
        }
    }

    if ( 'service_account' !== $data['type'] ) {
        return new WP_Error( 'invalid_type', __( 'The uploaded file is not a Firebase Service Account JSON.', 'backoffice-manager-for-firebase' ) );
    }

    return $data;
}

function bomff_get_service_account() {
    $stored = get_option( 'bomff_service_account_encrypted', '' );
    if ( empty( $stored ) ) {
        return null;
    }

    $json = bomff_decrypt_secret( $stored );
    if ( empty( $json ) ) {
        return null;
    }

    $data = bomff_validate_service_account( $json );
    return is_wp_error( $data ) ? null : $data;
}

function bomff_handle_service_account_upload() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'Unauthorized.', 'backoffice-manager-for-firebase' ) );
    }

    check_admin_referer( 'bomff_save_service_account' );

    if ( empty( $_FILES['bomff_service_account_json']['tmp_name'] ) ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_error' => rawurlencode( __( 'Please select a Service Account JSON file.', 'backoffice-manager-for-firebase' ) ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    $file = $_FILES['bomff_service_account_json'];

    if ( ! empty( $file['error'] ) ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_error' => rawurlencode( __( 'Upload failed.', 'backoffice-manager-for-firebase' ) ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    $json = file_get_contents( $file['tmp_name'] );
    $data = bomff_validate_service_account( $json );

    if ( is_wp_error( $data ) ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_error' => rawurlencode( $data->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    $encrypted = bomff_encrypt_secret( $json );
    if ( is_wp_error( $encrypted ) ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_error' => rawurlencode( $encrypted->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    update_option( 'bomff_service_account_encrypted', $encrypted, false );
    delete_transient( 'bomff_firebase_access_token' );

    wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_saved' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_bomff_save_service_account', 'bomff_handle_service_account_upload' );

function bomff_handle_service_account_delete() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'Unauthorized.', 'backoffice-manager-for-firebase' ) );
    }

    check_admin_referer( 'bomff_delete_service_account' );

    delete_option( 'bomff_service_account_encrypted' );
    delete_transient( 'bomff_firebase_access_token' );

    wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_deleted' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_bomff_delete_service_account', 'bomff_handle_service_account_delete' );

function bomff_base64url_encode( $data ) {
    return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

function bomff_get_access_token() {
    $cached = get_transient( 'bomff_firebase_access_token' );
    if ( ! empty( $cached ) ) {
        return $cached;
    }

    $service_account = bomff_get_service_account();
    if ( ! $service_account ) {
        return new WP_Error( 'not_configured', __( 'Firebase Service Account is not configured.', 'backoffice-manager-for-firebase' ) );
    }

    $now = time();

    $segments = array(
        bomff_base64url_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ),
        bomff_base64url_encode(
            wp_json_encode(
                array(
                    'iss'   => $service_account['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/datastore',
                    'aud'   => $service_account['token_uri'],
                    'iat'   => $now,
                    'exp'   => $now + 3600,
                )
            )
        ),
    );

    $signing_input = implode( '.', $segments );
    $signature     = '';

    $ok = openssl_sign( $signing_input, $signature, $service_account['private_key'], 'sha256WithRSAEncryption' );
    if ( ! $ok ) {
        return new WP_Error( 'jwt_sign_failed', __( 'Could not sign Firebase authentication request.', 'backoffice-manager-for-firebase' ) );
    }

    $jwt = $signing_input . '.' . bomff_base64url_encode( $signature );

    $response = wp_remote_post(
        $service_account['token_uri'],
        array(
            'timeout' => 20,
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $code || empty( $body['access_token'] ) ) {
        return new WP_Error( 'token_failed', __( 'Could not obtain Firebase access token.', 'backoffice-manager-for-firebase' ) );
    }

    set_transient( 'bomff_firebase_access_token', $body['access_token'], max( 60, intval( $body['expires_in'] ?? 3600 ) - 120 ) );

    return $body['access_token'];
}

function bomff_build_query_string( $query ) {
    if ( empty( $query ) || ! is_array( $query ) ) {
        return '';
    }

    $parts = array();

    foreach ( $query as $key => $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $single ) {
                $parts[] = rawurlencode( $key ) . '=' . rawurlencode( $single );
            }
        } else {
            $parts[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
        }
    }

    return implode( '&', $parts );
}

function bomff_firestore_url( $path = '', $query = array() ) {
    $service_account = bomff_get_service_account();
    if ( ! $service_account || empty( $service_account['project_id'] ) ) {
        return new WP_Error( 'not_configured', __( 'Firebase Service Account is not configured.', 'backoffice-manager-for-firebase' ) );
    }

    $base = sprintf(
        'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents',
        rawurlencode( $service_account['project_id'] )
    );

    if ( '' !== $path ) {
        $base .= '/' . ltrim( $path, '/' );
    }

    $query_string = bomff_build_query_string( $query );
    if ( '' !== $query_string ) {
        $base .= '?' . $query_string;
    }

    return $base;
}

function bomff_firestore_request( $method, $path = '', $body = null, $query = array() ) {
    $token = bomff_get_access_token();
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $url = bomff_firestore_url( $path, $query );
    if ( is_wp_error( $url ) ) {
        return $url;
    }

    $args = array(
        'method'  => $method,
        'timeout' => 30,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ),
    );

    if ( null !== $body ) {
        $args['body'] = wp_json_encode( $body );
    }

    $response = wp_remote_request( $url, $args );
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code     = wp_remote_retrieve_response_code( $response );
    $raw_body = wp_remote_retrieve_body( $response );
    $json     = '' !== $raw_body ? json_decode( $raw_body, true ) : array();

    if ( $code < 200 || $code >= 300 ) {
        $message = isset( $json['error']['message'] ) ? $json['error']['message'] : __( 'Firestore request failed.', 'backoffice-manager-for-firebase' );
        return new WP_Error( 'firestore_error', $message, array( 'status' => $code ) );
    }

    return is_array( $json ) ? $json : array();
}

function bomff_clean_collection_or_doc_id( $value ) {
    $value = sanitize_text_field( wp_unslash( $value ) );
    $value = trim( $value, " \t\n\r\0\x0B/" );

    if ( '' === $value || preg_match( '#(^|/)\.\.?($|/)#', $value ) ) {
        return '';
    }

    return preg_replace( '#[^A-Za-z0-9_\-/]#', '', $value );
}

function bomff_php_to_firestore_value( $value ) {
    if ( is_null( $value ) ) {
        return array( 'nullValue' => null );
    }

    if ( is_bool( $value ) ) {
        return array( 'booleanValue' => $value );
    }

    if ( is_int( $value ) ) {
        return array( 'integerValue' => (string) $value );
    }

    if ( is_float( $value ) ) {
        return array( 'doubleValue' => $value );
    }

    if ( is_array( $value ) ) {
        if ( isset( $value['_type'] ) && 'timestamp' === $value['_type'] && ! empty( $value['seconds'] ) ) {
            return array( 'timestampValue' => gmdate( 'Y-m-d\TH:i:s\Z', intval( $value['seconds'] ) ) );
        }

        $is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
        if ( $is_list ) {
            return array( 'arrayValue' => array( 'values' => array_map( 'bomff_php_to_firestore_value', $value ) ) );
        }

        $fields = array();
        foreach ( $value as $k => $v ) {
            $fields[ $k ] = bomff_php_to_firestore_value( $v );
        }

        return array( 'mapValue' => array( 'fields' => $fields ) );
    }

    return array( 'stringValue' => (string) $value );
}

function bomff_firestore_value_to_php( $value ) {
    if ( isset( $value['nullValue'] ) ) {
        return null;
    }
    if ( isset( $value['booleanValue'] ) ) {
        return (bool) $value['booleanValue'];
    }
    if ( isset( $value['integerValue'] ) ) {
        return intval( $value['integerValue'] );
    }
    if ( isset( $value['doubleValue'] ) ) {
        return floatval( $value['doubleValue'] );
    }
    if ( isset( $value['stringValue'] ) ) {
        return $value['stringValue'];
    }
    if ( isset( $value['timestampValue'] ) ) {
        return array( '_type' => 'timestamp', 'iso' => $value['timestampValue'] );
    }
    if ( isset( $value['arrayValue'] ) ) {
        return array_map( 'bomff_firestore_value_to_php', $value['arrayValue']['values'] ?? array() );
    }
    if ( isset( $value['mapValue'] ) ) {
        $out = array();
        foreach ( $value['mapValue']['fields'] ?? array() as $k => $v ) {
            $out[ $k ] = bomff_firestore_value_to_php( $v );
        }
        return $out;
    }

    return null;
}

function bomff_firestore_document_to_php( $doc ) {
    $parts = explode( '/', $doc['name'] ?? '' );
    $id    = end( $parts );
    $data  = array();

    foreach ( $doc['fields'] ?? array() as $k => $v ) {
        $data[ $k ] = bomff_firestore_value_to_php( $v );
    }

    return array(
        'id'         => $id,
        'data'       => $data,
        'createTime' => $doc['createTime'] ?? '',
        'updateTime' => $doc['updateTime'] ?? '',
    );
}

function bomff_php_to_firestore_document( $data ) {
    $fields = array();
    foreach ( $data as $k => $v ) {
        $fields[ $k ] = bomff_php_to_firestore_value( $v );
    }
    return array( 'fields' => $fields );
}

function bomff_get_active_structure() {
    $opt = get_option( 'bomff_active_structure', array() );
    return is_array( $opt ) ? $opt : array();
}

function bomff_ajax_require_admin() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'backoffice-manager-for-firebase' ) ), 403 );
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bomff_ajax' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'backoffice-manager-for-firebase' ) ), 403 );
    }
}

function bomff_ajax_get_status() {
    bomff_ajax_require_admin();

    $service_account = bomff_get_service_account();
    if ( ! $service_account ) {
        wp_send_json_success( array( 'configured' => false, 'projectId' => '', 'message' => __( 'Firebase Service Account is not configured.', 'backoffice-manager-for-firebase' ) ) );
    }

    wp_send_json_success(
        array(
            'configured'  => true,
            'projectId'   => $service_account['project_id'] ?? '',
            'clientEmail' => $service_account['client_email'] ?? '',
        )
    );
}
add_action( 'wp_ajax_bomff_get_status', 'bomff_ajax_get_status' );

function bomff_ajax_list_documents() {
    bomff_ajax_require_admin();

    $collection = isset( $_POST['collection'] ) ? bomff_clean_collection_or_doc_id( $_POST['collection'] ) : '';
    $page_size  = isset( $_POST['pageSize'] ) ? max( 1, min( 100, intval( $_POST['pageSize'] ) ) ) : 25;
    $page_token = isset( $_POST['pageToken'] ) ? sanitize_text_field( wp_unslash( $_POST['pageToken'] ) ) : '';

    if ( empty( $collection ) ) {
        wp_send_json_error( array( 'message' => __( 'Empty collection.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $query = array( 'pageSize' => $page_size, 'orderBy' => '__name__' );
    if ( ! empty( $page_token ) ) {
        $query['pageToken'] = $page_token;
    }

    $response = bomff_firestore_request( 'GET', $collection, null, $query );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ), 400 );
    }

    $documents = array();
    foreach ( $response['documents'] ?? array() as $doc ) {
        $documents[] = bomff_firestore_document_to_php( $doc );
    }

    wp_send_json_success( array( 'documents' => $documents, 'nextPageToken' => $response['nextPageToken'] ?? '' ) );
}
add_action( 'wp_ajax_bomff_list_documents', 'bomff_ajax_list_documents' );

function bomff_ajax_get_document() {
    bomff_ajax_require_admin();

    $collection = isset( $_POST['collection'] ) ? bomff_clean_collection_or_doc_id( $_POST['collection'] ) : '';
    $doc_id     = isset( $_POST['docId'] ) ? bomff_clean_collection_or_doc_id( $_POST['docId'] ) : '';

    if ( empty( $collection ) || empty( $doc_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Collection and document ID are required.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $response = bomff_firestore_request( 'GET', $collection . '/' . $doc_id );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ), 400 );
    }

    wp_send_json_success( array( 'document' => bomff_firestore_document_to_php( $response ) ) );
}
add_action( 'wp_ajax_bomff_get_document', 'bomff_ajax_get_document' );

function bomff_ajax_save_document() {
    bomff_ajax_require_admin();

    $collection = isset( $_POST['collection'] ) ? bomff_clean_collection_or_doc_id( $_POST['collection'] ) : '';
    $doc_id     = isset( $_POST['docId'] ) ? bomff_clean_collection_or_doc_id( $_POST['docId'] ) : '';
    $data_raw   = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';

    if ( empty( $collection ) || empty( $doc_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Collection and document ID are required.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $data = json_decode( $data_raw, true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid JSON data.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $existing_response = bomff_firestore_request( 'GET', $collection . '/' . $doc_id );
    $existing_fields   = array();

    if ( ! is_wp_error( $existing_response ) && ! empty( $existing_response['fields'] ) ) {
        $existing_fields = array_keys( $existing_response['fields'] );
    }

    $all_fields = array_values( array_unique( array_merge( $existing_fields, array_keys( $data ) ) ) );
    $query      = empty( $all_fields ) ? array() : array( 'updateMask.fieldPaths' => $all_fields );

    $response = bomff_firestore_request( 'PATCH', $collection . '/' . $doc_id, bomff_php_to_firestore_document( $data ), $query );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ), 400 );
    }

    do_action( 'bomff_firestore_document_saved', get_current_user_id(), $collection, $doc_id, $data );
    wp_send_json_success( array( 'document' => bomff_firestore_document_to_php( $response ) ) );
}
add_action( 'wp_ajax_bomff_save_document', 'bomff_ajax_save_document' );

function bomff_ajax_delete_document() {
    bomff_ajax_require_admin();

    $collection = isset( $_POST['collection'] ) ? bomff_clean_collection_or_doc_id( $_POST['collection'] ) : '';
    $doc_id     = isset( $_POST['docId'] ) ? bomff_clean_collection_or_doc_id( $_POST['docId'] ) : '';

    if ( empty( $collection ) || empty( $doc_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Collection and document ID are required.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $response = bomff_firestore_request( 'DELETE', $collection . '/' . $doc_id );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ), 400 );
    }

    do_action( 'bomff_firestore_document_deleted', get_current_user_id(), $collection, $doc_id );
    wp_send_json_success( array( 'deleted' => true ) );
}
add_action( 'wp_ajax_bomff_delete_document', 'bomff_ajax_delete_document' );

function bomff_ajax_get_structure() {
    bomff_ajax_require_admin();
    wp_send_json_success( array( 'structure' => bomff_get_active_structure() ) );
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

        $clean_fields[] = array(
            'name'     => $name,
            'type'     => $type,
            'required' => ! empty( $f['required'] ),
            'auto'     => ! empty( $f['auto'] ),
        );
    }

    if ( empty( $clean_fields ) ) {
        wp_send_json_error( array( 'message' => __( 'No valid fields.', 'backoffice-manager-for-firebase' ) ), 400 );
    }

    $structure = array( 'collection' => $collection, 'fields' => $clean_fields, 'updatedAt' => time() );
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
