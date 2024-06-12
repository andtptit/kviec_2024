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

namespace App\Helpers\Search;

use App\Helpers\Search\Traits\Filters;
use App\Helpers\Search\Traits\GroupBy;
use App\Helpers\Search\Traits\Having;
use App\Helpers\Search\Traits\OrderBy;
use App\Helpers\Search\Traits\Relations;
use App\Helpers\Search\Traits\Select;
use App\Http\Controllers\Api\Base\ApiResponseTrait;
use App\Http\Resources\EntityCollection;
use App\Models\Post;

class PostQueries
{
	use Select, Relations, Filters, GroupBy, Having, OrderBy;
	use ApiResponseTrait;
	
	protected static $cacheExpiration = 300; // 5mn (60s * 5)
	
	public $country;
	public $lang;
	public $perPage = 12;
	
	// Pre-Search Objects
	private array $preSearch;
	public $cat = null;
	public $city = null;
	public $admin = null;
	
	// Default Columns Selected
	protected $select = [];
	protected $groupBy = [];
	protected $having = [];
	protected $orderBy = [];
	
	protected $posts;
	protected $postsTable;
	
	// 'queryStringKey' => ['name' => 'column', 'order' => 'direction']
	public $orderByParametersFields = [];
	
	private array $webGlobalQueries = ['countryCode', 'languageCode'];
	private array $webQueriesPerController = [
		'CategoryController' => ['op', 'c', 'sc'],
		'CityController'     => ['op', 'l', 'location', 'r'],
		'TagController'      => ['op', 'tag'],
		'UserController'     => ['op', 'userId', 'username'],
		'CompanyController'  => ['op', 'companyId'],
		'SearchController'   => ['op'],
		'PostsController'    => ['op'], // Account\
	];
	
	/**
	 * PostQueries constructor.
	 *
	 * @param array $preSearch
	 */
	public function __construct(array $preSearch = [])
	{
		// Pre-Search
		if (isset($preSearch['cat']) && !empty($preSearch['cat'])) {
			$this->cat = $preSearch['cat'];
		}
		if (isset($preSearch['city']) && !empty($preSearch['city'])) {
			$this->city = $preSearch['city'];
		}
		if (isset($preSearch['admin']) && !empty($preSearch['admin'])) {
			$this->admin = $preSearch['admin'];
		}
		
		// Entries per page
		$perPage = config('settings.list.items_per_page');
		if (is_numeric($perPage) && $perPage > 1 && $perPage <= 50) {
			$this->perPage = $perPage;
		}
		if (isset($preSearch['perPage']) && !empty($preSearch['perPage']) && is_numeric($preSearch['perPage'])) {
			$this->perPage = $preSearch['perPage'];
		}
		
		// Save preSearch
		if (array_key_exists('perPage', $preSearch)) {
			unset($preSearch['perPage']);
		}
		$this->preSearch = $preSearch;
		
		// Init. Builder
		$this->posts = Post::query();
		$this->postsTable = (new Post())->getTable();
		
		// Add Default Select Columns
		$this->setSelect();
		
		// Relations
		$this->setRelations();
	}
	
	/**
	 * Get the results
	 *
	 * @param array|null $queriesToRemove
	 * @return array
	 */
	public function fetch(?array $queriesToRemove = null): array
	{
		// Apply Requested Filters
		$this->applyFilters();
		
		// Apply Aggregation & Reorder Statements
		$this->applyGroupBy();
		$this->applyHaving();
		$this->applyOrderBy();
		
		// Get Results
		$posts = $this->posts->paginate((int)$this->perPage);
		
		// Remove Distance from Request
		$this->removeDistanceFromRequest();
		
		// If the request is made from the app's Web environment,
		// use the Web URL as the pagination's base URL
		if (isFromTheAppsWebEnvironment()) {
			if (request()->hasHeader('X-WEB-REQUEST-URL')) {
				$posts->setPath(request()->header('X-WEB-REQUEST-URL'));
			}
		}
		
		// Add eventual web queries to $queriesToRemove
		$queriesToRemove = array_merge($queriesToRemove, $this->webGlobalQueries);
		$webController = null;
		if (request()->hasHeader('X-WEB-CONTROLLER')) {
			$webController = request()->header('X-WEB-CONTROLLER');
		}
		if (!empty($webController)) {
			$webQueries = $this->webQueriesPerController[$webController] ?? [];
			$queriesToRemove = array_merge($queriesToRemove, $webQueries);
		}
		
		// Append request queries in the pagination links
		$query = !empty($queriesToRemove)
			? request()->except($queriesToRemove)
			: request()->query();
		$query = collect($query)->map(fn($item) => is_null($item) ? '' : $item)->toArray();
		$posts->appends($query);
		
		// Get Count Results
		$count = [$posts->total()];
		
		// Wrap the listings for API calls
		$postsCollection = new EntityCollection('PostController', $posts);
		$message = ($posts->count() <= 0) ? t('no_posts_found') : null;
		$postsResult = $postsCollection->toResponse(request())->getData(true);
		
		// Add 'user' object in preSearch (If available)
		$this->preSearch['user'] = null;
		$searchBasedOnUser = (request()->filled('userId') || request()->filled('username'));
		if ($searchBasedOnUser) {
			$this->preSearch['user'] = data_get($postsResult, 'data.0.user');
		}
		
		// Add 'company' object in preSearch (If available)
		$this->preSearch['company'] = null;
		$searchBasedOnCompany = (request()->filled('companyId'));
		if ($searchBasedOnCompany) {
			$this->preSearch['company'] = data_get($postsResult, 'data.0.company');
		}
		
		$this->preSearch['distance'] = [
			'default' => self::$defaultDistance,
			'current' => self::$distance,
			'max'     => self::$maxDistance,
		];
		
		// Results Data
		$data = [
			'message'   => $message,
			'count'     => $count,
			'posts'     => $postsResult,
			'distance'  => self::$distance,
			'preSearch' => $this->preSearch,
		];
		
		if (config('settings.list.show_listings_tags')) {
			$data['tags'] = $this->getPostsTags($posts);
		}
		
		return $data;
	}
	
	/**
	 * Get found posts' tags (per page)
	 *
	 * @param $posts
	 * @return array|string|null
	 */
	private function getPostsTags($posts)
	{
		$tags = [];
		if ($posts->count() > 0) {
			foreach ($posts as $post) {
				if (!empty($post->tags)) {
					$tags = array_merge($tags, $post->tags);
				}
			}
			$tags = tagCleaner($tags);
		}
		
		return $tags;
	}
}
