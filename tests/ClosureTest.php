<?php
require_once(__DIR__ . '/../vendor/autoload.php');

/**
 * Copyright (c) 2016 Andrew Breksa
 */
class AppTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \AndrewBreksa\Unicorn\App\Application
     */
    public static $app = null;

    public function setUp()
    {
        if (!is_dir(__DIR__ . '/config/autoload')) {
            mkdir(__DIR__ . '/config/autoload', 0777, true);
        }
        file_put_contents(__DIR__ . '/config/autoload/settings.json', json_encode([
            'settings' => [
                'site' => [
                    'version' => \AndrewBreksa\Unicorn\App\Application::VERSION,
                    'title'   => 'test application',
                ],
            ],
        ]));
        $tmpApp = new \AndrewBreksa\Unicorn\App\Application(__DIR__);

        $tmpApp->bootstrap();
        $tmpApp->setEmit(false);
        $tmpApp->setRender(true);
        $tmpApp->getEventEmitter()->addListener(\AndrewBreksa\Unicorn\App\Application::EVENT_DISPATCH_EXCEPTION, function (\League\Event\Event $event, \AndrewBreksa\Unicorn\App\Application $application, \Exception $exception) {
            $response = $application->getResponse();
            $response = $response->withStatus(500);
            $response = $response->withBody(\AndrewBreksa\Unicorn\App\Application::newTempStream(json_encode([
                'exception' => $exception->getMessage(),
            ])));
            $application->setResponse($response);
        });

        $tmpApp->getEventEmitter()->addListener(\AndrewBreksa\Unicorn\App\Application::EVENT_ROUTE_EXCEPTION, function (\League\Event\Event $event, \AndrewBreksa\Unicorn\App\Application $application, \Exception $exception) {
            $response = $application->getResponse();
            $response = $response->withStatus(404);
            $response = $response->withBody(\AndrewBreksa\Unicorn\App\Application::newTempStream(json_encode([
                'exception' => $exception->getMessage(),
            ])));
            $application->setResponse($response);
        });

        $tmpApp->getEventEmitter()->addListener(\AndrewBreksa\Unicorn\App\Application::EVENT_RENDER, function (\League\Event\Event $event, \AndrewBreksa\Unicorn\App\Application $application) use ($tmpApp) {
            $response = $tmpApp->getResponse();
            $response->getBody()->rewind();
            $existing = json_decode($response->getBody()->getContents(), true);
            $data = [
                'title'   => $tmpApp->getConfig()['settings']['site']['title'],
                'version' => $tmpApp->getConfig()['settings']['site']['version'],
            ];
            if (!empty($response)) {
                $data = array_merge($data, $existing);
            }
            $response = $response->withBody(\AndrewBreksa\Unicorn\App\Application::newTempStream(json_encode($data)));
            $tmpApp->setResponse($response);
        });

        $tmpApp->getRouteCollection()->map('GET', '/', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($tmpApp) {
            $response = $response->withStatus(200);
            $response = $response->withBody(\AndrewBreksa\Unicorn\App\Application::newTempStream(json_encode([
                'page' => 'index',
            ])));
            return $response;
        });

        $tmpApp->getRouteCollection()->map('GET', '/exception', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($tmpApp) {
            throw new Exception('demo exception');
        });
        self::$app = $tmpApp;
    }

    public function testJsonAutoload()
    {
        self::assertArrayHasKey('settings', self::$app->getConfig(), 'json config autoloading not functioning');
    }

    public function testApplicationInContainer()
    {
        $request = (new Zend\Diactoros\ServerRequest())
            ->withUri(new Zend\Diactoros\Uri('http://example.com/'))
            ->withMethod('GET');
        $app = new AndrewBreksa\Unicorn\App\Application();
        $app->bootstrap();
        $app->setRequest($request);
        self::assertTrue($app->getContainer()->has(AndrewBreksa\Unicorn\App\Application::class), 'Application object not available via the container');
        $app2 = $app->getContainer()->get(AndrewBreksa\Unicorn\App\Application::class);
        self::assertInstanceOf(\AndrewBreksa\Unicorn\App\Application::class, $app2, 'Application is not an Unicron\\App\\Application');
    }

    public function testIndex()
    {
        $request = (new Zend\Diactoros\ServerRequest())
            ->withUri(new Zend\Diactoros\Uri('http://example.com/'))
            ->withMethod('GET');
        self::$app->setRequest($request);
        self::$app->run();
        self::$app->getResponse()->getBody()->rewind();
        $bodyData = json_decode(self::$app->getResponse()->getBody()->getContents(), true);
        self::assertArrayHasKey('page', $bodyData, 'response does not have the page key');
        self::assertEquals('index', $bodyData['page'], 'response does not have the correct page data set');
        self::assertArrayHasKey('version', $bodyData, 'response does not have the version key');
        self::assertEquals(\AndrewBreksa\Unicorn\App\Application::VERSION, $bodyData['version'], 'response does not have the proper version');
    }

    public function test404()
    {
        $request = (new Zend\Diactoros\ServerRequest())
            ->withUri(new Zend\Diactoros\Uri('http://example.com/404'))
            ->withMethod('GET');
        self::$app->setRequest($request);
        self::$app->run();
        self::$app->getResponse()->getBody()->rewind();
        $bodyData = json_decode(self::$app->getResponse()->getBody()->getContents(), true);
        self::assertEquals(404, self::$app->getResponse()->getStatusCode(), '404 not returned on a 404 request');
        self::assertArrayHasKey('version', $bodyData, 'response does not have the version key');
        self::assertEquals(\AndrewBreksa\Unicorn\App\Application::VERSION, $bodyData['version'], 'response does not have the proper version');
    }

    public function test500()
    {
        $request = (new Zend\Diactoros\ServerRequest())
            ->withUri(new Zend\Diactoros\Uri('http://example.com/exception'))
            ->withMethod('GET');
        self::$app->setRequest($request);
        self::$app->run();
        self::$app->getResponse()->getBody()->rewind();
        $bodyData = json_decode(self::$app->getResponse()->getBody()->getContents(), true);
        self::assertEquals(500, self::$app->getResponse()->getStatusCode(), '500 not returned on a exception request');
        self::assertArrayHasKey('version', $bodyData, 'response does not have the version key');
        self::assertEquals(\AndrewBreksa\Unicorn\App\Application::VERSION, $bodyData['version'], 'response does not have the proper version');
    }

    public function tearDown()
    {
        if (is_dir(__DIR__ . '/config/autoload')) {
            unlink(__DIR__ . '/config/autoload/settings.json');
            rmdir(__DIR__ . '/config/autoload');
            rmdir(__DIR__ . '/config');
        }
        self::$app = null;
    }
}
