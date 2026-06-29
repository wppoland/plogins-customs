<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Customs\Contract\HasHooks.
 *
 * @package Customs
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Customs\Admin\ProductFields;
use Customs\Admin\Settings;
use Customs\Cart\DutyFeeApplicator;

defined('ABSPATH') || exit;

return [
    DutyFeeApplicator::class,
    ProductFields::class,
    Settings::class,
];
