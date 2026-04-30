<?php

namespace Gbitstudio\Modules\Controllers;

use Controller;
use Exception;

abstract class GDTBaseController extends Controller
{
    /**
     * Send a JSON response.
     *
     * @param array $data
     */
    protected function jsonResponse(array $data)
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
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
     * Safely get a value from the POST request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getPostData($key, $default = null)
    {
        return isset($this->request->post[$key]) ? $this->request->post[$key] : $default;
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
