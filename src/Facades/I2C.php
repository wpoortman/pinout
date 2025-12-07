<?php

namespace DanJohnson95\Pinout\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array detect()
 * @method static int readByte(int $address, int $register)
 * @method static void writeByte(int $address, int $register, int $value)
 * @method static int readWord(int $address, int $register)
 * @method static void writeWord(int $address, int $register, int $value)
 * @method static array readBlock(int $address, int $register, int $length)
 * @method static void writeBlock(int $address, int $register, array $data)
 * @method static \DanJohnson95\Pinout\Devices\I2CDevice device(int $address)
 * @method static int getBus()
 * @method static \DanJohnson95\Pinout\Services\I2CService setBus(int $bus)
 *
 * @see \DanJohnson95\Pinout\Services\I2CService
 */
class I2C extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'i2c.service';
    }
}
