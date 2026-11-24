<?php use App\Support\View; ?>
<section class="hero">
    <div class="container hero-inner">
        <p class="eyebrow">Live prices and availability</p>
        <h1>Find and book the best attractions, tours and city passes</h1>
        <p class="lead">Browse <?= e(number_format($total)) ?> attractions across theme parks, museums, tours, shows and more.</p>
        <form class="hero-search" action="/attractions" method="get" role="search">
            <label class="sr-only" for="hero-q">Search</label>
            <input id="hero-q" type="search" name="q" placeholder="Where are you going or what do you want to do?" autocomplete="off" required>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <ul class="chips" aria-label="Popular categories">
            <?php foreach ($categories as $name => $count): ?>
                <li><a class="chip" href="/attractions?category=<?= e(rawurlencode($name)) ?>"><?= e($name) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section container" aria-labelledby="featured-h">
    <div class="section-head">
        <h2 id="featured-h">Featured attractions</h2>
        <a href="/attractions">View all</a>
    </div>
    <div class="grid">
        <?php foreach ($featured as $a): ?>
            <?= View::partial('card', ['a' => $a]) ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="section container" aria-labelledby="dest-h">
    <div class="section-head"><h2 id="dest-h">Top destinations</h2></div>
    <ul class="dest-grid">
        <?php foreach ($cities as $name => $count): ?>
            <li>
                <a class="dest" href="/attractions?city=<?= e(rawurlencode($name)) ?>">
                    <span class="dest-name"><?= e($name) ?></span>
                    <span class="dest-count"><?= e($count) ?> things to do</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="section container" aria-labelledby="why-h">
    <div class="section-head"><h2 id="why-h">How it works</h2></div>
    <ol class="steps">
        <li><h3>Search</h3><p>Find an attraction, tour or pass by name, city or category.</p></li>
        <li><h3>Pick a date</h3><p>Dated tickets show a live calendar with prices and remaining capacity.</p></li>
        <li><h3>Book</h3><p>Enter the guest details the supplier needs and receive your order number and voucher.</p></li>
    </ol>
</section>
