<?php
/**
 * JobClass - Job Board Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://laraclassifier.com/jobclass
 *
 * LICENSE
 * -------
 * This software is furnished under a license and may be used and copied
 * only in accordance with the terms of such license and with the inclusion
 * of the above copyright notice. If you Purchased from CodeCanyon,
 * Please read the full License from here - http://codecanyon.net/licenses/standard
 */

namespace App\Http\Controllers\Api;

use App\Models\Setting;

/**
 * @group Settings
 */
class SettingController extends BaseController
{
	/**
	 * List settings
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index()
	{
		$settings = config('settings');
		
		// Remove the 'purchase_code' value
		if (isset($settings['app'])) {
			$app = $settings['app'];
			if (isset($app['purchase_code'])) {
				unset($app['purchase_code']);
				$settings['app'] = $app;
			}
		}
		
		// Remove settings hidden values
		$settings = collect($settings)->mapWithKeys(function ($value, $key) {
			$value = collect($value)->reject(function ($v, $k) {
				return (in_array($k, Setting::$hiddenValues));
			});
			
			return [$key => $value];
		})->reject(function ($v, $k) {
			return (empty($v) || ($v->count() <= 0));
		})->toArray();
		
		$data = [
			'success' => true,
			'result'  => $settings,
		];
		
		return $this->apiResponse($data);
	}
	
	/**
	 * Get setting
	 *
	 * @urlParam key string required The settings key. Example: app
	 *
	 * @param $key
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function show($key)
	{
		$settingKey = 'settings.' . $key;
		
		if (!config()->has($settingKey)) {
			return $this->respondNotFound();
		}
		
		$settings = config($settingKey);
		
		// Remove the 'purchase_code' value
		if (is_array($settings)) {
			if (isset($settings['purchase_code'])) {
				unset($settings['purchase_code']);
			}
		}
		if (is_string($settings)) {
			if (str_ends_with($settingKey, 'purchase_code')) {
				$settings = null;
			}
		}
		
		// Remove settings hidden values
		if (is_array($settings)) {
			$settings = collect($settings)->reject(function ($v, $k) {
				return (in_array($k, Setting::$hiddenValues));
			})->toArray();
		}
		if (is_string($settings)) {
			foreach (Setting::$hiddenValues as $hiddenValue) {
				if (str_ends_with($settingKey, $hiddenValue)) {
					$settings = null;
					break;
				}
			}
		}
		
		if (empty($settings)) {
			return $this->respondNotFound();
		}
		
		$data = [
			'success' => true,
			'result'  => $settings,
		];
		
		return $this->apiResponse($data);
	}
}
