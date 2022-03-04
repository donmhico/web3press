<?php
/**
 * Plugin Name: Web3Press
 */

use Elliptic\EC;
use kornrunner\Keccak;

define( 'WEB3PRESS_DIR_URL', plugin_dir_url( __FILE__ ) );

$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require $autoload;
} else {
	die( 'Run composer install.' );
}

add_action( 'wp_ajax_nopriv_web3_validate_signature', 'web3_ajax_validate_signature' );
function web3_ajax_validate_signature() {
	$address   = filter_input( INPUT_POST, 'address', FILTER_SANITIZE_STRING );
	$message   = filter_input( INPUT_POST, 'message', FILTER_SANITIZE_STRING );
	$signature = filter_input( INPUT_POST, 'signature', FILTER_SANITIZE_STRING );

	if ( empty( $address ) || empty( $address ) || empty( $message ) ) {
		$error = new WP_Error( 'web3press_invalid_request', 'Make sure address, message, and signature are provided.' );
		wp_send_json_error( $error, 400 );
	}

	if ( ! verifySignature( $message, $signature, $address ) ) {
		$error = new WP_Error( 'web3press_invalid_signature', 'Unable to validate the signature!' );
		wp_send_json_error( $error, 400 );
	}

	$is_user_logged = false;

	$find_user = web3_find_user_by_address( $address );
	if ( ! empty( $find_user ) ) {
		// Login the found user.
		$is_user_logged = web3_login_user( $find_user->ID );
	}
	else {
		$new_user = web3_register_user( $address );
		if ( is_wp_error( $new_user ) ) {
			wp_send_json_error( $new_user, 400 );
		}

		$is_user_logged = web3_login_user( $new_user );
	}

	if ( ! $is_user_logged ) {
		$error = new WP_Error( 'web3press_unable_to_login', 'Unable to login using web3!' );
		wp_send_json_error( $error, 400 );
	}

	wp_send_json_success(
		[
			'action' => 'redirect',
			'url'    => get_edit_profile_url()
		]
	);
}

function web3_login_user( int $user_id ) {
	$user = get_user_by( 'id', $user_id );
	if ( empty( $user ) ) {
		return false;
	}

	clean_user_cache( $user );
	wp_clear_auth_cookie();

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID );
	update_user_caches( $user );

	do_action( 'wp_login', $user->user_login, $user );

	return true;
}

function web3_find_user_by_address( string $address ) {
	return get_user_by( 'login', $address );
}

function web3_register_user( string $address ) {
	return wp_insert_user([
		'user_login' => substr( $address, 0, 60 ),
		'user_pass'  => wp_generate_password(),
	]);
}

function pubKeyToAddress( $pubkey ) {
	return "0x" . substr( Keccak::hash( substr( hex2bin( $pubkey->encode("hex") ), 1 ), 256 ), 24 );
}

/**
 * https://github.com/simplito/elliptic-php#verifying-ethereum-signature
 */
function verifySignature($message, $signature, $address) {
	$msglen = strlen( $message );
	$hash   = Keccak::hash( "\x19Ethereum Signed Message:\n{$msglen}{$message}", 256 );
	$sign   = [
		"r" => substr( $signature, 2, 64 ),
		"s" => substr( $signature, 66, 64 )
	];

	$recid  = ord( hex2bin( substr( $signature, 130, 2 ) ) ) - 27;
	if ( $recid != ( $recid & 1 ) ) {
		return false;
	}

	$ec     = new EC( 'secp256k1' );
	$pubkey = $ec->recoverPubKey( $hash, $sign, $recid );

	return strtolower( $address ) == pubKeyToAddress( $pubkey );
}

add_action( 'login_enqueue_scripts', 'web3_enqueue_login_script' );
function web3_enqueue_login_script() {
	wp_register_script(
		'web3',
		WEB3PRESS_DIR_URL . 'src/js/web3.min.js'
	);

	wp_enqueue_script(
		'web3press-login-js',
		WEB3PRESS_DIR_URL . 'src/js/login.js',
		[ 'jquery', 'web3' ],
		'0.0.1',
		true
	);
}
