<?php use App\Support\View; ?>
<div class="container page-head">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><span>Attractions</span></nav>
    <h1><?= e($heading) ?></h1>
    <p class="muted"><?= e(number_format($total)) ?> results</p>
</div>

<div class="container layout-sidebar">
    <aside class="filters" aria-label="Filters">
        <form action="/attractions" method="get">
            <label for="f-q">Search</label>
            <input id="f-q" type="search" name="q" value="<?= e($q) ?>">
            <?php if ($near !== ''): ?><input type="hidden" name="near" value="<?= e($near) ?>"><?php endif; ?>
            <button type="button" class="btn btn-ghost btn-block" data-near hidden>Show attractions near me</button>
            <label for="f-city">Destination</label>
            <select id="f-city" name="city">
                <option value="">All destinations</option>
                <?php foreach ($cities as $name => $count): ?>
                    <option value="<?= e($name) ?>" <?= $name === $city ? 'selected' : '' ?>><?= e($name) ?> (<?= e($count) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label for="f-cat">Category</label>
            <select id="f-cat" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $name => $count): ?>
                    <option value="<?= e($name) ?>" <?= $name === $category ? 'selected' : '' ?>><?= e($name) ?> (<?= e($count) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-block">Apply</button>
            <?php if ($q !== '' || $city !== '' || $category !== '' || $near !== ''): ?><a class="btn btn-ghost btn-block" href="/attractions">Clear filters</a><?php endif; ?>
        </form>
    </aside>

    <section aria-label="Results">
        <?php if ($items === []): ?>
            <div class="empty"><h2>No attractions found</h2><p>Try a different search or clear the filters.</p></div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($items as $a): ?>
                    <?= View::partial('card', ['a' => $a]) ?>
                <?php endforeach; ?>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pager" aria-label="Pagination">
                    <?php if ($page > 1): ?><a rel="prev" href="<?= e(query_url('/attractions', ['q' => $q, 'city' => $city, 'category' => $category, 'near' => $near, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                    <span>Page <?= e($page) ?> of <?= e($pages) ?></span>
                    <?php if ($page < $pages): ?><a rel="next" href="<?= e(query_url('/attractions', ['q' => $q, 'city' => $city, 'category' => $category, 'near' => $near, 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
