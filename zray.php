<?php
namespace ZRay;

use Cake\Core\Configure;
use Cake\Core\Plugin;

class CakePHP
{
    protected $zre;

    public function setZRE($zre)
    {
        $this->zre = $zre;
    }

    public function beforeRun($context, &$storage)
    {
        $this->collectPlugins($storage);
        $this->collectConfigureData($storage);
    }

    public function afterRun($context, &$storage)
    {
        $request = $context['functionArgs'][0];
        $response = $context['functionArgs'][1];
        $this->collectRequest($request, $storage);
        $this->collectResponse($response, $storage);
    }

    protected function collectPlugins(&$storage)
    {
        $storage['plugins'] = Plugin::loaded();
    }

    /**
     * Collect data from Cake\Core\Configure;
     */
    protected function collectConfigureData(&$storage)
    {
        $data = Configure::read();
        $storage['configure'] = $data;
    }

    protected function collectRequest($request, &$storage)
    {
        $storage['request'][] = [
            'plugin' => $request->param('plugin'),
            'controller' => $request->param('controller'),
            'action' => $request->param('action'),
            'content type' => $request->contentType(),
        ];
    }

    protected function collectResponse($response, &$storage)
    {
        $storage['request'][] = [
            'status' => $response->statusCode(),
            'headers' => $response->header(),
            'content type' => $response->type(),
            'contents' => $response->body(),
        ];
    }
}

$zre = new \ZRayExtension("CakePHP");
$zrayCake = new CakePHP();
$zrayCake->setZRE($zre);
$zre->setMetadata(array(
    'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
));

$zre->setEnabledAfter('Cake\Routing\DispatcherFactory::create');
$zre->traceFunction(
    'Cake\Routing\Dispatcher::dispatch',
    array($zrayCake, 'beforeRun'),
    array($zrayCake, 'afterRun')
);
