<?php

namespace Dwnload\EddSoftwareLicenseManager\Edd\Models;

/**
 * Class CheckLicense
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd\Models
 */
class CheckLicense extends LicenseStatus {

    /** @var  bool $success */
    private $success;

    /** @var  string $checksum */
    private $checksum;

    /** @var  int $license_limit */
    private $license_limit;

    /** @var  int $site_count */
    private $site_count;

    /** @var  int $activations_left */
    private $activations_left;

    /** @var  bool|int */
    private $price_id;

    /**
     * @param bool $success
     */
    public function setSuccess( bool $success ) {
        $this->success = $success;
    }

    /**
     * @return string
     */
    public function getSuccess(): string {
        return $this->success;
    }

    /**
     * @param string $checksum
     */
    public function setChecksum( string $checksum ) {
        $this->checksum = $checksum;
    }

    /**
     * @return string
     */
    public function getChecksum(): string {
        return $this->checksum;
    }

    /**
     * @param int $license_limit
     */
    public function setLicenseLimit( int $license_limit ) {
        $this->license_limit = $license_limit;
    }

    /**
     * @return int
     */
    public function getLicenseLimit(): int {
        return $this->license_limit;
    }

    /**
     * @param int $site_count
     */
    public function setSiteCount( int $site_count ) {
        $this->site_count = $site_count;
    }

    /**
     * @return int
     */
    public function getSiteCount(): int {
        return $this->site_count;
    }

    /**
     * @param int $activations_left
     */
    public function setActivationsLeft( int $activations_left ) {
        $this->activations_left = $activations_left;
    }

    /**
     * @return int
     */
    public function getActivationsLeft(): int {
        return $this->activations_left;
    }

    /**
     * @param mixed $price_id
     */
    public function setPriceId( $price_id ) {
        $this->price_id = $price_id;
    }

    /**
     * @return mixed
     */
    public function getPriceId() {
        return $this->price_id;
    }
}
