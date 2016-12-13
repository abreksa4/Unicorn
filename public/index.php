<?php
require_once(__DIR__ . '/../vendor/autoload.php');
//Create the App
$app = new Unicorn\App\Application();

//add our GET:/ route
$app->getRouteCollection()->map('GET', '/', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) {
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

//run the App!
$app->run();