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

use App\Http\Controllers\Admin\ActionController;
use App\Http\Controllers\Admin\AdvertisingController;
use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BlacklistController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FileController;
use App\Http\Controllers\Admin\GenderController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\InlineRequestController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MetaTagController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\Panel\Library\PanelRoutes;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PictureController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostTypeController;
use App\Http\Controllers\Admin\ReportTypeController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SalaryTypeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubAdmin1Controller;
use App\Http\Controllers\Admin\SubAdmin2Controller;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Auth
Route::namespace('Auth')
	->group(function ($router) {
		// Authentication Routes...
		Route::controller(LoginController::class)
			->group(function ($router) {
				Route::get('login', 'showLoginForm')->name('login');
				Route::post('login', 'login');
				Route::get('logout', 'logout')->name('logout');
			});
		
		// Password Reset Routes...
		Route::controller(ForgotPasswordController::class)
			->group(function ($router) {
				Route::get('password/reset', 'showLinkRequestForm')->name('password.request');
				Route::post('password/email', 'sendResetLinkEmail')->name('password.email');
			});
	});

// Admin Panel Area
Route::middleware(['admin', 'clearance', 'banned.user', 'no.http.cache'])
	->group(function ($router) {
		// Dashboard
		Route::controller(DashboardController::class)
			->group(function ($router) {
				Route::get('dashboard', 'dashboard');
				Route::get('/', 'redirect');
			});
		
		// Extra (must be called before CRUD)
		Route::get('homepage/{action}', [HomeSectionController::class, 'reset'])->where('action', 'reset_(.*)');
		Route::controller(LanguageController::class)
			->group(function ($router) {
				Route::get('languages/sync_files', 'syncFilesLines');
				Route::get('languages/texts/{lang?}/{file?}', 'showTexts')->where('lang', '[^/]*')->where('file', '[^/]*');
				Route::post('languages/texts/{lang}/{file}', 'updateTexts')->where('lang', '[^/]+')->where('file', '[^/]+');
			});
		Route::get('permissions/create_default_entries', [PermissionController::class, 'createDefaultEntries']);
		Route::get('blacklists/add', [BlacklistController::class, 'banUser']);
		Route::get('categories/rebuild-nested-set-nodes', [CategoryController::class, 'rebuildNestedSetNodes']);
		
		// Panel's Default Routes
		PanelRoutes::resource('advertisings', AdvertisingController::class);
		PanelRoutes::resource('blacklists', BlacklistController::class);
		PanelRoutes::resource('categories', CategoryController::class);
		PanelRoutes::resource('categories/{catId}/subcategories', CategoryController::class);
		PanelRoutes::resource('cities', CityController::class);
		PanelRoutes::resource('companies', CompanyController::class);
		PanelRoutes::resource('countries', CountryController::class);
		PanelRoutes::resource('countries/{countryCode}/cities', CityController::class);
		PanelRoutes::resource('countries/{countryCode}/admins1', SubAdmin1Controller::class);
		PanelRoutes::resource('currencies', CurrencyController::class);
		PanelRoutes::resource('genders', GenderController::class);
		PanelRoutes::resource('homepage', HomeSectionController::class);
		PanelRoutes::resource('admins1/{admin1Code}/cities', CityController::class);
		PanelRoutes::resource('admins1/{admin1Code}/admins2', SubAdmin2Controller::class);
		PanelRoutes::resource('admins2/{admin2Code}/cities', CityController::class);
		PanelRoutes::resource('languages', LanguageController::class);
		PanelRoutes::resource('meta_tags', MetaTagController::class);
		PanelRoutes::resource('packages', PackageController::class);
		PanelRoutes::resource('pages', PageController::class);
		PanelRoutes::resource('payments', PaymentController::class);
		PanelRoutes::resource('payment_methods', PaymentMethodController::class);
		PanelRoutes::resource('permissions', PermissionController::class);
		PanelRoutes::resource('pictures', PictureController::class);
		PanelRoutes::resource('posts', PostController::class);
		PanelRoutes::resource('p_types', PostTypeController::class);
		PanelRoutes::resource('report_types', ReportTypeController::class);
		PanelRoutes::resource('roles', RoleController::class);
		PanelRoutes::resource('salary_types', SalaryTypeController::class);
		PanelRoutes::resource('settings', SettingController::class);
		PanelRoutes::resource('users', UserController::class);
		
		// Others
		Route::get('account', [UserController::class, 'account']);
		Route::post('ajax/{table}/{field}', [InlineRequestController::class, 'make'])
			->where('table', '[^/]+')
			->where('field', '[^/]+');
		
		// Backup
		Route::controller(BackupController::class)
			->group(function ($router) {
				Route::get('backups', 'index');
				Route::put('backups/create', 'create');
				Route::get('backups/download', 'download');
				Route::delete('backups/delete', 'delete');
			});
		
		// Actions
		Route::controller(ActionController::class)
			->group(function ($router) {
				Route::get('actions/clear_cache', 'clearCache');
				Route::get('actions/clear_images_thumbnails', 'clearImagesThumbnails');
				Route::get('actions/maintenance/{mode}', 'maintenance')->where('mode', 'down|up');
			});
		
		// Re-send Email or Phone verification message
		Route::controller(UserController::class)
			->group(function ($router) {
				$router->pattern('id', '[0-9]+');
				Route::get('users/{id}/verify/resend/email', 'reSendEmailVerification');
				Route::get('users/{id}/verify/resend/sms', 'reSendPhoneVerification');
			});
		Route::controller(PostController::class)
			->group(function ($router) {
				$router->pattern('id', '[0-9]+');
				Route::get('posts/{id}/verify/resend/email', 'reSendEmailVerification');
				Route::get('posts/{id}/verify/resend/sms', 'reSendPhoneVerification');
			});
		
		// Plugins
		Route::controller(PluginController::class)
			->group(function ($router) {
				$router->pattern('plugin', '.+');
				Route::get('plugins', 'index');
				Route::post('plugins/{plugin}/install', 'install');
				Route::get('plugins/{plugin}/install', 'install');
				Route::get('plugins/{plugin}/uninstall', 'uninstall');
				Route::get('plugins/{plugin}/delete', 'delete');
			});
		
		// System Info
		Route::get('system', [SystemController::class, 'systemInfo']);
	});

// Files (JS, CSS, ...)
Route::controller(FileController::class)
	->prefix('common')
	->group(function ($router) {
		Route::get('js/intl-tel-input/countries.js', 'intlTelInputData');
	});
