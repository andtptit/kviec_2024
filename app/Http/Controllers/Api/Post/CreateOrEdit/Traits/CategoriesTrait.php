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

namespace App\Http\Controllers\Api\Post\CreateOrEdit\Traits;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Scopes\ActiveScope;

trait CategoriesTrait
{
	/**
	 * @param null $catId
	 * @return array
	 */
	protected function categories($catId = null)
	{
		$countryCode = config('country.code');
		$cacheExpiration = $this->cacheExpiration ?? config('settings.optimization.cache_expiration', 86400);
		
		// Get the homepage's getCategories section
		$cacheId = $countryCode . '.selectBox.getCategories';
		$section = cache()->remember($cacheId, $cacheExpiration, function () use ($countryCode) {
			// Check if the Domain Mapping plugin is available
			if (config('plugins.domainmapping.installed')) {
				try {
					$section = \extras\plugins\domainmapping\app\Models\DomainHomeSection::query()
						->withoutGlobalScopes([ActiveScope::class])
						->where('country_code', $countryCode)
						->where('method', 'getCategories')
						->orderBy('lft')
						->first();
				} catch (\Throwable $e) {
				}
			}
			
			// Get the entry from the core
			if (empty($section)) {
				$section = HomeSection::query()
					->withoutGlobalScopes([ActiveScope::class])
					->where('method', 'getCategories')
					->orderBy('lft')
					->first();
			}
			
			return $section;
		});
		
		// Get the catId subcategories
		$catsAndSubCats = $this->getCategories($section->value, $catId);
		
		// Get the category info
		$category = Category::find($catId);
		$hasChildren = (empty($catId) || (isset($category->children) && $category->children->count() > 0));
		
		return [
			'categoriesOptions' => $section->value,
			'category'          => $category,
			'hasChildren'       => $hasChildren,
			'categories'        => $catsAndSubCats['categories'], // Children
			'subCategories'     => $catsAndSubCats['subCategories'], // Children of children
		];
	}
	
	/**
	 * Get list of categories
	 * Apply the homepage categories section settings
	 *
	 * @param array $value
	 * @param null $catId
	 * @return array
	 */
	protected function getCategories($value = [], $catId = null)
	{
		// Number of columns
		$numberOfCols = 3;
		
		// Get the Default Cache delay expiration
		$cacheExpiration = $this->getCacheExpirationTime($value);
		
		$cacheId = 'selectBox.categories.parents.' . (int)$catId . '.' . config('app.locale');
		
		if (isset($value['cat_display_type']) && in_array($value['cat_display_type'], ['cc_normal_list', 'cc_normal_list_s'])) {
			
			$categories = cache()->remember($cacheId, $cacheExpiration, function () {
				return Category::orderBy('lft')->get();
			});
			$categories = collect($categories)->keyBy('id');
			$categories = $subCategories = $categories->groupBy('parent_id');
			
			if ($categories->has($catId)) {
				$categories = $categories->get($catId);
				$subCategories = $subCategories->forget($catId);
				
				$maxRowsPerCol = round($categories->count() / $numberOfCols, 0, PHP_ROUND_HALF_EVEN);
				$maxRowsPerCol = ($maxRowsPerCol > 0) ? $maxRowsPerCol : 1;
				$categories = $categories->chunk($maxRowsPerCol);
			} else {
				$categories = collect([]);
				$subCategories = collect([]);
			}
			
		} else {
			
			$categories = cache()->remember($cacheId, $cacheExpiration, function () use ($catId) {
				return Category::childrenOf($catId)->orderBy('lft')->get();
			});
			
			if (isset($value['cat_display_type']) && $value['cat_display_type'] == 'c_picture_list') {
				$categories = collect($categories)->keyBy('id');
			} else {
				$maxRowsPerCol = ceil($categories->count() / $numberOfCols);
				$maxRowsPerCol = ($maxRowsPerCol > 0) ? $maxRowsPerCol : 1; // Fix array_chunk with 0
				$categories = $categories->chunk($maxRowsPerCol);
			}
			$subCategories = collect([]);
			
		}
		
		return [
			'categories'    => $categories,
			'subCategories' => $subCategories,
		];
	}
	
	/**
	 * @param array $value
	 * @return int
	 */
	private function getCacheExpirationTime($value = [])
	{
		// Get the default Cache Expiration Time
		$cacheExpiration = 0;
		if (isset($value['cache_expiration'])) {
			$cacheExpiration = (int)$value['cache_expiration'];
		}
		
		return $cacheExpiration;
	}
}
