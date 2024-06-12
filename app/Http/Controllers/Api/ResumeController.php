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

use App\Http\Controllers\Api\Resume\SaveResume;
use App\Http\Requests\ResumeRequest;
use App\Http\Resources\EntityCollection;
use App\Http\Resources\ResumeResource;
use App\Models\Resume;

/**
 * @group Resumes
 */
class ResumeController extends BaseController
{
	use SaveResume;
	
	/**
	 * List resumes
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
		$resumes = Resume::query();
		
		// Apply search filter
		if (request()->filled('q')) {
			$keywords = rawurldecode(request()->get('q'));
			$resumes->where('name', 'LIKE', '%' . $keywords . '%');
		}
		
		if (request()->get('belongLoggedUser')) {
			$userId = (auth('sanctum')->check()) ? auth('sanctum')->user()->id : '-1';
			$resumes->where('user_id', $userId);
		}
		
		// Sorting
		$resumes = $this->applySorting($resumes, ['created_at', 'name']);
		
		$resumes = $resumes->paginate($this->perPage);
		
		// If the request is made from the app's Web environment,
		// use the Web URL as the pagination's base URL
		$resumes = setPaginationBaseUrl($resumes);
		
		$collection = new EntityCollection(class_basename($this), $resumes);
		
		$message = ($resumes->count() <= 0) ? t('no_resumes_found') : null;
		
		return $this->respondWithCollection($collection, $message);
	}
	
	/**
	 * Get resume
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @queryParam embed string The Comma-separated list of the company relationships for Eager Loading - Possible values: user. Example: user
	 *
	 * @urlParam id int required The resume's ID. Example: 269
	 *
	 * @param $id
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function show($id)
	{
		$resume = Resume::query()->where('id', $id);
		
		if (request()->get('belongLoggedUser')) {
			$userId = (auth('sanctum')->check()) ? auth('sanctum')->user()->id : '-1';
			$resume->where('user_id', $userId);
		}
		
		$embed = explode(',', request()->get('embed'));
		
		if (in_array('user', $embed)) {
			$resume->with('user');
		}
		
		$resume = $resume->first();
		
		if (empty($resume)) {
			return $this->respondNotFound(t('resume_not_found'));
		}
		
		$resource = new ResumeResource($resume);
		
		return $this->respondWithResource($resource);
	}
	
	/**
	 * Store resume
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @bodyParam resume[].country_code string required The code of the user's country. Example: US
	 * @bodyParam resume[].name string The resume's name. Example: Software Engineer
	 * @bodyParam resume[].filename file required The resume's attached file.
	 *
	 * @param \App\Http\Requests\ResumeRequest $request
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function store(ResumeRequest $request)
	{
		$user = auth('sanctum')->user();
		if (!isset($user->id)) {
			return $this->respondNotFound(t('user_not_found'));
		}
		
		$resume = $this->storeResume($user->id, $request);
		
		$data = [
			'success' => true,
			'message' => t('Your resume has created successfully'),
			'result'  => (new ResumeResource($resume))->toArray($request),
		];
		
		return $this->apiResponse($data);
	}
	
	/**
	 * Update resume
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @bodyParam resume[].name string The resume's name. Example: Software Engineer
	 * @bodyParam resume[].filename file required The resume's attached file.
	 *
	 * @urlParam id int required The resume's ID. Example: 1
	 *
	 * @param $id
	 * @param \App\Http\Requests\ResumeRequest $request
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function update($id, ResumeRequest $request)
	{
		$user = auth('sanctum')->user();
		if (!isset($user->id)) {
			return $this->respondNotFound(t('user_not_found'));
		}
		
		$resume = Resume::where('user_id', $user->id)->where('id', $id)->first();
		
		if (empty($resume)) {
			return $this->respondNotFound(t('resume_not_found'));
		}
		
		$resume = $this->updateResume($user->id, $request, $resume);
		
		$data = [
			'success' => true,
			'message' => t('Your resume has updated successfully'),
			'result'  => (new ResumeResource($resume))->toArray($request),
		];
		
		return $this->apiResponse($data);
	}
	
	/**
	 * Delete resume(s)
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @urlParam ids string required The ID or comma-separated IDs list of resume(s).
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
		foreach ($ids as $resumeId) {
			$resume = Resume::query()
				->where('user_id', $user->id)
				->where('id', $resumeId)
				->first();
			
			if (!empty($resume)) {
				$res = $resume->delete();
			}
		}
		
		// Confirmation
		if ($res) {
			$data['success'] = true;
			
			$count = count($ids);
			if ($count > 1) {
				$data['message'] = t('x entities has been deleted successfully', ['entities' => t('resumes'), 'count' => $count]);
			} else {
				$data['message'] = t('1 entity has been deleted successfully', ['entity' => t('resume')]);
			}
		}
		
		return $this->apiResponse($data);
	}
}
