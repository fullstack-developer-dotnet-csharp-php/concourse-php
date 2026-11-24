<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Payments\Cashfree;
use App\Payments\CashfreeException;
use App\Payments\PendingStore;
use App\Support\Config;
use App\Support\Log;
use RuntimeException;
use Throwable;

final class Checkout
{
    public function __construct(private Concourse $api, private Cashfree $cashfree, private PendingStore $pending)
    {
    }

    public function quote(string $attractionId, Product $product, array $quantities, string $eventKey, string $visitDate): array
    {
        $prices = [];
        $currency = null;
        if ($product->needsEvent()) {
            if ($eventKey === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
                throw new RuntimeException('Choose a date before paying.');
            }
            $rows = $this->api->availability($attractionId, $product->id, $visitDate, $visitDate, true)['calendar'] ?? [];
            $row = null;
            foreach ($rows as $candidate) {
                if (($candidate['eventKey'] ?? null) === $eventKey) {
                    $row = $candidate;
                }
            }
            if ($row === null) {
                throw new RuntimeException('That date or time is no longer available. Pick another date.');
            }
            foreach ($row['offers'] as $o) {
                $prices[$o['offerId']] = ['price' => $o['price']['amount'] ?? null, 'currency' => $o['price']['currency'] ?? null, 'capacity' => $o['capacity'] ?? null];
            }
        } else {
            $fixed = [];
            foreach ($product->offers as $offer) {
                $fixed[$offer->offerId] = ['price' => $offer->price, 'currency' => $offer->currency, 'capacity' => $offer->maxQty];
            }
            if (array_filter($fixed, static fn(array $p) => $p['price'] === null)) {
                foreach ($this->api->pricing($attractionId, $product->id, true)['offers'] ?? [] as $row) {
                    $fixed[$row['offerId']] = ['price' => $row['price']['amount'] ?? null, 'currency' => $row['price']['currency'] ?? null, 'capacity' => $fixed[$row['offerId']]['capacity'] ?? null];
                }
            }
            $prices = $fixed;
        }

        $total = 0.0;
        foreach ($quantities as $offerId => $qty) {
            if (!isset($prices[$offerId]) || $prices[$offerId]['price'] === null) {
                throw new RuntimeException('No live price is available for ticket ' . $offerId . '.');
            }
            if ($prices[$offerId]['capacity'] !== null && $qty > $prices[$offerId]['capacity']) {
                throw new RuntimeException('Only ' . $prices[$offerId]['capacity'] . ' tickets are left for ticket ' . $offerId . '.');
            }
            $cur = (string) $prices[$offerId]['currency'];
            if ($currency !== null && $cur !== $currency) {
                throw new RuntimeException('Tickets with different currencies cannot be paid in one order.');
            }
            $currency = $cur;
            $total += (float) $prices[$offerId]['price'] * $qty;
        }
        if ($currency === null || $currency === '') {
            throw new RuntimeException('The ticket currency is not known, so a payment cannot be created.');
        }
        return ['amount' => round($total, 2), 'currency' => $currency];
    }

    public function start(string $attractionId, Product $product, array $body, array $quantities, array $customer, string $eventKey, string $visitDate): string
    {
        $quote = $this->quote($attractionId, $product, $quantities, $eventKey, $visitDate);
        $chargeCurrency = Config::string('payments.cashfree.currency');
        $charge = $quote['amount'];
        if ($quote['currency'] !== $chargeCurrency) {
            $rate = Config::string('payments.fx_rate');
            if ($rate === '' || !is_numeric($rate)) {
                throw new RuntimeException('Tickets are priced in ' . $quote['currency'] . ' but payment is taken in ' . $chargeCurrency . '. Set an exchange rate on the settings page.');
            }
            $charge = round($quote['amount'] * (float) $rate, 2);
        }
        if ($charge < 1) {
            throw new RuntimeException('The amount to pay is below the minimum of 1 ' . $chargeCurrency . '.');
        }

        $id = $this->pending->create([
            'attractionId' => $attractionId,
            'productId' => $product->id,
            'productName' => $product->name,
            'body' => $body,
            'amount' => $quote['amount'],
            'currency' => $quote['currency'],
            'chargeAmount' => $charge,
            'chargeCurrency' => $chargeCurrency,
            'customer' => $customer,
        ]);

        $meta = ['return_url' => abs_url('/pay/return?order_id={order_id}')];
        if (str_starts_with(base_url(), 'https://')) {
            $meta['notify_url'] = abs_url('/pay/webhook');
        } else {
            Log::notice('Payment webhook URL not sent because the site is not on https', ['base' => base_url()]);
        }
        try {
            $order = $this->createGatewayOrder($id, $charge, $chargeCurrency, $product, $customer, $meta);
        } catch (Throwable $e) {
            $this->pending->mutate($id, static function (array $r) use ($e): array {
                $r['status'] = 'gateway_error';
                $r['error'] = $e->getMessage();
                return $r;
            });
            throw $e;
        }
        if (empty($order['payment_session_id'])) {
            throw new RuntimeException('Cashfree did not return a payment session.');
        }
        $this->pending->mutate($id, static function (array $r) use ($order): array {
            $r['status'] = 'awaiting_payment';
            $r['paymentSessionId'] = $order['payment_session_id'];
            return $r;
        });
        Log::info('Payment started', ['ref' => $id, 'amount' => $charge, 'currency' => $chargeCurrency]);
        return $id;
    }

    private function createGatewayOrder(string $id, float $charge, string $chargeCurrency, Product $product, array $customer, array $meta): array
    {
        return $this->cashfree->createOrder([
            'order_id' => $id,
            'order_amount' => $charge,
            'order_currency' => $chargeCurrency,
            'order_note' => mb_substr($product->name, 0, 100),
            'customer_details' => [
                'customer_id' => $id,
                'customer_name' => trim($customer['first'] . ' ' . $customer['last']),
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
            ],
            'order_meta' => $meta,
        ]);
    }

    public function finalize(string $id): array
    {
        $record = $this->pending->get($id);
        if ($record === null) {
            throw new RuntimeException('Unknown payment reference.');
        }
        if (in_array($record['status'], ['booked', 'booking_failed'], true)) {
            return $record;
        }

        try {
            $cf = $this->cashfree->getOrder($id);
        } catch (CashfreeException $e) {
            Log::exception($e, 'Could not confirm payment with Cashfree');
            throw $e;
        }
        $status = (string) ($cf['order_status'] ?? '');
        if ($status !== 'PAID') {
            return $this->pending->mutate($id, static function (array $r) use ($status): array {
                $r['gatewayStatus'] = $status;
                if (in_array($status, ['EXPIRED', 'TERMINATED', 'TERMINATION_REQUESTED'], true)) {
                    $r['status'] = 'payment_failed';
                }
                return $r;
            });
        }
        if (abs((float) ($cf['order_amount'] ?? 0) - (float) $record['chargeAmount']) > 0.001 || ($cf['order_currency'] ?? '') !== $record['chargeCurrency']) {
            Log::critical('Paid amount does not match the checkout amount', ['ref' => $id, 'expected' => $record['chargeAmount'], 'got' => $cf['order_amount'] ?? null]);
            throw new RuntimeException('The paid amount does not match the order. Contact support with reference ' . $id . '.');
        }

        return $this->pending->mutate($id, function (array $r) use ($id): array {
            if (in_array($r['status'], ['booked', 'booking_failed'], true)) {
                return $r;
            }
            $r['status'] = 'paid';
            try {
                $order = $this->api->purchase($r['attractionId'], $r['productId'], $r['body'], $id);
                $r['status'] = 'booked';
                $r['orderNumber'] = $order->orderNumber;
                Log::info('Booking created after payment', ['ref' => $id, 'order' => $order->orderNumber]);
            } catch (Throwable $e) {
                $r['status'] = 'booking_failed';
                $r['error'] = $e->getMessage();
                Log::critical('Payment taken but the booking failed. Refund or rebook manually', ['ref' => $id, 'error' => $e->getMessage()]);
            }
            return $r;
        });
    }
}
