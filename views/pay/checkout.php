<div class="container page-head"><h1>Redirecting to payment</h1></div>
<div class="container narrow">
    <p id="pay-status">Opening the secure payment page for <strong><?= e(money((float) $record['chargeAmount'], $record['chargeCurrency'])) ?></strong>...</p>
    <p class="muted small">Reference <?= e($record['id']) ?><?= $mode === 'sandbox' ? ' (test mode: no real money is taken)' : '' ?></p>
    <noscript><div class="notice error">JavaScript is required to open the payment page.</div></noscript>
    <div id="pay-error" class="notice error" role="alert" hidden></div>
    <button id="pay-retry" class="btn btn-primary" type="button" hidden>Open payment page</button>
</div>
<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script>
(function () {
    var session = <?= json_encode($record['paymentSessionId'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var mode = <?= json_encode($mode, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var errorEl = document.getElementById('pay-error');
    var retry = document.getElementById('pay-retry');
    function openCheckout() {
        errorEl.hidden = true;
        if (typeof Cashfree !== 'function') {
            errorEl.textContent = 'The Cashfree script did not load. Check your connection and try again.';
            errorEl.hidden = false;
            retry.hidden = false;
            return;
        }
        var cf = Cashfree({ mode: mode === 'production' ? 'production' : 'sandbox' });
        cf.checkout({ paymentSessionId: session, redirectTarget: '_self' }).then(function (r) {
            if (r && r.error) { errorEl.textContent = r.error.message || 'The payment page could not be opened.'; errorEl.hidden = false; retry.hidden = false; }
        });
    }
    retry.addEventListener('click', openCheckout);
    window.addEventListener('load', openCheckout);
})();
</script>
