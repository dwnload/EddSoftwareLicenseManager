<?php

namespace Dwnload\EddSoftwareLicenseManager\Edd\Models;

use TheFrosty\WpUtilities\Models\BaseModel;

/**
 * Class PluginData
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class PluginData extends BaseModel {

    /** @var string $api_url */
    private $api_url;

    /** @var array $api_data */
    private $api_data = [];

    /** @var bool $beta */
    private $beta = false;

    /** @var int $item_id */
    private $item_id;

    /** @var string $license */
    private $license;

    /** @var string $name */
    private $name;

    /** @var string $plugin_file */
    private $plugin_file;

    /** @var string $slug */
    private $slug;

    /** @var string $version */
    private $version;

    /** @var bool $wp_override */
    private $wp_override = false;

    /** @var string $cache_key */
    private $cache_key;

    /**
     * @param string $url
     */
    public function setApiUrl( string $url ) {
        $this->api_url = trailingslashit( $url );
    }

    /**
     * @return string
     */
    public function getApiUrl(): string {
        return $this->api_url;
    }

    /**
     * @param array $data
     */
    public function setApiData( array $data ) {
        $this->api_data = $data;
    }

    /**
     * @return array
     */
    public function getApiData(): array {
        return $this->api_data;
    }

    /**
     * @param bool $allow_beta
     */
    public function setBeta( bool $allow_beta ) {
        $this->beta = $allow_beta;
    }

    /**
     * @return bool
     */
    public function getBeta(): bool {
        return $this->beta;
    }

    /**
     * @param int $item_id
     */
    public function setItemId( int $item_id ) {
        $this->item_id = $item_id;
    }

    /**
     * @return int
     */
    public function getItemId(): int {
        return $this->item_id;
    }

    /**
     * @param string $name
     */
    public function setName( string $name ) {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $license
     */
    public function setLicense( string $license ) {
        $this->license = $license;
    }

    /**
     * @return string
     */
    public function getLicense(): string {
        return $this->license;
    }

    /**
     * @param string $file
     */
    public function setPluginFile( string $file ) {
        $this->plugin_file = $file;
    }

    /**
     * @return string
     */
    public function getPluginFile(): string {
        return $this->plugin_file;
    }

    /**
     * @param string $slug
     */
    public function setSlug( string $slug ) {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getSlug(): string {
        return $this->slug;
    }

    /**
     * @param string $version
     */
    public function setVersion( string $version ) {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion(): string {
        return $this->version;
    }

    /**
     * @param bool $wp_override
     */
    public function setWpOverride( bool $wp_override ) {
        $this->wp_override = $wp_override;
    }

    /**
     * @return bool
     */
    public function getWpOverride(): bool {
        return $this->wp_override;
    }

    /**
     * @param string $cache_key
     */
    public function setCacheKey( string $cache_key ) {
        $this->cache_key = $cache_key;
    }

    /**
     * @return string
     */
    public function getCacheKey(): string {
        return md5( serialize( $this->slug . $this->license . $this->beta ) );
    }
}
