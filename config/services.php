<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Keep services thin; duty logic lives in the Duty namespace.
 *
 * @package Customs
 */

declare(strict_types=1);

use Customs\Admin\CarrierCsvExport;
use Customs\Admin\CommercialInvoice;
use Customs\Admin\ProductFields;
use Customs\Admin\Settings;
use Customs\Cart\DutyFeeApplicator;
use Customs\Container;
use Customs\Duty\DutyCalculator;
use Customs\Duty\TariffLineCounter;
use Customs\Geo\EuMembership;
use Customs\Migrator;
use Customs\Order\OrderCustomsData;
use Customs\Rest\CustomsOrderController;
use Customs\Settings\SettingsRepository;

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

    $c->singleton(OrderCustomsData::class, static fn (Container $c): OrderCustomsData => new OrderCustomsData(
        $c->get(SettingsRepository::class),
        $c->get(EuMembership::class),
    ));

    $c->singleton(CommercialInvoice::class, static fn (Container $c): CommercialInvoice => new CommercialInvoice(
        $c->get(OrderCustomsData::class),
        $c->get(SettingsRepository::class),
    ));

    $c->singleton(CarrierCsvExport::class, static fn (Container $c): CarrierCsvExport => new CarrierCsvExport(
        $c->get(OrderCustomsData::class),
        $c->get(SettingsRepository::class),
    ));

    $c->singleton(CustomsOrderController::class, static fn (Container $c): CustomsOrderController => new CustomsOrderController(
        $c->get(OrderCustomsData::class),
    ));
};
