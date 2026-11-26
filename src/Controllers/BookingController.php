<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Payments\Gateway;
use App\Services\Concourse;
use App\Support\Csrf;
use App\Support\NotFoundException;
use App\Support\Response;
use RuntimeException;

final class BookingController
{
    public function __construct(private Concourse $api, private Gateway $gateway)
    {
    }

    public function book(string $attractionId, string $productId): Response
    {
        Csrf::verify();
        if (preg_match('/^[0-9a-f]{24}$/', $attractionId) !== 1 || preg_match('/^[0-9a-f]{24}$/', $productId) !== 1) {
            throw new NotFoundException('Unknown id.');
        }
        $product = $this->api->product($productId);
        $quantities = array_map('intval', (array) ($_POST['qty'] ?? []));
        $guest = (array) ($_POST['guest'] ?? []);
        $idempotencyKey = input($_POST, 'idempotencyKey');

        if ($idempotencyKey === '') {
            throw new RuntimeException('Missing idempotency key.');
        }
        if (\App\Support\Legal::available() !== [] && input($_POST, 'agree') !== '1') {
            throw new RuntimeException('Please accept the policies linked above the Book button to continue.');
        }
        if (trim((string) ($guest['first'] ?? '')) === '' || trim((string) ($guest['last'] ?? '')) === '') {
            throw new RuntimeException('Guest first and last name are required.');
        }

        $lines = [];
        $travellers = [];
        $answers = [];
        $lineIndex = 0;
        $travellerNumber = 0;
        $byId = [];
        foreach ($product->offers as $offer) {
            $byId[$offer->offerId] = $offer;
        }
        foreach ($quantities as $offerId => $qty) {
            if ($qty < 1) {
                continue;
            }
            if (!isset($byId[$offerId])) {
                throw new RuntimeException('Unknown offer: ' . $offerId);
            }
            $lineIndex++;
            $lines[] = ['offerId' => (string) $offerId, 'quantity' => $qty];
            for ($num = 1; $num <= $qty; $num++) {
                $travellerNumber++;
                $row = ['line' => $lineIndex, 'num' => $num];
                foreach ((array) ($_POST['traveller'][$offerId][$num] ?? []) as $field => $value) {
                    if (trim((string) $value) !== '') {
                        $row[(string) $field] = trim((string) $value);
                    }
                }
                if (count($row) > 2) {
                    $travellers[] = $row;
                }
                foreach ((array) ($_POST['tanswers'][$offerId][$num] ?? []) as $key => $value) {
                    if (trim((string) $value) !== '') {
                        $unit = trim((string) ($_POST['tanswers_unit'][$offerId][$num][$key] ?? ''));
                        $answers[] = array_filter(['key' => (string) $key, 'value' => trim((string) $value), 'travellerNum' => $travellerNumber, 'unit' => $unit], static fn($v) => $v !== '');
                    }
                }
            }
        }
        if ($lines === []) {
            throw new RuntimeException('Select at least one ticket.');
        }
        foreach ((array) ($_POST['answers'] ?? []) as $key => $value) {
            if (trim((string) $value) !== '') {
                $unit = trim((string) ($_POST['answers_unit'][$key] ?? ''));
                $answers[] = array_filter(['key' => (string) $key, 'value' => trim((string) $value), 'unit' => $unit], static fn($v) => $v !== '');
            }
        }

        $body = [
            'lines' => $lines,
            'guest' => array_filter(['first' => trim((string) $guest['first']), 'last' => trim((string) $guest['last']), 'email' => trim((string) ($guest['email'] ?? ''))], static fn($v) => $v !== ''),
            'partnerOrderRef' => 'WEB-' . substr($idempotencyKey, 0, 12),
        ];
        $eventKey = input($_POST, 'eventKey');
        if ($eventKey !== '') {
            $body['eventKey'] = $eventKey;
        }
        if ($travellers !== []) {
            $body['travellers'] = $travellers;
        }
        if ($answers !== []) {
            $body['answers'] = $answers;
        }

        if ($this->gateway->enabled()) {
            $first = trim((string) $guest['first']);
            $last = trim((string) $guest['last']);
            $email = trim((string) ($guest['email'] ?? ''));
            $rawPhone = trim((string) ($guest['phone'] ?? ''));
            $phone = (str_starts_with($rawPhone, '+') ? '+' : '') . (preg_replace('/[^\d]/', '', $rawPhone) ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid email address is required to pay.');
            }
            if (strlen(ltrim($phone, '+')) < 10 || strlen(ltrim($phone, '+')) > 15) {
                throw new RuntimeException('A valid phone number (10 to 15 digits) is required to pay.');
            }
            $paid = array_filter($quantities, static fn(int $q) => $q > 0);
            $ref = $this->gateway->checkout()->start($attractionId, $product, $body, $paid, ['first' => $first, 'last' => $last, 'email' => $email, 'phone' => $phone], $eventKey, input($_POST, 'visitDate'));
            return Response::redirect('/pay/checkout/' . rawurlencode($ref));
        }

        $order = $this->api->purchase($attractionId, $productId, $body, $idempotencyKey);
        return Response::redirect('/orders/' . rawurlencode($order->orderNumber));
    }
}
