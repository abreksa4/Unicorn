<?php
require_once(__DIR__ . '/../vendor/autoload.php');
//Create the App
$app = \Unicorn\App\Application::getInstance();

//add our GET:/ route
$app->getContainer()->get('routeCollection')->map('GET', '/', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) {
	$response->getBody()->write('Hello World!');
	return $response;
});

//register a 404 handler
$app->getEventEmitter()->addListener(\Unicorn\App\Application::EVENT_ROUTE_EXCEPTION, function (\League\Event\Event $event, \Unicorn\App\Application $app, \Exception $exception) {
	/**
	 * @var $response \Psr\Http\Message\ResponseInterface
	 */
	$response = $app->getContainer()->get('response');
	$response->getBody()->write('404 - ' . $exception->getMessage());
	$response = $response->withStatus(404, 'Not Found');
	return $response;
});

//register another route from an array (could be read from configuration, no?)
\Unicorn\Helpers\ConfiguredRoutes::bootstrapRoutes($app, [
	'index' => [
		'method' => 'GET',
		'route' => '/config-routes',
		'handler' => function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) {
			$response->getBody()->write('Hello World from config!');
			return $response;
		}
	]
]);
//run the App!
$app->run();