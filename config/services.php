<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Keep services thin; duty logic lives in the Duty namespace.
 *
 * @package Customs
 */

declare(strict_types=1);

use Plogins\Customs\Admin\ProductFields;
use Plogins\Customs\Admin\Settings;
use Plogins\Customs\Cart\DutyFeeApplicator;
use Plogins\Customs\Container;
use Plogins\Customs\Duty\DutyCalculator;
use Plogins\Customs\Duty\TariffLineCounter;
use Plogins\Customs\Geo\EuMembership;
use Plogins\Customs\Migrator;
use Plogins\Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    $c->singleton(SettingsRepository::class, static fn (): SettingsRepository => new SettingsRepository());

    $c->singleton(EuMembership::class, static fn (): EuMembership => new EuMembership());

    $c->singleton(TariffLineCounter::class, static fn (Container $c): TariffLineCounter => new TariffLineCounter(
        $c->get(SettingsRepository::class),
    ));

    $c->singleton(DutyCalculator::class, static fn (Container $c): DutyCalculator => new DutyCalculator(
        $c->get(SettingsRepository::class),
        $c->get(EuMembership::class),
        $c->get(TariffLineCounter::class),
    ));

    $c->singleton(DutyFeeApplicator::class, static fn (Container $c): DutyFeeApplicator => new DutyFeeApplicator(
        $c->get(DutyCalculator::class),
        $c->get(SettingsRepository::class),
    ));

    $c->singleton(ProductFields::class, static fn (): ProductFields => new ProductFields());

    $c->singleton(Settings::class, static fn (Container $c): Settings => new Settings(
        $c->get(SettingsRepository::class),
        $c->get(EuMembership::class),
    ));
};
