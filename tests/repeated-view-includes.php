<?php
/**
 * Regression test for admin views included repeatedly in one request.
 *
 * Run with: php tests/repeated-view-includes.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'BOMFF_CAPABILITY', 'manage_options' );

function current_user_can() { return true; }
function bomff_get_service_account() { return false; }
function bomff_is_demo_mode_context() { return false; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function get_admin_page_title() { return 'Settings'; }
function get_option( $name, $default = false ) { return $default; }
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function esc_attr__( $text ) { return $text; }
function esc_html_e( $text ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_attr( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_kses_post( $text ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function bomff_get_get_value( $key, $default = '' ) { return isset( $_GET[ $key ] ) ? $_GET[ $key ] : $default; }
function add_query_arg( $key, $value = null, $url = '' ) { return $url . '?' . ( is_array( $key ) ? http_build_query( $key ) : rawurlencode( $key ) . '=' . rawurlencode( $value ) ); }
function wp_nonce_field() {}
function submit_button( $text ) { echo '<button>' . esc_html( $text ) . '</button>'; }
function do_action() {}

$views = array(
    dirname( __DIR__ ) . '/pages/firestore.php',
    dirname( __DIR__ ) . '/pages/auth.php',
    dirname( __DIR__ ) . '/bomff-settings.php',
);
$view_data = array();

ob_start();
foreach ( $views as $view ) {
    include $view;
    include $view;
}
ob_end_clean();

foreach ( $views as $view ) {
    $tokens = token_get_all( file_get_contents( $view ) );
    foreach ( $tokens as $token ) {
        if ( is_array( $token ) && T_FUNCTION === $token[0] ) {
            fwrite( STDERR, "View declares a function: {$view}\n" );
            exit( 1 );
        }
    }
}

echo "All admin views can be included twice without declaring functions.\n";
