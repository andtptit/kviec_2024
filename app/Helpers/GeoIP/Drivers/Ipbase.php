<?php

namespace App\Helpers\GeoIP\Drivers;

use App\Helpers\GeoIP\AbstractDriver;
use Illuminate\Support\Facades\Http;

class Ipbase extends AbstractDriver
{
	public function get($ip)
	{
		$data = $this->getRaw($ip);
		
		if (empty($data) || is_string($data)) {
			return $this->getDefault($ip, $data);
		}
		
		return [
			'driver'      => config('geoip.default'),
			'ip'          => $ip,
			'city'        => data_get($data, 'city'),
			'country'     => data_get($data, 'country_name'),
			'countryCode' => data_get($data, 'country_code'),
			'latitude'    => (float)number_format(data_get($data, 'latitude'), 5),
			'longitude'   => (float)number_format(data_get($data, 'longitude'), 5),
			'region'      => data_get($data, 'region_name'),
			'regionCode'  => data_get($data, 'region_code'),
			'timezone'    => data_get($data, 'time_zone'),
			'postalCode'  => data_get($data, 'zip_code'),
		];
	}
	
	/**
	 * ipbase
	 * https://ipbase.com/
	 * Free Plan:
	 * - 150 requests / mo
	 * - 10 requests / Min.
	 * - Location Data
	 *
	 * @param $ip
	 * @return array|mixed|string
	 */
	public function getRaw($ip)
	{
		$apiKey = config('geoip.drivers.ipbase.apiKey');
		$url = 'https://api.ipbase.com/v2/info';
		$query = [
			'ip'     => $ip,
			'apikey' => $apiKey,
		];
		
		try {
			$response = Http::get($url, $query);
			if ($response->successful()) {
				return $response->json();
			}
		} catch (\Throwable $e) {
			$response = $e;
		}
		
		return getCurlHttpError($response);
	}
}
