<?php

// Add Scripts
function domaincord_add_scripts() {
    wp_enqueue_style( 'domaincord-main-style', plugins_url().'/domaincord/css/style.css' );
    wp_enqueue_script( 'domaincord-main-script', plugins_url().'/domaincord/js/main.js' );
}

add_action('wp_enqueue_scripts', 'domaincord_add_scripts');