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

namespace App\Http\Requests\Admin;

use App\Helpers\Number;
use App\Rules\BetweenRule;
use App\Rules\BlacklistTitleRule;
use App\Rules\BlacklistWordRule;
use Illuminate\Validation\Rule;

class PostRequest extends Request
{
	/**
	 * Prepare the data for validation.
	 *
	 * @return void
	 */
	protected function prepareForValidation()
	{
		$input = $this->all();
		
		// salary_min
		if ($this->has('salary_min')) {
			if ($this->filled('salary_min')) {
				$input['salary_min'] = $this->input('salary_min');
				// If field's value contains only numbers and dot,
				// Then decimal separator is set as dot.
				if (preg_match('/^[0-9.]*$/', $input['salary_min'])) {
					$input['salary_min'] = Number::formatForDb($input['salary_min'], '.');
				} else {
					$input['salary_min'] = Number::formatForDb($input['salary_min'], config('currency.decimal_separator', '.'));
				}
			} else {
				$input['salary_min'] = null;
			}
		}
		
		// salary_max
		if ($this->has('salary_max')) {
			if ($this->filled('salary_max')) {
				$input['salary_max'] = $this->input('salary_max');
				// If field's value contains only numbers and dot,
				// Then decimal separator is set as dot.
				if (preg_match('/^[0-9.]*$/', $input['salary_max'])) {
					$input['salary_max'] = Number::formatForDb($input['salary_max'], '.');
				} else {
					$input['salary_max'] = Number::formatForDb($input['salary_max'], config('currency.decimal_separator', '.'));
				}
			} else {
				$input['salary_max'] = null;
			}
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
		
		// tags
		if ($this->filled('tags')) {
			$input['tags'] = tagCleaner($this->input('tags'));
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
		
		$rules['category_id'] = ['required', 'not_in:0'];
		$rules['post_type_id'] = ['required', 'not_in:0'];
		$rules['company_name'] = ['required', new BetweenRule(2, 200), new BlacklistTitleRule()];
		$rules['company_description'] = ['required', new BetweenRule(5, 12000), new BlacklistWordRule()];
		$rules['title'] = [
			'required',
			new BetweenRule(
				(int)config('settings.single.title_min_length', 2),
				(int)config('settings.single.title_max_length', 150)
			),
		];
		$rules['description'] = [
			'required',
			new BetweenRule(
				(int)config('settings.single.description_min_length', 5),
				(int)config('settings.single.description_max_length', 12000)
			),
		];
		$rules['contact_name'] = ['required', new BetweenRule(3, 200)];
		$rules['auth_field'] = ['required', Rule::in($authFields)];
		$rules['phone'] = ['max:30'];
		$rules['phone_country'] = ['required_with:phone'];
		
		$phoneIsEnabledAsAuthField = (config('settings.sms.enable_phone_as_auth_field') == '1');
		$phoneNumberIsRequired = ($phoneIsEnabledAsAuthField && $this->input('auth_field') == 'phone');
		
		// email
		$emailIsRequired = (!$phoneNumberIsRequired);
		if ($emailIsRequired) {
			$rules['email'][] = 'required';
		}
		$rules = $this->validEmailRules('email', $rules);
		
		// phone
		if ($phoneNumberIsRequired) {
			$rules['phone'][] = 'required';
		}
		$rules = $this->validPhoneNumberRules('phone', $rules);
		
		// Tags
		if ($this->filled('tags')) {
			$rules['tags.*'] = ['regex:' . tagRegexPattern()];
		}
		
		return $rules;
	}
	
	/**
	 * Get custom attributes for validator errors.
	 *
	 * @return array
	 */
	public function attributes(): array
	{
		$attributes = [];
		
		if ($this->filled('tags')) {
			foreach ($this->input('tags') as $key => $tag) {
				$attributes['tags.' . $key] = t('tag X', ['key' => ($key + 1)]);
			}
		}
		
		return $attributes;
	}
}
