<?php
/**
 * Constants needed by PHPStan to analyse the plugin without bootstrapping WordPress.
 *
 * @package Customs
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
}

namespace Customs {
    if (! defined('Customs\\VERSION')) {
        define('Customs\\VERSION', '0.1.0');
    }
    if (! defined('Customs\\PLUGIN_FILE')) {
        define('Customs\\PLUGIN_FILE', '/tmp/customs/customs.php');
    }
}

namespace {
    if (! defined('CUSTOMS_DIR')) {
        define('CUSTOMS_DIR', '/tmp/customs/');
    }
    if (! defined('CUSTOMS_URL')) {
        define('CUSTOMS_URL', 'https://example.com/wp-content/plugins/customs/');
    }
}
