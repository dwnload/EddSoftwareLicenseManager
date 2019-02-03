<?php

namespace Dwnload\EddSoftwareLicenseManager\Edd\Models;

use TheFrosty\WpUtilities\Models\BaseModel;

/**
 * Class LicenseStatus
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd\Models
 */
abstract class LicenseStatus extends BaseModel {

    const LICENSE_ACTIVE = 'active';
    const LICENSE_ACTIVATE = 'activate';
    const LICENSE_DISABLED = 'disabled';
    const LICENSE_DEACTIVATE = 'deactivate';
    const LICENSE_DEACTIVATED = 'deactivated';
    const LICENSE_EXPIRED = 'expired';
    const LICENSE_FAILED = 'inactive';
    const LICENSE_INACTIVE = 'inactive';
    const LICENSE_INVALID = 'invalid';
    const LICENSE_CHECK_LICENSE = 'check_license';
    const LICENSE_SITE_INACTIVE = 'site_inactive';

    /** @var  string $license */
    private $license = '';

    /** @var  string $item_name */
    private $item_name = '';

    /** @var  string $expires */
    private $expires = '';

    /** @var  int $payment_id */
    private $payment_id = 0;

    /** @var  string $customer_name */
    private $customer_name = '';

    /** @var  string $customer_email */
    private $customer_email = '';

    /**
     * Is this a valid call?
     * $this->license will be either "active" or "inactive".
     * $this->license will be either "deactivated" or "failed".
     *
     * @return bool
     */
    public function isValidResponse(): bool {
        return ! empty( $this->license );
    }

    /**
     * @param string $licence
     */
    public function setLicense( string $licence ) {
        $this->license = trim( $licence );
    }

    /**
     * @return string
     */
    public function getLicense(): string {
        return $this->license;
    }

    /**
     * @param string $item_name
     */
    public function setItemName( string $item_name ) {
        $this->item_name = $item_name;
    }

    /**
     * @return string
     */
    public function getItemName(): string {
        return $this->item_name;
    }

    /**
     * @param string $expires
     */
    public function setExpires( string $expires ) {
        $this->expires = $expires;
    }

    /**
     * @return string
     */
    public function getExpires(): string {
        return $this->expires;
    }

    /**
     * @param int $payment_id
     */
    public function setPaymentId( int $payment_id ) {
        $this->payment_id = $payment_id;
    }

    /**
     * @return int
     */
    public function getPaymentId(): int {
        return $this->payment_id;
    }

    /**
     * @param string $customer_name
     */
    public function setCustomerName( string $customer_name ) {
        $this->customer_name = $customer_name;
    }

    /**
     * @return string
     */
    public function getCustomerName(): string {
        return $this->customer_name;
    }

    /**
     * @param string $customer_email
     */
    public function setCustomerEmail( string $customer_email ) {
        $this->customer_email = $customer_email;
    }

    /**
     * @return string
     */
    public function getCustomerEmail(): string {
        return $this->customer_email;
    }
}
