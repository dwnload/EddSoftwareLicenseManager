<?php

declare(strict_types=1);

namespace Dwnload\EddSoftwareLicenseManager\Edd\Models;

/**
 * Class CheckLicense
 * @package Dwnload\EddSoftwareLicenseManager\Edd\Models
 */
class CheckLicense extends LicenseStatus
{

    /** @var  bool $success */
    private bool $success;

    /** @var  string $checksum */
    private string $checksum;

    /** @var  int $license_limit */
    private int $license_limit;

    /** @var  int $site_count */
    private int $site_count;

    /** @var  int|string $activations_left */
    private int|string $activations_left;

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return bool
     */
    public function getSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param string $checksum
     */
    public function setChecksum(string $checksum): void
    {
        $this->checksum = $checksum;
    }

    /**
     * @return string
     */
    public function getChecksum(): string
    {
        return $this->checksum;
    }

    /**
     * @param int $license_limit
     */
    public function setLicenseLimit(int $license_limit): void
    {
        $this->license_limit = $license_limit;
    }

    /**
     * @return int
     */
    public function getLicenseLimit(): int
    {
        return $this->license_limit;
    }

    /**
     * @param int $site_count
     */
    public function setSiteCount(int $site_count): void
    {
        $this->site_count = $site_count;
    }

    /**
     * @return int
     */
    public function getSiteCount(): int
    {
        return $this->site_count;
    }

    /**
     * @param int|string $activations_left
     */
    public function setActivationsLeft(int|string $activations_left): void
    {
        $this->activations_left = $activations_left;
    }

    /**
     * @return int
     */
    public function getActivationsLeft(): int|string
    {
        return $this->activations_left;
    }
}
