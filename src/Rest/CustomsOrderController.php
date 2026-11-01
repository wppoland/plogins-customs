<?php

declare(strict_types=1);

namespace Customs\Rest;

use Customs\Contract\HasHooks;
use Customs\Order\OrderCustomsData;

defined('ABSPATH') || exit;

/**
 * REST API controller exposing order customs data for external integrations,
 * shipping management software, and 3PL fulfillment systems.
 *
 * Endpoint: GET /wp-json/customs/v1/orders/<id>
 */
final class CustomsOrderController implements HasHooks
{
    private const NAMESPACE = 'customs/v1';
    private const ROUTE     = '/orders/(?P<id>[\d]+)';

    public function __construct(private readonly OrderCustomsData $orderData)
    {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getOrderCustomsData'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'id' => [
                        'description' => __('Unique identifier for the order.', 'plogins-customs'),
                        'type'        => 'integer',
                        'required'    => true,
                        'validate_callback' => static fn ($param): bool => is_numeric($param) && (int) $param > 0,
                    ],
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function getOrderCustomsData($request)
    {
        $orderId = (int) $request['id'];
        $order   = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return new \WP_Error(
                'customs_order_not_found',
                __('Order not found.', 'plogins-customs'),
                ['status' => 404]
            );
        }

        $payload = $this->orderData->forOrder($order);

        return new \WP_REST_Response($payload, 200);
    }
}
