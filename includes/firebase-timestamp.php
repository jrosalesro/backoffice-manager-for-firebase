<?php
/**
 * Firebase timestamp helpers.
 *
 * @package BackofficeManagerForFirebase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a Firebase seconds or milliseconds timestamp to integer seconds.
 *
 * @param int|string $value Firebase timestamp.
 * @return int|null
 */
function bomff_firebase_timestamp_to_seconds( $value ) {
	if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
		return null;
	}

	$timestamp = (int) $value;
	if ( $timestamp <= 0 ) {
		return null;
	}

	return $timestamp > 9999999999 ? intdiv( $timestamp, 1000 ) : $timestamp;
}
