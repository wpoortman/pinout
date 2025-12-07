<?php

namespace DanJohnson95\Pinout;

use DanJohnson95\Pinout\Services\I2CService;
use DanJohnson95\Pinout\Shell\Commandable;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/pinout.php',
            'pinout',
        );

        $this->publishes([
            __DIR__ . '/Config/pinout.php' => config_path('pinout.php'),
        ], 'config');

        $this->app->bind(Commandable::class, config('pinout.sys_file'));

        $this->app->singleton('i2c.service', fn () => new I2CService());
    }

    public function boot()
    {
        $this->commands([
            Console\GetCommand::class,
            Console\OnCommand::class,
            Console\OffCommand::class,
            Console\ListenInterruptsCommand::class,
            Console\StartCommand::class,
            Console\SetCommand::class,

            Console\I2C\I2CDetectCommand::class,
            Console\I2C\I2CReadCommand::class,
            Console\I2C\I2CWriteCommand::class,
            Console\AirQualityCommand::class,
        ]);
    }
}
