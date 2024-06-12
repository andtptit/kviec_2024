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

namespace App\Exceptions;

use App\Exceptions\Traits\JsonRenderTrait;
use App\Exceptions\Traits\NotificationTrait;
use App\Exceptions\Traits\PluginTrait;
use App\Helpers\Cookie;
use App\Helpers\DBTool;
use App\Helpers\UrlGen;
use Illuminate\Contracts\Container\Container;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Prologue\Alerts\Facades\Alert;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
	use JsonRenderTrait, PluginTrait, NotificationTrait;
	
	/**
	 * A list of the exception types that should not be reported.
	 *
	 * @var array
	 */
	protected $dontReport = [
		//
	];
	
	/**
	 * A list of the inputs that are never flashed for validation exceptions.
	 *
	 * @var array
	 */
	protected $dontFlash = [
		'current_password',
		'password',
		'password_confirmation',
	];
	
	/**
	 * Illuminate request class.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;
	
	/**
	 * Handler constructor.
	 *
	 * @param Container $container
	 */
	public function __construct(Container $container)
	{
		parent::__construct($container);
		
		$this->app = app();
		
		// Fix the 'files' & 'filesystem' binging.
		$this->app->register(\Illuminate\Filesystem\FilesystemServiceProvider::class);
		
		// Create a config var for current language
		$this->getLanguage();
	}
	
	/**
	 * Register the exception handling callbacks for the application.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->reportable(function (Throwable $e) {
			$this->sendNotification($e);
		});
	}
	
	/**
	 * Report or log an exception.
	 *
	 * @param \Throwable $e
	 * @return void
	 * @throws \Throwable
	 */
	public function report(Throwable $e)
	{
		// Prevent error 500 from PDO Exception
		if (appInstallFilesExist()) {
			if ($this->isPDOException($e)) {
				if (
					str_contains($e->getMessage(), 'max_user_connections')
					&& str_contains($e->getMessage(), 'active connections')
				) {
					// Too many connections
					$message = 'We are currently receiving a large number of connections. ';
					$message .= 'Please try again later. We apologize for the inconvenience.';
					$message = '<pre>' . $message . '</pre>';
					
					die($message);
				}
				
				if (($res = $this->testDatabaseConnection()) !== true) {
					die($res);
				}
			}
		} else {
			// Clear PDO error log during installation
			if ($this->isPDOException($e)) {
				$this->clearLog();
			}
		}
		
		parent::report($e);
	}
	
	/**
	 * Render an exception into an HTTP response.
	 *
	 * @param $request
	 * @param \Throwable $e
	 * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Routing\Redirector|\Symfony\Component\HttpFoundation\Response|void
	 * @throws \Throwable
	 */
	public function render($request, Throwable $e)
	{
		// Restore the request headers back to the original state
		// saved before API call (using sub request option)
		if (config('request.original.headers')) {
			request()->headers->replace(config('request.original.headers'));
		}
		
		// Show API or AJAX requests exceptions
		if (
			isFromApi()
			|| str_starts_with($request->path(), 'api/')
			|| $request->ajax()
			|| $request->expectsJson()
		) {
			return $this->jsonRender($e);
		}
		
		// Show HTTP exceptions
		if ($this->isHttpException($e)) {
			// Check if the app is installed when page is not found (or when 404 page is called),
			// to prevent any DB error when the app is not installed yet.
			if (method_exists($e, 'getStatusCode')) {
				if ($e->getStatusCode() == 404) {
					if (!appIsInstalled() && $request->input('exception') != '404') {
						return redirect(getRawBaseUrl() . '/install?exception=404');
					}
				}
			}
			
			if ($e instanceof PostTooLargeException) {
				$message = 'Maximum data (including files to upload) size to post and memory usage are limited on the server.';
				$message = 'Payload Too Large. ' . $message;
				$backLink = ' <a href="' . url()->previous() . '">' . t('Back') . '</a>';
				$message = $message . $backLink;
				
				abort(500, $message);
			}
			
			// Original Code
			return parent::render($request, $e);
		}
		
		/*
		 * Temporary fix when forms (after failed validation) are not redirect to back with explicit error messages per field
		 * Issue found on type of server: Apache/2.4.52 (Win64) OpenSSL/1.1.1m PHP/8.1.2
		 */
		if ($e instanceof ValidationException) {
			if (method_exists($e, 'errors')) {
				return back()->withErrors($e->errors())->withInput();
			}
		}
		
		// Show caching exception (APC or Redis)
		if (preg_match('#apc_#ui', $e->getMessage()) || preg_match('#/predis/#i', $e->getFile())) {
			$message = $e->getMessage() . "\n";
			if (preg_match('#apc_#ui', $e->getMessage())) {
				$message .= 'This looks like that the <a href="https://www.php.net/manual/en/book.apcu.php" target="_blank">APC extension</a> ';
				$message .= 'is not installed (or not properly installed) for PHP.' . "\n";
			}
			$message .= 'Make sure you have properly installed the components related to the selected cache driver on your server.' . "\n";
			$message .= 'To get your website up and running again you have to change the cache driver in the /.env file ';
			$message .= 'with the "file" or "array" driver (example: CACHE_DRIVER=file).' . "\n";
			
			$data = ['exception' => $e, 'message' => $message];
			
			return response()->view('errors.custom', $data, 500);
		}
		
		// Show DB exceptions
		if ($e instanceof \PDOException) {
			// Check if the app installation files exist,
			// to prevent any DB error (from the Admin Panel) when the app is not installed yet.
			if (!appInstallFilesExist() && $request->input('exception') != 'PDO') {
				$msg = $e->getMessage();
				if (!empty($msg)) {
					dd($msg);
				}
				
				$this->clearLog();
				
				return redirect(getRawBaseUrl() . '/install?exception=PDO');
			}
			
			/*
			 * DB Connection Error:
			 * http://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
			 */
			$dbErrorCodes = ['mysql' => ['1042', '1044', '1045', '1046', '1049'], 'standardized' => ['08S01', '42000', '28000', '3D000', '42000', '42S22'],];
			$tableErrorCodes = ['mysql' => ['1051', '1109', '1146'], 'standardized' => ['42S02'],];
			
			// Database errors
			if (in_array($e->getCode(), $dbErrorCodes['mysql']) || in_array($e->getCode(), $dbErrorCodes['standardized'])) {
				return response()->view('errors.custom', ['exception' => $e], 500);
			}
			
			// Tables and fields errors
			if (in_array($e->getCode(), $tableErrorCodes['mysql']) || in_array($e->getCode(), $tableErrorCodes['standardized'])) {
				$message = 'Some tables of the database are absent.' . "\n";
				$message .= $e->getMessage() . "\n";
				$message .= '1/ Remove all tables from the database (if existing)' . "\n";
				$message .= '2/ Delete the <code>/.env</code> file (required before re-installation)' . "\n";
				$message .= '3/ and reload this page -or- go to install URL: <a href="' . url('install') . '">' . url('install') . '</a>.' . "\n";
				$message .= 'BE CAREFUL: If your site is already in production, you will lose all your data in both cases.' . "\n";
				
				$data = ['exception' => $e, 'message' => $message];
				
				return response()->view('errors.custom', $data, 500);
			}
		}
		
		// Show Token exceptions
		if ($e instanceof TokenMismatchException) {
			$message = t('Your session has expired');
			if (isAdminPanel()) {
				Alert::error($message)->flash();
			} else {
				flash($message)->error();
			}
			$previousUrl = url()->previous();
			if (!str_contains($previousUrl, 'CsrfToken')) {
				$queryString = (parse_url($previousUrl, PHP_URL_QUERY) ? '&' : '?') . 'error=CsrfToken';
				$previousUrl = $previousUrl . $queryString;
			}
			
			return redirect($previousUrl)->withInput();
		}
		
		// Show MethodNotAllowed HTTP exceptions
		if ($e instanceof MethodNotAllowedHttpException) {
			$message = "Whoops! Seems you use a bad request method. Please try again.";
			$backLink = ' <a href="' . url()->previous() . '">' . t('Back') . '</a>';
			$message = $message . $backLink;
			abort(500, $message);
		}
		
		// Try to fix the cookies issue related the Laravel security release:
		// https://laravel.com/docs/5.6/upgrade#upgrade-5.6.30
		if (str_contains($e->getMessage(), 'unserialize()') && request()->get('exception') != 'unserialize') {
			// Unset cookies
			Cookie::forgetAll();
			
			// Customize and Redirect to the previous URL
			$previousUrl = url()->previous();
			$queryString = (parse_url($previousUrl, PHP_URL_QUERY) ? '&' : '?') . 'exception=unserialize';
			$previousUrl = $previousUrl . $queryString;
			
			redirectUrl($previousUrl, 301, config('larapen.core.noCacheHeaders'));
		}
		
		// Customize the HTTP 500 error page
		$filePath = $e->getFile();
		if (!empty($filePath)) {
			// Error not inside '/vendor/' folder
			if (!str_contains($filePath, '/vendor/')) {
				return response()->view('errors.500', ['exception' => $e], 500);
			}
			
			// Error inside composer class loader
			if (str_contains($filePath, '/vendor/composer/ClassLoader.php')) {
				$message = $e->getMessage();
				if (str_contains($message, '/extras/plugins/')) {
					$message = $this->fixForFolderNameIssue($message);
					
					$data = ['exception' => $e, 'message' => $message];
					
					return response()->view('errors.custom', $data, 500);
				}
			}
		}
		
		// Original Code
		return parent::render($request, $e);
	}
	
	/**
	 * Convert an authentication exception into an unauthenticated response.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Illuminate\Auth\AuthenticationException $exception
	 * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
	 */
	protected function unauthenticated($request, AuthenticationException $exception)
	{
		if (
			isFromApi()
			|| str_starts_with($request->path(), 'api/')
			|| $request->expectsJson()
		) {
			$message = $exception->getMessage();
			if (empty($message)) {
				$message = 'Unauthenticated.';
			}
			return $this->respondUnAuthorized($message);
		}
		
		return redirect()->guest(UrlGen::loginPath());
	}
	
	/**
	 * Convert a validation exception into a JSON response.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Illuminate\Validation\ValidationException $exception
	 * @return \Illuminate\Http\JsonResponse
	 */
	protected function invalidJson($request, ValidationException $exception): \Illuminate\Http\JsonResponse
	{
		return response()->json($exception->errors(), $exception->status);
	}
	
	// PRIVATE METHODS
	
	/**
	 * Is a PDO Exception
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	private function isPDOException(Throwable $e): bool
	{
		if (
			($e instanceof \PDOException)
			|| $e->getCode() == 1045
			|| str_contains($e->getMessage(), 'SQLSTATE')
			|| str_contains($e->getFile(), 'Database/Connectors/Connector.php')
		) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Test Database Connection
	 *
	 * @return bool
	 */
	private function testDatabaseConnection(): bool
	{
		$pdo = DBTool::getPDOConnexion();
		
		if ($pdo instanceof \PDO) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Create a config var for current language
	 */
	private function getLanguage()
	{
		// Get the language only the app is already installed
		// to prevent HTTP 500 error through DB connexion during the installation process.
		if (appInstallFilesExist()) {
			$this->app['config']->set('lang.abbr', config('app.locale'));
		}
	}
	
	/**
	 * Clear Laravel Log files
	 */
	private function clearLog()
	{
		$mask = storage_path('logs') . DIRECTORY_SEPARATOR . '*.log';
		$logFiles = glob($mask);
		if (is_array($logFiles) && !empty($logFiles)) {
			foreach ($logFiles as $filename) {
				@unlink($filename);
			}
		}
	}
}
