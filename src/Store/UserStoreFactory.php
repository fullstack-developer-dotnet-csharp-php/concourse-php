<?php
declare(strict_types=1);

namespace App\Store;

use App\Support\Config;

final class UserStoreFactory
{
    public static function make(): UserStore
    {
        $driver = Config::string('store.driver');
        return match ($driver) {
            'sleek' => new SleekUserStore(Config::string('store.sleek.dir')),
            'mysql' => new MysqlUserStore(
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
