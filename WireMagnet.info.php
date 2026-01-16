<?php
namespace ProcessWire;

$info = [
    'title' => 'WireMagnet - Secure Lead Magnet & File Delivery Manager',
    'summary' => 'Manages lead magnets, captures emails, and provides secure temporary download links.',
    'href' => 'https://github.com/markusthomas/WireMagnet',
    'version' => '1.0.0',
    'author' => 'Markus Thomas',
    'icon' => 'magnet',
    'requires' => 'ProcessWire>=3.0.200',
    'autoload' => true, // Necessary for URL Hooks
    'singular' => true,
    'installs' => 'ProcessWireMagnet',
];