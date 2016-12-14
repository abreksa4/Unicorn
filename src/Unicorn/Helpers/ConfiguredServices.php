<?php
/**
 * Copyright (c) 2016 Andrew Breksa
 */

namespace Unicorn\Helpers;


use Unicorn\App\Application;

class ConfiguredServices {

	/**
	 * @param \Unicorn\App\Application $application
	 * @param array                    $services
	 */
	public static function bootstrapServices(Application $application, array $services) {
		foreach ($services as $key => $callable) {
			$application->getContainer()->share($key, $callable);
		}
	}

}