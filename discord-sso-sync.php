<?php
/*
Plugin Name: Discord SSO Sync
Plugin URI: https://github.com/crock/discord-sso-sync
Description: Implement Discord Single-Sign-On (SSO) on your WordPress site with automatic creation of WordPress user.
Author: Alex Crocker
Version: 1.0.0
Author URI: https://crock.dev
Text Domain: crocbuzz
GitHub Plugin URI: https://github.com/crock/discord-sso-sync
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Scripts
require(plugin_dir_path(__FILE__).'/includes/discord-sso-sync-scripts.php');

// Load widget class
require_once(plugin_dir_path(__FILE__).'/includes/oauth-login-widget.php');

// Hook in widget
add_action('widgets_init', 'register_login_widget');
function register_login_widget() {
	register_widget('Discord_Oauth_Login_Widget');
}

add_action('admin_init', 'discord_admin_init');
function discord_admin_init() {
	register_setting('discord_oauth_settings_group', 'oauth_settings', 'oauth_settings_validate');
	add_settings_section('discord_oauth', 'oAuth Settings', 'discord_section_text', 'oauth');
	add_settings_section('widget_instructions', 'Activating Login Widget', 'widget_instructions_text', 'oauth');
}

function discord_admin_settings_page() { ?>
	<div class="wrap">
		<h1>Discord SSO Sync Settings</h1>
		<form method="post" action="options.php"> 
		<?php settings_fields( 'discord_oauth_settings_group' ); ?>
		<?php do_settings_sections( 'oauth' ); ?>
			<table class="form-table">
				<tr valign="top">
				<th scope="row">Client ID</th>
				<td><input type="text" name="client_id" value="<?php echo esc_attr( get_option('client_id') ); ?>" /></td>
				</tr>
				
				<tr valign="top">
				<th scope="row">Client Secret</th>
				<td><input type="text" name="client_secret" value="<?php echo esc_attr( get_option('client_secret') ); ?>" /></td>
				</tr>
				
				<tr valign="top">
				<th scope="row">oAuth Scopes</th>
				<td><input type="text" value="identify email" name="oauth_scopes" value="<?php echo esc_attr( get_option('oauth_scopes') ); ?>" /></td>
				</tr>
			</table>
		<?php submit_button(); ?>
		</form>
	</div>
<?php }

function widget_instructions_text() { ?>
	<p>Follow these instructions to add a Discord login widget to your site.</p>
	<div class="discord-sso-sync-widget-instructions">
		<p>Navigate to <strong>Appearance -> Widgets</strong> in the WordPress Admin Control Panel.</p>
		<img src="<?php echo plugins_url('/assets/images/widget-modal-screenshot.png', __FILE__) ?>" alt="Appearance Widget Modal Screenshot" />
	</div>
<?php }

function discord_section_text() { ?>
	<p>Enter your Discord oAuth settings.</p>
	<style>
		.discord-sso-sync-notice {
			background-color: #e5a0a0;
			border: 2px solid #ca5353;
			border-radius: 8px;
			padding: 10px;
		}

		.discord-sso-sync-notice > pre {
			padding: 5px;
			background-color: black;
			color: lime;
		}
	</style>
	<div class="discord-sso-sync-notice">
		<p><strong>Note:</strong><br />Please remember to set the following as the <em>Re-direct URI</em> in the <a href="https://discordapp.com/developers/applications/">Discord Developer Dashboard</a>.</p>
		<pre><?php echo get_site_url() . '/' . rest_get_url_prefix() . '/discord/callback' ?></pre>
	</div>
<?php }

add_action('admin_menu', 'discord_admin_settings_menu');
function discord_admin_settings_menu() {

	//create new top-level menu
	add_menu_page('Discord SSO Sync Settings', 'Discord SSO Sync', 'administrator', __FILE__, 'discord_admin_settings_page' , plugins_url('/assets/images/Discord-Logo-White.png', __FILE__) );

	//call register settings function
	add_action( 'admin_init', 'register_discord_admin_settings' );
}


function register_discord_admin_settings() {
	//register our settings
	register_setting( 'discord_oauth_settings_group', 'client_id' );
	register_setting( 'discord_oauth_settings_group', 'client_secret' );
	register_setting( 'discord_oauth_settings_group', 'oauth_scopes' );
}

function create_new_user_from_discord_or_login_existing($res, $user) {

	if ( email_exists( $user['email'] )) {
		$existingUser = get_user_by( 'email', $user['email'] );
		wp_set_auth_cookie( $existingUser->ID, true, is_ssl() );
		wp_redirect( home_url( '/' ) );
		exit;
	}
	
	$userdata = array(
		'user_login' => $user['username'],
		'user_email' => $user['email'],
		'user_pass' => NULL
	);

	$user_id = wp_insert_user( $userdata );

	add_user_meta($user_id, '_discord_access_token', $res['access_token'], true);
	add_user_meta($user_id, '_discord_refresh_token', $res['refresh_token'], true);

	wp_set_auth_cookie( $user_id, true, is_ssl() );
	wp_redirect(home_url( '/' ));
}

function exchange_code_for_token_response($code) {

	$url = 'https://discordapp.com/api/oauth2/token';
	$client_id = get_option( 'client_id' );
	$client_secret = get_option( 'client_secret' );
	$scopes = get_option( 'oauth_scopes' );

	$data = array(
		'client_id' => $client_id,
		'client_secret' => $client_secret,
		'scope' => $scopes,
		'grant_type' => 'authorization_code',
		'code' => $code,
		'redirect_uri' => site_url( rest_get_url_prefix() . '/discord/callback', is_ssl() ? 'https' : 'http' )
	);

	$headers = array(
		'Content-Type' => 'application/x-www-form-urlencoded'
	);

	$res = wp_remote_post( $url, array(
		'headers' => $headers,
		'body' => $data
	));

	return json_decode($res['body'], true);
}

function get_discord_user($res) {

	$url = 'https://discordapp.com/api/users/@me';

	$headers = array(
		'Authorization' => $res['token_type'] . ' ' . $res['access_token'],
		'User-Agent' => 'DiscordBot (https://discordapp.com/api, 6)'
	);

	$res = wp_remote_get( $url, array(
		'headers' => $headers
	));

	return json_decode($res['body'], true);
}

function site_login_via_oauth() {

	$code = $_GET['code'];
	$tokenResponse = exchange_code_for_token_response($code);
	$userResponse = get_discord_user($tokenResponse);

	create_new_user_from_discord_or_login_existing($tokenResponse, $userResponse);
}

function register_oauth_callback_route() {
	register_rest_route( 'discord', '/callback', array(
		'methods' => 'GET',
		'callback' => 'site_login_via_oauth',
	));
}
add_action('rest_api_init', 'register_oauth_callback_route');
