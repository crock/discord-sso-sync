<?php

// Add Scripts
function discord_sso_sync_add_scripts() {
    wp_enqueue_style( 'discord-sso-sync-main-style', plugins_url().'/discord-sso-sync/css/style.css' );
    wp_enqueue_script( 'discord-sso-sync-main-script', plugins_url().'/discord-sso-sync/js/main.js' );
}

add_action('wp_enqueue_scripts', 'discord_sso_sync_add_scripts');