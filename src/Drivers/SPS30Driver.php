<?php

namespace DanJohnson95\Pinout\Drivers;

use DanJohnson95\Pinout\Services\I2CService;
use DanJohnson95\Pinout\Exceptions\I2CException;

/**
 * Driver for Sensirion SPS30 Particulate Matter Sensor
 *
 * The SPS30 measures:
 * - PM1.0, PM2.5, PM4.0, PM10 (mass concentrations in μg/m³)
 * - NC0.5, NC1.0, NC2.5, NC4.0, NC10.0 (number concentrations in #/cm³)
 * - Typical particle size (μm)
 */
class SPS30Driver
{
    private const I2C_ADDRESS = 0x69;

    // Command addresses (16-bit)
    private const CMD_START_MEASUREMENT = 0x0010;
    private const CMD_STOP_MEASUREMENT = 0x0104;
    private const CMD_READ_DATA_READY = 0x0202;
    private const CMD_READ_MEASURED_VALUES = 0x0300;
    private const CMD_SLEEP = 0x1001;
    private const CMD_WAKE_UP = 0x1103;
    private const CMD_START_FAN_CLEANING = 0x5607;
    private const CMD_READ_WRITE_AUTO_CLEANING_INTERVAL = 0x8004;
    private const CMD_READ_DEVICE_INFO = 0xD033;
    private const CMD_RESET = 0xD304;

    private I2CService $i2c;
    private int $address;

    public function __construct(I2CService $i2c, int $address = self::I2C_ADDRESS)
    {
        $this->i2c = $i2c;
        $this->address = $address;
    }

    /**
     * Calculate CRC-8 checksum (polynomial 0x31, init 0xFF)
     */
    private function calculateCRC(array $data): int
    {
        $crc = 0xFF;

        foreach ($data as $byte) {
            $crc ^= $byte;
            for ($i = 0; $i < 8; $i++) {
                if ($crc & 0x80) {
                    $crc = ($crc << 1) ^ 0x31;
                } else {
                    $crc = $crc << 1;
                }
            }
        }

        return $crc & 0xFF;
    }

    /**
     * Send a command to the sensor
     * @throws I2CException
     */
    private function sendCommand(int $command, array $data = []): void
    {
        $bytes = [
            ($command >> 8) & 0xFF,  // MSB
            $command & 0xFF           // LSB
        ];

        // Add data with CRC for every 2 bytes
        for ($i = 0; $i < count($data); $i += 2) {
            $bytes[] = $data[$i];
            $bytes[] = $data[$i + 1] ?? 0x00;
            $bytes[] = $this->calculateCRC([$data[$i], $data[$i + 1] ?? 0x00]);
        }

        // Use block write to send command
        $this->i2c->writeBlock($this->address, $bytes[0], array_slice($bytes, 1));
    }

    /**
     * Read data from sensor and verify CRC
     * @throws I2CException
     */
    private function readData(int $length): array
    {
        // Read length includes CRC bytes (every 2 data bytes + 1 CRC byte)
        $totalBytes = $length + (int)($length / 2);
        $rawData = $this->i2c->readBlock($this->address, 0x00, $totalBytes);

        $data = [];
        for ($i = 0; $i < count($rawData); $i += 3) {
            $byte1 = $rawData[$i];
            $byte2 = $rawData[$i + 1] ?? 0;
            $crc = $rawData[$i + 2] ?? 0;

            // Verify CRC
            $calculatedCRC = $this->calculateCRC([$byte1, $byte2]);
            if ($crc !== $calculatedCRC) {
                throw new I2CException('CRC verification failed');
            }

            $data[] = $byte1;
            $data[] = $byte2;
        }

        return $data;
    }

    /**
     * Convert 4 bytes to IEEE-754 float
     * @throws I2CException
     */
    private function bytesToFloat(array $bytes): float
    {
        if (count($bytes) !== 4) {
            throw new I2CException('Expected 4 bytes for float conversion');
        }

        // Pack bytes and unpack as float
        $packed = pack('C*', ...$bytes);
        $unpacked = unpack('f', $packed);

        return $unpacked[1];
    }

    /**
     * Start measurement mode
     */
    public function startMeasurement(): void
    {
        // Start measurement with default output format (IEEE754 float)
        $this->sendCommand(self::CMD_START_MEASUREMENT, [0x03, 0x00]);
        usleep(20000); // Wait 20ms
    }

    /**
     * Stop measurement mode
     */
    public function stopMeasurement(): void
    {
        $this->sendCommand(self::CMD_STOP_MEASUREMENT);
        usleep(20000);
    }

    /**
     * Check if data is ready
     * @throws I2CException
     */
    public function isDataReady(): bool
    {
        $this->sendCommand(self::CMD_READ_DATA_READY);
        usleep(20000);

        $data = $this->readData(2);
        return $data[1] === 0x01;
    }

    /**
     * Read measured values from the sensor
     *
     * @return array{
     *   mc_1p0: float,
     *   mc_2p5: float,
     *   mc_4p0: float,
     *   mc_10p0: float,
     *   nc_0p5: float,
     *   nc_1p0: float,
     *   nc_2p5: float,
     *   nc_4p0: float,
     *   nc_10p0: float,
     *   typical_particle_size: float
     * }
     * @throws I2CException
     */
    public function readMeasurement(): array
    {
        if (!$this->isDataReady()) {
            throw new I2CException('Data not ready. Wait for measurement to complete.');
        }

        $this->sendCommand(self::CMD_READ_MEASURED_VALUES);
        usleep(20000);

        // Read 60 bytes (10 floats × 4 bytes + 30 CRC bytes)
        $data = $this->readData(40);

        // Parse 10 float values
        $values = [];
        for ($i = 0; $i < 40; $i += 4) {
            $values[] = $this->bytesToFloat(array_slice($data, $i, 4));
        }

        return [
            'mc_1p0' => $values[0],    // PM1.0 mass concentration (μg/m³)
            'mc_2p5' => $values[1],    // PM2.5 mass concentration (μg/m³)
            'mc_4p0' => $values[2],    // PM4.0 mass concentration (μg/m³)
            'mc_10p0' => $values[3],   // PM10 mass concentration (μg/m³)
            'nc_0p5' => $values[4],    // NC0.5 number concentration (#/cm³)
            'nc_1p0' => $values[5],    // NC1.0 number concentration (#/cm³)
            'nc_2p5' => $values[6],    // NC2.5 number concentration (#/cm³)
            'nc_4p0' => $values[7],    // NC4.0 number concentration (#/cm³)
            'nc_10p0' => $values[8],   // NC10.0 number concentration (#/cm³)
            'typical_particle_size' => $values[9], // Typical particle size (μm)
        ];
    }

    /**
     * Start fan cleaning manually
     */
    public function startFanCleaning(): void
    {
        $this->sendCommand(self::CMD_START_FAN_CLEANING);
        // Fan cleaning takes ~10 seconds
        sleep(10);
    }

    /**
     * Read auto-cleaning interval in seconds
     * @throws I2CException
     */
    public function getAutoCleaningInterval(): int
    {
        $this->sendCommand(self::CMD_READ_WRITE_AUTO_CLEANING_INTERVAL);
        usleep(20000);

        $data = $this->readData(4);

        // Convert to 32-bit integer
        return ($data[0] << 24) | ($data[1] << 16) | ($data[2] << 8) | $data[3];
    }

    /**
     * Set auto-cleaning interval in seconds (0 to disable)
     */
    public function setAutoCleaningInterval(int $seconds): void
    {
        $data = [
            ($seconds >> 24) & 0xFF,
            ($seconds >> 16) & 0xFF,
            ($seconds >> 8) & 0xFF,
            $seconds & 0xFF,
        ];

        $this->sendCommand(self::CMD_READ_WRITE_AUTO_CLEANING_INTERVAL, $data);
        usleep(20000);
    }

    /**
     * Read device serial number
     * @throws I2CException
     */
    public function getSerialNumber(): string
    {
        $this->sendCommand(self::CMD_READ_DEVICE_INFO);
        usleep(20000);

        $data = $this->readData(32);

        // Convert bytes to string (null-terminated)
        $serial = '';
        foreach ($data as $byte) {
            if ($byte === 0x00) {
                break;
            }
            $serial .= chr($byte);
        }

        return $serial;
    }

    /**
     * Put sensor into sleep mode (low power)
     */
    public function sleep(): void
    {
        $this->sendCommand(self::CMD_SLEEP);
        usleep(5000);
    }

    /**
     * Wake up sensor from sleep mode
     */
    public function wakeUp(): void
    {
        $this->sendCommand(self::CMD_WAKE_UP);
        usleep(5000);
    }

    /**
     * Reset the sensor
     */
    public function reset(): void
    {
        $this->sendCommand(self::CMD_RESET);
        usleep(100000); // Wait 100ms after reset
    }

    /**
     * Quick measurement - start, wait, read, stop
     * @throws I2CException
     */
    public function quickMeasurement(): array
    {
        $this->startMeasurement();

        // Wait for data to be ready (max 30 seconds)
        $timeout = 30;
        while (!$this->isDataReady() && $timeout > 0) {
            sleep(1);
            $timeout--;
        }

        if ($timeout === 0) {
            throw new I2CException('Measurement timeout');
        }

        $data = $this->readMeasurement();
        $this->stopMeasurement();

        return $data;
    }
}
