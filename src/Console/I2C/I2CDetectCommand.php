<?php

namespace DanJohnson95\Pinout\Console\I2C;

use DanJohnson95\Pinout\Facades\I2C;
use Illuminate\Console\Command;

class I2CDetectCommand extends Command
{
    protected $signature = 'pinout:i2c-detect {--bus=1 : The I2C bus number}';

    protected $description = 'Detect all I2C devices on the bus';

    public function handle(): int
    {
        $bus = (int) $this->option('bus');

        $this->info("Scanning I2C bus {$bus}...");

        try {
            I2C::setBus($bus);
            $devices = I2C::detect();

            if (empty($devices)) {
                $this->warn('No I2C devices found.');
                return self::SUCCESS;
            }

            $this->info('Found ' . count($devices) . ' device(s):');
            $this->newLine();

            $rows = [];
            foreach ($devices as $address) {
                $rows[] = [
                    sprintf('0x%02x', $address),
                    $address,
                    sprintf('0b%08b', $address)
                ];
            }

            $this->table(
                ['Hex', 'Decimal', 'Binary'],
                $rows
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
