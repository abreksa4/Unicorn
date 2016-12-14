<?php
/**
 * Copyright (c) 2016 Andrew Breksa
 */

namespace Unicorn\App;


use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use League\Container\Container;
use League\Container\ReflectionContainer;
use League\Event\Emitter;
use League\Route\Http\Exception\NotFoundException;
use League\Route\RouteCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;

class Application implements ContainerInterface {
	const VERSION = '0';
	const EVENT_BOOTSTRAP = 'app.bootstrap';
	const EVENT_DISPATCH = 'app.delegate';
	const EVENT_ROUTE_EXCEPTION = 'app.route.exception';
	const EVENT_DISPATCH_EXCEPTION = 'app.dispatch.exception';
	const EVENT_RENDER = 'app.render';
	const EVENT_FINISH = 'app.finish';
	/**
	 * @var Application
	 */
	private static $instance = NULL;
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
	/**
	 * @var ResponseInterface
	 */
	protected $response;
	/**
	 * @var ServerRequestInterface
	 */
	protected $request;

	/**
	 * Application constructor.
	 */
	private function __construct() {
		$this->eventEmitter = new Emitter();
		$this->container = new Container();
		$this->getContainer()->delegate(
			new ReflectionContainer
		);
		$this->getContainer()->delegate($this);
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

	private function bootstrap() {
		$this->eventEmitter->emit(self::EVENT_BOOTSTRAP, $this);
		$this->request = \Zend\Diactoros\ServerRequestFactory::fromGlobals(
			$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
		);
		$this->response = new Response();
		$this->getContainer()->share('emitter', \Zend\Diactoros\Response\SapiEmitter::class);
	}

	/**
	 * @return Application
	 */
	public static function getInstance() {
		if (Application::$instance == NULL) {
			Application::$instance = new Application();
		}
		return Application::$instance;
	}

	public function run() {
		$this->eventEmitter->emit(self::EVENT_DISPATCH, $this);
		try {
			$this->response = $this->getRouteCollection()->dispatch($this->getRequest(), $this->getResponse());
		} catch (NotFoundException $exception) {
			$this->getEventEmitter()->emit(self::EVENT_ROUTE_EXCEPTION, $this, $exception);
		} catch (\Exception $exception) {
			$this->getEventEmitter()->emit(self::EVENT_DISPATCH_EXCEPTION, $this, $exception);
		}
		$this->getEventEmitter()->emit(self::EVENT_RENDER, $this);
		$this->getEventEmitter()->emit(self::EVENT_FINISH, $this);
		$this->getContainer()->get('emitter')->emit($this->getResponse());
	}

	/**
	 * @return RouteCollection
	 */
	public function getRouteCollection() {
		return $this->routeCollection;
	}

	/**
	 * @return \Psr\Http\Message\ServerRequestInterface
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * @return \League\Event\Emitter
	 */
	public function getEventEmitter() {
		return $this->eventEmitter;
	}

	/**
	 * @return array
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @throws \Interop\Container\Exception\NotFoundException  No entry was found for this identifier.
	 * @throws ContainerException Error while retrieving the entry.
	 *
	 * @return mixed Entry.
	 */
	public function get($id) {
		if ($this->has($id)) {
			$method = 'get' . ucwords($id);
			return $this->$method;
		}
		return NULL;
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return boolean
	 */
	public function has($id) {
		return in_array($id, ['response', 'request', 'config', 'eventEmitter', 'routeCollection']);
	}
}