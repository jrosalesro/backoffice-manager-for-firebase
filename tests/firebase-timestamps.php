<?php
/**
 * Regression tests for Firebase timestamp conversion.
 *
 * Run with: php tests/firebase-timestamps.php
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/firebase-timestamp.php';

$cases = array(
	array( 1700000000, 1700000000 ),
	array( '1700000000', 1700000000 ),
	array( 1700000000123, 1700000000 ),
	array( '1700000000999', 1700000000 ),
);

foreach ( $cases as $case ) {
	list( $input, $expected ) = $case;
	$actual = bomff_firebase_timestamp_to_seconds( $input );
	if ( $expected !== $actual ) {
		fwrite( STDERR, "Timestamp conversion failed: {$input}\n" );
		exit( 1 );
	}
}

foreach ( array( '', 'not-a-date', 0, -1, 1.5, array() ) as $invalid ) {
	if ( null !== bomff_firebase_timestamp_to_seconds( $invalid ) ) {
		fwrite( STDERR, "Invalid timestamp was accepted.\n" );
		exit( 1 );
	}
}

echo "Firebase seconds and milliseconds convert to integer seconds.\n";
