# [Unicorn](https://github.com/abreksa4/Unicorn)
_A tiny single class RAD PSR-7 web application "framework"_

Unicorn is essentially a wrapper around [zendframework/zend-diactoros](https://github.com/zendframework/zend-diactoros) 
and a couple of [The PHP League](https://thephpleague.com/) packages: 
[league/event](http://event.thephpleague.com/2.0/), [league/container](http://container.thephpleague.com/), and 
[league/route](http://route.thephpleague.com/).

Still in it's infancy, Unicorn was born from my frustrations of wanting a framework to handle the plumbing for me, but
not force a specific architecture or style, as applications tend to get very domain specific (as they should be).

- Want to build a closure application? Easy. 
- Want to build a PSR-7 middleware app? Use your own pipeline implementation. (I recommend 
[league/pipeline](http://pipeline.thephpleague.com/). Remember, the `Application` object follows the singleton pattern 
and is accessible via `Application::getInstance()`. I would include support for league/pipeline that by default, but I 
feel this is not always required by a PSR-7 application, and therefore outside of the Unicorn mission statement)
- Want to use a "full", controller-based MVC framework? Just specify the class and method via routing.
- Want to use all of the above in the same app? Go ahead. 

Unicorn attempts to do only the minimum, providing a service container, a router, a few event hooks for the various 
stages of the application lifecycle, and a PSR-7 implementation. And if you'd like, consider it an anti-framework 
framework. **You might have to write some code...** ;)

## Installation
Unicorn isn't hosted on Packagist as of yet, so:

1. Add the following to your `composer.json`:
	```
	"repositories": [
        {
          "type": "git",
          "url": "https://github.com/abreksa4/Unicorn"
        }
      ]
    ```

2. Run `composer require andrewbreksa/unicorn`
      
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
	const EVENT_EMIT_ERROR = 'app.emit.exception';
	const EVENT_FINISH = 'app.finish';
```

Each event is emitted before the action takes place (if any, `Application::EVENT_RENDER` doesn't do anything), 
and the event manager is accessible via `Application::getInstance()->getEventEmitter()`.
Fairly simple, see the [league/event](http://event.thephpleague.com/2.0/) documentation.

## Routing
You can access the router via `Application::getInstance()->getRouteCollection()`. From there check out the 
[league/route](http://route.thephpleague.com/) docs for more info. 

A note on the return values of methods/closures/etc called on dispatch: If `NULL` is returned (or nothing at all, 
implied) then `Application->$response` is not updated, but the emitting process is carried out. However, if you return 
`FALSE`, the emitting process is not carried out and `Application::EVENT_RENDER` (needless to say, 
`Application::EVENT_EMIT_ERROR`) is not emitted either. This can be used to run other, "non-Unicorn", code or frameworks 
on specific routes.

## PSR-7
Really? Ok, well checkout [php-fig.org](http://www.php-fig.org/psr/psr-7/) for more info.

## Configuration
Configuration is (can be) stored in `./config/autoload`. Any `*.php` or `*.json` file here will either be `include`d or 
parsed and added to `Application::getInstance()->getConfig()`. And as always, feel free to completely ignore this 
convention and do whatever you please.

Regardless of how the configuration is set, on `Application->bootstrap()` (after `EVENT_BOOTSTRAP`), if there is any of 
`services`, `routes`, or `eventListeners` in `Application->getConfig()`, the values of these keys are passed to 
`Application->bootstrapServices()`, `Application->bootstrapRoutes()` and `Application->bootstrapEventListeners()` 
respectively.

## Dependency container
Unicorn uses [league/container](http://container.thephpleague.com/), which follows the 
[container-interop](https://github.com/container-interop/container-interop) standard. All of the 
`Application::getInstance()->get*()` objects are also available via the container 
(`Application::getInstance()->getContainer()`) as Unicorn registers itself as a delegate ( except the container itself, 
`baseDir`, and `data`. The dependency container is also set to use the `League\Container\ReflectionContainer` as a 
delegate, so constructor dependencies should be automatically if available 
[more on auto-wiring](http://container.thephpleague.com/auto-wiring/). 

## Notes
As the architecture and use cases of Unicorn are still being fleshed out, I've yet to put together thorough 
documentation. It's more of a pet project really that I'm using for RAD on a couple of personal projects. 

Event listeners attached to the various `Application::EVENT_*` events are passed a `\League\Event\Event` and 
the Application instance by default. `EVENT_*_EXCEPTION` events are passed an additional `\Exception` parameter. 

## Conclusion
Unicorn is supposed to do just about nothing, or in short, everything you should need for any PHP web application. If 
you're tired of fitting your domain requirements to a framework or needing to write hacky workarounds to problems caused 
by lack of control, you just might enjoy working with Unicorn. 

Feel free to fork and open pull requests against this repo. I don't claim to know what I'm doing, and I feel Unicorn 
would best serve the community evolving as a project defined by common project requirements instead of a specific set of 
design patterns and 'full-featured' mentality.