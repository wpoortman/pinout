<?php

namespace DanJohnson95\Pinout\Devices;

use DanJohnson95\Pinout\Services\I2CService;

class I2CDevice
{
    private int $address;
    private I2CService $service;

    public function __construct(int $address, I2CService $service)
    {
        $this->address = $address;
        $this->service = $service;
    }

    /**
     * Get the device address
     */
    public function getAddress(): int
    {
        return $this->address;
    }

    /**
     * Read a byte from a register
     */
    public function readByte(int $register): int
    {
        return $this->service->readByte($this->address, $register);
    }

    /**
     * Write a byte to a register
     */
    public function writeByte(int $register, int $value): self
    {
        $this->service->writeByte($this->address, $register, $value);
        return $this;
    }

    /**
     * Read a word (2 bytes) from a register
     */
    public function readWord(int $register): int
    {
        return $this->service->readWord($this->address, $register);
    }

    /**
     * Write a word (2 bytes) to a register
     */
    public function writeWord(int $register, int $value): self
    {
        $this->service->writeWord($this->address, $register, $value);
        return $this;
    }

    /**
     * Read multiple bytes from registers (block read)
     */
    public function readBlock(int $register, int $length): array
    {
        return $this->service->readBlock($this->address, $register, $length);
    }

    /**
     * Write multiple bytes to registers (block write)
     */
    public function writeBlock(int $register, array $data): self
    {
        $this->service->writeBlock($this->address, $register, $data);
        return $this;
    }

    /**
     * Set a specific bit in a register
     */
    public function setBit(int $register, int $bit): self
    {
        $value = $this->readByte($register);
        $value |= (1 << $bit);
        $this->writeByte($register, $value);
        return $this;
    }

    /**
     * Clear a specific bit in a register
     */
    public function clearBit(int $register, int $bit): self
    {
        $value = $this->readByte($register);
        $value &= ~(1 << $bit);
        $this->writeByte($register, $value);
        return $this;
    }

    /**
     * Toggle a specific bit in a register
     */
    public function toggleBit(int $register, int $bit): self
    {
        $value = $this->readByte($register);
        $value ^= (1 << $bit);
        $this->writeByte($register, $value);
        return $this;
    }

    /**
     * Check if a specific bit is set in a register
     */
    public function isBitSet(int $register, int $bit): bool
    {
        $value = $this->readByte($register);
        return (bool) ($value & (1 << $bit));
    }

    /**
     * Write multiple bits in a register (with mask)
     */
    public function writeBits(int $register, int $value, int $mask): self
    {
        $current = $this->readByte($register);
        $current = ($current & ~$mask) | ($value & $mask);
        $this->writeByte($register, $current);
        return $this;
    }

    /**
     * Read multiple bits from a register (with mask and shift)
     */
    public function readBits(int $register, int $mask, int $shift = 0): int
    {
        $value = $this->readByte($register);
        return ($value & $mask) >> $shift;
    }
}
