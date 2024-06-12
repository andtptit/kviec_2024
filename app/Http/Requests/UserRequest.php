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

namespace App\Http\Requests;

use App\Rules\BetweenRule;
use App\Rules\BlacklistTitleRule;
use App\Rules\BlacklistWordRule;
use App\Rules\UsernameIsAllowedRule;
use App\Rules\UsernameIsValidRule;
use Illuminate\Validation\Rule;

class UserRequest extends Request
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize(): bool
	{
		if (in_array($this->method(), ['POST', 'CREATE'])) {
			return true;
		} else {
			$guard = isFromApi() ? 'sanctum' : null;
			
			return auth($guard)->check();
		}
	}
	
	/**
	 * Prepare the data for validation.
	 *
	 * @return void
	 */
	protected function prepareForValidation()
	{
		// Don't apply this to the Admin Panel
		if (isAdminPanel()) {
			return;
		}
		
		$input = $this->all();
		
		// name
		if ($this->filled('name')) {
			$input['name'] = strCleanerLite($this->input('name'));
			$input['name'] = onlyNumCleaner($input['name']);
		}
		
		// auth_field
		$input['auth_field'] = getAuthField();
		
		// phone
		if ($this->filled('phone')) {
			$input['phone'] = phoneE164($this->input('phone'), getPhoneCountry());
			$input['phone_national'] = phoneNational($this->input('phone'), getPhoneCountry());
		} else {
			$input['phone'] = null;
			$input['phone_national'] = null;
		}
		
		request()->merge($input); // Required!
		$this->merge($input);
	}
	
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules(): array
	{
		$rules = [];
		
		$authFields = array_keys(getAuthFields());
		
		// CREATE
		if (in_array($this->method(), ['POST', 'CREATE'])) {
			$rules = $this->storeRules($authFields);
		}
		
		// UPDATE
		if (in_array($this->method(), ['PUT', 'PATCH', 'UPDATE'])) {
			$rules = $this->updateRules($authFields);
		}
		
		return $rules;
	}
	
	/**
	 * @param array $authFields
	 * @return array
	 */
	private function storeRules(array $authFields): array
	{
		$rules = [
			'name'          => ['required', new BetweenRule(2, 200)],
			'user_type_id'  => ['required', 'not_in:0'],
			'country_code'  => ['sometimes', 'required', 'not_in:0'],
			'auth_field'    => ['required', Rule::in($authFields)],
			'phone'         => ['max:30'],
			'phone_country' => ['required_with:phone'],
			'password'      => ['required', 'confirmed'],
			'accept_terms'  => ['accepted'],
		];
		
		$phoneIsEnabledAsAuthField = (config('settings.sms.enable_phone_as_auth_field') == '1');
		$phoneNumberIsRequired = ($phoneIsEnabledAsAuthField && $this->input('auth_field') == 'phone');
		
		// email
		$emailIsRequired = (!$phoneNumberIsRequired);
		if ($emailIsRequired) {
			$rules['email'][] = 'required';
		}
		$rules = $this->validEmailRules('email', $rules);
		if ($this->filled('email')) {
			$rules['email'][] = 'unique:users,email';
		}
		
		// phone
		if ($phoneNumberIsRequired) {
			$rules['phone'][] = 'required';
		}
		$rules = $this->validPhoneNumberRules('phone', $rules);
		if ($this->filled('phone')) {
			$rules['phone'][] = 'unique:users,phone';
		}
		
		// username
		$usernameIsEnabled = !config('larapen.core.disable.username');
		if ($usernameIsEnabled) {
			if ($this->filled('username')) {
				$rules['username'] = [
					'between:3,50',
					'unique:users,username',
					new UsernameIsValidRule(),
					new UsernameIsAllowedRule(),
				];
			}
		}
		
		// password
		$rules = $this->validPasswordRules('password', $rules);
		
		// COMPANY: Check 'resume' is required
		if (config('larapen.core.register.showCompanyFields')) {
			if ($this->input('user_type_id') == 1) {
				$rules['company.name'] = ['required', new BetweenRule(2, 200), new BlacklistTitleRule()];
				$rules['company.description'] = ['required', new BetweenRule(5, 1000), new BlacklistWordRule()];
				
				// Check 'logo' is required
				if ($this->file('logo')) {
					$rules['logo'] = [
						'required',
						'image',
						'mimes:' . getUploadFileTypes('image'),
						'max:' . (int)config('settings.upload.max_image_size', 1000),
					];
				}
			}
		}
		
		// CANDIDATE: Check 'resume' is required
		if (config('larapen.core.register.showResumeFields')) {
			if ($this->input('user_type_id') == 2) {
				$rules['resume.filename'] = [
					'required',
					'mimes:' . getUploadFileTypes('file'),
					'max:' . (int)config('settings.upload.max_file_size', 1000),
				];
			}
		}
		
		return $this->captchaRules($rules);
	}
	
	/**
	 * @param array $authFields
	 * @return array
	 */
	private function updateRules(array $authFields): array
	{
		$guard = isFromApi() ? 'sanctum' : null;
		$user = auth($guard)->user();
		
		$rules = [];
		if (empty($user->user_type_id) || $user->user_type_id == 0) {
			$rules['user_type_id'] = ['required', 'not_in:0'];
		} else {
			$rules['name'] = ['required', 'max:100'];
			$rules['auth_field'] = ['required', Rule::in($authFields)];
			$rules['phone'] = ['max:30'];
			$rules['phone_country'] = ['required_with:phone'];
			$rules['username'] = [new UsernameIsValidRule(), new UsernameIsAllowedRule()];
			
			// Check if these fields has changed
			$emailChanged = ($this->filled('email') && $this->input('email') != $user->email);
			$phoneChanged = ($this->filled('phone') && $this->input('phone') != $user->phone);
			$usernameChanged = ($this->filled('username') && $this->input('username') != $user->username);
			
			$phoneIsEnabledAsAuthField = (config('settings.sms.enable_phone_as_auth_field') == '1');
			$phoneNumberIsRequired = ($phoneIsEnabledAsAuthField && $this->input('auth_field') == 'phone');
			
			// email
			$emailIsRequired = (!$phoneNumberIsRequired);
			if ($emailIsRequired) {
				$rules['email'][] = 'required';
			}
			$rules = $this->validEmailRules('email', $rules);
			if ($emailChanged) {
				$rules['email'][] = 'unique:users,email';
			}
			
			// phone
			if ($phoneNumberIsRequired) {
				$rules['phone'][] = 'required';
			}
			$rules = $this->validPhoneNumberRules('phone', $rules);
			if ($phoneChanged) {
				$rules['phone'][] = 'unique:users,phone';
			}
			
			// username
			if ($this->filled('username')) {
				$rules['username'][] = 'between:3,50';
			}
			if ($usernameChanged) {
				$rules['username'][] = 'required';
				$rules['username'][] = 'unique:users,username';
			}
			
			// password
			$rules = $this->validPasswordRules('password', $rules);
			if ($this->filled('password')) {
				$rules['password'][] = 'confirmed';
			}
			
			if ($this->filled('user_accept_terms') && $this->input('user_accept_terms') != 1) {
				$rules['accept_terms'] = ['accepted'];
			}
		}
		
		return $rules;
	}
}
