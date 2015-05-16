<?php
namespace ZRay;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\EventManager;

class CakePHP
{
    protected $zre;
    protected $views = [];

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
        $this->setupEvents();
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
        $this->collectEnv($storage);
        $this->collectViews($storage);
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
                'subject' => get_class($event->subject()),
            ];
        }
        $data['memory'] = $this->formatSizeUnits(memory_get_usage(true));
        $data['time'] = $this->formatTime($context['durationInclusive']) . 'ms';
        $storage['events'][] = $data;
    }

    protected function formatSizeUnits($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format ( $bytes / 1073741824, 2 ) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format ( $bytes / 1048576, 2 ) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format ( $bytes / 1024, 2 ) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }

    protected function formatTime($ms) {
        return floor($ms / 1000);
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
        $storage['configure'][] = Configure::read();
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

    protected function collectEnv(&$storage)
    {
        $storage['Env'][] = ['name' => 'Application Path', 'value' => APP];
        $storage['Env'][] = ['name' => 'Config Path', 'value' => CONFIG];
        $storage['Env'][] = ['name' => 'Temp Path', 'value' => TMP];
        $storage['Env'][] = ['name' => 'Logs Path', 'value' => LOGS];
        $storage['Env'][] = ['name' => 'Cake Version', 'value' => Configure::version()];
    }

    /**
     * For some reason zray won't trace View::_render() without
     * corrupting the rest of the data, so we'll use cake's event system
     * instead.
     */
    public function setupEvents()
    {
        $events = EventManager::instance();
        $events->on('View.beforeRenderFile', function ($event) {
            $this->views[] = $event->data[0];
        });
    }

    public function collectViews(&$storage)
    {
        $storage['Views'][] = $this->views;
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
$zre->traceFunction(
    'Cake\Event\EventManager::dispatch',
    function () {},
    array($zrayCake, 'afterEvent')
);
