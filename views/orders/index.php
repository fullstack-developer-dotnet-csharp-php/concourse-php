<div class="container page-head">
    <h1>Orders</h1>
    <p class="muted"><?= e(count($orders)) ?> orders returned by the API for this key.</p>
</div>
<div class="container">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Order</th><th>Status</th><th>Ordered</th><th>Visit</th><th>Qty</th><th>Total</th><th>Guest</th><th>Ref</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="/orders/<?= e(rawurlencode($o->orderNumber)) ?>"><?= e($o->orderNumber) ?></a></td>
                    <td><span class="badge badge-<?= e($o->status) ?>"><?= e($o->status) ?></span></td>
                    <td><?= e($o->orderDate ? substr($o->orderDate, 0, 10) : '') ?></td>
                    <td><?= e($o->eventDate ? substr($o->eventDate, 0, 10) : '') ?></td>
                    <td><?= e($o->quantity) ?></td>
                    <td><?= e(money($o->priceTotal, $o->currency)) ?></td>
                    <td><?= e($o->guestName) ?></td>
                    <td><?= e($o->partnerOrderRef) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
