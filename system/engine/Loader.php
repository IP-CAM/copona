<?php

namespace Copona\System\Engine;

use Copona\System\Library\Extension\ExtensionManager;
use Copona\System\Library\Template\TemplateFactory;

class Loader
{
    /**
     * @var \Registry
     */
    protected $registry;

    public function __construct($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Load Controller
     *
     * @param $route
     * @param array $data
     * @return bool|mixed|null
     */
    public function controller($route, $data = [])
    {
        // Sanitize the call
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route);

        $output = null;

        // Trigger the pre events
        $result = $this->registry->get('event')->trigger('controller/' . $route . '/before', array(
          &$route,
          &$data,
          &$output
        ));

        if ($result) {
            return $result;
        }

        if (!$output) {
            $action = new Action($route);
            $output = $action->execute($this->registry, $data);
        }

        // Trigger the post events
        $this->registry->get('event')->trigger('controller/' . $route . '/after', array(
          &$route,
          &$data,
          &$output
        ));

        if ($output instanceof \RuntimeException) {
            return false;
        }

        return $output;
    }

    /**
     * Load model
     *
     * @param $route
     * @return mixed|null
     */
    public function model($route)
    {
        // Sanitize the call
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route);

        // Trigger the pre events
        $this->registry->get('event')->trigger('model/' . $route . '/before', array(
          &$route
        ));

        $model_name = 'model_' . str_replace(['/', '-', '.'], ['_', '', ''], (string)$route);

        if (!$this->registry->has($model_name)) {

            $extension_model = ExtensionManager::findModel($route);

            if ($extension_model) {
                $file = $extension_model;
            } else {
                $file = DIR_APPLICATION . 'model/' . $route . '.php';
            }

            if (is_file($file)) {

                include_once($file);

                $class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', $route);

                $model = new $class($this->registry);

                $this->registry->set($model_name, $model);

                return $model;

            } else {
                throw new \RuntimeException('Error: Could not load model ' . $route . '!');
            }
        }

        // Trigger the post events
        $this->registry->get('event')->trigger('model/' . $route . '/after', array(
          &$route
        ));
    }

    /**
     * Load views
     *
     * @param $route
     * @param array $data
     * @return string
     */
    public function view($route, $data = [])
    {
        // Sanitize the call
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route);

        $adapter = TemplateFactory::create($this->registry->get('config')->get('template_engine'));

        $extensions_support = $adapter->getExtensionsSupport();

        $extension_view = ExtensionManager::findView($route, $extensions_support);

        if ($extension_view) {
            $file = $extension_view;
        } else {

            // This is only here for compatibility with older extensions
            if (substr($route, -3) == 'tpl') {
                $route = substr($route, 0, -3);
            }

            if (APPLICATION != 'catalog') {

                foreach ($extensions_support as $ext) {
                    if (file_exists(DIR_TEMPLATE . $route . '.' . $ext)) {
                        $file = DIR_TEMPLATE . $route . '.' . $ext;
                        break;
                    }
                }

            } else {

                if (!\Config::get(\Config::get('config_theme') . '_status')) {
                    throw new \RuntimeException('Error: A theme has not been assigned to this store!');
                }

                if (\Config::get('config_theme') == 'theme_default') {
                    $theme = \Config::get('theme_default_directory');
                } else {
                    $theme = \Config::get('config_theme');
                }

                foreach ($extensions_support as $ext) {
                    if (is_file(DIR_TEMPLATE . $theme . '/template/' . $route . '.' . $ext)) {
                        $file = DIR_TEMPLATE . $theme . '/template/' . $route . '.' . $ext;
                        break;
                    } else {
                        $file = DIR_TEMPLATE . 'default/template/' . $route . '.' . $ext;
                        break;
                    }
                }
            }
        }

        return $adapter->render($file, $data);
    }

    /**
     * Load Library
     *
     * @deprecated
     * @param $route
     */
    public function library($route)
    {
        // Sanitize the call
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route);

        $file = DIR_SYSTEM . 'library/' . $route . '.php';
        $class = str_replace('/', '\\', $route);

        if (is_file($file)) {
            include_once($file);

            $this->registry->set(basename($route), new $class($this->registry));
        } else {
            throw new \RuntimeException('Error: Could not load library ' . $route . '!');
        }
    }

    /**
     * Load helper
     *
     * @param $route
     */
    public function helper($route)
    {
        $file = DIR_SYSTEM . 'helper/' . preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route) . '.php';

        if (is_file($file)) {
            include_once($file);
        } else {
            throw new \RuntimeException('Error: Could not load helper ' . $route . '!');
        }
    }

    /**
     * Load language
     *
     * @param $route
     * @return null
     */
    public function language($route)
    {
        $output = null;

        $this->registry->get('event')->trigger('language/' . $route . '/before', array(
          &$route,
          &$output
        ));

        $output = $this->registry->get('language')->load($route);

        $this->registry->get('event')->trigger('language/' . $route . '/after', array(
          &$route,
          &$output
        ));

        return $output;
    }
}