<?php

namespace DanJohnson95\Pinout\Services;

use DanJohnson95\Pinout\Exceptions\I2CException;
use RuntimeException;

class I2CService
{
    private int $bus;

    public function __construct(int $bus = 1)
    {
        $this->bus = $bus;
        $this->checkI2CTools();
    }

    /**
     * Check if i2c-tools are installed
     */
    private function checkI2CTools(): void
    {
        exec('which i2cdetect', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                'i2c-tools not found. Please install with: sudo apt-get install i2c-tools'
            );
        }
    }

    /**
     * Detect all I2C devices on the bus
     *
     * @return array Array of detected device addresses
     */
    public function detect(): array
    {
        $command = sprintf('i2cdetect -y %d', $this->bus);
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new I2CException('Failed to detect I2C devices');
        }

        return $this->parseDetectOutput($output);
    }

    /**
     * Parse i2cdetect output to extract device addresses
     */
    private function parseDetectOutput(array $output): array
    {
        $devices = [];

        // Skip first line (header)
        array_shift($output);

        foreach ($output as $line) {
            // Match hex addresses (not -- or UU)
            preg_match_all('/\s([0-9a-f]{2})\s/', $line, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $address) {
                    $devices[] = hexdec($address);
                }
            }
        }

        return $devices;
    }

    /**
     * Read a byte from an I2C device register
     *
     * @param int $address Device address (7-bit)
     * @param int $register Register address
     * @return int Byte value read
     */
    public function readByte(int $address, int $register): int
    {
        $command = sprintf(
            'i2cget -y %d 0x%02x 0x%02x',
            $this->bus,
            $address,
            $register
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            throw new I2CException(
                sprintf('Failed to read from device 0x%02x register 0x%02x', $address, $register)
            );
        }

        return hexdec($output[0]);
    }

    /**
     * Write a byte to an I2C device register
     *
     * @param int $address Device address (7-bit)
     * @param int $register Register address
     * @param int $value Byte value to write
     */
    public function writeByte(int $address, int $register, int $value): void
    {
        $command = sprintf(
            'i2cset -y %d 0x%02x 0x%02x 0x%02x',
            $this->bus,
            $address,
            $register,
            $value
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new I2CException(
                sprintf('Failed to write to device 0x%02x register 0x%02x', $address, $register)
            );
        }
    }

    /**
     * Read a word (2 bytes) from an I2C device register
     *
     * @param int $address Device address (7-bit)
     * @param int $register Register address
     * @return int Word value read
     */
    public function readWord(int $address, int $register): int
    {
        $command = sprintf(
            'i2cget -y %d 0x%02x 0x%02x w',
            $this->bus,
            $address,
            $register
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            throw new I2CException(
                sprintf('Failed to read word from device 0x%02x register 0x%02x', $address, $register)
            );
        }

        return hexdec($output[0]);
    }

    /**
     * Write a word (2 bytes) to an I2C device register
     *
     * @param int $address Device address (7-bit)
     * @param int $register Register address
     * @param int $value Word value to write
     */
    public function writeWord(int $address, int $register, int $value): void
    {
        $command = sprintf(
            'i2cset -y %d 0x%02x 0x%02x 0x%04x w',
            $this->bus,
            $address,
            $register,
            $value
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new I2CException(
                sprintf('Failed to write word to device 0x%02x register 0x%02x', $address, $register)
            );
        }
    }

    /**
     * Read multiple bytes from an I2C device (block read)
     *
     * @param int $address Device address (7-bit)
     * @param int $register Starting register address
     * @param int $length Number of bytes to read
     * @return array Array of byte values
     */
    public function readBlock(int $address, int $register, int $length): array
    {
        $command = sprintf(
            'i2cget -y %d 0x%02x 0x%02x i %d',
            $this->bus,
            $address,
            $register,
            $length
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            throw new I2CException(
                sprintf('Failed to read block from device 0x%02x register 0x%02x', $address, $register)
            );
        }

        // Parse the output (format: "0x01 0x02 0x03...")
        $bytes = [];
        foreach ($output as $line) {
            preg_match_all('/0x([0-9a-f]{2})/i', $line, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $byte) {
                    $bytes[] = hexdec($byte);
                }
            }
        }

        return $bytes;
    }

    /**
     * Write multiple bytes to an I2C device (block write)
     *
     * @param int $address Device address (7-bit)
     * @param int $register Starting register address
     * @param array $data Array of byte values to write
     */
    public function writeBlock(int $address, int $register, array $data): void
    {
        $dataHex = array_map(fn($byte) => sprintf('0x%02x', $byte), $data);
        $dataString = implode(' ', $dataHex);

        $command = sprintf(
            'i2cset -y %d 0x%02x 0x%02x %s i',
            $this->bus,
            $address,
            $register,
            $dataString
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new I2CException(
                sprintf('Failed to write block to device 0x%02x register 0x%02x', $address, $register)
            );
        }
    }

    /**
     * Get the current I2C bus number
     */
    public function getBus(): int
    {
        return $this->bus;
    }

    /**
     * Set the I2C bus number
     */
    public function setBus(int $bus): self
    {
        $this->bus = $bus;
        return $this;
    }

    /**
     * Create an I2CDevice instance for a specific address
     */
    public function device(int $address): \DanJohnson95\Pinout\Devices\I2CDevice
    {
        return new \DanJohnson95\Pinout\Devices\I2CDevice($address, $this);
    }
}
