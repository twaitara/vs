<?php
/**
 * Minimal PSR-4 autoloader for the bundled Dompdf packages in lib_dompdf/.
 * Avoids needing Composer on the server.
 */
spl_autoload_register(function (string $class): void {
    static $map = [
        'Dompdf\\'         => __DIR__ . '/lib_dompdf/dompdf/src/',
        'FontLib\\'        => __DIR__ . '/lib_dompdf/php-font-lib/src/FontLib/',
        'Svg\\'            => __DIR__ . '/lib_dompdf/php-svg-lib/src/Svg/',
        'Sabberworm\\CSS\\'=> __DIR__ . '/lib_dompdf/php-css-parser/src/',
        'Masterminds\\'    => __DIR__ . '/lib_dompdf/html5/src/',
    ];
    foreach ($map as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $rest = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $rest) . '.php';
            if (is_file($file)) { require $file; return; }
            // Dompdf\Cpdf lives in lib/, not src/
            if ($prefix === 'Dompdf\\') {
                $alt = __DIR__ . '/lib_dompdf/dompdf/lib/' . str_replace('\\', '/', $rest) . '.php';
                if (is_file($alt)) { require $alt; return; }
            }
        }
    }
});
