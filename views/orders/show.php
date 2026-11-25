<?php echo App\Support\View::partial('admin_nav'); ?>
<div class="container page-head">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="/orders">Orders</a><span><?= e($order->orderNumber) ?></span></nav>
    <h1>Order <?= e($order->orderNumber) ?> <span class="badge badge-<?= e($order->status) ?>"><?= e($order->status) ?></span></h1>
    <?php if ($order->env): ?><p class="muted">Environment: <?= e($order->env) ?></p><?php endif; ?>
</div>
<div class="container layout-main">
    <div class="content">
        <dl class="facts">
            <dt>Guest</dt><dd><?= e($order->guestName) ?><?= $order->guestEmail ? ' (' . e($order->guestEmail) . ')' : '' ?></dd>
            <dt>Ordered</dt><dd><?= e($order->orderDate) ?></dd>
            <?php if ($order->eventDate): ?><dt>Visit date</dt><dd><?= e(substr($order->eventDate, 0, 10)) ?></dd><?php endif; ?>
            <?php if ($order->partnerOrderRef): ?><dt>Reference</dt><dd><?= e($order->partnerOrderRef) ?></dd><?php endif; ?>
            <dt>Attraction</dt><dd><?= e($order->attractionId) ?></dd>
            <dt>Product</dt><dd><?= e($order->productId) ?></dd>
        </dl>
        <h2>Tickets</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Offer</th><th>Qty</th><th>Each</th><th>Total</th><th>Barcodes</th></tr></thead>
                <tbody>
                <?php foreach ($order->lines as $l): ?>
                    <tr>
                        <td><?= e($l['label'] ?? '') ?></td><td><?= e($l['quantity'] ?? '') ?></td>
                        <td><?= e(money(isset($l['priceEach']) ? (float) $l['priceEach'] : null, $order->currency)) ?></td>
                        <td><?= e(money(isset($l['priceTotal']) ? (float) $l['priceTotal'] : null, $order->currency)) ?></td>
                        <td><?= e(implode(', ', array_map(static fn($t) => $t['barcode'] ?? '', $l['tickets'] ?? []))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p><strong>Total: <?= e(money($order->priceTotal, $order->currency)) ?></strong></p>

        <?php if ($order->cancellation): ?>
            <h2>Cancellation</h2>
            <dl class="facts">
                <dt>Requested</dt><dd><?= e($order->cancellation['requestedAt'] ?? '') ?></dd>
                <dt>Completed</dt><dd><?= e($order->cancellation['completedAt'] ?? 'Pending') ?></dd>
                <dt>Reason</dt><dd><?= e($order->cancellation['reason'] ?? '') ?></dd>
                <dt>Refund</dt><dd><?= isset($order->cancellation['refundAmount']) ? e(money((float) $order->cancellation['refundAmount'], $order->currency)) : 'not available' ?></dd>
            </dl>
        <?php endif; ?>
    </div>
    <aside class="side">
        <div class="side-card">
            <?php if ($order->vouchers !== []): ?>
                <h3>Voucher</h3>
                <a class="btn btn-ghost btn-block" href="/orders/<?= e(rawurlencode($order->orderNumber)) ?>/voucher">Open voucher</a>
                <a class="btn btn-ghost btn-block" href="/orders/<?= e(rawurlencode($order->orderNumber)) ?>/voucher?format=pdf">Download PDF</a>
            <?php endif; ?>
            <?php if (!$order->isCancelled()): ?>
                <h3>Cancel order</h3>
                <form action="/orders/<?= e(rawurlencode($order->orderNumber)) ?>/cancel" method="post" data-confirm="Cancel this order?">
                    <?= App\Support\Csrf::field() ?>
                    <label class="small">Reason<input name="reason" maxlength="200"></label>
                    <button class="btn btn-danger btn-block" type="submit">Cancel order</button>
                </form>
            <?php endif; ?>
        </div>
    </aside>
</div>
