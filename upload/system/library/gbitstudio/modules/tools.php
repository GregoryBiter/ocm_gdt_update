<?php
namespace GbitStudio\Updater;
class Tools
{

    protected $registry;
    public function __construct(\Registry $registry)
    {
        $this->registry = $registry;
    }
    public function getVersion()
    {
        return '1.0.0';
    }

    public function getLayout(array &$data)
    {
        $data['header'] = $this->registry->get('load')->controller('common/header');
        $data['column_left'] = $this->registry->get('load')->controller('common/column_left');
        $data['footer'] = $this->registry->get('load')->controller('common/footer');
    }

    public function json($data)
    {
        $this->registry->get('response')->addHeader('Content-Type: application/json');
        $this->registry->get('response')->setOutput(json_encode($data));
    }

    public function view($route, $data = array())
    {
        $this->registry->get('response')->setOutput(
            $this->registry->get('load')->view($route, $data)
        );
    }

    public function getPostIsConfig($key, $default = null)
    {
        if (isset($this->registry->get('request')->post[$key])) {
            return $this->registry->get('request')->post[$key];
        }
        return $this->registry->get('config')->get($key) ?: $default;
    }
}
