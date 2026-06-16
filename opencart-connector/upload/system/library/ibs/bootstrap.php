<?php

/**
 * Instantiate IBS API libraries without relying on Loader registry keys.
 * OpenCart 2.x only includes library files; OC3 registers basename($route) only.
 */
if (!function_exists('ibs_sync_api_services')) {
    function ibs_sync_api_services($registry): array
    {
        require_once DIR_SYSTEM . 'library/ibs/connector_version.php';
        require_once DIR_SYSTEM . 'library/ibs/connector_build.php';
        require_once DIR_SYSTEM . 'library/ibs/api_auth.php';
        require_once DIR_SYSTEM . 'library/ibs/api_response.php';

        return [
            new \ibs\api_auth($registry),
            new \ibs\api_response($registry),
        ];
    }
}
