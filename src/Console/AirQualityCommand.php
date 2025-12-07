<?php

namespace DanJohnson95\Pinout\Console;

use Illuminate\Console\Command;
use DanJohnson95\Pinout\Facades\I2C;
use DanJohnson95\Pinout\Drivers\SPS30Driver;

class AirQualityCommand extends Command
{
    protected $signature = 'pinout:aq:read';
    protected $description = 'Read air quality from SPS30 sensor';

    public function handle(): int
    {
        $sensor = new SPS30Driver(I2C::getFacadeRoot());

        $this->info('Starting measurement...');

        try {
            $data = $sensor->quickMeasurement();

            $this->table(
                ['Measurement', 'Value', 'Unit'],
                [
                    ['PM1.0', number_format($data['mc_1p0'], 2), 'μg/m³'],
                    ['PM2.5', number_format($data['mc_2p5'], 2), 'μg/m³'],
                    ['PM4.0', number_format($data['mc_4p0'], 2), 'μg/m³'],
                    ['PM10', number_format($data['mc_10p0'], 2), 'μg/m³'],
                    ['Particle Count (0.5μm)', number_format($data['nc_0p5'], 2), '#/cm³'],
                    ['Typical Particle Size', number_format($data['typical_particle_size'], 2), 'μm'],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
