<?php

namespace App\Helpers\GeoIP;

abstract class AbstractDriver
{
	public function __construct()
	{
		//...
	}
	
	/**
	 * Get GeoIP info from IP.
	 *
	 * @param string|null $ip
	 *
	 * @return array
	 */
	abstract public function get(?string $ip);
	
	/**
	 * Get the raw GeoIP info from the driver.
	 *
	 * @param string|null $ip
	 *
	 * @return mixed
	 */
	abstract public function getRaw(?string $ip);
	
	/**
	 * Get the default values (all null).
	 *
	 * @param string|null $ip
	 * @param $responseError
	 * @return array
	 */
	protected function getDefault(?string $ip, $responseError = null): array
	{
		$responseError = getCurlHttpError($responseError); // required!
		
		return [
			'driver'      => config('geoip.default'),
			'ip'          => $ip,
			'error'       => $responseError,
			'city'        => null,
			'country'     => null,
			'countryCode' => null,
			'latitude'    => null,
			'longitude'   => null,
			'region'      => null,
			'regionCode'  => null,
			'timezone'    => null,
			'postalCode'  => null,
		];
	}
}
