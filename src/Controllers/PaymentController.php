<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Payments\Gateway;
use App\Services\Concourse;
use App\Support\Config;
use App\Support\Log;
use App\Support\NotFoundException;
use App\Support\Response;
use App\Support\Seo;
use App\Support\View;
use RuntimeException;

final class PaymentController
{
    public function __construct(private Gateway $gateway, private Concourse $api)
    {
    }

    public function checkout(string $id): Response
    {
        $record = $this->gateway->pending()->get($id);
        if ($record === null) {
            throw new NotFoundException('Unknown payment reference.');
        }
        if ($record['status'] !== 'awaiting_payment') {
            return Response::redirect('/pay/result/' . rawurlencode($id));
        }
        return $this->page('pay/checkout', 'Payment', ['record' => $record, 'mode' => $this->gateway->cashfree()->mode()]);
    }

    public function returned(): Response
    {
        $id = input($_GET, 'order_id');
        if ($id === '') {
            throw new NotFoundException('The payment return link has no order reference.');
        }
        if ($this->gateway->pending()->get($id) === null) {
            throw new NotFoundException('Unknown payment reference.');
        }
        $this->gateway->checkout()->finalize($id);
        return Response::redirect('/pay/result/' . rawurlencode($id));
    }

    public function result(string $id): Response
    {
        $record = $this->gateway->pending()->get($id);
        if ($record === null) {
            throw new NotFoundException('Unknown payment reference.');
        }
        if (in_array($record['status'], ['awaiting_payment', 'paid'], true)) {
            $record = $this->gateway->checkout()->finalize($id);
        }
        return $this->page('pay/result', 'Booking status', ['record' => $record, 'debug' => Config::bool('debug') && \App\Support\Admin::isLoggedIn()]);
    }

    public function voucher(string $id): Response
    {
        $record = $this->gateway->pending()->get($id);
        if ($record === null || $record['status'] !== 'booked' || empty($record['orderNumber'])) {
            throw new NotFoundException('No ticket is available for this reference.');
        }
        $pdf = ($_GET['format'] ?? '') === 'pdf';
        $result = $this->api->voucher($record['orderNumber'], $pdf);
        $headers = ['Content-Type' => $result['contentType'] ?: ($pdf ? 'application/pdf' : 'text/html; charset=utf-8'), 'X-Robots-Tag' => 'noindex'];
        if ($pdf) {
            $headers['Content-Disposition'] = 'attachment; filename="tickets-' . $record['orderNumber'] . '.pdf"';
        }
        return new Response(200, $result['body'], $headers);
    }

    public function webhook(): Response
    {
        $raw = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
        $timestamp = (string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '');
        if (!$this->gateway->cashfree()->verifyWebhook($raw, $signature, $timestamp)) {
            Log::warning('Cashfree webhook rejected: bad signature');
            return Response::text('invalid signature', 'text/plain', 401);
        }
        $payload = json_decode($raw, true);
        $orderId = is_array($payload) ? (string) ($payload['data']['order']['order_id'] ?? '') : '';
        $type = is_array($payload) ? (string) ($payload['type'] ?? '') : '';
        Log::info('Cashfree webhook received', ['type' => $type, 'order' => $orderId]);
        if ($orderId !== '' && $this->gateway->pending()->get($orderId) !== null && $type === 'PAYMENT_SUCCESS_WEBHOOK') {
            $this->gateway->checkout()->finalize($orderId);
        }
        return Response::text('ok', 'text/plain');
    }

    private function page(string $template, string $title, array $data): Response
    {
        $seo = Seo::current();
        $seo->title = $title . ' | ' . Config::string('site.name');
        $seo->noindex = true;
        return Response::html(View::render($template, $data));
    }
}
