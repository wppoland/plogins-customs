<?php
/**
 * Autoloading: prefer Composer's optimized classmap when present. Fall back to a
 * minimal PSR-4 autoloader so the plugin still boots if vendor/ is somehow absent.
 *
 * @package Customs
 */

declare(strict_types=1);

namespace Plogins\Customs;

defined('ABSPATH') || exit;

$customs_composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($customs_composer)) {
    require_once $customs_composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'Plogins\\Customs\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
