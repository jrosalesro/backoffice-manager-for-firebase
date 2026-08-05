<?php
/**
 * Regression tests for request nonce, capability, and Firebase ID handling.
 *
 * Run with: php tests/input-security.php
 */

define( 'ABSPATH', __DIR__ . '/' );

define( 'BOMFF_TESTING', true );

$GLOBALS['bomff_current_user_can'] = true;
$GLOBALS['bomff_nonce_valid']      = true;
$GLOBALS['bomff_json_response']    = null;
$GLOBALS['bomff_die_message']      = null;
$GLOBALS['bomff_wp_error_class']   = null;

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

function add_action() {}
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url() { return 'https://example.test/plugin/'; }
function current_user_can() { return $GLOBALS['bomff_current_user_can']; }
function check_ajax_referer() { if ( ! $GLOBALS['bomff_nonce_valid'] ) { wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 ); } }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_textarea_field( $text ) { return sanitize_text_field( $text ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function esc_html( $text ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_send_json_error( $data = null, $status_code = null ) { $GLOBALS['bomff_json_response'] = array( 'success' => false, 'data' => $data, 'status' => $status_code ); throw new Exception( 'json_response' ); }
function wp_send_json_success( $data = null ) { $GLOBALS['bomff_json_response'] = array( 'success' => true, 'data' => $data, 'status' => 200 ); throw new Exception( 'json_response' ); }
function get_user_meta( $id, $key, $single = false ) { return array(); }
function update_user_meta() {}
function get_current_user_id() { return 123; }
function get_option( $name, $default = false ) { return $default; }
function update_option() {}
function delete_option() {}
function delete_user_meta() {}
function wp_die( $message ) { $GLOBALS['bomff_die_message'] = $message; throw new Exception( 'wp_die' ); }
function do_action() {}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function wp_safe_redirect() { throw new Exception( 'redirect' ); }
function check_admin_referer() {}
function wp_verify_nonce() { return true; }
function wp_json_encode( $data ) { return json_encode( $data ); }

require dirname( __DIR__ ) . '/backoffice-manager-for-firebase.php';

function bomff_test_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, $message . "\n" );
        exit( 1 );
    }
}

function bomff_test_run_ajax( $callable, $post ) {
    $_POST                         = $post;
    $GLOBALS['bomff_json_response'] = null;
    try { $callable(); } catch ( Exception $e ) { if ( 'json_response' !== $e->getMessage() && 'wp_die' !== $e->getMessage() ) { throw $e; } }
    return $GLOBALS['bomff_json_response'];
}

$GLOBALS['bomff_nonce_valid'] = false;
$response = bomff_test_run_ajax( 'bomff_ajax_get_status', array() );
bomff_test_assert( false === $response['success'] && 403 === $response['status'], 'Missing/invalid nonce was not rejected.' );

$GLOBALS['bomff_nonce_valid']      = true;
$GLOBALS['bomff_current_user_can'] = false;
$response = bomff_test_run_ajax( 'bomff_ajax_get_status', array() );
bomff_test_assert( false === $response['success'] && 403 === $response['status'], 'Missing capability was not rejected.' );

$GLOBALS['bomff_current_user_can'] = true;
$response = bomff_test_run_ajax( 'bomff_ajax_get_status', array( 'demoMode' => 'true' ) );
bomff_test_assert( true === $response['success'] && true === $response['data']['demoMode'], 'Valid nonce and capability did not allow successful request.' );

$valid_collection = 'users/user-1/orders';
$valid_doc_id     = 'ABC_123-~!@#$%^&*()+=,.;:';
bomff_test_assert( $valid_collection === bomff_validate_firestore_path( ' /' . $valid_collection . '/ ' ), 'Valid Firebase collection path was not preserved.' );
bomff_test_assert( $valid_doc_id === bomff_validate_firestore_document_id( $valid_doc_id ), 'Valid Firebase document ID was not preserved.' );
bomff_test_assert( '' === bomff_validate_firestore_path( '../users' ), 'Traversal-like collection path was accepted.' );
bomff_test_assert( '' === bomff_validate_firestore_document_id( 'parent/child' ), 'Document ID containing slash was accepted.' );

$_GET = array( 's' => '<b>Alice</b>', 'tab' => 'permissions', 'page_token' => ' next ' );
bomff_test_assert( 'Alice' === sanitize_text_field( bomff_get_get_value( 's' ) ), 'Read-only search filter did not sanitize correctly.' );
bomff_test_assert( 'permissions' === sanitize_key( bomff_get_get_value( 'tab' ) ), 'Read-only tab filter stopped working.' );
bomff_test_assert( 'next' === sanitize_text_field( bomff_get_get_value( 'page_token' ) ), 'Read-only pagination filter stopped working.' );

echo "Input security checks passed.\n";
