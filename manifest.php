<?php
require_once __DIR__ . '/lib.php';
header('Content-Type: application/manifest+json');
echo json_encode([
    'name'             => 'Kennet Valuers',
    'short_name'       => 'Valuers',
    'description'      => 'Vehicle valuations on the go — for field valuers.',
    'start_url'        => url('dashboard.php'),
    'scope'            => BASE_URL . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#0f1216',
    'theme_color'      => '#0f1216',
    'icons' => [
        ['src' => url('icons/icon-192.png'),     'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('icons/icon-512.png'),     'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('icons/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'New Bank Valuation', 'url' => url('bank_form.php')],
        ['name' => 'New Insurance Valuation', 'url' => url('insurance_form.php')],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
