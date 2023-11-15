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

    /** @var  int $activations_left */
    private int $activations_left;

    /** @var int $price_id */
    private int $price_id;

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
     * @param int $activations_left
     */
    public function setActivationsLeft(int $activations_left): void
    {
        $this->activations_left = $activations_left;
    }

    /**
     * @return int
     */
    public function getActivationsLeft(): int
    {
        return $this->activations_left;
    }

    /**
     * @param mixed $price_id
     */
    public function setPriceId(int $price_id): void
    {
        $this->price_id = $price_id;
    }

    /**
     * @return mixed
     */
    public function getPriceId(): int
    {
        return $this->price_id;
    }
}
