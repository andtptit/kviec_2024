{{--
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
--}}
@extends('layouts.master')

<?php
// Category
if ($post->category) {
    if (empty($post->category->parent_id)) {
        $postCatParentId = $post->category->id;
    } else {
        $postCatParentId = $post->category->parent_id;
    }
} else {
    $postCatParentId = 0;
}
?>
@section('content')
	@includeFirst([config('larapen.core.customizedViewPath') . 'common.spacer', 'common.spacer'])
	<div class="main-container">
		<div class="container">
			<div class="row">
				
				@includeFirst([config('larapen.core.customizedViewPath') . 'post.inc.notification', 'post.inc.notification'])

				<div class="col-md-9 page-content">
					<div class="inner-box category-content" style="overflow: visible;">
						<h2 class="title-2">
							<strong><i class="fas fa-edit"></i> {{ t('update_my_ad') }}</strong> -&nbsp;
							<a href="{{ \App\Helpers\UrlGen::post($post) }}"
							   data-bs-placement="top"
							   data-bs-toggle="tooltip"
							   title="{!! $post->title !!}"
							>
								{!! str($post->title)->limit(45) !!}
							</a>
						</h2>
						
						<div class="row">
							<div class="col-sm-12">
								<form class="form-horizontal" id="postForm" method="POST" action="{{ url()->current() }}" enctype="multipart/form-data">
									{!! csrf_field() !!}
									<input name="_method" type="hidden" value="PUT">
									<input type="hidden" name="post_id" value="{{ $post->id }}">
									<fieldset>
										{{-- COMPANY --}}
										<div class="content-subheading mt-0">
											<i class="far fa-building"></i>
											<strong>{{ t('Company Information') }}</strong>
										</div>
										
										{{-- company_id --}}
										<?php $companyIdError = (isset($errors) && $errors->has('company_id')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3 required">
											<label class="col-md-3 col-form-label{{ $companyIdError }}">
												{{ t('Select a Company') }} <sup>*</sup>
											</label>
											<div class="col-md-8">
												<select id="companyId" name="company_id" class="form-control selecter{{ $companyIdError }}">
													<option value="0" data-logo=""
															@if (old('company_id', 0)==0)
																selected="selected"
															@endif
													>[+] {{ t('New Company') }}</option>
													@if (isset($companies) && $companies->count() > 0)
														@foreach ($companies as $item)
															<option value="{{ $item->id }}" data-logo="{{ $item->logo_url_small }}"
																	@if (old('company_id', (isset($postCompany) ? $postCompany->id : 0))==$item->id)
																		selected="selected"
																	@endif
															>{{ $item->name }}</option>
														@endforeach
													@endif
												</select>
											</div>
										</div>
										
										{{-- logo --}}
										<div id="logoField" class="row mb-3">
											<label class="col-md-3 col-form-label">&nbsp;</label>
											<div class="col-md-8">
												<div class="mb10">
													<div id="logoFieldValue"></div>
												</div>
												<small id="" class="form-text text-muted">
													<a id="companyFormLink" href="{{ url('account/companies/0/edit') }}" class="btn btn-default">
														<i class="far fa-edit"></i> {{ t('Edit the Company') }}
													</a>
												</small>
											</div>
										</div>
									
										@includeFirst([config('larapen.core.customizedViewPath') . 'account.company._form', 'account.company._form'], ['originForm' => 'post'])
										
									
										{{-- POST --}}
										<div class="content-subheading">
											<i class="far fa-building"></i>
											<strong>{{ t('ad_details') }}</strong>
										</div>
										
										{{-- category_id --}}
										<?php $categoryIdError = (isset($errors) && $errors->has('category_id')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3 required">
											<label class="col-md-3 col-form-label{{ $categoryIdError }}">{{ t('category') }} <sup>*</sup></label>
											<div class="col-md-8">
												<div id="catsContainer" class="rounded{{ $categoryIdError }}">
													<a href="#browseCategories" data-bs-toggle="modal" class="cat-link" data-id="0">
														{{ t('select_a_category') }}
													</a>
												</div>
											</div>
											<input type="hidden" name="category_id" id="categoryId" value="{{ old('category_id', $post->category->id) }}">
										</div>

										{{-- title --}}
										<?php $titleError = (isset($errors) && $errors->has('title')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3 required">
											<label class="col-md-3 col-form-label{{ $titleError }}" for="title">{{ t('Title') }} <sup>*</sup></label>
											<div class="col-md-8">
												<input id="title" name="title" placeholder="{{ t('Job title') }}" class="form-control input-md{{ $titleError }}"
													   type="text" value="{{ old('title', $post->title) }}">
												<div class="form-text text-muted">
													{{ t('A great title needs at least 60 characters.') }}
												</div>
											</div>
										</div>

										{{-- description --}}
										<?php $descriptionError = (isset($errors) && $errors->has('description')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3 required">
											<?php
												$descriptionErrorLabel = '';
												$descriptionColClass = 'col-md-8';
												if (config('settings.single.wysiwyg_editor') != 'none') {
													$descriptionColClass = 'col-md-12';
													$descriptionErrorLabel = $descriptionError;
												} else {
													$post->description = strip_tags($post->description);
												}
											?>
											<label class="col-md-3 col-form-label{{ $descriptionErrorLabel }}" for="description">
												{{ t('Description') }} <sup>*</sup>
											</label>
											<div class="{{ $descriptionColClass }}">
												<textarea class="form-control {{ $descriptionError }}"
														  id="description"
														  name="description"
														  rows="15"
														  style="height: 300px"
												>{{ old('description', $post->description) }}</textarea>
												<div class="form-text text-muted">{{ t('Describe what makes your ad unique') }}</div>
											</div>
										</div>

										{{-- post_type_id --}}
										<?php $postTypeIdError = (isset($errors) && $errors->has('post_type_id')) ? ' is-invalid' : ''; ?>
										<div id="postTypeBloc" class="row mb-3 required">
											<label class="col-md-3 col-form-label{{ $postTypeIdError }}">
												{{ t('Job Type') }} <sup>*</sup>
											</label>
											<div class="col-md-8">
												<select name="post_type_id" id="postTypeId" class="form-control selecter{{ $postTypeIdError }}">
													@foreach ($postTypes as $postType)
														<option value="{{ $postType->id }}"
																@if (old('post_type_id', $post->post_type_id) == $postType->id)
																	selected="selected"
																@endif
														>{{ $postType->name }}</option>
													@endforeach
												</select>
											</div>
										</div>

										{{-- salary_min & salary_max --}}
										<?php $salaryMinError = (isset($errors) && $errors->has('salary_min')) ? ' is-invalid' : ''; ?>
										<?php $salaryMaxError = (isset($errors) && $errors->has('salary_max')) ? ' is-invalid' : ''; ?>
										<div id="salaryBloc" class="row mb-3">
											<label class="col-md-3 col-form-label" for="salary_max">{{ t('Salary') }}</label>
											<div class="col-md-4">
												<div class="row">
													<div class="input-group col-md-12">
														@if (config('currency')['in_left'] == 1)
															<span class="input-group-text">{!! config('currency')['symbol'] !!}</span>
														@endif
														<?php
														$salaryMin = \App\Helpers\Number::format(old('salary_min', $post->salary_min), 2, '.', '');
														?>
														<input id="salary_min"
															   name="salary_min"
															   class="form-control{{ $salaryMinError }}"
															   data-bs-toggle="tooltip"
															   title="{{ t('salary_min') }}"
															   placeholder="{{ t('salary_min') }}"
															   type="number"
															   min="0"
															   step="{{ getInputNumberStep((int)config('currency.decimal_places', 2)) }}"
															   value="{!! $salaryMin !!}"
														>
														@if (config('currency')['in_left'] == 0)
															<span class="input-group-text">{!! config('currency')['symbol'] !!}</span>
														@endif
													</div>
													<div class="input-group col-md-12">
														@if (config('currency')['in_left'] == 1)
															<span class="input-group-text">{!! config('currency')['symbol'] !!}</span>
														@endif
														<?php
														$salaryMax = \App\Helpers\Number::format(old('salary_max', $post->salary_max), 2, '.', '');
														?>
														<input id="salary_max"
															   name="salary_max"
															   class="form-control{{ $salaryMaxError }}"
															   data-bs-toggle="tooltip"
															   title="{{ t('salary_max') }}"
															   placeholder="{{ t('salary_max') }}"
															   type="number"
															   min="0"
															   step="{{ getInputNumberStep((int)config('currency.decimal_places', 2)) }}"
															   value="{!! $salaryMax !!}"
														>
														@if (config('currency')['in_left'] == 0)
															<span class="input-group-text">{!! config('currency')['symbol'] !!}</span>
														@endif
													</div>
												</div>
											</div>

											{{-- salary_type_id --}}
											<?php $salaryTypeIdError = (isset($errors) && $errors->has('salary_type_id')) ? ' is-invalid' : ''; ?>
											<div class="col-md-4">
												<select name="salary_type_id" id="salaryTypeId" class="form-control selecter{{ $salaryTypeIdError }}">
													@foreach ($salaryTypes as $salaryType)
														<option value="{{ $salaryType->id }}"
																@if (old('salary_type_id', $post->salary_type_id) == $salaryType->id)
																	selected="selected"
																@endif
														>{{ t('per') . ' ' . $salaryType->name }}</option>
													@endforeach
												</select>
												<div class="form-check form-check-inline px-0">
													<label class="form-check-label pt-2">
														<input id="negotiable"
															   name="negotiable"
															   type="checkbox"
															   value="1" {{ (old('negotiable', $post->negotiable)=='1') ? 'checked="checked"' : '' }}
														>&nbsp;{{ t('negotiable') }}
													</label>
												</div>
											</div>
										</div>

										{{-- start_date --}}
										<?php $startDateError = (isset($errors) && $errors->has('start_date')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3">
											<label class="col-md-3 col-form-label{{ $startDateError }}" for="start_date">{{ t('Start Date') }} </label>
											<div class="col-md-9 col-lg-8 col-xl-6">
												<input id="startDate" name="start_date"
													   placeholder="{{ t('Start Date') }}"
													   class="form-control input-md{{ $startDateError }} cf-date"
													   type="text"
													   value="{{ old('start_date', $post->start_date) }}"
													   autocomplete="off"
												>
											</div>
										</div>

										{{-- contact_name --}}
										<?php $contactNameError = (isset($errors) && $errors->has('contact_name')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3 required">
											<label class="col-md-3 col-form-label{{ $contactNameError }}" for="contact_name">
												{{ t('Contact Name') }} <sup>*</sup>
											</label>
											<div class="col-md-9 col-lg-8 col-xl-6">
												<div class="input-group">
													<span class="input-group-text"><i class="far fa-user"></i></span>
													<input id="contactName" name="contact_name"
														   placeholder="{{ t('Contact Name') }}"
														   class="form-control input-md{{ $contactNameError }}"
														   type="text"
														   value="{{ old('contact_name', $post->contact_name) }}"
													>
												</div>
											</div>
										</div>
										
										{{-- auth_field (as notification channel) --}}
										@php
											$authFields = getAuthFields(true);
											$authFieldError = (isset($errors) && $errors->has('auth_field')) ? ' is-invalid' : '';
											$usersCanChooseNotifyChannel = isUsersCanChooseNotifyChannel();
											$authFieldValue = (isset($post->auth_field) && !empty($post->auth_field)) ? $post->auth_field : getAuthField();
											$authFieldValue = ($usersCanChooseNotifyChannel) ? (old('auth_field', $authFieldValue)) : $authFieldValue;
										@endphp
										@if ($usersCanChooseNotifyChannel)
											<div class="row mb-3 required">
												<label class="col-md-3 col-form-label" for="auth_field">{{ t('notifications_channel') }} <sup>*</sup></label>
												<div class="col-md-9">
													@foreach ($authFields as $iAuthField => $notificationType)
														<div class="form-check form-check-inline pt-2">
															<input name="auth_field"
																   id="{{ $iAuthField }}AuthField"
																   value="{{ $iAuthField }}"
																   class="form-check-input auth-field-input{{ $authFieldError }}"
																   type="radio" {{ ($authFieldValue == $iAuthField) ? 'checked' : '' }}
															>
															<label class="form-check-label mb-0" for="{{ $iAuthField }}AuthField">
																{{ $notificationType }}
															</label>
														</div>
													@endforeach
													<div class="form-text text-muted">
														{{ t('notifications_channel_hint') }}
													</div>
												</div>
											</div>
										@else
											<input id="{{ $authFieldValue }}AuthField" name="auth_field" type="hidden" value="{{ $authFieldValue }}">
										@endif
										
										@php
											$forceToDisplay = isBothAuthFieldsCanBeDisplayed() ? ' force-to-display' : '';
										@endphp

										{{-- email --}}
										@php
											$emailError = (isset($errors) && $errors->has('email')) ? ' is-invalid' : '';
										@endphp
										<div class="row mb-3 auth-field-item required{{ $forceToDisplay }}">
											<label class="col-md-3 col-form-label{{ $emailError }}" for="email"> {{ t('Contact Email') }}
												@if (getAuthField() == 'email')
													<sup>*</sup>
												@endif
											</label>
											<div class="col-md-9 col-lg-8 col-xl-6">
												<div class="input-group">
													<span class="input-group-text"><i class="far fa-envelope"></i></span>
													<input id="email" name="email"
														   class="form-control{{ $emailError }}"
														   placeholder="{{ t('email_address') }}"
														   type="text"
														   value="{{ old('email', $post->email) }}"
													>
												</div>
											</div>
										</div>

										{{-- phone --}}
										@php
											$phoneError = (isset($errors) && $errors->has('phone')) ? ' is-invalid' : '';
											$phoneValue = $post->phone ?? null;
											$phoneCountryValue = $post->phone_country ?? config('country.code');
											$phoneValue = phoneE164($phoneValue, $phoneCountryValue);
											$phoneValueOld = phoneE164(old('phone', $phoneValue), old('phone_country', $phoneCountryValue));
										@endphp
										<div class="row mb-3 auth-field-item required{{ $forceToDisplay }}">
											<label class="col-md-3 col-form-label{{ $phoneError }}" for="phone">{{ t('phone_number') }}
												@if (getAuthField() == 'phone')
													<sup>*</sup>
												@endif
											</label>
											<div class="col-md-9 col-lg-8 col-xl-6">
												<div class="input-group">
													<input id="phone" name="phone"
														   class="form-control input-md{{ $phoneError }}"
														   type="tel"
														   value="{{ $phoneValueOld }}"
													>
													<span class="input-group-text iti-group-text">
														<input name="phone_hidden" id="phoneHidden" type="checkbox"
															   value="1" {{ (old('phone_hidden', $post->phone_hidden)=='1') ? 'checked="checked"' : '' }}>&nbsp;
														<small>{{ t('Hide') }}</small>
													</span>
												</div>
												<input name="phone_country" type="hidden" value="{{ old('phone_country', $phoneCountryValue) }}">
											</div>
										</div>
										
										{{-- country_code --}}
										<input id="countryCode" name="country_code"
											   type="hidden"
											   value="{{ !empty($post->country_code) ? $post->country_code : config('country.code') }}"
										>
									
										@if (config('country.admin_field_active') == 1 && in_array(config('country.admin_type'), ['1', '2']))
											{{-- admin_code --}}
											<?php $adminCodeError = (isset($errors) && $errors->has('admin_code')) ? ' is-invalid' : ''; ?>
											<div id="locationBox" class="row mb-3 required">
												<label class="col-md-3 col-form-label{{ $adminCodeError }}" for="admin_code">
													{{ t('location') }} <sup>*</sup>
												</label>
												<div class="col-md-8">
													<select id="adminCode" name="admin_code" class="form-control large-data-selecter{{ $adminCodeError }}">
														<option value="0" {{ (!old('admin_code') || old('admin_code')==0) ? 'selected="selected"' : '' }}>
															{{ t('select_your_location') }}
														</option>
													</select>
												</div>
											</div>
										@endif
									
										{{-- city_id --}}
										<?php $cityIdError = (isset($errors) && $errors->has('city_id')) ? ' is-invalid' : ''; ?>
										<div id="cityBox" class="row mb-3 required">
											<label class="col-md-3 col-form-label{{ $cityIdError }}" for="city_id">{{ t('city') }} <sup>*</sup></label>
											<div class="col-md-8">
												<select id="cityId" name="city_id" class="form-control large-data-selecter{{ $cityIdError }}">
													<option value="0" {{ (!old('city_id') || old('city_id')==0) ? 'selected="selected"' : '' }}>
														{{ t('select_a_city') }}
													</option>
												</select>
											</div>
										</div>
										
										{{-- application_url --}}
										<?php $applicationUrlError = (isset($errors) && $errors->has('application_url')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3">
											<label class="col-md-3 col-form-label" for="title">{{ t('Application URL') }}</label>
											<div class="col-md-8">
												<div class="input-group">
													<span class="input-group-text"><i class="fas fa-reply"></i></span>
													<input id="application_url" name="application_url"
														   placeholder="{{ t('Application URL') }}" class="form-control input-md{{ $applicationUrlError }}" type="text"
														   value="{{ old('application_url', $post->application_url) }}">
												</div>
												<div class="form-text text-muted">
													{{ t('Candidates will follow this URL address to apply for the job') }}
												</div>
											</div>
										</div>
										
										{{-- tags --}}
										<?php $tagsError = (isset($errors) && $errors->has('tags.*')) ? ' is-invalid' : ''; ?>
										<div class="row mb-3">
											<label class="col-md-3 col-form-label{{ $tagsError }}" for="tags">{{ t('Tags') }}</label>
											<div class="col-md-8">
												<select id="tags" name="tags[]" class="form-control tags-selecter" multiple="multiple">
													<?php $tags = old('tags', $post->tags); ?>
													@if (!empty($tags))
														@foreach($tags as $iTag)
															<option selected="selected">{{ $iTag }}</option>
														@endforeach
													@endif
												</select>
												<div class="form-text text-muted">
													{!! t('tags_hint', [
															'limit' => (int)config('settings.single.tags_limit', 15),
															'min'   => (int)config('settings.single.tags_min_length', 2),
															'max'   => (int)config('settings.single.tags_max_length', 30)
														]) !!}
												</div>
											</div>
										</div>
									
										@includeFirst([config('larapen.core.customizedViewPath') . 'post.createOrEdit.singleStep.inc.packages', 'post.createOrEdit.singleStep.inc.packages'])

										{{-- Button --}}
										<div class="row mb-3">
											<div class="col-md-12 text-center">
												<a href="{{ \App\Helpers\UrlGen::post($post) }}" class="btn btn-default btn-lg"> {{ t('Back') }}</a>
												<button id="submitPostForm" class="btn btn-success btn-lg submitPostForm"> {{ t('Update') }} </button>
											</div>
										</div>

									</fieldset>
								</form>

							</div>
						</div>
					</div>
				</div>
				<!-- /.page-content -->

				<div class="col-md-3 reg-sidebar">
					@includeFirst([config('larapen.core.customizedViewPath') . 'post.createOrEdit.inc.right-sidebar', 'post.createOrEdit.inc.right-sidebar'])
				</div>
				
			</div>
		</div>
	</div>
	@includeFirst([config('larapen.core.customizedViewPath') . 'post.createOrEdit.inc.category-modal', 'post.createOrEdit.inc.category-modal'])
@endsection

@section('after_styles')
@endsection

@section('after_scripts')
	<script>
		defaultAuthField = '{{ old('auth_field', $authFieldValue ?? getAuthField()) }}';
		phoneCountry = '{{ old('phone_country', ($phoneCountryValue ?? '')) }}';
	</script>
@endsection

@includeFirst([config('larapen.core.customizedViewPath') . 'post.createOrEdit.inc.form-assets', 'post.createOrEdit.inc.form-assets'])
