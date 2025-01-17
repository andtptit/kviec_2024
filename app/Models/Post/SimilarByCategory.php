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

namespace App\Models\Post;

use App\Models\Category;
use App\Models\Post;

trait SimilarByCategory
{
	/**
	 * Get similar Posts (Posts in the same Category)
	 *
	 * @param int|null $limit
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 */
	public function getSimilarByCategory(?int $limit = 20)
	{
		$posts = Post::query();
		
		$postsTable = (new Post())->getTable();
		
		$select = [
			$postsTable . '.id',
			$postsTable . '.country_code',
			'category_id',
			'post_type_id',
			'company_id',
			'company_name',
			'logo',
			'title',
			$postsTable . '.description',
			'salary_min',
			'salary_max',
			'salary_type_id',
			'city_id',
			'featured',
			'email_verified_at',
			'phone_verified_at',
			'reviewed_at',
			$postsTable . '.created_at',
			$postsTable . '.archived_at'
		];
		
		if (!empty($select)) {
			foreach ($select as $column) {
				$posts->addSelect($column);
			}
		}
		
		// Get the sub-categories of the current ad parent's category
		$similarCatIds = [];
		if (!empty($this->category)) {
			if ($this->category->id == $this->category->parent_id) {
				$similarCatIds[] = $this->category->id;
			} else {
				if (!empty($this->category->parent_id)) {
					$similarCatIds   = Category::childrenOf($this->category->parent_id)->get()
						->keyBy('id')
						->keys()
						->toArray();
					$similarCatIds[] = (int)$this->category->parent_id;
				} else {
					$similarCatIds[] = (int)$this->category->id;
				}
			}
		}
		
		// Default Filters
		$posts->currentCountry()->verified()->unarchived();
		if (config('settings.single.listings_review_activation')) {
			$posts->reviewed();
		}
		
		// Get ads from same category
		if (!empty($similarCatIds)) {
			if (count($similarCatIds) == 1) {
				if (isset($similarCatIds[0]) && !empty(isset($similarCatIds[0]))) {
					$posts->where('category_id', (int)$similarCatIds[0]);
				}
			} else {
				$posts->whereIn('category_id', $similarCatIds);
			}
		}
		
		// Relations
		$posts->with('postType')->has('postType');
		$posts->with('category', fn ($query) => $query->with('parent'))->has('category');
		$posts->with('salaryType')->has('salaryType');
		$posts->with('city')->has('city');
		$posts->with('savedByLoggedUser');
		$posts->with('latestPayment', fn ($query) => $query->with('package'));
		$posts->with('user');
		
		if (isset($this->id)) {
			$posts->where($postsTable . '.id', '!=', $this->id);
		}
		
		// Set ORDER BY
		// $posts->orderBy('created_at', 'DESC');
		$seed = rand(1, 9999);
		$posts->inRandomOrder($seed);
		
		// return $posts->take((int)$limit)->get();
		return $posts->paginate((int)$limit);
	}
}
