# [Unicorn](https://github.com/abreksa4/Unicorn)
_A tiny single class RAD PSR-7 web application "framework" maintained by [Andrew Breksa](https://github.com/abreksa4) 
<[andrew@andrewbreksa.com](mailto:andrew@andrewbreksa.com)>_

Unicorn is essentially a wrapper around [zend-diactoros](https://github.com/zendframework/zend-diactoros) and a couple 
of ["The PHP League"](https://thephpleague.com/) packages ([league/event](http://event.thephpleague.com/2.0/), 
[league/container](http://container.thephpleague.com/), [league/route](http://route.thephpleague.com/)).

Still in it's infancy, Unicorn was born from my frustrations of wanting a framework to handle the plumbing for me, but
not force a specific architecture or style, as applications tend to get very domain specific (as they should be).

- Want to build a closure application? Easy. 
- Want to build a PSR-7 middleware app? Use your own pipeline implementation. 
- Want to use a "full", controller-based MVC framework? Just specify the class and method via routing.
- Want to use all of the above in the same app? Go ahead. 

Unicorn attempts to do only the minimum, providing a service container, a router, a few event hooks for the various 
stages of the application lifecycle, and a PSR-7 implementation (parser and emitter). And if you'd like, consider it an
anti-framework framework. You might have to write some code... ;)

## Installation
Unicorn isn't hosted on Packagegist as of yet, so follow this handy guide by Eugene Morgan to get Unicorn in your 
project: 
[Adding a random GitHub repository to your project using Composer](http://eugenemorgan.com/adding-a-random-github-repository-to-your-project-using-composer/)

## Usage
Check out the example [index.php](https://github.com/abreksa4/Unicorn/blob/master/public/index.php) to see how to setup 
a basic app. (And I mean basic).

## Application lifecycle/events
_As defined in \Unicorn\App\Application_
```
	const EVENT_BOOTSTRAP = 'app.bootstrap';
	const EVENT_DISPATCH = 'app.dispatch';
	const EVENT_ROUTE_EXCEPTION = 'app.route.exception';
	const EVENT_DISPATCH_EXCEPTION = 'app.dispatch.exception';
	const EVENT_RENDER = 'app.render';
	const EVENT_FINISH = 'app.finish';
```
Each event is emitted before the action takes place (if any, render doesn't do anything), and the event manager is 
accessible via `Application::getInstance()->getEventEmitter()`.
Fairly simple, see the [league/event](http://event.thephpleague.com/2.0/) documentation.

## Routing
You can access the router via `Application::getInstance()->getRouteCollection()`. From there check out the 
[league/route](http://route.thephpleague.com/) docs for more info.

## PSR-7
Really? Ok, well checkout [php-fig.org](http://www.php-fig.org/psr/psr-7/) for more info.

## Configuration
Configuration is (can be) stored in `./config/autoload`. Any `*.php` or `*.json` file here will either be `include`d or 
parsed and added to `Application::getInstance()->getConfig()`. And as always, feel free to completely ignore this 
convention and do whatever you please.

Regardless of how the configuration is set, on `Application::bootstrap()`, if there is either `services` or `routes` in 
Application::getConfig(), the values of these keys are passed to `Application::bootstrapServices()` and 
`Application::bootstrapRoutes()` respectively.

## Dependency container
Unicorn uses [league/container](http://container.thephpleague.com/), which follows the 
[container-interop](https://github.com/container-interop/container-interop) standard. All of the 
`Application::getInstance()->get*()` objects are also available via the container 
(`Application::getInstance()->getContainer()`) except the container itself and `data`, as Unicorn registers itself as a 
delegate.

## Conclusion
Unicorn is supposed to do just about nothing, or in short, everything you should need for any PHP web application. If 
you're tired of fitting your domain requirements to a framework, you just might enjoy working with Unicorn. As always, 
feel free to fork and open pull requests against this repo. I don't claim to know what I'm doing. :)
