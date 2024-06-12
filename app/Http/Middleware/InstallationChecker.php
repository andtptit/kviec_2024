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

namespace App\Http\Middleware;

use App\Http\Controllers\Api\Base\ApiResponseTrait;
use App\Http\Controllers\Web\Install\Traits\Update\CleanUpTrait;
use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class InstallationChecker
{
	use CleanUpTrait, ApiResponseTrait;
	
	/**
	 * Handle an incoming request.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure $next
	 * @param $guard
	 * @return \Illuminate\Http\RedirectResponse|mixed
	 */
	public function handle(Request $request, Closure $next, $guard = null)
	{
		if (isFromApi()) {
			return $this->handleApi($request, $next);
		} else {
			return $this->handleWeb($request, $next, $guard);
		}
	}
	
	/**
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure $next
	 * @return \Illuminate\Http\JsonResponse|mixed
	 */
	private function handleApi(Request $request, Closure $next)
	{
		// Since the Admin panel doesn't call the API, skip requests from there to allow admins to log in to into it.
		if (request()->hasHeader('X-WEB-REQUEST-URL')) {
			if (isFromAdminPanel(request()->header('X-WEB-REQUEST-URL'))) {
				return $next($request);
			}
		}
		
		if (!$this->alreadyInstalled()) {
			$message = 'The application is not installed. ';
			$message .= 'Please install it by visiting the URL "' . url('install') . '" from a web browser.';
			
			$data = [
				'success' => false,
				'message' => $message,
				'extra'   => ['error' => ['type' => 'install']],
			];
			
			return $this->apiResponse($data, 401);
		}
		
		if (updateIsAvailable()) {
			$message = 'Your application needs to be upgraded. ';
			$message .= 'To achieve this, visit the URL "' . url('upgrade') . '" in a web browser and follow the steps.';
			
			$data = [
				'success' => false,
				'message' => $message,
				'extra'   => ['error' => ['type' => 'upgrade']],
			];
			
			return $this->apiResponse($data, 401);
		}
		
		return $next($request);
	}
	
	/**
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure $next
	 * @param $guard
	 * @return \Illuminate\Http\RedirectResponse|mixed
	 */
	private function handleWeb(Request $request, Closure $next, $guard = null)
	{
		if (request()->segment(1) == 'install') {
			// Check if installation is processing
			$InstallInProgress = (
				!empty(session('databaseImported'))
				|| !empty(session('cronJobs'))
				|| !empty(session('installFinished'))
			);
			
			if ($this->alreadyInstalled() && !$InstallInProgress) {
				return redirect()->to('/');
			}
		} else {
			// Check if an update is available
			if (updateIsAvailable()) {
				if (auth()->check()) {
					$aclTableNames = config('permission.table_names');
					if (isset($aclTableNames['permissions'])) {
						if (Schema::hasTable($aclTableNames['permissions'])) {
							if (auth()->guard($guard)->user()->can(Permission::getStaffPermissions()) && !isDemoDomain()) {
								return redirect()->to(getRawBaseUrl() . '/upgrade');
							}
						}
					}
				} else {
					// Clear all the cache (TMP)
					$this->clearCache();
				}
			}
			
			// Check if the website is installed
			if (!$this->alreadyInstalled()) {
				return redirect()->to(getRawBaseUrl() . '/install');
			}
			
			$this->checkPurchaseCode();
		}
		
		return $next($request);
	}
	
	/**
	 * If application is already installed.
	 *
	 * @return bool|\Illuminate\Http\RedirectResponse
	 */
	private function alreadyInstalled()
	{
		// Check if installation has just finished
		if (session('installFinished') == 1) {
			// Write file
			File::put(storage_path('installed'), '');
			
			session()->forget('installFinished');
			session()->flush();
			
			// Redirect to the homepage after installation
			return redirect()->to('/');
		}
		
		// Check if the app is installed
		return appIsInstalled();
	}
	
	/**
	 * Check Purchase Code
	 * ===================
	 * Checking your purchase code. If you do not have one, please follow this link:
	 * https://codecanyon.net/item/jobclass-geolocalized-job-portal-script/18776089
	 * to acquire a valid code.
	 *
	 * IMPORTANT: Do not change this part of the code to prevent any data losing issue.
	 *
	 * @return void
	 */
	private function checkPurchaseCode(): void
	{
		return;
		$tab = [
			'install',
			admin_uri(),
		];
		
		// Don't check the purchase code for these areas (install, admin, etc. )
		if (in_array(request()->segment(1), $tab)) {
			return;
		}
		
		// Make the purchase code verification only if 'installed' file exists
		if (file_exists(storage_path('installed')) && !config('settings.error')) {
			// Get purchase code from 'installed' file
			$purchaseCode = file_get_contents(storage_path('installed'));
			
			// Send the purchase code checking
			if (
				empty($purchaseCode)
				|| empty(config('settings.app.purchase_code'))
				|| $purchaseCode != config('settings.app.purchase_code')
			) {
				$data = [];
				$endpoint = getPurchaseCodeApiEndpoint(config('settings.app.purchase_code'), config('larapen.core.itemId'));
				try {
					/*
					 * Make the request and wait for 30 seconds for response.
					 * If it does not receive one, wait 5000 milliseconds (5 seconds), and then try again.
					 * Keep trying up to 2 times, and finally give up and throw an exception.
					 */
					$response = Http::withoutVerifying()->timeout(30)->retry(2, 5000)->get($endpoint)->throw();
					$data = $response->json();
				} catch (\Throwable $e) {
					$endpoint = (str_starts_with($endpoint, 'https:'))
						? str_replace('https:', 'http:', $endpoint)
						: str_replace('http:', 'https:', $endpoint);
					
					try {
						$response = Http::withoutVerifying()->timeout(30)->retry(2, 5000)->get($endpoint)->throw();
						$data = $response->json();
					} catch (\Throwable $e) {
						$data['message'] = getCurlHttpError($e);
					}
				}
				
				// Checking
				if (data_get($data, 'valid')) {
					file_put_contents(storage_path('installed'), data_get($data, 'license_code'));
				} else {
					// Invalid purchase code
					dd(data_get($data, 'message'));
				}
			}
		}
	}
}
