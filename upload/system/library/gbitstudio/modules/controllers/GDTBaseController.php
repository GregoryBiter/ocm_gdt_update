<?php

namespace Gbitstudio\Modules\Controllers;

use Controller;
use Exception;
abstract class GDTBaseController extends Controller
{

    /**
     * Get translated text from language.
     * Supports dot-notation for package/route prefix (e.g. 'extension/module/gdt_updater.text_success').
     *
     * @param string $key
     * @param array $replace
     * @return string
     */
    protected function trans(string $key, array $replace = []): string
    {
        $text = $this->language->get($key);

        if ($text === $key && strpos($key, '.') !== false) {
            $parts = explode('.', $key, 2);
            $this->load->language($parts[0]);
            $text = $this->language->get($parts[1]);
        }

        if (!empty($replace)) {
            $text = vsprintf($text, $replace);
        }

        return $text ?: $key;
    }

    /**
     * Alias for trans()
     *
     * @param string $key
     * @param array $replace
     * @return string
     */
    protected function translate(string $key, array $replace = []): string
    {
        return $this->trans($key, $replace);
    }

    /**
     * Controller-level translation helper
     *
     * @param string $key
     * @param array $replace
     * @return string
     */
    public function __(string $key, array $replace = []): string
    {
        return $this->trans($key, $replace);
    }

    /**
     * Send a JSON response.
     *
     * @param array $data
     */
    protected function jsonResponse(array $data)
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $status
     */
    protected function redirect(string $url, int $status = 302)
    {
        $this->response->redirect($url, $status);
    }

    /**
     * Render view template and send to response
     *
     * @param string $template
     * @param array $data
     */
    protected function render(string $template, array $data = [])
    {
        $this->response->setOutput($this->load->view($template, $data));
    }

    /**
     * Safely get a value from the POST request.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function getPostData(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->request->post;
        }
        return isset($this->request->post[$key]) ? $this->request->post[$key] : $default;
    }

    /**
     * Safely get a value from the GET request.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function getGetData(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->request->get;
        }
        return isset($this->request->get[$key]) ? $this->request->get[$key] : $default;
    }

    /**
     * Check if request method is POST.
     *
     * @return bool
     */
    protected function isPost(): bool
    {
        return isset($this->request->server['REQUEST_METHOD']) && strtoupper($this->request->server['REQUEST_METHOD']) === 'POST';
    }

    /**
     * Build an admin URL with current user_token automatically included.
     *
     * @param string $route
     * @param array $params
     * @param bool $secure
     * @return string
     */
    protected function buildUrl(string $route, array $params = [], bool $secure = true): string
    {
        $userToken = isset($this->session->data['user_token']) ? $this->session->data['user_token'] : '';
        $query = [];
        if ($userToken !== '') {
            $query['user_token'] = $userToken;
        }
        foreach ($params as $k => $v) {
            $query[$k] = $v;
        }
        $queryString = http_build_query($query);
        return $this->url->link($route, $queryString, $secure);
    }

    /**
     * Get OpenCart config value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        $val = $this->config->get($key);
        return $val !== null ? $val : $default;
    }

    /**
     * Load and retrieve an OpenCart model.
     *
     * @param string $modelRoute
     * @return mixed
     */
    protected function getModel(string $modelRoute)
    {
        $this->load->model($modelRoute);
        $modelProp = 'model_' . str_replace('/', '_', $modelRoute);
        return isset($this->{$modelProp}) ? $this->{$modelProp} : null;
    }

    /**
     * Check if the user has permission to modify the module.
     *
     * @param string $permission
     * @param string $route
     * @throws Exception
     */
    protected function validatePermission($permission = 'modify', $route = 'extension/module/gdt_updater')
    {
        if (!$this->user->hasPermission($permission, $route)) {
            throw new Exception($this->language->get('error_permission') ?: 'Permission denied.');
        }
    }

    /**
     * Handle exceptions and return a unified JSON error response.
     *
     * @param Exception $e
     */
    protected function handleException(Exception $e)
    {
        $this->jsonResponse(['error' => $e->getMessage()]);
    }
}
