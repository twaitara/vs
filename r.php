<?php
/**
 * Front controller — resolves an opaque page code (…/vs/<code>) to the real
 * script and runs it, so the page name never appears in the address bar.
 */
require_once __DIR__ . '/lib.php';

$file = code_page($_GET['c'] ?? '');
if ($file === '' || !is_file(__DIR__ . '/' . $file) || strpos($file, '/') !== false) {
    http_response_code(404);
    exit('Page not found.');
}
$GLOBALS['ROUTE_PAGE'] = $file; // so the target knows its real identity
unset($_GET['c']);
require __DIR__ . '/' . $file;
