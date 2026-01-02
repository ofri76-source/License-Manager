<?php
/*
Plugin Name: KBBM Callback Debug
*/
add_action('init', function () {
    if (!isset($_GET['kbbm_partner_callback']) || $_GET['kbbm_partner_callback'] !== '1') {
        return;
    }

    // עצור כל הפניות "חכמות"
    remove_all_actions('template_redirect');
    remove_all_filters('redirect_canonical');

    header('Content-Type: text/plain; charset=utf-8');
    echo "CALLBACK REACHED\n";
    echo "URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
    echo "Host: " . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
    echo "Scheme: " . ((is_ssl()) ? 'https' : 'http') . "\n";
    echo "Logged in: " . (is_user_logged_in() ? 'yes' : 'no') . "\n";
    echo "Has code: " . (isset($_GET['code']) ? 'yes' : 'no') . "\n";
    echo "Has state: " . (isset($_GET['state']) ? 'yes' : 'no') . "\n";
    echo "Has error: " . (isset($_GET['error']) ? 'yes' : 'no') . "\n";
    exit;
}, 0);
