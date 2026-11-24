<?php $status = $record['status']; ?>
<div class="container page-head">
    <?php if ($status === 'booked'): ?>
        <h1>Booking confirmed</h1>
    <?php elseif ($status === 'booking_failed'): ?>
        <h1>Payment received, booking not completed</h1>
    <?php elseif ($status === 'payment_failed'): ?>
        <h1>Payment was not completed</h1>
    <?php else: ?>
        <h1>Waiting for payment confirmation</h1>
    <?php endif; ?>
</div>
<div class="container narrow">
    <dl class="facts">
        <dt>Reference</dt><dd><?= e($record['id']) ?></dd>
        <dt>Ticket</dt><dd><?= e($record['productName']) ?></dd>
        <dt>Guest</dt><dd><?= e($record['customer']['first'] . ' ' . $record['customer']['last']) ?></dd>
        <dt>Amount</dt><dd><?= e(money((float) $record['chargeAmount'], $record['chargeCurrency'])) ?></dd>
        <?php if ($status === 'booked'): ?><dt>Order number</dt><dd><?= e($record['orderNumber']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($status === 'booked'): ?>
        <p>Your tickets are booked. Keep the order number above.</p>
    <?php elseif ($status === 'booking_failed'): ?>
        <div class="notice error" role="alert">Your payment went through but the supplier did not confirm the booking. Keep the reference <strong><?= e($record['id']) ?></strong> and contact support so it can be rebooked or refunded.<?php if ($debug && !empty($record['error'])): ?><pre><?= e($record['error']) ?></pre><?php endif; ?></div>
    <?php elseif ($status === 'payment_failed'): ?>
        <div class="notice error" role="alert">The payment was not completed and nothing was booked. You can go back and try again.</div>
        <p><a class="btn btn-primary" href="/attractions">Browse attractions</a></p>
    <?php else: ?>
        <div class="notice">We have not received a confirmation from the bank yet. This page can be refreshed in a moment<?= !empty($record['gatewayStatus']) ? ' (gateway status: ' . e($record['gatewayStatus']) . ')' : '' ?>.</div>
        <p><a class="btn btn-primary" href="/pay/result/<?= e(rawurlencode($record['id'])) ?>">Refresh</a></p>
    <?php endif; ?>
</div>
