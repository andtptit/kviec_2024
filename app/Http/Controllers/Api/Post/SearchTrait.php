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

namespace App\Http\Controllers\Api\Post;

use App\Helpers\Search\PostQueries;
use App\Http\Controllers\Api\Post\Search\CategoryTrait;
use App\Http\Controllers\Api\Post\Search\LocationTrait;
use App\Http\Controllers\Api\Post\Search\SidebarTrait;
use App\Http\Resources\EntityCollection;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Scopes\ReviewedScope;
use App\Models\Scopes\VerifiedScope;
use Larapen\LaravelDistance\Libraries\mysql\DistanceHelper;

trait SearchTrait
{
	use CategoryTrait, LocationTrait, SidebarTrait;
	
	/**
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function getPosts()
	{
		// Create the MySQL Distance Calculation function, If doesn't exist
		$distanceCalculationFormula = config('settings.list.distance_calculation_formula', 'haversine');
		if (!DistanceHelper::checkIfDistanceCalculationFunctionExists($distanceCalculationFormula)) {
			DistanceHelper::createDistanceCalculationFunction($distanceCalculationFormula);
		}
		
		$preSearch = [];
		
		$options = ['search', 'sponsored', 'latest', 'similar'];
		if (in_array(request()->get('op'), $options)) {
			$embed = ['user', 'category', 'parent', 'postType', 'city', 'savedByLoggedUser', 'pictures', 'latestPayment', 'package', 'company'];
			request()->query->add(['embed' => implode(',', $embed)]);
			
			$posts = [];
			if (request()->get('op') == 'search') {
				$searchData = $this->searchPosts($preSearch);
				$preSearch = $searchData['preSearch'] ?? $preSearch;
				
				$data = [
					'success' => true,
					'message' => $searchData['message'] ?? null,
					'result'  => $searchData['posts'],
					'extra'   => [
						'count'     => $searchData['count'] ?? [],
						'preSearch' => $preSearch,
						'sidebar'   => $this->getSidebar($preSearch),
						'tags'      => $searchData['tags'] ?? [],
					],
				];
				
				return $this->apiResponse($data);
			} else if (request()->get('op') == 'sponsored') {
				$posts = $this->sponsoredPosts();
			} else if (request()->get('op') == 'latest') {
				$posts = $this->latestPosts();
			} else if (request()->get('op') == 'similar' && request()->filled('postId')) {
				$res = $this->similarPosts(request()->get('postId'), request()->get('distance'));
				$posts = $res['posts'] ?? [];
				$post = $res['post'] ?? null;
				
				$postResource = new PostResource($post);
				$postApiResult = $this->respondWithResource($postResource)->getData(true);
				$post = data_get($postApiResult, 'result');
			}
			
			$resourceCollection = new EntityCollection(class_basename($this), $posts);
			
			if (!empty($posts)) {
				$totalPosts = $posts->count();
				$message = ($totalPosts <= 0) ? t('no_posts_found') : null;
			} else {
				$totalPosts = 0;
				$message = t('no_posts_found');
			}
			
			$data = [
				'success' => true,
				'message' => $message,
				'result'  => $resourceCollection,
				'extra'   => [
					'count' => [$totalPosts],
				],
			];
			if (isset($post) && !empty($post)) {
				$data['extra']['preSearch'] = ['post' => $post];
			}
			
			return $this->apiResponse($data);
		}
		
		if (!isset($posts)) {
			$posts = $this->normalQuery();
		}
		
		$resourceCollection = new EntityCollection(class_basename($this), $posts);
		$message = ($posts->count() <= 0) ? t('no_posts_found') : null;
		$resourceCollection = $this->respondWithCollection($resourceCollection, $message);
		
		$data = json_decode($resourceCollection->content(), true);
		$data['extra'] = [
			'count'     => $count ?? null,
			'preSearch' => $preSearch,
		];
		
		return $this->apiResponse($data);
	}
	
	/**
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	protected function normalQuery()
	{
		$countryCode = request()->get('country_code', config('country.code'));
		
		$posts = Post::query()->with(['user'])->whereHas('country')->countryOf($countryCode);
		
		if (request()->get('logged')) {
			if (auth('sanctum')->check()) {
				$user = auth('sanctum')->user();
				
				$posts->where('user_id', $user->id);
				
				if (request()->get('pendingApproval')) {
					$posts->withoutGlobalScopes([VerifiedScope::class, ReviewedScope::class])->unverified();
				} else if (request()->get('archived')) {
					$posts->archived();
				} else {
					$posts->verified()->unarchived()->reviewed();
				}
			} else {
				abort(401);
			}
		}
		
		$embed = explode(',', request()->get('embed'));
		
		if (in_array('country', $embed)) {
			$posts->with('country');
		}
		if (in_array('user', $embed)) {
			$posts->with('user');
		}
		if (in_array('category', $embed)) {
			$posts->with('category');
		}
		if (in_array('postType', $embed)) {
			$posts->with('postType');
		}
		if (in_array('city', $embed)) {
			$posts->with('city');
		}
		if (in_array('latestPayment', $embed)) {
			if (in_array('package', $embed)) {
				$posts->with(['latestPayment' => fn ($builder) => $builder->with(['package'])]);
			} else {
				$posts->with('latestPayment');
			}
		}
		
		// Sorting
		$posts = $this->applySorting($posts, ['created_at']);
		
		$posts = $posts->paginate($this->perPage);
		
		// If the request is made from the app's Web environment,
		// use the Web URL as the pagination's base URL
		$posts = setPaginationBaseUrl($posts);
		
		return $posts;
	}
	
	/**
	 * @param $preSearch
	 * @return array
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	protected function searchPosts(&$preSearch)
	{
		$location = $this->getLocation();
		
		$preSearch = [
			'cat'   => $this->getCategory(),
			'city'  => $location['city'] ?? null,
			'admin' => $location['admin'] ?? null,
		];
		
		return (new PostQueries($preSearch))->fetch(['op', 'embed']);
	}
	
	/**
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	protected function sponsoredPosts()
	{
		$type = 'sponsored';
		
		$maxItems = request()->get('maxItems', 20);
		$orderBy = request()->get('orderBy', 'random');
		$cacheExpiration = request()->get('cacheExpiration', $this->cacheExpiration);
		
		if (!is_numeric($maxItems) || (int)$maxItems <= 0) {
			$maxItems = 20;
		}
		if ($orderBy == 'created_at') {
			$orderBy = 'date';
		}
		if (!in_array($orderBy, ['date', 'random'])) {
			$orderBy = 'random';
		}
		if (!is_numeric($cacheExpiration) || (int)$cacheExpiration <= 0) {
			$cacheExpiration = 3600;
		}
		
		$cacheId = config('country.code') . '.home.getPosts.' . $type;
		
		return cache()->remember($cacheId, $cacheExpiration, function () use ($maxItems, $type, $orderBy) {
			return Post::getLatestOrSponsored($maxItems, $type, $orderBy);
		});
	}
	
	/**
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	protected function latestPosts()
	{
		$type = 'latest';
		
		$maxItems = request()->get('maxItems', 20);
		$orderBy = request()->get('orderBy', 'random');
		$cacheExpiration = request()->get('cacheExpiration', $this->cacheExpiration);
		
		if (!is_numeric($maxItems) || (int)$maxItems <= 0) {
			$maxItems = 20;
		}
		if ($orderBy == 'created_at') {
			$orderBy = 'date';
		}
		if (!in_array($orderBy, ['date', 'random'])) {
			$orderBy = 'random';
		}
		if (!is_numeric($cacheExpiration) || (int)$cacheExpiration <= 0) {
			$cacheExpiration = 3600;
		}
		
		$cacheId = config('country.code') . '.home.getPosts.' . $type;
		
		return cache()->remember($cacheId, $cacheExpiration, function () use ($maxItems, $type, $orderBy) {
			return Post::getLatestOrSponsored($maxItems, $type, $orderBy);
		});
	}
	
	/**
	 * @param int|null $postId
	 * @param int|null $distance
	 * @return array
	 */
	protected function similarPosts(?int $postId, ?int $distance = 50): array
	{
		$posts = [];
		
		$cacheId = 'post.withoutGlobalScopes.' . $postId . '.' . config('app.locale');
		$post = cache()->remember($cacheId, $this->cacheExpiration, function () use ($postId) {
			return Post::withoutGlobalScopes([VerifiedScope::class, ReviewedScope::class])
				->with(['category', 'city'])
				->where('id', $postId)
				->first();
		});
		
		if ($post->count() <= 0) {
			return $posts;
		}
		
		$similarPostsLimit = (int)config('settings.single.similar_listings_limit', 10);
		if (config('settings.single.similar_listings') == '1') {
			$cacheId = 'posts.similar.category.' . $post->category_id . '.post.' . $post->id . '.limit.' . $similarPostsLimit;
			$posts = cache()->remember($cacheId, $this->cacheExpiration, function () use ($post, $similarPostsLimit) {
				return $post->getSimilarByCategory($similarPostsLimit);
			});
		}
		
		if (config('settings.single.similar_listings') == '2') {
			$distance = $distance ?? 50; // km OR miles
			$cacheId = 'posts.similar.city.' . $post->city_id . '.post.' . $post->id . '.limit.' . $similarPostsLimit;
			$posts = cache()->remember($cacheId, $this->cacheExpiration, function () use ($post, $distance, $similarPostsLimit) {
				return $post->getSimilarByLocation($distance, $similarPostsLimit);
			});
		}
		
		return ['post' => $post, 'posts' => $posts];
	}
}
