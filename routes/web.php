<?php

use App\Http\Controllers\Web\Account\CloseController;
use App\Http\Controllers\Web\Account\CompanyController as AccountCompanyController;
use App\Http\Controllers\Web\Account\EditController as AccountEditController;
use App\Http\Controllers\Web\Account\MessagesController;
use App\Http\Controllers\Web\Account\PostsController;
use App\Http\Controllers\Web\Account\ResumeController as AccountResumeController;
use App\Http\Controllers\Web\Account\SavedSearchesController;
use App\Http\Controllers\Web\Account\TransactionsController;
use App\Http\Controllers\Web\Ajax\CategoryController as AjaxCategoryController;
use App\Http\Controllers\Web\Ajax\LocationController;
use App\Http\Controllers\Web\Ajax\PostController as AjaxPostController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\SocialController;
use App\Http\Controllers\Web\CountriesController;
use App\Http\Controllers\Web\FileController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\Install\InstallController;
use App\Http\Controllers\Web\Install\UpdateController;
use App\Http\Controllers\Web\Locale\LocaleController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\Post\CreateOrEdit\MultiSteps\CreateController;
use App\Http\Controllers\Web\Post\CreateOrEdit\MultiSteps\EditController;
use App\Http\Controllers\Web\Post\CreateOrEdit\MultiSteps\PaymentController;
use App\Http\Controllers\Web\Post\CreateOrEdit\SingleStep\CreateController as SingleCreateController;
use App\Http\Controllers\Web\Post\CreateOrEdit\SingleStep\EditController as SingleEditController;
use App\Http\Controllers\Web\Post\DetailsController;
use App\Http\Controllers\Web\Post\ReportController;
use App\Http\Controllers\Web\Search\CategoryController;
use App\Http\Controllers\Web\Search\CityController;
use App\Http\Controllers\Web\Search\CompanyController;
use App\Http\Controllers\Web\Search\SearchController;
use App\Http\Controllers\Web\Search\TagController;
use App\Http\Controllers\Web\Search\UserController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SitemapsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/


/*
|--------------------------------------------------------------------------
| Upgrading
|--------------------------------------------------------------------------
|
| The upgrading process routes
|
*/
Route::namespace('App\Http\Controllers\Web\Install')
	->controller(UpdateController::class)
	->middleware(['web', 'no.http.cache'])
	->group(function () {
		Route::get('upgrade', 'index');
		Route::post('upgrade/run', 'run');
	});


/*
|--------------------------------------------------------------------------
| Installation
|--------------------------------------------------------------------------
|
| The installation process routes
|
*/
Route::namespace('App\Http\Controllers\Web\Install')
	->controller(InstallController::class)
	->middleware(['web', 'install.checker', 'no.http.cache'])
	->prefix('install')
	->group(function () {
		Route::get('/', 'starting');
		Route::get('site_info', 'siteInfo');
		Route::post('site_info', 'siteInfo');
		Route::get('system_compatibility', 'systemCompatibility');
		Route::get('database', 'database');
		Route::post('database', 'database');
		Route::get('database_import', 'databaseImport');
		Route::get('cron_jobs', 'cronJobs');
		Route::get('finish', 'finish');
	});


/*
|--------------------------------------------------------------------------
| Back-end
|--------------------------------------------------------------------------
|
| The admin panel routes
|
*/
Route::namespace('App\Http\Controllers\Admin')
	->middleware(['web', 'install.checker'])
	->prefix(config('larapen.admin.route', 'admin'))
	->group(__DIR__ . '/web/admin.php');


/*
|--------------------------------------------------------------------------
| Front-end
|--------------------------------------------------------------------------
|
| The not translated front-end routes
|
*/
Route::namespace('App\Http\Controllers\Web')
	->middleware(['web', 'install.checker'])
	->group(function ($router) {
		// Select Language
		Route::namespace('Locale')
			->group(function ($router) {
				Route::get('locale/{code}', [LocaleController::class, 'setLocale']);
			});
		
		// FILES
		Route::controller(FileController::class)
			->prefix('common')
			->group(function ($router) {
				Route::get('file', 'watchMediaContent');
				Route::get('js/fileinput/locales/{code}.js', 'bootstrapFileinputLocales');
				Route::get('js/intl-tel-input/countries.js', 'intlTelInputData');
				Route::get('css/style.css', 'cssStyle');
			});
		
		if (!plugin_exists('domainmapping')) {
			// SITEMAPS (XML)
			Route::get('sitemaps.xml', [SitemapsController::class, 'getAllCountriesSitemapIndex']);
		}
		
		// Impersonate (As admin user, login as another user)
		Route::middleware(['auth'])
			->group(function ($router) {
				Route::impersonate();
			});
	});


/*
|--------------------------------------------------------------------------
| Front-end
|--------------------------------------------------------------------------
|
| The translated front-end routes
|
*/
Route::namespace('App\Http\Controllers\Web')
	->middleware(['web', 'install.checker'])
	->group(function ($router) {
		// Country Code Pattern
		$countryCodePattern = implode('|', array_map('strtolower', array_keys(getCountries())));
		$countryCodePattern = !empty($countryCodePattern) ? $countryCodePattern : 'us';
		/*
		 * NOTE:
		 * '(?i:foo)' : Make 'foo' case-insensitive
		 */
		$countryCodePattern = '(?i:' . $countryCodePattern . ')';
		$router->pattern('countryCode', $countryCodePattern);
		
		
		// HOMEPAGE
		if (!doesCountriesPageCanBeHomepage()) {
			Route::get('/', [HomeController::class, 'index']);
			Route::get(dynamicRoute('routes.countries'), [CountriesController::class, 'index']);
		} else {
			Route::get('/', [CountriesController::class, 'index']);
		}
		
		
		// AUTH
		Route::namespace('Auth')
			->middleware(['guest', 'no.http.cache'])
			->group(function ($router) {
				// Registration Routes...
				Route::controller(RegisterController::class)
					->group(function ($router) {
						Route::get(dynamicRoute('routes.register'), 'showRegistrationForm');
						Route::post(dynamicRoute('routes.register'), 'register');
						Route::get('register/finish', 'finish');
					});
				
				// Authentication Routes...
				Route::controller(LoginController::class)
					->group(function ($router) {
						Route::get(dynamicRoute('routes.login'), 'showLoginForm');
						Route::post(dynamicRoute('routes.login'), 'login');
					});
				
				// Forgot Password Routes...
				Route::controller(ForgotPasswordController::class)
					->group(function ($router) {
						Route::get('password/reset', 'showLinkRequestForm');
						Route::post('password/email', 'sendResetLink');
						
						// Email Address or Phone Number verification
						$router->pattern('field', 'email|phone');
						$router->pattern('token', '.*');
						Route::get('password/{id}/verify/resend/email', 'reSendEmailVerification');
						Route::get('password/{id}/verify/resend/sms', 'reSendPhoneVerification');
						Route::get('password/verify/{field}/{token?}', 'verification');
						Route::post('password/verify/{field}/{token?}', 'verification');
					});
				
				Route::controller(ResetPasswordController::class)
					->group(function ($router) {
						// Reset Password using Token
						Route::get('password/token', 'showTokenRequestForm');
						Route::post('password/token', 'sendResetToken');
						
						// Reset Password using Link (Core Routes...)
						Route::get('password/reset/{token}', 'showResetForm');
						Route::post('password/reset', 'reset');
					});
				
				// Social Authentication
				Route::controller(SocialController::class)
					->group(function ($router) {
						$router->pattern('provider', 'facebook|linkedin|twitter|google');
						Route::get('auth/{provider}', 'redirectToProvider');
						Route::get('auth/{provider}/callback', 'handleProviderCallback');
					});
			});
		
		Route::namespace('Auth')
			->group(function ($router) {
				// Email Address or Phone Number verification
				Route::controller(RegisterController::class)
					->group(function ($router) {
						$router->pattern('field', 'email|phone');
						Route::get('users/{id}/verify/resend/email', 'reSendEmailVerification');
						Route::get('users/{id}/verify/resend/sms', 'reSendPhoneVerification');
						Route::get('users/verify/{field}/{token?}', 'verification');
						Route::post('users/verify/{field}/{token?}', 'verification');
					});
				
				// User Logout
				Route::get(dynamicRoute('routes.logout'), [LoginController::class, 'logout']);
			});
		
		
		// POSTS
		Route::namespace('Post')
			->group(function ($router) {
				$router->pattern('id', '[0-9]+');
				
				$hidPrefix = config('larapen.core.hashableIdPrefix');
				if (is_string($hidPrefix) && !empty($hidPrefix)) {
					$router->pattern('hashableId', '([0-9]+)?(' . $hidPrefix . '[a-z0-9A-Z]{11})?');
				} else {
					$router->pattern('hashableId', '([0-9]+)?([a-z0-9A-Z]{11})?');
				}
				
				// $router->pattern('slug', '.*');
				$bannedSlugs = regexSimilarRoutesPrefixes();
				if (!empty($bannedSlugs)) {
					/*
					 * NOTE:
					 * '^(?!companies|users)$' : Don't match 'companies' or 'users'
					 * '^(?=.*)$'              : Match any character
					 * '^((?!\/).)*$'          : Match any character, but don't match string with '/'
					 */
					$router->pattern('slug', '^(?!' . implode('|', $bannedSlugs) . ')(?=.*)((?!\/).)*$');
				} else {
					$router->pattern('slug', '^(?=.*)((?!\/).)*$');
				}
				
				// SingleStep Post creation
				Route::namespace('CreateOrEdit\SingleStep')
					->controller(SingleCreateController::class)
					->group(function ($router) {
						Route::get('create', 'getForm');
						Route::post('create', 'postForm');
						Route::get('create/finish', 'finish');
						
						// Payment Gateway Success & Cancel
						Route::get('create/payment/success', 'paymentConfirmation');
						Route::get('create/payment/cancel', 'paymentCancel');
						Route::post('create/payment/success', 'paymentConfirmation');
						
						// Email Address or Phone Number verification
						$router->pattern('field', 'email|phone');
						Route::get('posts/{id}/verify/resend/email', 'reSendEmailVerification');
						Route::get('posts/{id}/verify/resend/sms', 'reSendPhoneVerification');
						Route::get('posts/verify/{field}/{token?}', 'verification');
						Route::post('posts/verify/{field}/{token?}', 'verification');
					});
				
				// MultiSteps Post creation
				Route::namespace('CreateOrEdit\MultiSteps')
					->controller(CreateController::class)
					->group(function ($router) {
						Route::get('posts/create', 'getPostStep');
						Route::post('posts/create', 'postPostStep');
						Route::get('posts/create/payment', 'getPaymentStep');
						Route::post('posts/create/payment', 'postPaymentStep');
						Route::post('posts/create/finish', 'finish');
						Route::get('posts/create/finish', 'finish');
						
						// Payment Gateway Success & Cancel
						Route::get('posts/create/payment/success', 'paymentConfirmation');
						Route::post('posts/create/payment/success', 'paymentConfirmation');
						Route::get('posts/create/payment/cancel', 'paymentCancel');
						
						// Email Address or Phone Number verification
						$router->pattern('field', 'email|phone');
						Route::get('posts/{id}/verify/resend/email', 'reSendEmailVerification');
						Route::get('posts/{id}/verify/resend/sms', 'reSendPhoneVerification');
						Route::get('posts/verify/{field}/{token?}', 'verification');
						Route::post('posts/verify/{field}/{token?}', 'verification');
					});
				
				Route::middleware(['auth'])
					->group(function ($router) {
						$router->pattern('id', '[0-9]+');
						
						// SingleStep Post edition
						Route::namespace('CreateOrEdit\SingleStep')
							->controller(SingleEditController::class)
							->group(function ($router) {
								Route::get('edit/{id}', 'getForm');
								Route::put('edit/{id}', 'postForm');
								
								// Payment Gateway Success & Cancel
								Route::get('edit/{id}/payment/success', 'paymentConfirmation');
								Route::get('edit/{id}/payment/cancel', 'paymentCancel');
								Route::post('edit/{id}/payment/success', 'paymentConfirmation');
							});
						
						// MultiSteps Post edition
						Route::namespace('CreateOrEdit\MultiSteps')
							->group(function ($router) {
								Route::controller(EditController::class)
									->group(function ($router) {
										Route::get('posts/{id}/edit', 'getForm');
										Route::put('posts/{id}/edit', 'postForm');
									});
								Route::controller(PaymentController::class)
									->group(function ($router) {
										Route::get('posts/{id}/payment', 'getForm');
										Route::post('posts/{id}/payment', 'postForm');
										
										// Payment Gateway Success & Cancel
										Route::get('posts/{id}/payment/success', 'paymentConfirmation');
										Route::post('posts/{id}/payment/success', 'paymentConfirmation');
										Route::get('posts/{id}/payment/cancel', 'paymentCancel');
									});
							});
					});
				
				// Post's Details
				Route::get(dynamicRoute('routes.post'), [DetailsController::class, 'index']);
				
				// Send report abuse
				Route::controller(ReportController::class)
					->group(function ($router) {
						Route::get('posts/{hashableId}/report', 'showReportForm');
						Route::post('posts/{hashableId}/report', 'sendReport');
					});
			});
		
		
		Route::namespace('Search')
			->group(function ($router) {
				Route::post('send-by-email', [SearchController::class, 'sendByEmail']);
			});
		
		
		// ACCOUNT
		Route::namespace('Account')
			->prefix('account')
			->group(function ($router) {
				// Messenger
				// Contact Job's Author
				Route::post('messages/posts/{id}', [MessagesController::class, 'store']);
				
				Route::middleware(['auth', 'banned.user', 'no.http.cache'])
					->group(function ($router) {
						$router->pattern('id', '[0-9]+');
						
						// Users
						Route::controller(AccountEditController::class)
							->group(function ($router) {
								Route::get('/', 'index');
								Route::middleware(['impersonate.protect'])
									->group(function ($router) {
										Route::put('/', 'updateDetails');
										Route::put('settings', 'updateDetails');
										Route::put('photo', 'updatePhoto');
										Route::put('photo/delete', 'updatePhoto');
									});
							});
						Route::controller(CloseController::class)
							->group(function ($router) {
								Route::get('close', 'index');
								Route::middleware(['impersonate.protect'])
									->group(function ($router) {
										Route::post('close', 'submit');
									});
							});
						
						// Companies
						Route::prefix('companies')
							->controller(AccountCompanyController::class)
							->group(function ($router) {
								Route::get('/', 'index');
								Route::get('create', 'create');
								Route::post('/', 'store');
								Route::get('{id}', 'show');
								Route::get('{id}/edit', 'edit');
								Route::put('{id}', 'update');
								Route::get('{id}/delete', 'destroy');
								Route::post('delete', 'destroy');
							});
						
						// Resumes
						Route::prefix('resumes')
							->controller(AccountResumeController::class)
							->group(function ($router) {
								Route::get('/', 'index');
								Route::get('create', 'create');
								Route::post('/', 'store');
								Route::get('{id}', 'show');
								Route::get('{id}/edit', 'edit');
								Route::put('{id}', 'update');
								Route::get('{id}/delete', 'destroy');
								Route::post('delete', 'destroy');
							});
						
						// Posts
						Route::controller(PostsController::class)
							->prefix('posts')
							->group(function ($router) {
								$router->pattern('pagePath', '(list|archived|pending-approval|favourite)+');
								Route::get('{pagePath}', 'getPage');
								Route::get('{pagePath}/{id}/delete', 'destroy');
								Route::post('{pagePath}/delete', 'destroy');
								
								Route::get('list/{id}/offline', 'index');
								Route::get('archived/{id}/repost', 'archivedPosts');
							});
						
						// Saved Searches
						Route::controller(SavedSearchesController::class)
							->prefix('saved-searches')
							->group(function ($router) {
								$router->pattern('id', '[0-9]+');
								Route::get('/', 'index');
								Route::get('{id}', 'show');
								Route::get('{id}/delete', 'destroy');
								Route::post('delete', 'destroy');
							});
						
						// Messenger
						Route::controller(MessagesController::class)
							->prefix('messages')
							->group(function ($router) {
								$router->pattern('id', '[0-9]+');
								Route::post('check-new', 'checkNew');
								Route::get('/', 'index');
								Route::post('/', 'store');
								Route::get('{id}', 'show');
								Route::put('{id}', 'update');
								Route::get('{id}/actions', 'actions');
								Route::post('actions', 'actions');
								Route::get('{id}/delete', 'destroy');
								Route::post('delete', 'destroy');
							});
						
						// Transactions
						Route::get('transactions', [TransactionsController::class, 'index']);
					});
			});
		
		
		// AJAX
		Route::namespace('Ajax')
			->prefix('ajax')
			->group(function ($router) {
				Route::controller(LocationController::class)
					->group(function ($router) {
						$router->pattern('id', '[0-9]+');
						Route::get('countries/{countryCode}/admins/{adminType}', 'getAdmins');
						Route::get('countries/{countryCode}/admins/{adminType}/{adminCode}/cities', 'getCities');
						Route::get('countries/{countryCode}/cities/{id}', 'getSelectedCity');
						Route::post('countries/{countryCode}/cities/autocomplete', 'searchedCities');
						Route::post('countries/{countryCode}/admin1/cities', 'getAdmin1WithCities');
					});
				Route::controller(AjaxCategoryController::class)
					->group(function ($router) {
						Route::post('category/select-category', 'getCategoriesHtml');
					});
				Route::controller(AjaxPostController::class)
					->group(function ($router) {
						Route::post('save/post', 'savePost');
						Route::post('save/search', 'saveSearch');
						Route::post('post/phone', 'getPhone');
					});
			});
		
		
		// FEEDS
		Route::feeds();
		
		
		if (!plugin_exists('domainmapping')) {
			// SITEMAPS (XML)
			Route::controller(SitemapsController::class)
				->group(function ($router) {
					Route::get('{countryCode}/sitemaps.xml', 'getSitemapIndexByCountry');
					Route::get('{countryCode}/sitemaps/pages.xml', 'getPagesSitemapByCountry');
					Route::get('{countryCode}/sitemaps/categories.xml', 'getCategoriesSitemapByCountry');
					Route::get('{countryCode}/sitemaps/cities.xml', 'getCitiesSitemapByCountry');
					Route::get('{countryCode}/sitemaps/posts.xml', 'getListingsSitemapByCountry');
				});
		}
		
		
		// PAGES
		Route::controller(PageController::class)
			->group(function ($router) {
				Route::get(dynamicRoute('routes.pricing'), 'pricing');
				Route::get(dynamicRoute('routes.pageBySlug'), 'cms');
				Route::get(dynamicRoute('routes.contact'), 'contact');
				Route::post(dynamicRoute('routes.contact'), 'contactPost');
			});
		
		
		// SITEMAP (HTML)
		Route::get(dynamicRoute('routes.sitemap'), [SitemapController::class, 'index']);
		
		
		// SEARCH
		Route::group(['namespace' => 'Search'], function ($router) {
			$router->pattern('id', '[0-9]+');
			$router->pattern('username', '[a-zA-Z0-9]+');
			Route::get(dynamicRoute('routes.companies'), [CompanyController::class, 'index']);
			Route::get(dynamicRoute('routes.search'), [SearchController::class, 'index']);
			Route::get(dynamicRoute('routes.searchPostsByUserId'), [UserController::class, 'index']);
			Route::get(dynamicRoute('routes.searchPostsByUsername'), [UserController::class, 'profile']);
			Route::get(dynamicRoute('routes.searchPostsByCompanyId'), [CompanyController::class, 'profile']);
			Route::get(dynamicRoute('routes.searchPostsByTag'), [TagController::class, 'index']);
			Route::get(dynamicRoute('routes.searchPostsByCity'), [CityController::class, 'index']);
			Route::get(dynamicRoute('routes.searchPostsBySubCat'), [CategoryController::class, 'index']);
			Route::get(dynamicRoute('routes.searchPostsByCat'), [CategoryController::class, 'index']);
		});
	});
