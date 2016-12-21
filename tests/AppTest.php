<?php
require_once(__DIR__ . '/../vendor/autoload.php');

/**
 * Copyright (c) 2016 Andrew Breksa
 */
class AppTest extends PHPUnit_Framework_TestCase {
	/**
	 * @var \AndrewBreksa\Unicorn\App\Application
	 */
	public static $app = NULL;

	public function setUp() {
		if (!is_dir(__DIR__ . '/config/autoload')) {
			mkdir(__DIR__ . '/config/autoload', 0777, TRUE);
		}
		file_put_contents(__DIR__ . '/config/autoload/settings.json', json_encode([
			'settings' => [
				'site' => [
					'version' => \AndrewBreksa\Unicorn\App\Application::VERSION,
					'title'   => 'test application',
				],
			],
		]));
		$tmpApp = \AndrewBreksa\Unicorn\App\Application::getInstance(__DIR__);

		$tmpApp->bootstrap();
		$tmpApp->setEmit(FALSE);
		$tmpApp->setRender(TRUE);
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
			$existing = json_decode($response->getBody()->getContents(), TRUE);
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

	public function testJsonAutoload() {
		self::assertArrayHasKey('settings', self::$app->getConfig(), 'json config autoloading not functioning');
	}

	public function testIndex() {
		$request = (new Zend\Diactoros\ServerRequest())
			->withUri(new Zend\Diactoros\Uri('http://example.com/'))
			->withMethod('GET');
		self::$app->setRequest($request);
		self::$app->run();
		self::$app->getResponse()->getBody()->rewind();
		$bodyData = json_decode(self::$app->getResponse()->getBody()->getContents(), TRUE);
		self::assertArrayHasKey('page', $bodyData, 'response does not have the page key');
		self::assertEquals('index', $bodyData['page'], 'response does not have the correct page data set');
		self::assertArrayHasKey('version', $bodyData, 'response does not have the version key');
		self::assertEquals(\AndrewBreksa\Unicorn\App\Application::VERSION, $bodyData['version'], 'response does not have the proper version');
	}

	public function test404() {
		$request = (new Zend\Diactoros\ServerRequest())
			->withUri(new Zend\Diactoros\Uri('http://example.com/404'))
			->withMethod('GET');
		self::$app->setRequest($request);
		self::$app->run();
		self::$app->getResponse()->getBody()->rewind();
		$bodyData = json_decode(self::$app->getResponse()->getBody()->getContents(), TRUE);
		self::assertEquals(404, self::$app->getResponse()->getStatusCode(), '404 not returned on a 404 request');
	}

	public function test500() {
		$request = (new Zend\Diactoros\ServerRequest())
			->withUri(new Zend\Diactoros\Uri('http://example.com/exception'))
			->withMethod('GET');
		self::$app->setRequest($request);
		self::$app->run();
		self::$app->getResponse()->getBody()->rewind();
		$bodyData = json_decode(self::$app->getResponse()->getBody()->getContents(), TRUE);
		self::assertEquals(500, self::$app->getResponse()->getStatusCode(), '500 not returned on a exception request');
		self::assertArrayHasKey('version', $bodyData, 'response does not have the version key');
		self::assertEquals(\AndrewBreksa\Unicorn\App\Application::VERSION, $bodyData['version'], 'response does not have the proper version');
	}

	public function tearDown() {
		if (is_dir(__DIR__ . '/config/autoload')) {
			unlink(__DIR__ . '/config/autoload/settings.json');
			rmdir(__DIR__ . '/config/autoload');
			rmdir(__DIR__ . '/config');
		}
		self::$app->destroy();
	}
}