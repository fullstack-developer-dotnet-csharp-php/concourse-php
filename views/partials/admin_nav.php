<?php use App\Support\Config; ?>
<div class="container admin-nav">
    <a href="/admin/settings">Settings</a>
    <a href="/admin/status">System</a>
    <a href="/admin/logs">Logs</a>
    <?php if (Config::bool('site.orders_enabled')): ?><a href="/orders">Orders</a><?php endif; ?>
    <a href="/">View site</a>
</div>
