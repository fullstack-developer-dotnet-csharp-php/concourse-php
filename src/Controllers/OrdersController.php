<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Concourse;
use App\Support\Csrf;
use App\Support\Response;
use App\Support\Seo;
use App\Support\View;

final class OrdersController
{
    public function __construct(private Concourse $api)
    {
    }

    public function index(): Response
    {
        $this->seo('Orders');
        return Response::html(View::render('orders/index', ['orders' => $this->api->orders(100)]));
    }

    public function show(string $number): Response
    {
        $order = $this->api->order($number);
        $this->seo('Order ' . $order->orderNumber);
        return Response::html(View::render('orders/show', ['order' => $order]));
    }

    public function cancel(string $number): Response
    {
        Csrf::verify();
        $reason = input($_POST, 'reason');
        $this->api->cancel($number, $reason !== '' ? $reason : null);
        return Response::redirect('/orders/' . rawurlencode($number));
    }

    public function voucher(string $number): Response
    {
        $pdf = ($_GET['format'] ?? '') === 'pdf';
        $result = $this->api->voucher($number, $pdf);
        return new Response(200, $result['body'], ['Content-Type' => $result['contentType'] ?: ($pdf ? 'application/pdf' : 'text/html; charset=utf-8'), 'X-Robots-Tag' => 'noindex']);
    }

    private function seo(string $title): void
    {
        $seo = Seo::current();
        $seo->title = $title;
        $seo->noindex = true;
    }
}
