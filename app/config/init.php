<?php

// We always work with UTF8 encoding
mb_internal_encoding('UTF-8');

// Make sure we have a timezone set
date_default_timezone_set('Europe/Paris');

// Autoloading of classes (both /vendor and /classes)
require_once __DIR__ . '/../../vendor/autoload.php';

$settings_filename = __DIR__ . '/settings.inc.php';
if (file_exists($settings_filename)) {
    require $settings_filename;
} else {
    echo "<h1>Error: missing config file</h1>";
    echo "<p>Config file is missing: <code>{$settings_filename}</code></p>";
    echo "<p>Rename <strong>config/settings.inc.php.ini</strong> as <strong>config/settings.inc.php</strong> to get started.</p>";
    exit;
}

// Cache class
define('CACHE_ENABLED', true);
define('CACHE_PATH', __DIR__ . '/../../cache/');   // This folder needs to be writable by PHP
define('CACHE_TIME', 15 * 60);    // Default: 15 minutes

// For debugging
if (DEBUG) {
    error_reporting(E_ALL);
}

// Set up Twig environment
$templates = new Twig_Loader_Filesystem(__DIR__ . '/../templates/');
$options = [
    'cache' => false,
    // 'cache' => 'cache',
];

$twig = new Twig_Environment($templates, $options);

// This is the default template, views can define a different one
// TODO: kill with twig above
$template = 'default.php';
