<?php
/**
 * Admin view loader.
 *
 * @package BackofficeManagerForFirebase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders an allow-listed admin view in local function scope.
 *
 * Keeping the view data inside this function prevents view variables from
 * leaking into WordPress' global namespace.
 *
 * @param string $view Relative view path.
 * @param array  $data Data made available to the view.
 * @return void
 */
function bomff_render_admin_view( $view, $data = array() ) {
	$allowed_views = array(
		'pages/firestore.php',
		'pages/auth.php',
		'bomff-settings.php',
	);

	if ( ! in_array( $view, $allowed_views, true ) ) {
		return;
	}

	$bomff_view_data = is_array( $data ) ? $data : array();
	require BOMFF_PLUGIN_PATH . $view;
}
