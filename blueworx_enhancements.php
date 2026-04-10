<?php
/**
 * Plugin Name: BlueWorx Enhancements
 * Description: BlueWorx custom enhancements with GitHub-backed private update support.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: BlueWorx
 * License: GPL-2.0-or-later
 * Text Domain: blueworx-enhancements
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BWX_ENHANCEMENTS_VERSION', '1.0.0');
define('BWX_GITHUB_REPOSITORY', 'your-org/blueworx_enhancements');
define('BWX_GITHUB_BRANCH', 'main');

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

/**
 * Retrieve GitHub token from constant, environment, or filter.
 */
function bwx_get_github_token(): string
{
    $token = '';

    if (defined('BWX_GITHUB_TOKEN') && BWX_GITHUB_TOKEN) {
        $token = (string) BWX_GITHUB_TOKEN;
    }

    if ($token === '') {
        $env = getenv('BWX_GITHUB_TOKEN');
        if (is_string($env) && $env !== '') {
            $token = $env;
        }
    }

    /**
     * Filter for custom secure token loading.
     *
     * @param string $token GitHub personal access token.
     */
    return (string) apply_filters('bwx_github_update_token', $token);
}

$updateChecker = Puc\v5p6\PucFactory::buildUpdateChecker(
    sprintf('https://github.com/%s/', BWX_GITHUB_REPOSITORY),
    __FILE__,
    'blueworx-enhancements'
);

$updateChecker->setBranch(BWX_GITHUB_BRANCH);

$githubToken = bwx_get_github_token();
if ($githubToken !== '') {
    $updateChecker->setAuthentication($githubToken);
}

/**
 * Ensure GitHub API requests include repository and branch overrides.
 */
add_filter('puc_request_info_options-blueworx-enhancements', static function (array $requestOptions): array {
    $requestOptions['headers']['Accept'] = 'application/vnd.github+json';
    return $requestOptions;
});
