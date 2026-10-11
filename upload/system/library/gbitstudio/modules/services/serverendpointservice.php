<?php

namespace Gbitstudio\Modules\Services;

class ServerEndpointService
{
    private $config;
    public function __construct($config)
    {
        $this->config = $config;
    }
    public function build($base, $operation, $code = '')
    {
        $type = $this->config->get('module_gdt_updater_server_type') ?: 'laravel';
        if ($type === 'laravel') {
            $path = $operation === 'download' ? rawurlencode($code) . '/download' : $operation;
            return rtrim($base, '/') . '/api/v1/modules/' . $path;
        }
        if ($type !== 'opencart') {
            throw new \RuntimeException('Unknown update server type');
        }
        $parts = parse_url(html_entity_decode($base, ENT_QUOTES, 'UTF-8'));
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http','https'], true) || empty($parts['host'])) {
            throw new \RuntimeException('Enter the OpenCart catalog URL');
        }
        $path = $parts['path'] ?? '/';
        if (substr($path, -9) !== 'index.php') {
            $path = rtrim($path, '/') . '/index.php';
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $route = $operation === 'list' ? 'modules' : ($operation === 'check-batch' ? 'checkBatch' : $operation);
        if (!in_array($route, ['modules','check','checkBatch','download','upload'], true)) {
            throw new \RuntimeException('Unknown update operation');
        }
        $query['route'] = 'gdt_update_server/api/' . $route;
        if ($code !== '') {
            $query['code'] = $code;
        }
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . $path . '?' . http_build_query($query);
    }
}
