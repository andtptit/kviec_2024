<?php
/*
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

namespace App\Providers\AppService;

use App\Http\Kernel;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait TelescopeTrait
{
	/**
	 * @return void
	 */
	private function runInspection(): void
	{
		// Is Debug Bar enabled?
		$isDebugBarEnabled = (config('app.debug') && config('larapen.core.debugBar'));
		if (!$isDebugBarEnabled) {
			Debugbar::disable();
		}
		
		// Know if the server is taking too long to respond than a specific timeout
		$isRequestLifecycleCanBeChecked = (!app()->isProduction());
		if ($isRequestLifecycleCanBeChecked) {
			$kernel = $this->app[Kernel::class] ?? null;
			if (!is_null($kernel)) {
				$httpRequestTimeout = (int)config('larapen.core.performance.httpRequestTimeout', 1);
				$kernel->whenRequestLifecycleIsLongerThan($httpRequestTimeout, function ($startedAt, $request, $response) {
					$message = 'The script detects that your server is taking too long to respond.';
					Log::warning($message);
				});
			}
		}
		
		// Disable lazy loading (completely) to increase performance optimization
		// WARNING: Never apply that on production to prevent exception errors.
		if (!appIsBeingInstalledOrUpgraded()) {
			$isLazyLoadingCanBeDisabled = (
				!app()->isProduction()
				&& config('larapen.core.performance.preventLazyLoading')
			);
			Model::preventLazyLoading($isLazyLoadingCanBeDisabled);
		}
	}
}
