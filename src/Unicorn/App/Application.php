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
	/**
	 * The Unicorn version
	 */
	const VERSION = '0';
	/**
	 * Runs on Application->bootstrap()
	 */
	const EVENT_BOOTSTRAP = 'app.bootstrap';
	/**
	 * Runs before dispatch
	 */
	const EVENT_DISPATCH = 'app.dispatch';
	/**
	 * Runs in case of a NotFoundException, listeners get $this and $exceptions as arguments as well as the Event object
	 */
	const EVENT_ROUTE_EXCEPTION = 'app.route.exception';
	/**
	 * Runs in case of an \Exception during dispatch, listeners get $this and $exceptions as arguments as well as the
	 * Event object
	 */
	const EVENT_DISPATCH_EXCEPTION = 'app.dispatch.exception';
	/**
	 * Runs after dispatch. Unicorn includes no default functionality to redner
	 */
	const EVENT_RENDER = 'app.render';
	/**
	 * Runs in case of an \Exception during emitting the PSR-7 response, listeners get $this and $exceptions as
	 * arguments as well as the Event object
	 */
	const EVENT_EMIT_ERROR = 'app.emit.exception';
	/**
	 * Runs after the PSR-7 response has been emitted
	 */
	const EVENT_FINISH = 'app.finish';
	/**
	 * @var Application
	 */
	private static $instance = NULL;
	/**
	 * @var array
	 */
	public $data = [];
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
	public $config = [];
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
	 * @var string
	 */
	protected $basedir;

	/**
	 * Application constructor.
	 */
	private function __construct($basedir) {
		$this->basedir = $basedir;
		$this->eventEmitter = new Emitter();
		$this->container = new Container();
		$this->getContainer()->delegate(
			new ReflectionContainer
		);
		$this->getContainer()->delegate($this);
		$this->routeCollection = new RouteCollection($this->getContainer());
		foreach (glob($this->basedir . '/config/autoload/*.php') as $file) {
			$this->config = array_merge($this->config, include($file));
		}
		foreach (glob($this->basedir . '/config/autoload/*.json') as $file) {
			$this->config = array_merge($this->config, json_decode(file_get_contents($file), TRUE));
		}
		$this->getContainer()->share(Application::class, $this);
	}

	/**
	 * Get the league/container container
	 *
	 * @return \League\Container\Container
	 */
	public function getContainer() {
		return $this->container;
	}

	/**
	 * Get the singleton instance of Application
	 *
	 * @param string|null $basedir
	 * @return Application
	 * @throws \InvalidArgumentException if the $basedir is not set on initial creation
	 */
	public static function getInstance($basedir = NULL) {
		if (Application::$instance == NULL) {
			if(is_null($basedir)){
				throw new \InvalidArgumentException('$basedir is required on initial Application creation');
			}
			Application::$instance = new Application($basedir);
		}
		return Application::$instance;
	}

	/**
	 * Get arbitrary data
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Set arbitrary data
	 *
	 * @param array $data
	 *
	 * @return Application
	 */
	public function setData($data) {
		$this->data = $data;
		return $this;
	}

	/**
	 * Create the Request and Response objects, and share the PSR-7 Emitter with the container
	 */
	public function bootstrap() {
		$this->eventEmitter->emit(self::EVENT_BOOTSTRAP, $this);
		$this->request = \Zend\Diactoros\ServerRequestFactory::fromGlobals(
			$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
		);
		$this->setResponse(new Response());
		$this->getContainer()->share('emitter', \Zend\Diactoros\Response\SapiEmitter::class);
		if (array_key_exists('services', $this->getConfig())) {
			$this->bootstrapServices($this->getConfig()['services']);
		}
		if (array_key_exists('routes', $this->getConfig())) {
			$this->bootstrapRoutes($this->getConfig()['routes']);
		}
	}

	/**
	 * Execute the Application
	 */
	public function run() {
		$this->eventEmitter->emit(self::EVENT_DISPATCH, $this);
		try {
			$result = $this->getRouteCollection()->dispatch($this->getRequest(), $this->getResponse());
			if(!is_null($result) && $result instanceof ResponseInterface){
				$this->setResponse($result);
			}
		} catch (NotFoundException $exception) {
			$this->getEventEmitter()->emit(self::EVENT_ROUTE_EXCEPTION, $this, $exception);
		} catch (\Exception $exception) {
			$this->getEventEmitter()->emit(self::EVENT_DISPATCH_EXCEPTION, $this, $exception);
		}
		$this->getEventEmitter()->emit(self::EVENT_RENDER, $this);
		try {
		$this->getContainer()->get('emitter')->emit($this->getResponse());
		} catch (\Exception $exception) {
			$this->getEventEmitter()->emit(self::EVENT_EMIT_ERROR, $this, $exception);
		}
		$this->getEventEmitter()->emit(self::EVENT_FINISH, $this);
	}

	/**
	 * Get the league/route router
	 *
	 * @return RouteCollection
	 */
	public function getRouteCollection() {
		return $this->routeCollection;
	}

	/**
	 * Get the PSR-7 request
	 *
	 * @return \Psr\Http\Message\ServerRequestInterface
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * Get the PSR-7 response
	 *
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * Set the PSR-7 response
	 *
	 * @param \Psr\Http\Message\ResponseInterface $response
	 *
	 * @return Application
	 */
	public function setResponse($response) {
		$this->response = $response;
		return $this;
	}

	/**
	 * Get the event Emitter to either listen or emitt events
	 *
	 * @return \League\Event\Emitter
	 */
	public function getEventEmitter() {
		return $this->eventEmitter;
	}

	/**
	 * Return the application configuration
	 *
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
			return $this->$method();
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

	/**
	 * @param array $config
	 * @return Application
	 */
	public function setConfig($config) {
		$this->config = $config;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getBasedir() {
		return $this->basedir;
	}

	/**
	 * @param string $basedir
	 * @return Application
	 */
	public function setBasedir($basedir) {
		$this->basedir = $basedir;

		return $this;
	}

	/**
	 * Takes an associative array of routeName => ['method'=>string, 'route'=>string, 'hsndler'=>callable]
	 * and registers those routes
	 *
	 * @param array $routes
	 */
	public function bootstrapRoutes(array $routes) {
		foreach ($routes as $name => $rinfo) {
			$this->getRouteCollection()->map(strtoupper($rinfo['method']), $rinfo['route'], $rinfo['handler']);
		}
	}

	/**
	 * Takes an associative array of $serviceName => $callable to share with the service container
	 *
	 * @param array $services
	 */
	public function bootstrapServices(array $services) {
		foreach ($services as $key => $callable) {
			$this->getContainer()->share($key, $callable);
		}
	}
}
