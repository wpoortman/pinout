<?php

namespace DanJohnson95\Pinout\Console\I2C;

use DanJohnson95\Pinout\Facades\I2C;
use Illuminate\Console\Command;

class I2CReadCommand extends Command
{
    protected $signature = 'pinout:i2c-read
                            {address : Device address (hex or decimal)}
                            {register : Register address (hex or decimal)}
                            {--bus=1 : The I2C bus number}
                            {--word : Read a word (2 bytes) instead of a byte}
                            {--block= : Read multiple bytes (block length)}';

    protected $description = 'Read from an I2C device register';

    public function handle(): int
    {
        $bus = (int) $this->option('bus');
        $address = $this->parseNumber($this->argument('address'));
        $register = $this->parseNumber($this->argument('register'));

        try {
            I2C::setBus($bus);

            if ($this->option('block')) {
                $length = (int) $this->option('block');
                $data = I2C::readBlock($address, $register, $length);

                $this->info(sprintf(
                    'Read %d bytes from device 0x%02x register 0x%02x:',
                    count($data),
                    $address,
                    $register
                ));

                $rows = [];
                foreach ($data as $index => $byte) {
                    $rows[] = [
                        $index,
                        sprintf('0x%02x', $byte),
                        $byte,
                        sprintf('0b%08b', $byte)
                    ];
                }

                $this->table(
                    ['Index', 'Hex', 'Decimal', 'Binary'],
                    $rows
                );

            } elseif ($this->option('word')) {
                $value = I2C::readWord($address, $register);

                $this->info(sprintf(
                    'Read word from device 0x%02x register 0x%02x:',
                    $address,
                    $register
                ));

                $this->table(
                    ['Format', 'Value'],
                    [
                        ['Hex', sprintf('0x%04x', $value)],
                        ['Decimal', $value],
                        ['Binary', sprintf('0b%016b', $value)]
                    ]
                );

            } else {
                $value = I2C::readByte($address, $register);

                $this->info(sprintf(
                    'Read byte from device 0x%02x register 0x%02x:',
                    $address,
                    $register
                ));

                $this->table(
                    ['Format', 'Value'],
                    [
                        ['Hex', sprintf('0x%02x', $value)],
                        ['Decimal', $value],
                        ['Binary', sprintf('0b%08b', $value)]
                    ]
                );
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
