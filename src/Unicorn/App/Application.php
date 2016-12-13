<?php
/**
 * Created by PhpStorm.
 * User: Andrew Breksa
 * Date: 12/12/2016
 * Time: 12:40 PM
 */

namespace Unicorn\App;


use League\Container\Container;
use League\Container\ReflectionContainer;
use League\Event\Emitter;
use League\Route\Http\Exception;
use League\Route\Http\Exception\NotFoundException;
use League\Route\RouteCollection;

class Application {
	const VERSION = '0';
	const EVENT_BOOTSTRAP = 'app.bootstrap';
	const EVENT_DISPATCH = 'app.delegate';
	const EVENT_RENDER = 'app.render';
	const EVENT_FINISH = 'app.finish';
	const EVENT_ROUTE_EXCEPTION = 'app.route.exception';
	const EVENT_DISPATCH_EXCEPTION = 'app.dispatch.exception';
	/**
	 * @var \League\Event\Emitter
	 */
	protected $eventEmitter;
	/**
	 * @var \League\Container\Container
	 */
	protected $container;
	/**
	 * @var array
	 */
	protected $config = [];

	/**
	 * @var RouteCollection
	 */
	protected $routeCollection;

	public function __construct() {
		$this->eventEmitter = new Emitter();
		$this->container = new Container();
		$this->getContainer()->delegate(
			new ReflectionContainer
		);
		$this->routeCollection = new RouteCollection($this->getContainer());
		foreach (glob(__DIR__ . '/../../../config/autoload/*.php') as $file) {
			$this->config = array_merge($this->config, include($file));
		}
		foreach (glob(__DIR__ . '/../../../config/autoload/*.json') as $file) {
			$this->config = array_merge($this->config, json_decode(file_get_contents($file), TRUE));
		}
		$this->getContainer()->share(Application::class, $this);
		$this->bootstrap();
	}

	/**
	 * @return \League\Container\Container
	 */
	public function getContainer() {
		return $this->container;
	}

	public function bootstrap() {
		$this->eventEmitter->emit(self::EVENT_BOOTSTRAP, $this);
		$this->getContainer()->share('response', \Zend\Diactoros\Response::class);
		$this->getContainer()->share('request', function () {
			return \Zend\Diactoros\ServerRequestFactory::fromGlobals(
				$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
			);
		});
		$this->getContainer()->share('emitter', \Zend\Diactoros\Response\SapiEmitter::class);
		foreach ($this->config['services'] as $serviceName => $callback) {
			$this->getContainer()->share($serviceName, $callback);
		}
	}

	public function run() {
		$this->bootstrap();
		$this->dispatch();
		$this->render();
		$this->finish();
	}

	private function dispatch() {
		$this->eventEmitter->emit(self::EVENT_DISPATCH, $this);
		try {
			$this->getRouteCollection()->dispatch($this->getContainer()->get('request'), $this->getContainer()->get('response'));
		} catch (NotFoundException $exception) {
			$this->getEventEmitter()->emit(self::EVENT_ROUTE_EXCEPTION, $this, $exception);
		} catch (\Exception $exception) {
			$this->getEventEmitter()->emit(self::EVENT_DISPATCH_EXCEPTION, $this, $exception);
		}
	}

	/**
	 * @return RouteCollection
	 */
	public function getRouteCollection() {
		return $this->routeCollection;
	}

	/**
	 * @return \League\Event\Emitter
	 */
	public function getEventEmitter() {
		return $this->eventEmitter;
	}

	private function render() {
		$this->getEventEmitter()->emit(self::EVENT_RENDER, $this);
	}

	private function finish() {
		$this->getEventEmitter()->emit(self::EVENT_FINISH, $this);
		$this->getContainer()->get('emitter')->emit($this->getContainer()->get('response'));
	}

	/**
	 * @return array
	 */
	public function getConfig() {
		return $this->config;
	}
}