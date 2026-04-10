<?php

namespace Puc\v5p6;

if (!defined('ABSPATH')) {
    exit;
}

class UpdateChecker
{
    private string $metadataUrl;
    private string $pluginFile;
    private string $slug;
    private string $branch = 'main';
    private string $authentication = '';

    public function __construct(string $metadataUrl, string $pluginFile, string $slug)
    {
        $this->metadataUrl = $metadataUrl;
        $this->pluginFile = $pluginFile;
        $this->slug = $slug;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdateInfo']);
        add_filter('plugins_api', [$this, 'injectPluginInfo'], 20, 3);
    }

    public function setBranch(string $branch): void
    {
        $this->branch = $branch;
    }

    public function setAuthentication(string $token): void
    {
        $this->authentication = trim($token);
    }

    public function injectUpdateInfo($transient)
    {
        if (!is_object($transient) || !isset($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $pluginBasename = plugin_basename($this->pluginFile);
        $currentVersion = $transient->checked[$pluginBasename] ?? null;
        if (!is_string($currentVersion)) {
            return $transient;
        }

        $release = $this->getLatestRelease();
        if ($release === null) {
            return $transient;
        }

        $newVersion = ltrim((string)($release['tag_name'] ?? ''), 'v');
        if ($newVersion === '' || !version_compare($newVersion, $currentVersion, '>')) {
            return $transient;
        }

        $assetUrl = $this->findZipAssetUrl($release);
        if ($assetUrl === '') {
            return $transient;
        }

        $update = (object) [
            'slug' => $this->slug,
            'plugin' => $pluginBasename,
            'new_version' => $newVersion,
            'url' => $this->metadataUrl,
            'package' => $assetUrl,
        ];

        $transient->response[$pluginBasename] = $update;

        return $transient;
    }

    public function injectPluginInfo($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->getLatestRelease();
        if ($release === null) {
            return $result;
        }

        $version = ltrim((string)($release['tag_name'] ?? ''), 'v');
        $sections = [
            'description' => wp_kses_post((string) ($release['body'] ?? 'Release information unavailable.')),
            'changelog' => wp_kses_post((string) ($release['body'] ?? 'Release information unavailable.')),
        ];

        return (object) [
            'name' => 'BlueWorx Enhancements',
            'slug' => $this->slug,
            'version' => $version,
            'author' => '<a href="https://github.com/' . esc_attr($this->getRepositoryPath()) . '">BlueWorx</a>',
            'homepage' => $this->metadataUrl,
            'sections' => $sections,
            'download_link' => $this->findZipAssetUrl($release),
        ];
    }

    private function getRepositoryPath(): string
    {
        return trim(parse_url($this->metadataUrl, PHP_URL_PATH) ?? '', '/');
    }

    private function getLatestRelease(): ?array
    {
        $repoPath = $this->getRepositoryPath();
        if ($repoPath === '') {
            return null;
        }

        $requestOptions = [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
            ],
            'timeout' => 15,
        ];

        if ($this->authentication !== '') {
            $requestOptions['headers']['Authorization'] = 'Bearer ' . $this->authentication;
        }

        $requestOptions = apply_filters('puc_request_info_options-' . $this->slug, $requestOptions);

        $releaseEndpoint = sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            $repoPath
        );

        $response = wp_remote_get($releaseEndpoint, $requestOptions);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function findZipAssetUrl(array $release): string
    {
        if (empty($release['assets']) || !is_array($release['assets'])) {
            return '';
        }

        foreach ($release['assets'] as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');
            $url = (string) ($asset['browser_download_url'] ?? '');
            if (str_ends_with(strtolower($name), '.zip') && $url !== '') {
                return $url;
            }
        }

        return '';
    }
}

class PucFactory
{
    public static function buildUpdateChecker(string $metadataUrl, string $pluginFile, string $slug): UpdateChecker
    {
        return new UpdateChecker($metadataUrl, $pluginFile, $slug);
    }
}
