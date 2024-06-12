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

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Company\SaveCompany;
use App\Http\Requests\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\EntityCollection;
use App\Models\Company;

/**
 * @group Companies
 */
class CompanyController extends BaseController
{
	use SaveCompany;
	
	/**
	 * List companies
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @queryParam sort string The sorting parameter (Order by DESC with the given column. Use "-" as prefix to order by ASC). Possible values: created_at, name. Example: created_at
	 * @queryParam perPage int Items per page. Can be defined globally from the admin settings. Cannot be exceeded 100. Example: 2
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function index()
	{
		$companies = Company::query()->with(['user', 'user.permissions', 'user.roles']);
		
		if (request()->get('hasPosts')) {
			$companies->whereHas('posts', function ($query) {
				$query->currentCountry()->verified()->unarchived();
				if (config('settings.single.listings_review_activation')) {
					$query->reviewed();
				}
			});
		}
		
		if (request()->get('countPosts')) {
			$companies->withCount([
				'posts' => function ($query) {
					$query->currentCountry()->verified()->unarchived();
					if (config('settings.single.listings_review_activation')) {
						$query->reviewed();
					}
				},
			]);
		}
		
		// Apply search filter
		if (request()->filled('q')) {
			$keywords = rawurldecode(request()->get('q'));
			$companies->where(function ($query) use ($keywords) {
				$query->where('name', 'LIKE', '%' . $keywords . '%')
					->whereOr('description', 'LIKE', '%' . $keywords . '%');
			});
		}
		
		if (request()->get('belongLoggedUser')) {
			$userId = (auth('sanctum')->check()) ? auth('sanctum')->user()->id : '-1';
			$companies->where('user_id', $userId);
		}
		
		// Sorting
		$companies = $this->applySorting($companies, ['created_at', 'name']);
		
		$companies = $companies->paginate($this->perPage);
		
		// If the request is made from the app's Web environment,
		// use the Web URL as the pagination's base URL
		$companies = setPaginationBaseUrl($companies);
		
		$collection = new EntityCollection(class_basename($this), $companies);
		
		$message = ($companies->count() <= 0) ? t('no_companies_found') : null;
		
		return $this->respondWithCollection($collection, $message);
	}
	
	/**
	 * Get company
	 *
	 * @queryParam embed string The Comma-separated list of the company relationships for Eager Loading - Possible values: user. Example: user
	 *
	 * @urlParam id int required The company's ID. Example: 44
	 *
	 * @param $id
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function show($id)
	{
		$company = Company::query()->where('id', $id);
		
		if (request()->get('belongLoggedUser')) {
			$userId = (auth('sanctum')->check()) ? auth('sanctum')->user()->id : '-1';
			$company->where('user_id', $userId);
		}
		
		$embed = explode(',', request()->get('embed'));
		
		if (in_array('user', $embed)) {
			$company->with('user');
		}
		
		$company = $company->first();
		
		if (empty($company)) {
			return $this->respondNotFound(t('company_not_found'));
		}
		
		$resource = new CompanyResource($company);
		
		return $this->respondWithResource($resource);
	}
	
	/**
	 * Store company
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @bodyParam company[].country_code string required The code of the company's country. Example: US
	 * @bodyParam company[].name string required The company's name. Example: Foo Inc
	 * @bodyParam company[].logo file The company's logo.
	 * @bodyParam company[].description string required The company's description. Example: Nostrum quia est aut quas. Consequuntur ut quis odit voluptatem laborum quia.
	 * @bodyParam company[].city_id int The company city's ID.
	 * @bodyParam company[].address string The company's address. Example: 5 rue de l'Echelle
	 * @bodyParam company[].phone string The company's phone number. Example: +17656766467
	 * @bodyParam company[].fax string The company's fax number. Example: +80159266712
	 * @bodyParam company[].email string The company's email address. Example: contact@domain.tld
	 * @bodyParam company[].website string The company's website URL. Example: https://domain.tld
	 * @bodyParam company[].facebook string The company's Facebook URL.
	 * @bodyParam company[].twitter string The company's Twitter URL.
	 * @bodyParam company[].linkedin string The company's LinkedIn URL.
	 * @bodyParam company[].pinterest string The company's Pinterest URL.
	 *
	 * @param \App\Http\Requests\CompanyRequest $request
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function store(CompanyRequest $request)
	{
		$user = auth('sanctum')->user();
		if (!isset($user->id)) {
			return $this->respondNotFound(t('user_not_found'));
		}
		
		// Create Company
		$company = $this->storeCompany($user->id, $request);
		
		$data = [
			'success' => true,
			'message' => t('Your company has created successfully'),
			'result'  => (new CompanyResource($company))->toArray($request),
		];
		
		return $this->apiResponse($data);
	}
	
	/**
	 * Update company
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @bodyParam company[].country_code string required The code of the company's country. Example: US
	 * @bodyParam company[].name string required The company's name. Example: Foo Inc
	 * @bodyParam company[].logo file The company's logo.
	 * @bodyParam company[].description string required The company's description. Example: Nostrum quia est aut quas. Consequuntur ut quis odit voluptatem laborum quia.
	 * @bodyParam company[].city_id int The company city's ID.
	 * @bodyParam company[].address string The company's address. Example: 5 rue de l'Echelle
	 * @bodyParam company[].phone string The company's phone number. Example: +17656766467
	 * @bodyParam company[].fax string The company's fax number. Example: +80159266712
	 * @bodyParam company[].email string The company's email address. Example: contact@domain.tld
	 * @bodyParam company[].website string The company's website URL. Example: https://domain.tld
	 * @bodyParam company[].facebook string The company's Facebook URL.
	 * @bodyParam company[].twitter string The company's Twitter URL.
	 * @bodyParam company[].linkedin string The company's LinkedIn URL.
	 * @bodyParam company[].pinterest string The company's Pinterest URL.
	 *
	 * @urlParam id int required The company's ID. Example: 1
	 *
	 * @param $id
	 * @param \App\Http\Requests\CompanyRequest $request
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function update($id, CompanyRequest $request)
	{
		$user = auth('sanctum')->user();
		if (!isset($user->id)) {
			return $this->respondNotFound(t('user_not_found'));
		}
		
		$company = Company::where('user_id', $user->id)->where('id', $id)->first();
		
		if (empty($company)) {
			return $this->respondNotFound(t('company_not_found'));
		}
		
		// Update the Company
		$company = $this->updateCompany($user->id, $request, $company);
		
		$data = [
			'success' => true,
			'message' => t('Your company details has updated successfully'),
			'result'  => (new CompanyResource($company))->toArray($request),
		];
		
		return $this->apiResponse($data);
	}
	
	/**
	 * Delete company(ies)
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @urlParam ids string required The ID or comma-separated IDs list of company(ies).
	 *
	 * @param $ids
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Exception
	 */
	public function destroy($ids)
	{
		$user = auth('sanctum')->user();
		if (!isset($user->id)) {
			return $this->respondNotFound(t('user_not_found'));
		}
		
		$data = [
			'success' => false,
			'message' => t('no_deletion_is_done'),
			'result'  => null,
		];
		
		// Get Entries ID (IDs separated by comma accepted)
		$ids = explode(',', $ids);
		
		// Delete
		$res = false;
		foreach ($ids as $companyId) {
			$company = Company::query()
				->where('user_id', $user->id)
				->where('id', $companyId)
				->first();
			
			if (!empty($company)) {
				$res = $company->delete();
			}
		}
		
		// Confirmation
		if ($res) {
			$data['success'] = true;
			
			$count = count($ids);
			if ($count > 1) {
				$data['message'] = t('x entities has been deleted successfully', ['entities' => t('companies'), 'count' => $count]);
			} else {
				$data['message'] = t('1 entity has been deleted successfully', ['entity' => t('company')]);
			}
		}
		
		return $this->apiResponse($data);
	}
}
