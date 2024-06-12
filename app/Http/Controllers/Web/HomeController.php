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

namespace App\Http\Controllers\Web;

use Larapen\LaravelMetaTags\Facades\MetaTag;

class HomeController extends FrontController
{
	/**
	 * @return \Illuminate\Contracts\View\View
	 */
	public function index()
	{
		// Call API endpoint
		$endpoint = '/homeSections';
		$data = makeApiRequest('get', $endpoint);
		
		$message = $this->handleHttpError($data);
		$sections = (array)data_get($data, 'result.data');
		
		// Share sections' options in views,
		// that requires to be accessible everywhere in the app's views (including the master view).
		foreach ($sections as $section) {
			$optionName = data_get($section, 'method') . 'Op';
			view()->share($optionName, (array)data_get($section, $optionName));
		}
		
		// Get SEO
		$getSearchFormOp = data_get($sections, 'getSearchForm.getSearchFormOp') ?? [];
		$this->setSeo($getSearchFormOp);
		
		return appView('home.index', compact('sections'));
	}
	
	/**
	 * Set SEO information
	 *
	 * @param array $getSearchFormOp
	 */
	protected function setSeo(array $getSearchFormOp = [])
	{
		// Meta Tags
		[$title, $description, $keywords] = getMetaTag('home');
		MetaTag::set('title', $title);
		MetaTag::set('description', strip_tags($description));
		MetaTag::set('keywords', $keywords);
		
		// Open Graph
		$this->og->title($title)->description($description);
		$ogImageUrl = config('settings.seo.og_image_url');
		if (empty($ogImageUrl)) {
			if (!empty(config('country.background_image_url'))) {
				$ogImageUrl = config('country.background_image_url');
			}
		}
		if (empty($ogImageUrl)) {
			if (!empty(data_get($getSearchFormOp, 'background_image_url'))) {
				$ogImageUrl = data_get($getSearchFormOp, 'background_image_url');
			}
		}
		if (!empty($ogImageUrl)) {
			if ($this->og->has('image')) {
				$this->og->forget('image')->forget('image:width')->forget('image:height');
			}
			$this->og->image($ogImageUrl, [
				'width'  => 600,
				'height' => 600,
			]);
		}
		view()->share('og', $this->og);
	}
}
