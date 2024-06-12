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

namespace App\Http\Controllers\Web\Post;

use App\Helpers\Arr;
use App\Helpers\UrlGen;
use App\Http\Controllers\Web\Post\Traits\CatBreadcrumbTrait;
use App\Models\Package;
use App\Http\Controllers\Web\FrontController;
use App\Models\Resume;
use Larapen\LaravelMetaTags\Facades\MetaTag;

class DetailsController extends FrontController
{
	use CatBreadcrumbTrait;
	
	/**
	 * DetailsController constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->middleware(function ($request, $next) {
			$this->commonQueries();
			
			return $next($request);
		});
	}
	
	/**
	 * Common Queries
	 */
	public function commonQueries()
	{
		// Count Packages
		$countPackages = Package::applyCurrency()->count();
		view()->share('countPackages', $countPackages);
		
		// Count Payment Methods
		view()->share('countPaymentMethods', $this->countPaymentMethods);
	}
	
	/**
	 * Show Post's Details.
	 *
	 * @param $postId
	 * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function index($postId)
	{
		// Get and Check the Controller's Method Parameters
		$parameters = request()->route()->parameters();
		
		// Check if the Post's ID key exists
		$idKey = array_key_exists('hashableId', $parameters) ? 'hashableId' : 'id';
		$idKeyDoesNotExist = (
			empty($parameters[$idKey])
			|| (!isHashedId($parameters[$idKey]) && !is_numeric($parameters[$idKey]))
		);
		
		// Show 404 error if the Post's ID key cannot be found
		abort_if($idKeyDoesNotExist, 404);
		
		// Set the Parameters
		$postId = $parameters[$idKey];
		$slug = $parameters['slug'] ?? null;
		
		// Forcing 301 redirection for hashed (or non-hashed) ID to update links in search engine indexes
		if (config('settings.seo.listing_hashed_id_seo_redirection')) {
			if (config('settings.seo.listing_hashed_id_enabled') && !isHashedId($postId) && is_numeric($postId)) {
				// Don't lose important notification, so we need to persist your flash data for the request (the redirect request)
				request()->session()->reflash();
				
				$uri = UrlGen::postPathBasic(hashId($postId), $slug);
				
				return redirect($uri, 301)->withHeaders(config('larapen.core.noCacheHeaders'));
			}
			if (!config('settings.seo.listing_hashed_id_enabled') && isHashedId($postId) && !is_numeric($postId)) {
				// Don't lose important notification, so we need to persist your flash data for the request (the redirect request)
				request()->session()->reflash();
				
				$uri = UrlGen::postPathBasic(hashId($postId, true), $slug);
				
				return redirect($uri, 301)->withHeaders(config('larapen.core.noCacheHeaders'));
			}
		}
		
		// Decode Hashed ID
		$postId = hashId($postId, true) ?? $postId;
		
		// Call API endpoint
		$endpoint = '/posts/' . $postId;
		$queryParams = [
			'detailed' => 1,
		];
		$queryParams = array_merge(request()->all(), $queryParams);
		$headers = session()->has('postIsVisited') ? ['X-VISITED-BY-SAME-SESSION' => $postId] : [];
		$data = makeApiRequest('get', $endpoint, $queryParams, $headers);
		
		$message = $this->handleHttpError($data);
		$post = data_get($data, 'result');
		
		// Listing not found
		if (empty($post)) {
			abort(404, $message ?? t('post_not_found'));
		}
		
		session()->put('postIsVisited', $postId);
		
		// Get possible post's registered Author (That's NOT the logged user)
		$user = data_get($post, 'user');
		
		// Get the logged user's resumes
		$resumes = $this->getLoggedUserResumes();
		$totalResumes = count($resumes);
		
		// Get the user's latest Resume
		$lastResume = [];
		if (isset($resumes[0])) {
			$lastResume = $resumes[0];
		}
		
		// Get ad's user decision about comments activation
		$commentsAreDisabledByUser = (data_get($user, 'disable_comments') == 1);
		
		// Category Breadcrumb
		$catBreadcrumb = $this->getCatBreadcrumb(data_get($post, 'category'), 1);
		
		// GET SIMILAR POSTS
		$widgetSimilarPosts = $this->similarPosts(data_get($post, 'id'));
		
		// Meta Tags
		[$title, $description, $keywords] = getMetaTag('listingDetails');
		$title = str_replace('{ad.title}', data_get($post, 'title'), $title);
		$title = str_replace('{location.name}', data_get($post, 'city.name'), $title);
		$description = str_replace('{ad.description}', str(str_strip(strip_tags(data_get($post, 'description'))))->limit(200), $description);
		$keywords = str_replace('{ad.tags}', str_replace(',', ', ', @implode(',', data_get($post, 'tags'))), $keywords);
		
		$title = removeUnmatchedPatterns($title);
		$description = removeUnmatchedPatterns($description);
		$keywords = removeUnmatchedPatterns($keywords);
		
		// Fallback
		if (empty($title)) {
			$title = data_get($post, 'title') . ', ' . data_get($post, 'city.name');
		}
		if (empty($description)) {
			$description = str(str_strip(strip_tags(data_get($post, 'description'))))->limit(200);
		}
		
		MetaTag::set('title', $title);
		MetaTag::set('description', $description);
		MetaTag::set('keywords', $keywords);
		
		// Open Graph
		$this->og->title($title)->description($description)->type('article');
		if (!empty(data_get($post, 'logo_url_big'))) {
			if ($this->og->has('image')) {
				$this->og->forget('image')->forget('image:width')->forget('image:height');
			}
			$this->og->image(data_get($post, 'logo_url_big'), [
				'width'  => 600,
				'height' => 600,
			]);
		}
		view()->share('og', $this->og);
		
		return appView('post.details', compact(
			'post', 'user', 'catBreadcrumb',
			'resumes', 'totalResumes', 'lastResume', // <--- Required in job apply form
			'commentsAreDisabledByUser', 'widgetSimilarPosts'
		));
	}
	
	/**
	 * @param $postId
	 * @return array|mixed|\stdClass|null
	 */
	public function similarPosts($postId)
	{
		$post = null;
		$posts = [];
		$totalPosts = 0;
		$widgetSimilarPosts = null;
		$message = null;
		
		// GET SIMILAR POSTS
		if (in_array(config('settings.single.similar_listings'), ['1', '2'])) {
			// Call API endpoint
			$endpoint = '/posts';
			$queryParams = [
				'op'       => 'similar',
				'postId'   => $postId,
				'distance' => 50, // km OR miles
			];
			$queryParams = array_merge(request()->all(), $queryParams);
			$headers = [
				'X-WEB-CONTROLLER' => class_basename(get_class($this)),
			];
			$data = makeApiRequest('get', $endpoint, $queryParams, $headers);
			
			$message = data_get($data, 'message');
			$posts = data_get($data, 'result.data');
			$totalPosts = data_get($data, 'extra.count.0');
			$post = data_get($data, 'extra.preSearch.post');
		}
		
		if (config('settings.single.similar_listings') == '1') {
			// Featured Area Data
			$widgetSimilarPosts = [
				'title'      => t('Similar Jobs'),
				'link'       => UrlGen::category(data_get($post, 'category')),
				'posts'      => $posts,
				'totalPosts' => $totalPosts,
				'message'    => $message,
			];
			$widgetSimilarPosts = ($totalPosts > 0) ? Arr::toObject($widgetSimilarPosts) : null;
		} else if (config('settings.single.similar_listings') == '2') {
			$distance = 50; // km OR miles
			
			// Featured Area Data
			$widgetSimilarPosts = [
				'title'      => t('more_jobs_at_x_distance_around_city', [
					'distance' => $distance,
					'unit'     => getDistanceUnit(config('country.code')),
					'city'     => data_get($post, 'city.name'),
				]),
				'link'       => UrlGen::city(data_get($post, 'city')),
				'posts'      => $posts,
				'totalPosts' => $totalPosts,
				'message'    => $message,
			];
			$widgetSimilarPosts = ($totalPosts > 0) ? Arr::toObject($widgetSimilarPosts) : null;
		}
		
		return $widgetSimilarPosts;
	}
	
	/**
	 * Get the logged user's resumes in view
	 *
	 * @return array
	 */
	private function getLoggedUserResumes(): array
	{
		if (!auth()->check()) {
			return [];
		}
		
		$user = auth()->user();
		
		// Get the User's Resumes
		$limit = config('larapen.core.selectResumeInto', 5);
		$cacheId = 'resumes.take.' . $limit . '.where.user.' . $user->id;
		$resumes = cache()->remember($cacheId, $this->cacheExpiration, function () use ($user, $limit) {
			return Resume::where('user_id', $user->id)->take($limit)->orderByDesc('id')->get();
		});
		
		return $resumes->toArray();
	}
}
