<?php
require_once(__DIR__ . '/../vendor/autoload.php');

//Create the App
$app = new AndrewBreksa\Unicorn\App\Application();
$app->bootstrap();

//add something to "render" the output
$app->getEventEmitter()->addListener(AndrewBreksa\Unicorn\App\Application::EVENT_RENDER, function (\League\Event\Event $event, \AndrewBreksa\Unicorn\App\Application $application) {
    $application->getResponse()->getBody()->rewind();
    $data = $application->getResponse()->getBody()->getContents();
    $newBody = new \Zend\Diactoros\Stream(fopen('php://temp', 'r+'));
    $newBody->write(json_encode(["data" => $data, 'data_from_getData()' => (array_key_exists('data1', $application->getData()) ? $application->getData()['data1'] : '')]));
    $application->setResponse($application->getResponse()->withBody($newBody));
});

//add our GET:/ route
$app->getContainer()->get('routeCollection')->map('GET', '/', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) {
    $response->getBody()->write('Hello World!');

    return $response;
});

//register a 404 handler
$app->getEventEmitter()->addListener(\AndrewBreksa\Unicorn\App\Application::EVENT_ROUTE_EXCEPTION, function (\League\Event\Event $event, \AndrewBreksa\Unicorn\App\Application $app, \Exception $exception) {
    /**
     * @var $response \Psr\Http\Message\ResponseInterface
     */
    $response = $app->getContainer()->get('response');
    $response = $response->withStatus(404);
    $response->getBody()->write('404 - ' . $exception->getMessage());
    $app->data['data1'] = 'this is a string';
    $app->setResponse($response);
});

//register another route from an array (could be read from configuration, no?)
$app->bootstrapRoutes([
    'index' => [
        'method'  => 'GET',
        'route'   => '/config-routes',
        'handler' => function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) {
            $response->getBody()->write('Hello World from config!');

            return $response;
        },
    ],
]);

//run the App!
$app->run();
