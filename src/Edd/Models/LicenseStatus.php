<?php

declare(strict_types=1);

namespace Dwnload\EddSoftwareLicenseManager\Edd\Models;

use TheFrosty\WpUtilities\Models\BaseModel;

/**
 * Class LicenseStatus
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd\Models
 */
abstract class LicenseStatus extends BaseModel
{

    public const LICENSE_ACTIVE = 'active';
    public const LICENSE_ACTIVATE = 'activate';
    public const LICENSE_DISABLED = 'disabled';
    public const LICENSE_DEACTIVATE = 'deactivate';
    public const LICENSE_DEACTIVATED = 'deactivated';
    public const LICENSE_EXPIRED = 'expired';
    public const LICENSE_FAILED = 'inactive';
    public const LICENSE_INACTIVE = 'inactive';
    public const LICENSE_INVALID = 'invalid';
    public const LICENSE_CHECK_LICENSE = 'check_license';
    public const LICENSE_SITE_INACTIVE = 'site_inactive';

    /** @var  string $license */
    private string $license = '';

    /** @var  string $item_name */
    private string $item_name = '';

    /** @var  string $expires */
    private string $expires = '';

    /** @var  int $payment_id */
    private int $payment_id = 0;

    /** @var  string $customer_name */
    private string $customer_name = '';

    /** @var  string $customer_email */
    private string $customer_email = '';

    /**
     * Is this a valid call?
     * $this->license will be either "active" or "inactive".
     * $this->license will be either "deactivated" or "failed".
     *
     * @return bool
     */
    public function isValidResponse(): bool
    {
        return !empty($this->license);
    }

    /**
     * @param string $licence
     */
    protected function setLicense(string $licence): void
    {
        $this->license = trim($licence);
    }

    /**
     * @return string
     */
    public function getLicense(): string
    {
        return $this->license;
    }

    /**
     * @param string $item_name
     */
    protected function setItemName(string $item_name): void
    {
        $this->item_name = $item_name;
    }

    /**
     * @return string
     */
    public function getItemName(): string
    {
        return $this->item_name;
    }

    /**
     * @param string $expires
     */
    protected function setExpires(string $expires): void
    {
        $this->expires = $expires;
    }

    /**
     * @return string
     */
    public function getExpires(): string
    {
        return $this->expires;
    }

    /**
     * @param int $payment_id
     */
    protected function setPaymentId(int $payment_id): void
    {
        $this->payment_id = $payment_id;
    }

    /**
     * @return int
     */
    public function getPaymentId(): int
    {
        return $this->payment_id;
    }

    /**
     * @param string $customer_name
     */
    protected function setCustomerName(string $customer_name): void
    {
        $this->customer_name = $customer_name;
    }

    /**
     * @return string
     */
    public function getCustomerName(): string
    {
        return $this->customer_name;
    }

    /**
     * @param string $customer_email
     */
    public function setCustomerEmail(string $customer_email): void
    {
        $this->customer_email = $customer_email;
    }

    /**
     * @return string
     */
    protected function getCustomerEmail(): string
    {
        return $this->customer_email;
    }
}
