<?php
use App\Models\Offer;
use App\Support\Config;

$needsEvent = $product->needsEvent();
$first = $month;
$prev = $first->modify('-1 month');
$next = $first->modify('+1 month');
$canPrev = $first > new DateTimeImmutable('first day of this month midnight');
$gridStart = $first->modify('-' . (int) $first->format('w') . ' days');
$last = $first->modify('last day of this month');
$gridEnd = $last->modify('+' . (6 - (int) $last->format('w')) . ' days');

$offerData = array_map(static fn(Offer $o) => [
    'offerId' => $o->offerId, 'label' => $o->label, 'age' => $o->ageLabel(), 'bookable' => $o->bookable,
    'minQty' => $o->minQty, 'maxQty' => $o->maxQty, 'needsEvent' => $o->needsEvent,
    'price' => $o->price ?? ($priceFix[$o->offerId] ?? null),
    'travellerFields' => $o->travellerFields, 'questions' => $o->questions,
], $product->offers);

$calendarData = [];
foreach ($days as $date => $entries) {
    foreach ($entries as $entry) {
        $row = ['eventKey' => $entry['eventKey'], 'slot' => $entry['slot'] ?? null, 'offers' => []];
        foreach ($entry['offers'] ?? [] as $o) {
            $row['offers'][$o['offerId']] = ['price' => $o['price']['amount'] ?? null, 'capacity' => $o['capacity'] ?? null];
        }
        $calendarData[$date][] = $row;
    }
}
$bookingData = ['currency' => $currency, 'needsEvent' => $needsEvent, 'offers' => $offerData, 'calendar' => $calendarData];
?>
        <?php if ($product->bookable && !empty($noAvailability)): ?>
            <section class="notice-card" id="booking" aria-labelledby="noavail-h">
                <h2 id="noavail-h">No dates are open for this ticket yet</h2>
                <p>The supplier has not released any dates for sale between today and <?= e($horizon->format('j F Y')) ?>. Tickets can only be booked once dates are open, so there is nothing to choose right now.</p>
                <p class="muted">Please check again later<?php if ($product->fromPrice() !== null): ?>. Prices start from <strong><?= e(money($product->fromPrice(), $product->offers[0]->currency ?? 'USD')) ?></strong> per person<?php endif; ?>.</p>
                <a class="btn btn-ghost" href="/attractions">Browse other attractions</a>
            </section>
        <?php elseif ($product->bookable): ?>
            <form id="booking" class="booking" action="/book/<?= e(rawurlencode($attraction->id)) ?>/<?= e(rawurlencode($product->id)) ?>" method="post" novalidate>
                <input type="hidden" name="idempotencyKey" value="<?= e($idempotencyKey) ?>">
                <input type="hidden" name="eventKey" id="eventKey" value="">
                <input type="hidden" name="visitDate" id="visitDate" value="">
                <?= App\Support\Csrf::field() ?>

                <?php if ($availabilityError): ?>
                    <div class="notice error" role="alert">
                        <strong>Availability could not be loaded.</strong>
                        <p>The supplier returned HTTP <?= e($availabilityError->status) ?><?= $availabilityError->errorCode ? ' (' . e($availabilityError->errorCode) . ')' : '' ?>. Prices and dates cannot be shown for this ticket right now.</p>
                        <?php if (Config::bool('debug')): ?><pre class="small"><?= e($availabilityError->getMessage()) ?></pre><?php endif; ?>
                    </div>
                <?php elseif ($needsEvent): ?>
                    <section aria-labelledby="cal-h">
                        <?php if (!empty($nextDate)): $nd = new DateTimeImmutable($nextDate); ?>
                            <div class="notice info" role="status">No dates are open in <?= e($first->format('F Y')) ?>. The next available date is <strong><?= e($nd->format('l, j F Y')) ?></strong>. <a href="<?= e($path . '?month=' . $nd->format('Y-m') . ($anchor ?? '')) ?>" rel="nofollow">Go to <?= e($nd->format('F Y')) ?></a></div>
                        <?php endif; ?>
                        <div class="cal-head">
                            <?php if ($canPrev): ?><a class="btn btn-ghost" href="<?= e($path . '?month=' . $prev->format('Y-m') . ($anchor ?? '')) ?>" rel="nofollow">Prev</a><?php else: ?><span class="btn btn-ghost is-disabled">Prev</span><?php endif; ?>
                            <h2 id="cal-h"><?= e($first->format('F Y')) ?></h2>
                            <a class="btn btn-ghost" href="<?= e($path . '?month=' . $next->format('Y-m') . ($anchor ?? '')) ?>" rel="nofollow">Next</a>
                        </div>
                        <div class="cal" role="grid" aria-label="Choose a date">
                            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $n): ?><div class="cal-dow" role="columnheader"><?= $n ?></div><?php endforeach; ?>
                            <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')): ?>
                                <?php
                                $key = $d->format('Y-m-d');
                                $in = $d->format('m') === $first->format('m');
                                $entries = $in ? ($calendarData[$key] ?? []) : [];
                                $min = null;
                                foreach ($entries as $en) {
                                    foreach ($en['offers'] as $of) {
                                        if ($of['price'] !== null && ($min === null || $of['price'] < $min)) {
                                            $min = $of['price'];
                                        }
                                    }
                                }
                                ?>
                                <?php if (!$in): ?>
                                    <div class="cal-cell cal-out" role="gridcell"></div>
                                <?php elseif ($entries === []): ?>
                                    <div class="cal-cell cal-off" role="gridcell"><span class="cal-num"><?= e($d->format('j')) ?></span><?php if ($d >= $today): ?><span class="cal-note">n/a</span><?php endif; ?></div>
                                <?php else: ?>
                                    <button type="button" class="cal-cell cal-on" role="gridcell" data-date="<?= e($key) ?>" aria-label="<?= e($d->format('l, F j')) ?>">
                                        <span class="cal-num"><?= e($d->format('j')) ?></span>
                                        <?php if ($min !== null): ?><span class="cal-price"><?= e(money((float) $min, $currency)) ?></span><?php endif; ?>
                                    </button>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <p class="muted small">Dates marked n/a have no availability returned for this ticket.</p>
                    </section>
                <?php endif; ?>

                <section aria-labelledby="sel-h">
                    <h2 id="sel-h">Select tickets</h2>
                    <p id="date-hint" class="muted"><?= $needsEvent ? 'Choose a date in the calendar first.' : '' ?></p>
                    <div id="slots" class="slots" hidden></div>
                    <div id="offers"></div>
                </section>

                <section id="guest-section" hidden aria-labelledby="guest-h">
                    <h2 id="guest-h">Guest details</h2>
                    <div class="form-grid">
                        <label>First name<input name="guest[first]" required></label>
                        <label>Last name<input name="guest[last]" required></label>
                        <?php $paying = Config::bool('payments.enabled'); ?>
                        <label>Email<?= $paying ? ' *' : '' ?><input type="email" name="guest[email]"<?= $paying ? ' required' : '' ?>></label>
                        <?php if ($paying): ?><label>Phone *<input type="tel" name="guest[phone]" required inputmode="tel" autocomplete="tel"></label><?php endif; ?>
                    </div>
                    <div id="booking-fields"></div>
                    <div id="travellers"></div>
                </section>

                <?php $policies = App\Support\Legal::available(); if ($policies): ?>
                <p class="agree"><label class="check"><input type="checkbox" name="agree" value="1" required> I agree to the <?php $n = 0; foreach ($policies as $slug => $title): ?><?= $n++ > 0 ? ', ' : '' ?><a href="/<?= e($slug) ?>" target="_blank" rel="noopener"><?= e(mb_strtolower($title)) ?></a><?php endforeach; ?>.</label></p>
                <?php endif; ?>
                <div class="book-bar" id="book-bar" hidden>
                    <div><span class="muted">Total</span> <strong id="total">-</strong></div>
                    <button type="submit" class="btn btn-primary"><?= Config::bool('payments.enabled') ? 'Pay and book' : 'Book now' ?></button>
                </div>
                <p id="form-error" class="error" role="alert" hidden></p>
            </form>
            <a class="mobile-cta" href="#booking" aria-label="Choose your tickets">
                <?php if ($product->fromPrice() !== null): ?><span class="price"><small>From</small><?= e(money($product->fromPrice(), $product->offers[0]->currency ?? 'USD')) ?></span><?php else: ?><span class="muted small">Live prices and availability</span><?php endif; ?>
                <span class="btn btn-primary"><?= $needsEvent ? 'Choose a date' : 'Choose tickets' ?></span>
            </a>
            <script type="application/json" id="booking-data"><?= json_encode($bookingData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <?php endif; ?>
