# Unicorn
_A single class PSR-7 web application "framework"_

Unicorn is essentially a wrapper around [zend-diactoros](https://github.com/zendframework/zend-diactoros) and a couple 
of ["The PHP League"](https://thephpleague.com/) packages ([league/event](http://event.thephpleague.com/2.0/), 
[league/container](http://container.thephpleague.com/), [league/route](http://route.thephpleague.com/)).

Unicorn may seem a bit like Zend Framework 2 due to it's inclusion of an Event architecture, though it feels like a 
slimmed down Slim. (Puny, no?)

Still in it's infancy, Unicorn was born of my frustrations while wanting a framework to handle the plumbing for me, but
not force a specific architecture or style. 

- Want to build a "slim-esque" closure application? Easy. 
- Want to build a PSR-7 middleware app? Use your own pipeline implementation. 
- Want to use a "full", controller-based MVC framework? Just specify the class and method via routing.
- Want to use all of the above in the same app? Go ahead. 

Unicorn attempts to do only the minimum, providing a service container, a router, a few event hooks for the various 
stages of the application lifecycle, and a PSR-7 implementation (parser and emmiter). 