<?php

namespace DanJohnson95\Pinout\Console\I2C;

use DanJohnson95\Pinout\Facades\I2C;
use Illuminate\Console\Command;

class I2CWriteCommand extends Command
{
    protected $signature = 'pinout:i2c-write
                            {address : Device address (hex or decimal)}
                            {register : Register address (hex or decimal)}
                            {value : Value to write (hex or decimal, or comma-separated for block)}
                            {--bus=1 : The I2C bus number}
                            {--word : Write a word (2 bytes) instead of a byte}
                            {--block : Write multiple bytes (block write)}';

    protected $description = 'Write to an I2C device register';

    public function handle(): int
    {
        $bus = (int) $this->option('bus');
        $address = $this->parseNumber($this->argument('address'));
        $register = $this->parseNumber($this->argument('register'));

        try {
            I2C::setBus($bus);

            if ($this->option('block')) {
                $values = array_map(
                    fn($v) => $this->parseNumber(trim($v)),
                    explode(',', $this->argument('value'))
                );

                I2C::writeBlock($address, $register, $values);

                $this->info(sprintf(
                    'Wrote %d bytes to device 0x%02x starting at register 0x%02x',
                    count($values),
                    $address,
                    $register
                ));

                $rows = [];
                foreach ($values as $index => $byte) {
                    $rows[] = [
                        $register + $index,
                        sprintf('0x%02x', $byte),
                        $byte
                    ];
                }

                $this->table(
                    ['Register', 'Hex', 'Decimal'],
                    $rows
                );

            } elseif ($this->option('word')) {
                $value = $this->parseNumber($this->argument('value'));

                I2C::writeWord($address, $register, $value);

                $this->info(sprintf(
                    'Wrote word 0x%04x to device 0x%02x register 0x%02x',
                    $value,
                    $address,
                    $register
                ));

            } else {
                $value = $this->parseNumber($this->argument('value'));

                I2C::writeByte($address, $register, $value);

                $this->info(sprintf(
                    'Wrote byte 0x%02x to device 0x%02x register 0x%02x',
                    $value,
                    $address,
                    $register
                ));
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function parseNumber(string $input): int
    {
        $input = strtolower(trim($input));

        if (str_starts_with($input, '0x')) {
            return hexdec(substr($input, 2));
        }

        if (str_starts_with($input, '0b')) {
            return bindec(substr($input, 2));
        }

        return (int) $input;
    }
}
