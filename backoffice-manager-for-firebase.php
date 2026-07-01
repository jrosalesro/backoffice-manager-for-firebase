<?php
/*
Plugin Name: Firebase Integration for WordPress – Firestore & Auth
Description: Connect WordPress to Firebase Firestore and Authentication to manage collections, documents, and Auth users from your admin area.
Version: 0.4.0
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


function bomff_is_demo_mode_context() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( 'bomff-demo' === $page ) {
        return true;
    }

    $demo = isset( $_GET['bomff_demo'] ) ? sanitize_text_field( wp_unslash( $_GET['bomff_demo'] ) ) : '';
    return '1' === $demo;
}

function bomff_is_demo_mode_request() {
    $demo = isset( $_POST['demoMode'] ) ? sanitize_text_field( wp_unslash( $_POST['demoMode'] ) ) : '';
    return '1' === $demo || 'true' === $demo;
}


function bomff_is_onboarding_completed() {
    return '1' === get_option( 'bomff_onboarding_completed', '0' );
}

function bomff_is_welcome_screen_requested() {
    $requested = isset( $_GET['bomff_show_welcome'] ) ? sanitize_text_field( wp_unslash( $_GET['bomff_show_welcome'] ) ) : '';
    $nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    return '1' === $requested && wp_verify_nonce( $nonce, 'bomff_show_welcome' );
}

function bomff_should_show_welcome_screen() {
    if ( bomff_is_demo_mode_context() ) {
        return false;
    }

    if ( bomff_is_welcome_screen_requested() ) {
        return true;
    }

    if ( bomff_get_service_account() ) {
        return false;
    }

    return ! bomff_is_onboarding_completed();
}

function bomff_render_welcome_screen() {
    $connect_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'       => 'bomff_complete_onboarding',
                'bomff_choice' => 'connect',
            ),
            admin_url( 'admin-post.php' )
        ),
        'bomff_complete_onboarding'
    );

    $demo_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'       => 'bomff_complete_onboarding',
                'bomff_choice' => 'demo',
            ),
            admin_url( 'admin-post.php' )
        ),
        'bomff_complete_onboarding'
    );
    ?>
    <div class="wrap bomff-wrap bomff-welcome-wrap">
        <div class="bomff-welcome-hero">
            <p class="bomff-welcome-kicker"><?php esc_html_e( 'Getting Started', 'backoffice-manager-for-firebase' ); ?></p>
            <h1><?php esc_html_e( 'Welcome to Firebase Integration for WordPress', 'backoffice-manager-for-firebase' ); ?></h1>
            <p class="bomff-welcome-description">
                <?php esc_html_e( 'Manage your Firebase data directly from WordPress.', 'backoffice-manager-for-firebase' ); ?>
            </p>
        </div>

        <div class="bomff-welcome-options" aria-label="<?php echo esc_attr__( 'Getting started options', 'backoffice-manager-for-firebase' ); ?>">
            <div class="bomff-welcome-card">
                <div class="bomff-welcome-icon" aria-hidden="true">🚀</div>
                <h2><?php esc_html_e( 'Connect Firebase', 'backoffice-manager-for-firebase' ); ?></h2>
                <p><?php esc_html_e( 'Configure a real Firebase project and start managing your Firestore data.', 'backoffice-manager-for-firebase' ); ?></p>
                <a class="button button-primary button-hero" href="<?php echo esc_url( $connect_url ); ?>">
                    <?php esc_html_e( 'Connect Firebase', 'backoffice-manager-for-firebase' ); ?>
                </a>
            </div>

            <div class="bomff-welcome-card">
                <div class="bomff-welcome-icon" aria-hidden="true">🧪</div>
                <h2><?php esc_html_e( 'Explore Demo Mode', 'backoffice-manager-for-firebase' ); ?></h2>
                <p><?php esc_html_e( 'Try the plugin using realistic sample data. No Firebase account or credentials required.', 'backoffice-manager-for-firebase' ); ?></p>
                <a class="button button-secondary button-hero" href="<?php echo esc_url( $demo_url ); ?>">
                    <?php esc_html_e( 'Start Demo Mode', 'backoffice-manager-for-firebase' ); ?>
                </a>
            </div>
        </div>

        <p class="bomff-welcome-note">
            <?php esc_html_e( 'You can switch between Demo Mode and a real Firebase project at any time.', 'backoffice-manager-for-firebase' ); ?>
        </p>
    </div>
    <?php
}

function bomff_handle_complete_onboarding() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'Unauthorized.', 'backoffice-manager-for-firebase' ) );
    }

    check_admin_referer( 'bomff_complete_onboarding' );

    $choice = isset( $_GET['bomff_choice'] ) ? sanitize_key( wp_unslash( $_GET['bomff_choice'] ) ) : 'connect';

    update_option( 'bomff_onboarding_completed', '1', false );

    if ( 'demo' === $choice ) {
        wp_safe_redirect( admin_url( 'admin.php?page=bomff-demo' ) );
        exit;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=bomff-settings' ) );
    exit;
}
add_action( 'admin_post_bomff_complete_onboarding', 'bomff_handle_complete_onboarding' );

function bomff_handle_show_onboarding() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'Unauthorized.', 'backoffice-manager-for-firebase' ) );
    }

    check_admin_referer( 'bomff_show_onboarding' );

    delete_option( 'bomff_onboarding_completed' );

    $welcome_url = wp_nonce_url(
        add_query_arg(
            array(
                'page'               => 'bomff-admin-panel',
                'bomff_show_welcome' => '1',
            ),
            admin_url( 'admin.php' )
        ),
        'bomff_show_welcome'
    );

    wp_safe_redirect( $welcome_url );
    exit;
}
add_action( 'admin_post_bomff_show_onboarding', 'bomff_handle_show_onboarding' );

function bomff_add_admin_menu() {
    add_menu_page(
        __( 'Firebase Integration for WordPress', 'backoffice-manager-for-firebase' ),
        __( 'Firebase Integration', 'backoffice-manager-for-firebase' ),
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
        __( 'Authentication', 'backoffice-manager-for-firebase' ),
        __( 'Authentication', 'backoffice-manager-for-firebase' ),
        BOMFF_CAPABILITY,
        'bomff-auth',
        'bomff_render_auth_page'
    );

    add_submenu_page(
        'bomff-admin-panel',
        __( 'Demo Mode', 'backoffice-manager-for-firebase' ),
        __( 'Demo Mode', 'backoffice-manager-for-firebase' ),
        BOMFF_CAPABILITY,
        'bomff-demo',
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

    if ( bomff_should_show_welcome_screen() ) {
        bomff_render_welcome_screen();
        return;
    }

    include BOMFF_PLUGIN_PATH . 'pages/firestore.php';
}

function bomff_render_auth_page() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    if ( bomff_should_show_welcome_screen() ) {
        bomff_render_welcome_screen();
        return;
    }

    include BOMFF_PLUGIN_PATH . 'pages/auth.php';
}

function bomff_render_settings_page() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
    }

    include BOMFF_PLUGIN_PATH . 'bomff-settings.php';
}

function bomff_enqueue_admin_scripts( $hook ) {
    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

    if ( ! in_array( $page, array( 'bomff-admin-panel', 'bomff-auth', 'bomff-demo', 'bomff-settings' ), true ) ) {
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
    $demo_mode       = bomff_is_demo_mode_context();

    wp_localize_script(
        'bomff-admin-js',
        'bomffFirebaseConfig',
        array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'settingsUrl' => admin_url( 'admin.php?page=bomff-settings' ),
            'demoUrl'     => admin_url( 'admin.php?page=bomff-demo' ),
            'nonce'       => wp_create_nonce( 'bomff_ajax' ),
            'isPro'       => (bool) apply_filters( 'bomff_is_pro', false ),
            'configured'  => (bool) $service_account,
            'demoMode'    => (bool) $demo_mode,
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
    delete_transient( 'bomff_firebase_access_token_v2' );

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
    delete_transient( 'bomff_firebase_access_token_v2' );

    wp_safe_redirect( add_query_arg( array( 'page' => 'bomff-settings', 'bomff_deleted' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_bomff_delete_service_account', 'bomff_handle_service_account_delete' );

function bomff_base64url_encode( $data ) {
    return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

function bomff_get_access_token() {
    $cached = get_transient( 'bomff_firebase_access_token_v2' );
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
                    'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/identitytoolkit https://www.googleapis.com/auth/firebase',
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

    set_transient( 'bomff_firebase_access_token_v2', $body['access_token'], max( 60, intval( $body['expires_in'] ?? 3600 ) - 120 ) );

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

function bomff_demo_seed_data() {
    $now = gmdate( 'c' );

    return array(
        'users'    => array(
            'usr_1001' => array(
                'name'       => 'Maya Chen',
                'email'      => 'maya.chen@example.com',
                'role'       => 'customer',
                'status'     => 'active',
                'createdAt'  => '2026-05-12',
                'lastLogin'  => $now,
                'orders'     => 3,
                'newsletter' => true,
                'address'    => array(
                    'city'    => 'Austin',
                    'region'  => 'TX',
                    'country' => 'US',
                ),
            ),
            'usr_1002' => array(
                'name'       => 'Daniel Rivera',
                'email'      => 'daniel.rivera@example.com',
                'role'       => 'manager',
                'status'     => 'active',
                'createdAt'  => '2026-04-03',
                'lastLogin'  => '2026-06-01T16:42:00Z',
                'orders'     => 8,
                'newsletter' => false,
                'address'    => array(
                    'city'    => 'Denver',
                    'region'  => 'CO',
                    'country' => 'US',
                ),
            ),
            'usr_1003' => array(
                'name'       => 'Olivia Thompson',
                'email'      => 'olivia.thompson@example.com',
                'role'       => 'customer',
                'status'     => 'pending',
                'createdAt'  => '2026-05-28',
                'lastLogin'  => null,
                'orders'     => 0,
                'newsletter' => true,
                'address'    => array(
                    'city'    => 'Portland',
                    'region'  => 'OR',
                    'country' => 'US',
                ),
            ),
        ),
        'orders'   => array(
            'ord_9001' => array(
                'userId'      => 'usr_1001',
                'customer'    => 'Maya Chen',
                'status'      => 'paid',
                'total'       => 149.98,
                'currency'    => 'USD',
                'createdAt'   => '2026-05-29T14:10:00Z',
                'items'       => array( 'prod_2001', 'prod_2003' ),
                'shipping'    => array(
                    'method' => 'UPS Ground',
                    'city'   => 'Austin',
                ),
            ),
            'ord_9002' => array(
                'userId'      => 'usr_1002',
                'customer'    => 'Daniel Rivera',
                'status'      => 'fulfilled',
                'total'       => 79.99,
                'currency'    => 'USD',
                'createdAt'   => '2026-05-21T09:35:00Z',
                'items'       => array( 'prod_2002' ),
                'shipping'    => array(
                    'method' => 'USPS Priority',
                    'city'   => 'Denver',
                ),
            ),
            'ord_9003' => array(
                'userId'      => 'usr_1001',
                'customer'    => 'Maya Chen',
                'status'      => 'processing',
                'total'       => 249.5,
                'currency'    => 'USD',
                'createdAt'   => '2026-06-02T18:22:00Z',
                'items'       => array( 'prod_2004', 'prod_2005' ),
                'shipping'    => array(
                    'method' => 'FedEx 2Day',
                    'city'   => 'Austin',
                ),
            ),
        ),
        'products' => array(
            'prod_2001' => array(
                'name'        => 'Ergonomic Keyboard',
                'sku'         => 'KEY-ERG-001',
                'price'       => 89.99,
                'inventory'   => 42,
                'active'      => true,
                'category'    => 'Accessories',
                'tags'        => array( 'office', 'productivity', 'hardware' ),
                'updatedAt'   => '2026-05-30T12:00:00Z',
            ),
            'prod_2002' => array(
                'name'        => 'USB-C Docking Station',
                'sku'         => 'DOCK-USBC-009',
                'price'       => 79.99,
                'inventory'   => 18,
                'active'      => true,
                'category'    => 'Accessories',
                'tags'        => array( 'usb-c', 'desk', 'hardware' ),
                'updatedAt'   => '2026-05-22T08:15:00Z',
            ),
            'prod_2003' => array(
                'name'        => 'Desk Cable Tray',
                'sku'         => 'DSK-CBL-014',
                'price'       => 59.99,
                'inventory'   => 64,
                'active'      => true,
                'category'    => 'Office',
                'tags'        => array( 'desk', 'organization' ),
                'updatedAt'   => '2026-05-26T10:45:00Z',
            ),
            'prod_2004' => array(
                'name'        => 'Adjustable Laptop Stand',
                'sku'         => 'STAND-LAP-022',
                'price'       => 119.5,
                'inventory'   => 27,
                'active'      => true,
                'category'    => 'Office',
                'tags'        => array( 'ergonomic', 'desk' ),
                'updatedAt'   => '2026-06-01T15:30:00Z',
            ),
            'prod_2005' => array(
                'name'        => 'Noise Cancelling Headset',
                'sku'         => 'HEAD-NC-031',
                'price'       => 130,
                'inventory'   => 11,
                'active'      => false,
                'category'    => 'Audio',
                'tags'        => array( 'remote-work', 'audio' ),
                'updatedAt'   => '2026-05-18T11:05:00Z',
            ),
        ),
    );
}

function bomff_get_demo_data() {
    $data = get_user_meta( get_current_user_id(), 'bomff_demo_firestore_data', true );

    if ( ! is_array( $data ) || empty( $data ) ) {
        $data = bomff_demo_seed_data();
        update_user_meta( get_current_user_id(), 'bomff_demo_firestore_data', $data );
    }

    return $data;
}

function bomff_update_demo_data( $data ) {
    update_user_meta( get_current_user_id(), 'bomff_demo_firestore_data', is_array( $data ) ? $data : bomff_demo_seed_data() );
}

function bomff_demo_document_to_php( $doc_id, $data ) {
    return array(
        'id'         => $doc_id,
        'data'       => is_array( $data ) ? $data : array(),
        'createTime' => '',
        'updateTime' => gmdate( 'c' ),
    );
}

function bomff_demo_list_documents( $collection, $page_size, $page_token ) {
    $data = bomff_get_demo_data();

    if ( ! isset( $data[ $collection ] ) || ! is_array( $data[ $collection ] ) ) {
        return array( 'documents' => array(), 'nextPageToken' => '' );
    }

    $docs   = $data[ $collection ];
    $ids    = array_keys( $docs );
    sort( $ids, SORT_NATURAL );
    $offset = '' !== $page_token ? max( 0, intval( $page_token ) ) : 0;
    $slice  = array_slice( $ids, $offset, $page_size );

    $documents = array();
    foreach ( $slice as $doc_id ) {
        $documents[] = bomff_demo_document_to_php( $doc_id, $docs[ $doc_id ] );
    }

    $next_offset = $offset + count( $slice );

    return array(
        'documents'     => $documents,
        'nextPageToken' => $next_offset < count( $ids ) ? (string) $next_offset : '',
    );
}

function bomff_demo_get_document( $collection, $doc_id ) {
    $data = bomff_get_demo_data();

    if ( ! isset( $data[ $collection ][ $doc_id ] ) || ! is_array( $data[ $collection ][ $doc_id ] ) ) {
        return new WP_Error( 'demo_not_found', __( 'Demo document not found.', 'backoffice-manager-for-firebase' ) );
    }

    return bomff_demo_document_to_php( $doc_id, $data[ $collection ][ $doc_id ] );
}

function bomff_demo_save_document( $collection, $doc_id, $doc_data ) {
    $data = bomff_get_demo_data();

    if ( ! isset( $data[ $collection ] ) || ! is_array( $data[ $collection ] ) ) {
        $data[ $collection ] = array();
    }

    $data[ $collection ][ $doc_id ] = $doc_data;
    bomff_update_demo_data( $data );

    return bomff_demo_document_to_php( $doc_id, $doc_data );
}

function bomff_demo_delete_document( $collection, $doc_id ) {
    $data = bomff_get_demo_data();

    if ( isset( $data[ $collection ][ $doc_id ] ) ) {
        unset( $data[ $collection ][ $doc_id ] );
        bomff_update_demo_data( $data );
    }

    return true;
}

function bomff_get_active_structure( $demo_mode = false ) {
    $opt = $demo_mode ? get_user_meta( get_current_user_id(), 'bomff_demo_active_structure', true ) : get_option( 'bomff_active_structure', array() );
    return is_array( $opt ) ? $opt : array();
}

function bomff_update_active_structure( $structure, $demo_mode = false ) {
    if ( $demo_mode ) {
        update_user_meta( get_current_user_id(), 'bomff_demo_active_structure', $structure );
        return;
    }

    update_option( 'bomff_active_structure', $structure, false );
}

function bomff_delete_active_structure( $demo_mode = false ) {
    if ( $demo_mode ) {
        delete_user_meta( get_current_user_id(), 'bomff_demo_active_structure' );
        return;
    }

    delete_option( 'bomff_active_structure' );
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

    if ( bomff_is_demo_mode_request() ) {
        wp_send_json_success(
            array(
                'configured'  => true,
                'demoMode'    => true,
                'projectId'   => 'demo-project',
                'clientEmail' => '',
                'message'     => __( 'Demo Mode: no real Firebase project is connected.', 'backoffice-manager-for-firebase' ),
            )
        );
    }

    $service_account = bomff_get_service_account();
    if ( ! $service_account ) {
        wp_send_json_success( array( 'configured' => false, 'demoMode' => false, 'projectId' => '', 'message' => __( 'Firebase Service Account is not configured.', 'backoffice-manager-for-firebase' ) ) );
    }

    wp_send_json_success(
        array(
            'configured'  => true,
            'demoMode'    => false,
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

    if ( bomff_is_demo_mode_request() ) {
        wp_send_json_success( bomff_demo_list_documents( $collection, $page_size, $page_token ) );
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

    if ( bomff_is_demo_mode_request() ) {
        $document = bomff_demo_get_document( $collection, $doc_id );
        if ( is_wp_error( $document ) ) {
            wp_send_json_error( array( 'message' => $document->get_error_message() ), 404 );
        }

        wp_send_json_success( array( 'document' => $document ) );
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

    if ( bomff_is_demo_mode_request() ) {
        $document = bomff_demo_save_document( $collection, $doc_id, $data );
        wp_send_json_success( array( 'document' => $document, 'demoMode' => true ) );
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

    if ( bomff_is_demo_mode_request() ) {
        bomff_demo_delete_document( $collection, $doc_id );
        wp_send_json_success( array( 'deleted' => true, 'demoMode' => true ) );
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
    wp_send_json_success( array( 'structure' => bomff_get_active_structure( bomff_is_demo_mode_request() ) ) );
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
    bomff_update_active_structure( $structure, bomff_is_demo_mode_request() );

    wp_send_json_success( array( 'structure' => $structure ) );
}
add_action( 'wp_ajax_bomff_save_structure', 'bomff_ajax_save_structure' );

function bomff_ajax_delete_structure() {
    bomff_ajax_require_admin();
    bomff_delete_active_structure( bomff_is_demo_mode_request() );
    wp_send_json_success( array( 'deleted' => true ) );
}
add_action( 'wp_ajax_bomff_delete_structure', 'bomff_ajax_delete_structure' );


function bomff_auth_error_is_identity_toolkit_disabled( $http_status, $firebase_code, $firebase_message, $details = array() ) {
    $code    = strtoupper( (string) $firebase_code );
    $message = strtoupper( (string) $firebase_message );

    if ( false !== strpos( $message, 'IDENTITYTOOLKIT.GOOGLEAPIS.COM' ) || false !== strpos( $message, 'IDENTITY TOOLKIT API' ) ) {
        if ( false !== strpos( $message, 'DISABLED' ) || false !== strpos( $message, 'NOT BEEN USED' ) || false !== strpos( $message, 'ENABLE IT' ) ) {
            return true;
        }
    }

    if ( in_array( $code, array( 'SERVICE_DISABLED', 'API_DISABLED' ), true ) ) {
        return true;
    }

    if ( is_array( $details ) ) {
        $encoded_details = strtoupper( wp_json_encode( $details ) );
        $mentions_identity_toolkit = false !== strpos( $encoded_details, 'IDENTITYTOOLKIT.GOOGLEAPIS.COM' );
        $mentions_disabled_api     = false !== strpos( $encoded_details, 'SERVICE_DISABLED' ) || false !== strpos( $encoded_details, 'API_DISABLED' );

        if ( $mentions_identity_toolkit && $mentions_disabled_api ) {
            return true;
        }
    }

    return false;
}

function bomff_auth_error_suggestions( $http_status, $firebase_code, $firebase_message, $details = array() ) {
    $suggestions = array();
    $code        = strtoupper( (string) $firebase_code );
    $message     = strtoupper( (string) $firebase_message );

    if ( 401 === (int) $http_status || false !== strpos( $message, 'UNAUTHENTICATED' ) ) {
        $suggestions[] = __( 'The access token could not be generated or has expired. Re-save the service account credentials and try again.', 'backoffice-manager-for-firebase' );
    }

    if ( 403 === (int) $http_status || 'PERMISSION_DENIED' === $code || false !== strpos( $message, 'PERMISSION_DENIED' ) ) {
        $suggestions[] = __( 'The service account does not have permission to use Firebase Authentication. Grant the service account an IAM role that can manage Firebase Authentication users.', 'backoffice-manager-for-firebase' );
    }

    $mentions_identity_toolkit = false !== strpos( $message, 'IDENTITYTOOLKIT' );
    $mentions_disabled_api     = false !== strpos( $message, 'API' ) && false !== strpos( $message, 'DISABLED' );

    if ( bomff_auth_error_is_identity_toolkit_disabled( $http_status, $firebase_code, $firebase_message, $details ) || 404 === (int) $http_status || $mentions_identity_toolkit || $mentions_disabled_api ) {
        $suggestions[] = __( 'Enable the Identity Toolkit API for this Firebase project, then retry the Authentication action.', 'backoffice-manager-for-firebase' );
    }

    if ( false !== strpos( $message, 'PROJECT' ) && ( false !== strpos( $message, 'MISMATCH' ) || false !== strpos( $message, 'NOT FOUND' ) || false !== strpos( $message, 'INVALID' ) ) ) {
        $suggestions[] = __( 'The configured Project ID may not match the service account credentials. Verify the Project ID in Settings and in the uploaded service account JSON.', 'backoffice-manager-for-firebase' );
    }

    if ( 'INVALID_ARGUMENT' === $code || false !== strpos( $message, 'INVALID_ARGUMENT' ) ) {
        $suggestions[] = __( 'Check the submitted user ID, email address, and request parameters for invalid or missing values.', 'backoffice-manager-for-firebase' );
    }

    return array_values( array_unique( $suggestions ) );
}

function bomff_parse_auth_error_response( $response, $method, $url, $request_body = null ) {
    $http_status  = wp_remote_retrieve_response_code( $response );
    $status_text  = wp_remote_retrieve_response_message( $response );
    $raw_body     = wp_remote_retrieve_body( $response );
    $json        = '' !== $raw_body ? json_decode( $raw_body, true ) : array();
    $error       = is_array( $json ) && isset( $json['error'] ) && is_array( $json['error'] ) ? $json['error'] : array();

    $firebase_code    = isset( $error['status'] ) ? (string) $error['status'] : '';
    $firebase_message = isset( $error['message'] ) ? (string) $error['message'] : __( 'Firebase Authentication request failed.', 'backoffice-manager-for-firebase' );
    $details          = isset( $error['details'] ) && is_array( $error['details'] ) ? $error['details'] : array();

    if ( '' === $firebase_code && isset( $error['code'] ) ) {
        $firebase_code = (string) $error['code'];
    }

    return array(
        'status'                        => (int) $http_status,
        'status_text'                   => (string) $status_text,
        'firebase_code'                 => $firebase_code,
        'firebase_message'              => $firebase_message,
        'suggestions'                   => bomff_auth_error_suggestions( $http_status, $firebase_code, $firebase_message, $details ),
        'identity_toolkit_api_disabled' => bomff_auth_error_is_identity_toolkit_disabled( $http_status, $firebase_code, $firebase_message, $details ),
        'project_id'                    => bomff_get_service_account()['project_id'] ?? '',
        'details'                       => array(
            'method'       => $method,
            'url'          => $url,
            'request_body' => $request_body,
            'response'     => is_array( $json ) ? $json : $raw_body,
            'raw_body'     => $raw_body,
            'api_details'  => $details,
        ),
    );
}

function bomff_auth_error_message_from_data( $error_data ) {
    if ( ! is_array( $error_data ) ) {
        return __( 'Firebase Authentication request failed.', 'backoffice-manager-for-firebase' );
    }

    $parts = array();
    if ( ! empty( $error_data['status'] ) ) {
        $status_label = sprintf( __( 'HTTP %d', 'backoffice-manager-for-firebase' ), (int) $error_data['status'] );
        if ( ! empty( $error_data['status_text'] ) ) {
            $status_label .= ' ' . (string) $error_data['status_text'];
        }
        $parts[] = $status_label;
    }
    if ( ! empty( $error_data['firebase_code'] ) ) {
        $parts[] = (string) $error_data['firebase_code'];
    }
    if ( ! empty( $error_data['firebase_message'] ) ) {
        $parts[] = (string) $error_data['firebase_message'];
    }

    return ! empty( $parts ) ? implode( ' — ', $parts ) : __( 'Firebase Authentication request failed.', 'backoffice-manager-for-firebase' );
}

function bomff_auth_store_error_details( $error ) {
    if ( ! is_wp_error( $error ) ) {
        return '';
    }

    $data = $error->get_error_data();
    if ( ! is_array( $data ) || empty( $data['bomff_auth_error'] ) ) {
        return '';
    }

    $key = wp_generate_uuid4();
    set_transient( 'bomff_auth_error_' . $key, $data, 10 * MINUTE_IN_SECONDS );
    return $key;
}

function bomff_auth_request( $method, $endpoint, $body = null, $query = array() ) {
    $service_account = bomff_get_service_account();
    if ( ! $service_account || empty( $service_account['project_id'] ) ) {
        return new WP_Error( 'not_configured', __( 'Firebase Service Account credentials are not configured. Upload a service account JSON key in Settings to use Authentication.', 'backoffice-manager-for-firebase' ) );
    }

    $token = bomff_get_access_token();
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $url = sprintf( 'https://identitytoolkit.googleapis.com/v1/projects/%s/%s', rawurlencode( $service_account['project_id'] ), ltrim( $endpoint, '/' ) );
    $query_string = bomff_build_query_string( $query );
    if ( '' !== $query_string ) {
        $url .= '?' . $query_string;
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
        $error_data = bomff_parse_auth_error_response( $response, $method, $url, $body );
        $error_data['bomff_auth_error'] = true;
        return new WP_Error( 'auth_error', bomff_auth_error_message_from_data( $error_data ), $error_data );
    }

    return is_array( $json ) ? $json : array();
}

function bomff_auth_normalize_user( $user ) {
    return array(
        'uid'            => $user['localId'] ?? '',
        'email'          => $user['email'] ?? '',
        'displayName'    => $user['displayName'] ?? '',
        'phoneNumber'    => $user['phoneNumber'] ?? '',
        'photoUrl'       => $user['photoUrl'] ?? '',
        'providerUserInfo' => $user['providerUserInfo'] ?? array(),
        'emailVerified'  => ! empty( $user['emailVerified'] ),
        'disabled'       => ! empty( $user['disabled'] ),
        'createdAt'      => $user['createdAt'] ?? '',
        'lastLoginAt'    => $user['lastLoginAt'] ?? '',
        'customClaims'   => isset( $user['customAttributes'] ) ? json_decode( $user['customAttributes'], true ) : array(),
    );
}

function bomff_auth_list_users( $page_token = '', $max_results = 100 ) {
    $body = array( 'maxResults' => max( 1, min( 1000, absint( $max_results ) ) ) );
    if ( '' !== $page_token ) {
        $body['nextPageToken'] = $page_token;
    }

    $response = bomff_auth_request( 'POST', 'accounts:batchGet', $body );
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return array(
        'users'         => array_map( 'bomff_auth_normalize_user', $response['users'] ?? array() ),
        'nextPageToken' => $response['nextPageToken'] ?? '',
    );
}

function bomff_auth_get_user( $uid ) {
    $response = bomff_auth_request( 'POST', 'accounts:lookup', array( 'localId' => array( $uid ) ) );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    if ( empty( $response['users'][0] ) ) {
        return new WP_Error( 'auth_user_not_found', __( 'Firebase Auth user not found.', 'backoffice-manager-for-firebase' ) );
    }
    return bomff_auth_normalize_user( $response['users'][0] );
}

function bomff_auth_filter_users( $users, $search ) {
    $search = strtolower( trim( $search ) );
    if ( '' === $search ) {
        return $users;
    }
    return array_values( array_filter( $users, function ( $user ) use ( $search ) {
        return false !== strpos( strtolower( $user['uid'] ), $search ) || false !== strpos( strtolower( $user['email'] ), $search ) || false !== strpos( strtolower( $user['displayName'] ), $search );
    } ) );
}

function bomff_auth_format_date( $millis ) {
    if ( empty( $millis ) ) {
        return '—';
    }
    $seconds = intval( $millis ) > 9999999999 ? intval( $millis ) / 1000 : intval( $millis );
    return esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $seconds ), 'Y-m-d H:i:s' ) );
}

function bomff_auth_provider_labels( $user ) {
    $providers = array();
    foreach ( $user['providerUserInfo'] as $provider ) {
        if ( ! empty( $provider['providerId'] ) ) {
            $providers[] = $provider['providerId'];
        }
    }
    return $providers ? implode( ', ', array_unique( $providers ) ) : '—';
}

function bomff_auth_admin_notice_redirect( $args ) {
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
}

function bomff_handle_auth_action() {
    if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
        wp_die( esc_html__( 'Unauthorized.', 'backoffice-manager-for-firebase' ) );
    }

    $auth_action = isset( $_POST['bomff_auth_action'] ) ? sanitize_key( wp_unslash( $_POST['bomff_auth_action'] ) ) : '';
    $uid         = isset( $_POST['uid'] ) ? sanitize_text_field( wp_unslash( $_POST['uid'] ) ) : '';

    check_admin_referer( 'bomff_auth_action_' . $auth_action . '_' . $uid );

    $result = true;
    if ( in_array( $auth_action, array( 'disable', 'enable' ), true ) ) {
        $result = bomff_auth_request( 'POST', 'accounts:update', array( 'localId' => $uid, 'disableUser' => 'disable' === $auth_action ) );
    } elseif ( 'delete' === $auth_action ) {
        $result = bomff_auth_request( 'POST', 'accounts:delete', array( 'localId' => $uid ) );
    } elseif ( in_array( $auth_action, array( 'password_reset', 'email_verification' ), true ) ) {
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        if ( empty( $email ) ) {
            $result = new WP_Error( 'missing_email', __( 'This action requires a user email address.', 'backoffice-manager-for-firebase' ) );
        } elseif ( 'password_reset' === $auth_action ) {
            $result = bomff_auth_request( 'POST', 'accounts:sendOobCode', array( 'requestType' => 'PASSWORD_RESET', 'email' => $email ) );
        } else {
            $result = bomff_auth_request( 'POST', 'accounts:sendOobCode', array( 'requestType' => 'VERIFY_EMAIL', 'email' => $email, 'returnOobLink' => false ) );
        }
    } else {
        $result = new WP_Error( 'invalid_action', __( 'Invalid Authentication action.', 'backoffice-manager-for-firebase' ) );
    }

    if ( is_wp_error( $result ) ) {
        $error_key = bomff_auth_store_error_details( $result );
        $args      = array( 'page' => 'bomff-auth', 'bomff_auth_error' => rawurlencode( $result->get_error_message() ) );
        if ( '' !== $error_key ) {
            $args['bomff_auth_error_key'] = rawurlencode( $error_key );
        }
        bomff_auth_admin_notice_redirect( $args );
    }

    bomff_auth_admin_notice_redirect( array( 'page' => 'bomff-auth', 'bomff_auth_updated' => '1' ) );
}
add_action( 'admin_post_bomff_auth_action', 'bomff_handle_auth_action' );
