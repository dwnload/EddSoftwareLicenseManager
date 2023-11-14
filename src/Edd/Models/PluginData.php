<?php

declare(strict_types=1);

namespace Dwnload\EddSoftwareLicenseManager\Edd\Models;

use TheFrosty\WpUtilities\Models\BaseModel;

/**
 * Class PluginData
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class PluginData extends BaseModel
{

    /** @var string $api_url */
    private string $api_url;

    /** @var array $api_data */
    private array $api_data = [];

    /** @var bool $beta */
    private bool $beta = false;

    /** @var int $item_id */
    private int $item_id;

    /** @var string $license */
    private string $license;

    /** @var string $name */
    private string $name;

    /** @var string $plugin_file */
    private string $plugin_file;

    /** @var string $slug */
    private string $slug;

    /** @var string $version */
    private string $version;

    /** @var bool $wp_override */
    private bool $wp_override = false;

    /** @var string $cache_key */
    private string $cache_key;

    /**
     * @param string $url
     */
    protected function setApiUrl(string $url): void
    {
        $this->api_url = trailingslashit($url);
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->api_url;
    }

    /**
     * @param array $data
     */
    protected function setApiData(array $data): void
    {
        $this->api_data = $data;
    }

    /**
     * @return array
     */
    public function getApiData(): array
    {
        return $this->api_data;
    }

    /**
     * @param bool $allow_beta
     */
    protected function setBeta(bool $allow_beta): void
    {
        $this->beta = $allow_beta;
    }

    /**
     * @return bool
     */
    public function getBeta(): bool
    {
        return $this->beta;
    }

    /**
     * @param int $item_id
     */
    protected function setItemId(int $item_id): void
    {
        $this->item_id = $item_id;
    }

    /**
     * @return int
     */
    public function getItemId(): int
    {
        return $this->item_id;
    }

    /**
     * @param string $name
     */
    protected function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $license
     */
    protected function setLicense(string $license): void
    {
        $this->license = $license;
    }

    /**
     * @return string
     */
    public function getLicense(): string
    {
        return $this->license;
    }

    /**
     * @param string $file
     */
    protected function setPluginFile(string $file): void
    {
        $this->plugin_file = $file;
    }

    /**
     * @return string
     */
    public function getPluginFile(): string
    {
        return $this->plugin_file;
    }

    /**
     * @param string $slug
     */
    protected function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $version
     */
    protected function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param bool $wp_override
     */
    protected function setWpOverride(bool $wp_override): void
    {
        $this->wp_override = $wp_override;
    }

    /**
     * @return bool
     */
    public function getWpOverride(): bool
    {
        return $this->wp_override;
    }

    /**
     * @param string $cache_key
     */
    protected function setCacheKey(string $cache_key): void
    {
        $this->cache_key = $cache_key;
    }

    /**
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->cache_key ?? md5(serialize($this->slug . $this->license . $this->beta));
    }
}
