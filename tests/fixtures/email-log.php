<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Sends one email through wp_mail() on the harness, with the mailer faked so
 * the outcome is certain. The harness has no mail server, so without this a
 * "sent" row could never be made and a "failed" row would depend on how PHP
 * happens to be set up.
 *
 * Usage: php email-log.php /absolute/path/to/wp-load.php <mode> [subject]
 *
 *   ok      The mailer accepts the message (an HTML one).
 *   fail    The mailer refuses it: SMTP to a port nothing listens on.
 *   off     Removes the fake mailer; nothing is sent.
 *
 * Prints JSON: the result wp_mail() gave, and the sender name it used.
 *
 * @package BlueWorxLabsTests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.AlternativeFunctions -- A CLI fixture writing its own files and stderr.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Reports a bad path on the command line.

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$mode    = isset( $argv[2] ) ? $argv[2] : '';
$subject = isset( $argv[3] ) ? $argv[3] : 'Email log fixture';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

if ( ! in_array( $mode, array( 'ok', 'fail', 'off' ), true ) ) {
	fwrite( STDERR, "Mode must be ok, fail or off.\n" );
	exit( 1 );
}

$mu_dir  = dirname( $wp_load ) . '/wp-content/mu-plugins';
$mu_file = $mu_dir . '/blueworx-test-mail-transport.php';

if ( 'off' === $mode ) {
	if ( is_file( $mu_file ) ) {
		unlink( $mu_file );
	}

	require $wp_load;
	delete_option( 'bw_test_mail_mode' );
	echo wp_json_encode( array( 'mode' => $mode ) ) . "\n";
	exit( 0 );
}

if ( ! is_dir( $mu_dir ) ) {
	mkdir( $mu_dir, 0777, true );
}

// The fake transport. In "ok" mode the mailer builds the message as it would
// for real and then reports success instead of handing it on, so the From,
// the content type and the recipients are all exactly what a real send would
// have had. In "fail" mode it really does try SMTP, against a port with
// nothing behind it, and PHPMailer refuses the way it would on a site whose
// SMTP details are wrong.
file_put_contents(
	$mu_file,
	<<<'PHP'
<?php
/**
 * Plugin Name: BW Test Mail Transport
 * Description: Test fixture only. Fakes the outcome of every send.
 */

add_action(
	'phpmailer_init',
	function ( &$phpmailer ) {
		$mode = get_option( 'bw_test_mail_mode' );

		if ( 'fail' === $mode ) {
			$phpmailer->isSMTP();
			$phpmailer->Host     = '127.0.0.1';
			$phpmailer->Port     = 9;
			$phpmailer->Timeout  = 2;
			$phpmailer->SMTPAutoTLS = false;
			return;
		}

		if ( 'ok' !== $mode ) {
			return;
		}

		if ( ! class_exists( 'BW_Test_Accepting_Mailer' ) ) {
			class BW_Test_Accepting_Mailer extends PHPMailer\PHPMailer\PHPMailer {
				public function postSend() {
					return true;
				}
			}
		}

		$fake = new BW_Test_Accepting_Mailer( true );
		$ref  = new ReflectionClass( PHPMailer\PHPMailer\PHPMailer::class );

		foreach ( $ref->getProperties() as $property ) {
			if ( $property->isStatic() ) {
				continue;
			}

			$property->setAccessible( true );
			$property->setValue( $fake, $property->getValue( $phpmailer ) );
		}

		$phpmailer = $fake;
	},
	1
);
PHP
);

require $wp_load;

update_option( 'bw_test_mail_mode', $mode );

$from_name = '';
add_action(
	'phpmailer_init',
	function ( $phpmailer ) use ( &$from_name ) {
		$from_name = (string) $phpmailer->FromName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own property name.
	},
	PHP_INT_MAX
);

$error = '';
add_action(
	'wp_mail_failed',
	function ( $wp_error ) use ( &$error ) {
		$error = $wp_error->get_error_message();
	}
);

if ( 'ok' === $mode ) {
	$result = wp_mail(
		array( 'first@example.invalid', 'second@example.invalid' ),
		$subject,
		'<p>Hello from the <strong>email log fixture</strong>.</p>',
		array( 'Content-Type: text/html; charset=UTF-8' )
	);
} else {
	$result = wp_mail( 'nobody@example.invalid', $subject, 'This one is meant to fail.' );
}

echo wp_json_encode(
	array(
		'mode'       => $mode,
		'result'     => (bool) $result,
		'from_name'  => $from_name,
		'site_title' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'error'      => $error,
	)
) . "\n";
