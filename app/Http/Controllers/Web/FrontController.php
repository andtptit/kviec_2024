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

namespace App\Http\Controllers\Web;

use App\Helpers\UrlGen;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Traits\CommonTrait;
use App\Http\Controllers\Web\Traits\EnvFileTrait;
use App\Http\Controllers\Web\Traits\RobotsTxtTrait;
use App\Http\Controllers\Web\Traits\SettingsTrait;
use App\Models\Permission;

class FrontController extends Controller
{
	use SettingsTrait, EnvFileTrait, RobotsTxtTrait, CommonTrait;
	
	public $request;
	public $data = [];
	
	/**
	 * FrontController constructor.
	 */
	public function __construct()
	{
		// Set the storage disk
		$this->setStorageDisk();
		
		// Check & Change the App Key (If needed)
		$this->checkAndGenerateAppKey();
		
		// Load the Plugins
		$this->loadPlugins();
		
		// Check & Update the '/.env' file
		$this->checkDotEnvEntries();
		
		// Check & Update the '/public/robots.txt' file
		$this->checkRobotsTxtFile();
		
		// From Laravel 5.3.4+ (for sessions usage in Controllers)
		$this->middleware(function ($request, $next) {
			// Load Localization Data first
			// Check out the SetCountryLocale Middleware
			$this->applyFrontSettings();
			
			// Get & Share Users Menu
			$userMenu = $this->getUserMenu();
			view()->share('userMenu', $userMenu);
			
			return $next($request);
		});
		
		// Check the 'Domain Mapping' plugin
		if (config('plugins.domainmapping.installed')) {
			$this->middleware(['domain.verification']);
		}
	}
	
	/*
	 * Handle HTTP error for GET requests
	 */
	protected function handleHttpError(?array $data = [])
	{
		// Parsing the API response
		$message = !empty(data_get($data, 'message')) ? data_get($data, 'message') : 'Unknown Error.';
		
		// HTTP Error Found
		if (!data_get($data, 'isSuccessful')) {
			$errorCode = (int)data_get($data, 'status');
			$errorCode = (strlen($errorCode) == 3) ? $errorCode : 500;
			abort($errorCode, $message);
		}
		
		return $message;
	}
	
	/*
	 * Handle HTTP error for non GET requests
	 * @todo: Check the redirect can be done externally
	 */
	protected function handleHttpErrorWithRedirect(?array $data = [], $withInput = [])
	{
		// Parsing the API response
		$message = !empty(data_get($data, 'message')) ? data_get($data, 'message') : 'Unknown Error.';
		
		// HTTP Error Found
		if (!data_get($data, 'isSuccessful')) {
			flash($message)->error();
			
			if (!empty($withInput)) {
				return redirect()->back()->withInput($withInput);
			} else {
				return redirect()->back();
			}
		}
		
		return $message;
	}
	
	/**
	 * @return \Illuminate\Support\Collection
	 */
	private function getUserMenu(): \Illuminate\Support\Collection
	{
		if (!auth()->check()) {
			return collect();
		}
		
		$menuArray = [];
		
		// company
		if (in_array(auth()->user()->user_type_id, [1])) {
			$menuArray = [
				[
					'name'       => t('My companies'),
					'url'        => url('account/companies'),
					'icon'       => 'far fa-building',
					'group'      => t('my_ads'),
					'countVar'   => 'companies',
					'inDropdown' => true,
					'isActive'   => (request()->segment(2) == 'companies'),
				],
				[
					'name'       => t('my_ads'),
					'url'        => url('account/posts/list'),
					'icon'       => 'fas fa-th-list',
					'group'      => t('my_ads'),
					'countVar'   => 'posts.published',
					'inDropdown' => true,
					'isActive'   => (request()->segment(3) == 'list'),
				],
				[
					'name'       => t('pending_approval'),
					'url'        => url('account/posts/pending-approval'),
					'icon'       => 'fas fa-hourglass-half',
					'group'      => t('my_ads'),
					'countVar'   => 'posts.pendingApproval',
					'inDropdown' => true,
					'isActive'   => (request()->segment(3) == 'pending-approval'),
				],
				[
					'name'       => t('archived_ads'),
					'url'        => url('account/posts/archived'),
					'icon'       => 'fas fa-calendar-times',
					'group'      => t('my_ads'),
					'countVar'   => 'posts.archived',
					'inDropdown' => true,
					'isActive'   => (request()->segment(3) == 'archived'),
				],
				[
					'name'             => t('messenger'),
					'url'              => url('account/messages'),
					'icon'             => 'far fa-envelope',
					'group'            => t('my_ads'),
					'countVar'         => 0,
					'countCustomClass' => ' count-threads-with-new-messages',
					'inDropdown'       => true,
					'isActive'         => (request()->segment(2) == 'messages'),
				],
				[
					'name'       => t('Transactions'),
					'url'        => url('account/transactions'),
					'icon'       => 'fas fa-coins',
					'group'      => t('my_ads'),
					'countVar'   => 'transactions',
					'inDropdown' => true,
					'isActive'   => (request()->segment(2) == 'transactions'),
				],
			];
		}
		
		// job seeker
		if (in_array(auth()->user()->user_type_id, [2])) {
			$menuArray = [
				[
					'name'       => t('My resumes'),
					'url'        => url('account/resumes'),
					'icon'       => 'fas fa-paperclip',
					'group'      => t('my_ads'),
					'countVar'   => 'resumes',
					'inDropdown' => true,
					'isActive'   => (request()->segment(2) == 'resumes'),
				],
				[
					'name'       => t('Favourite jobs'),
					'url'        => url('account/posts/favourite'),
					'icon'       => 'fas fa-bookmark',
					'group'      => t('my_ads'),
					'countVar'   => 'posts.favourite',
					'inDropdown' => true,
					'isActive'   => (request()->segment(3) == 'favourite'),
				],
				[
					'name'       => t('Saved searches'),
					'url'        => url('account/saved-searches'),
					'icon'       => 'fas fa-bell',
					'group'      => t('my_ads'),
					'countVar'   => 'savedSearch',
					'inDropdown' => true,
					'isActive'   => (request()->segment(2) == 'saved-searches'),
				],
				[
					'name'             => t('messenger'),
					'url'              => url('account/messages'),
					'icon'             => 'far fa-envelope',
					'group'            => t('my_ads'),
					'countVar'         => 0,
					'countCustomClass' => ' count-threads-with-new-messages',
					'inDropdown'       => true,
					'isActive'         => (request()->segment(2) == 'messages'),
				],
			];
		}
		
		$myAccount = [
			'name'       => t('My Account'),
			'url'        => url('account'),
			'icon'       => 'fas fa-cog',
			'group'      => t('My Account'),
			'countVar'   => null,
			'inDropdown' => true,
			'isActive'   => (request()->segment(1) == 'account' && request()->segment(2) == null),
		];
		
		$menuArray[] = $myAccount;
		
		if (app('impersonate')->isImpersonating()) {
			$logOut = [
				'name'       => t('Leave'),
				'url'        => route('impersonate.leave'),
				'icon'       => 'fas fa-sign-out-alt',
				'group'      => t('My Account'),
				'countVar'   => null,
				'inDropdown' => true,
				'isActive'   => false,
			];
		} else {
			$logOut = [
				'name'       => t('log_out'),
				'url'        => UrlGen::logout(),
				'icon'       => 'fas fa-sign-out-alt',
				'group'      => t('My Account'),
				'countVar'   => null,
				'inDropdown' => true,
				'isActive'   => false,
			];
		}
		
		$closeAccount = [
			'name'       => t('Close account'),
			'url'        => url('account/close'),
			'icon'       => 'fas fa-times-circle',
			'group'      => t('My Account'),
			'countVar'   => null,
			'inDropdown' => false,
			'isActive'   => (request()->segment(2) == 'close'),
		];
		
		if (auth()->user()->can(Permission::getStaffPermissions())) {
			$adminPanel = [
				'name'       => t('admin_panel'),
				'url'        => admin_url('/'),
				'icon'       => 'fas fa-cogs',
				'group'      => t('admin_panel'),
				'countVar'   => null,
				'inDropdown' => true,
				'isActive'   => false,
			];
		}
		
		if (isset($adminPanel) && !empty($adminPanel)) {
			array_push($menuArray, $logOut, $closeAccount, $adminPanel);
		} else {
			array_push($menuArray, $logOut, $closeAccount);
		}
		
		// Set missed information
		return collect($menuArray)->map(function ($item, $key) {
			// countCustomClass
			$item['countCustomClass'] = (isset($item['countCustomClass'])) ? $item['countCustomClass'] : '';
			
			// path
			$tmp = [];
			preg_match('|(account.*)|ui', $item['url'], $tmp);
			$item['path'] = (isset($tmp[1])) ? $tmp[1] : '-1';
			$item['path'] = str_replace(['account', '/'], '', $item['path']);
			
			return $item;
		});
	}
}
