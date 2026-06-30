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

namespace Plogins\Customs {
    if (! defined('Plogins\\Customs\\VERSION')) {
        define('Plogins\\Customs\\VERSION', '0.1.1');
    }
    if (! defined('Plogins\\Customs\\PLUGIN_FILE')) {
        define('Plogins\\Customs\\PLUGIN_FILE', '/tmp/plogins-customs/plogins-customs.php');
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
