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

namespace App\Http\Controllers\Api\Resume;

use App\Helpers\Files\Upload;
use App\Http\Requests\Request;
use App\Models\Resume;

trait SaveResume
{
	/**
	 * Store the user's resume
	 *
	 * @param $userId
	 * @param \App\Http\Requests\Request $request
	 * @return \App\Models\Resume
	 */
	protected function storeResume($userId, Request $request): Resume
	{
		return $this->saveResume($userId, $request);
	}
	
	/**
	 * Update the user's resume
	 *
	 * @param $userId
	 * @param \App\Http\Requests\Request $request
	 * @param \App\Models\Resume $resume
	 * @return \App\Models\Resume
	 */
	protected function updateResume($userId, Request $request, Resume $resume): Resume
	{
		return $this->saveResume($userId, $request, $resume);
	}
	
	/**
	 * Save the user's resume
	 *
	 * @param $userId
	 * @param \App\Http\Requests\Request $request
	 * @param \App\Models\Resume|null $resume
	 * @return \App\Models\Resume
	 */
	public function saveResume($userId, Request $request, Resume|null $resume = null): Resume
	{
		// Get Resume Input
		$resumeInput = $request->input('resume');
		if (empty($resumeInput['user_id'])) {
			$resumeInput['user_id'] = $userId;
		}
		if (empty($resumeInput['country_code'])) {
			$resumeInput['country_code'] = config('country.code');
		}
		$resumeInput['active'] = 1;
		
		// Create
		if (empty($resume)) {
			$resume = new Resume();
		}
		
		// Update
		foreach ($resumeInput as $key => $value) {
			if (in_array($key, $resume->getFillable())) {
				$resume->{$key} = $value;
			}
		}
		$resume->save();
		
		// Save the Resume's File
		if ($request->hasFile('resume.filename')) {
			$destPath = 'resumes/' . strtolower($resume->country_code) . '/' . $resume->user_id;
			$resume->filename = Upload::file($destPath, $request->file('resume.filename'), 'private');
			
			if (empty($resume->name)) {
				$resume->name = last(explode('/', $resume->filename));
			}
			
			$resume->save();
		}
		
		return $resume;
	}
}
