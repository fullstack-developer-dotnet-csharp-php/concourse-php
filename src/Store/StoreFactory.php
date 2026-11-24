<?php
declare(strict_types=1);

namespace App\Store;

use App\Support\Config;

final class StoreFactory
{
    public static function make(): CatalogStore
    {
        $driver = Config::string('store.driver');
        return match ($driver) {
            'sleek' => new SleekCatalogStore(Config::string('store.sleek.dir')),
            'mysql' => new MysqlCatalogStore(
                Config::string('store.mysql.host'),
                Config::int('store.mysql.port'),
                Config::string('store.mysql.name'),
                Config::string('store.mysql.user'),
                Config::string('store.mysql.password')
            ),
            default => throw new \InvalidArgumentException('Unknown store.driver "' . $driver . '". Use "sleek" or "mysql".'),
        };
    }
}
