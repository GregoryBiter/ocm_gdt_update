<?php

namespace Gbitstudio\Modules {
    /**
     * RegistryHolder keeps a static reference to the active OpenCart Registry
     */
    class RegistryHolder
    {
        private static $registry = null;

        public static function set(\Registry $registry): void
        {
            self::$registry = $registry;
        }

        public static function get(): ?\Registry
        {
            return self::$registry;
        }
    }
}

namespace {
    use Gbitstudio\Modules\RegistryHolder;

    if (!function_exists('oc_registry')) {
        function oc_registry(): ?\Registry
        {
            return RegistryHolder::get();
        }
    }

    if (!function_exists('config')) {
        /**
         * Get OpenCart config value
         *
         * @param string|null $key
         * @param mixed $default
         * @return mixed
         */
        function config($key = null, $default = null)
        {
            $reg = oc_registry();
            if (!$reg || !$reg->has('config')) {
                return $default;
            }
            if ($key === null) {
                return $reg->get('config');
            }
            $val = $reg->get('config')->get($key);
            return $val !== null ? $val : $default;
        }
    }

    if (!function_exists('route')) {
        /**
         * Generate OpenCart admin route URL
         *
         * @param string $route
         * @param array $params
         * @param bool $secure
         * @return string
         */
        function route(string $route, array $params = [], bool $secure = true): string
        {
            $reg = oc_registry();
            if (!$reg || !$reg->has('url')) {
                return $route;
            }

            $urlService = $reg->get('url');
            $session = $reg->has('session') ? $reg->get('session') : null;
            $userToken = ($session && isset($session->data['user_token'])) ? $session->data['user_token'] : '';

            $query = [];
            if ($userToken !== '') {
                $query['user_token'] = $userToken;
            }
            foreach ($params as $k => $v) {
                $query[$k] = $v;
            }

            $queryString = http_build_query($query);
            return $urlService->link($route, $queryString, $secure);
        }
    }

    if (!function_exists('response')) {
        class OCResponseProxy
        {
            /** @var \Registry|null */
            private $reg;

            public function __construct(?\Registry $reg)
            {
                $this->reg = $reg;
            }

            public function json(array $data, int $status = 200): array
            {
                if ($this->reg && $this->reg->has('response')) {
                    $response = $this->reg->get('response');
                    $response->addHeader('Content-Type: application/json');
                    $response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
                }
                return $data;
            }

            public function redirect(string $url, int $status = 302): void
            {
                if ($this->reg && $this->reg->has('response')) {
                    $this->reg->get('response')->redirect($url, $status);
                }
            }
        }

        /**
         * Helper for HTTP responses
         *
         * @param string|null $content
         * @return OCResponseProxy
         */
        function response(?string $content = null): OCResponseProxy
        {
            $reg = oc_registry();
            $proxy = new OCResponseProxy($reg);
            if ($content !== null && $reg && $reg->has('response')) {
                $reg->get('response')->setOutput($content);
            }
            return $proxy;
        }
    }

    if (!function_exists('view')) {
        /**
         * Render OpenCart view
         *
         * @param string $template
         * @param array $data
         * @return string
         */
        function view(string $template, array $data = []): string
        {
            $reg = oc_registry();
            if ($reg && $reg->has('load')) {
                return $reg->get('load')->view($template, $data);
            }
            return '';
        }
    }

    if (!function_exists('model')) {
        /**
         * Load and get an OpenCart model
         *
         * @param string $modelRoute
         * @return mixed
         */
        function model(string $modelRoute)
        {
            $reg = oc_registry();
            if (!$reg || !$reg->has('load')) {
                return null;
            }
            $reg->get('load')->model($modelRoute);
            $modelProp = 'model_' . str_replace('/', '_', $modelRoute);
            return $reg->has($modelProp) ? $reg->get($modelProp) : null;
        }
    }

    if (!function_exists('__')) {
        /**
         * OpenCart language translator helper
         *
         * @param string $key
         * @param array $replace
         * @return string
         */
        function __(string $key, array $replace = []): string
        {
            $reg = oc_registry();
            if (!$reg || !$reg->has('language')) {
                return $key;
            }

            $language = $reg->get('language');
            $text = $language->get($key);

            // If key contains dot and is not found, attempt loading package file
            if ($text === $key && strpos($key, '.') !== false) {
                $parts = explode('.', $key, 2);
                $language->load($parts[0]);
                $text = $language->get($parts[1]);
            }

            if (!empty($replace)) {
                $text = vsprintf($text, $replace);
            }

            return $text ?: $key;
        }
    }

    if (!function_exists('request')) {
        class OCRequestProxy
        {
            /** @var \Registry|null */
            private $reg;

            public function __construct(?\Registry $reg)
            {
                $this->reg = $reg;
            }

            public function isMethod(string $method): bool
            {
                if (!$this->reg || !$this->reg->has('request')) {
                    return false;
                }
                $server = $this->reg->get('request')->server;
                return strcasecmp($server['REQUEST_METHOD'] ?? 'GET', $method) === 0;
            }

            public function post(?string $key = null, $default = null)
            {
                if (!$this->reg || !$this->reg->has('request')) {
                    return $default;
                }
                $post = $this->reg->get('request')->post;
                if ($key === null) {
                    return $post;
                }
                return isset($post[$key]) ? $post[$key] : $default;
            }

            public function get(?string $key = null, $default = null)
            {
                if (!$this->reg || !$this->reg->has('request')) {
                    return $default;
                }
                $get = $this->reg->get('request')->get;
                if ($key === null) {
                    return $get;
                }
                return isset($get[$key]) ? $get[$key] : $default;
            }
        }

        /**
         * Helper for HTTP requests
         *
         * @return OCRequestProxy
         */
        function request(): OCRequestProxy
        {
            return new OCRequestProxy(oc_registry());
        }
    }
}
