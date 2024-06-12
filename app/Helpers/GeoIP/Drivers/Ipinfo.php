<?php

namespace App\Helpers\GeoIP\Drivers;

use App\Helpers\GeoIP\AbstractDriver;
use Illuminate\Support\Facades\Http;

class Ipinfo extends AbstractDriver
{
	public function get($ip)
	{
		$data = $this->getRaw($ip);
		
		if (empty($data) || is_string($data) || data_get($data, 'bogon')) {
			return $this->getDefault($ip, $data);
		}
		
		$loc = data_get($data, 'loc');
		$locArray = !empty($loc) ? explode(',', $loc) : [];
		
		return [
			'driver'      => config('geoip.default'),
			'ip'          => $ip,
			'city'        => data_get($data, 'city'),
			'country'     => null,
			'countryCode' => data_get($data, 'country'),
			'latitude'    => $locArray[0] ?? null,
			'longitude'   => $locArray[1] ?? null,
			'region'      => data_get($data, 'region'),
			'regionCode'  => null,
			'timezone'    => data_get($data, 'timezone'),
			'postalCode'  => data_get($data, 'postal'),
		];
	}
	
	/**
	 * ipinfo
	 * https://ipinfo.io/
	 * Free plan: Geolocation: 50k requests per month
	 *
	 * @param $ip
	 * @return array|mixed|string
	 */
	public function getRaw($ip)
	{
		$token = config('geoip.drivers.ipinfo.token');
		
		$url = 'https://ipinfo.io/' . $ip . '/json?token=' . $token;
		
		try {
			$response = Http::get($url);
			if ($response->successful()) {
				return $response->json();
			}
		} catch (\Throwable $e) {
			$response = $e;
		}
		
		return getCurlHttpError($response);
	}
}
