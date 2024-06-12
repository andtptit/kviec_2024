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

use App\Models\Package;
use App\Http\Resources\EntityCollection;
use App\Http\Resources\PackageResource;

/**
 * @group Packages
 */
class PackageController extends BaseController
{
	/**
	 * List packages
	 *
	 * @queryParam embed string Comma-separated list of the package relationships for Eager Loading - Possible values: currency. Example: null
	 * @queryParam sort string The sorting parameter (Order by DESC with the given column. Use "-" as prefix to order by ASC). Possible values: lft. Example: -lft
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function index()
	{
		// Cache control
		$noCache = (request()->filled('noCache') && (int)request()->get('noCache') == 1);
		$cacheDriver = config('cache.default');
		$cacheExpiration = $this->cacheExpiration;
		if ($noCache) {
			config()->set('cache.default', 'array');
			$cacheExpiration = '-1';
		}
		
		$cacheId = 'packages.with.currency.' . config('app.locale');
		$packages = cache()->remember($cacheId, $cacheExpiration, function () {
			$packages = Package::query()->applyCurrency();
			
			$embed = explode(',', request()->get('embed'));
			
			if (in_array('currency', $embed)) {
				$packages->with('currency');
			}
			
			// Sorting
			$packages = $this->applySorting($packages, ['lft']);
			
			return $packages->get();
		});
		
		// Reset caching parameters
		config()->set('cache.default', $cacheDriver);
		
		$resourceCollection = new EntityCollection(class_basename($this), $packages);
		
		$message = ($packages->count() <= 0) ? t('no_packages_found') : null;
		
		return $this->respondWithCollection($resourceCollection, $message);
	}
	
	/**
	 * Get package
	 *
	 * @queryParam embed string Comma-separated list of the package relationships for Eager Loading - Possible values: currency. Example: currency
	 *
	 * @urlParam id int required The package's ID. Example: 2
	 *
	 * @param $id
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function show($id)
	{
		// Cache control
		$noCache = (request()->filled('noCache') && (int)request()->get('noCache') == 1);
		$cacheDriver = config('cache.default');
		$cacheExpiration = $this->cacheExpiration;
		if ($noCache) {
			config()->set('cache.default', 'array');
			$cacheExpiration = '-1';
		}
		
		$cacheId = 'package.id.' . $id . '.' . config('app.locale');
		$package = cache()->remember($cacheId, $cacheExpiration, function () use ($id) {
			$package = Package::query()->where('id', $id);
			
			$embed = explode(',', request()->get('embed'));
			
			if (in_array('currency', $embed)) {
				$package->with('currency');
			}
			
			return $package->first();
		});
		
		// Reset caching parameters
		config()->set('cache.default', $cacheDriver);
		
		abort_if(empty($package), 404, t('package_not_found'));
		
		$package->setLocale(config('app.locale'));
		
		$resource = new PackageResource($package);
		
		return $this->respondWithResource($resource);
	}
}
