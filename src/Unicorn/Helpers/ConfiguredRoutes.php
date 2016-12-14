<?php
/**
 * Copyright (c) 2016 Andrew Breksa
 */

namespace Unicorn\Helpers;


use Unicorn\App\Application;

class ConfiguredRoutes {

	/**
	 * @param \Unicorn\App\Application $application
	 * @param array                    $routes
	 */
	public static function bootstrapRoutes(Application $application, array $routes) {
		foreach ($routes as $name => $rinfo) {
			$application->getRouteCollection()->map(strtoupper($rinfo['method']), $rinfo['route'], $rinfo['handler']);
		}
	}
}