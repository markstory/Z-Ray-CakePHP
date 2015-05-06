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

    /**
     * Capture data before a request is dispatched.
     */
    public function beforeRun($context, &$storage)
    {
        $this->collectPlugins($storage);
        $this->collectConfigureData($storage);
    }

    /**
     * Capture data after a request is dispatched.
     */
    public function afterRun($context, &$storage)
    {
        $request = $context['functionArgs'][0];
        $response = $context['functionArgs'][1];
        $this->collectRequest($request, $storage);
        $this->collectResponse($response, $storage);
    }

    /**
     * Capture data for each event triggered
     */
    public function afterEvent($context, &$storage)
    {
        $event = $context['functionArgs'][0];
        if (is_string($event)) {
            $data = [
                'name' => $event
            ];
        } else {
            $data = [
                'name' => $event->name(),
                'subject' => $event->subject(),
                'data' => $event->data(),
            ];
        }
        // $storage['events'][] = $event;
    }

    protected function collectPlugins(&$storage)
    {
        foreach (Plugin::loaded() as $plugin) {
            $storage['plugins'][] = [
                'name' => $plugin,
                'path' => Plugin::path($plugin),
                'class path' => Plugin::classPath($plugin),
                'config path' => Plugin::configPath($plugin),
            ];
        }
    }

    /**
     * Collect data from Cake\Core\Configure;
     */
    protected function collectConfigureData(&$storage)
    {
        $storage['configure'] = Configure::read();
    }

    protected function collectRequest($request, &$storage)
    {
        $storage['request']['Request'] = [
            'plugin' => $request->param('plugin'),
            'controller' => $request->param('controller'),
            'action' => $request->param('action'),
            'passed' => $request->param('pass'),
            'data' => $request->data,
            'content type' => $request->contentType(),
        ];
    }

    protected function collectResponse($response, &$storage)
    {
        $storage['request']['Response'] = [
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
    'Cake\Event\EventManager::dispatch',
    function () {},
    array($zrayCake, 'afterEvent')
);
$zre->traceFunction(
    'Cake\Routing\Dispatcher::dispatch',
    array($zrayCake, 'beforeRun'),
    array($zrayCake, 'afterRun')
);
