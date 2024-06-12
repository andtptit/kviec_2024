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

@section('content')
	{!! csrf_field() !!}
	<input type="hidden" id="postId" name="post_id" value="{{ data_get($post, 'id') }}">
	
	@if (session()->has('flash_notification'))
		@includeFirst([config('larapen.core.customizedViewPath') . 'common.spacer', 'common.spacer'])
		<?php $paddingTopExists = true; ?>
		<div class="container">
			<div class="row">
				<div class="col-12">
					@include('flash::message')
				</div>
			</div>
		</div>
		<?php session()->forget('flash_notification.message'); ?>
	@endif
	
	{{-- Archived listings message --}}
	@if (!empty(data_get($post, 'archived_at')))
		@includeFirst([config('larapen.core.customizedViewPath') . 'common.spacer', 'common.spacer'])
		<?php $paddingTopExists = true; ?>
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="alert alert-warning" role="alert">
						{!! t('This ad has been archived') !!}
					</div>
				</div>
			</div>
		</div>
	@endif
	
	<div class="main-container">
		
		<?php if (isset($topAdvertising) && !empty($topAdvertising)): ?>
			@includeFirst([config('larapen.core.customizedViewPath') . 'layouts.inc.advertising.top', 'layouts.inc.advertising.top'], ['paddingTopExists' => $paddingTopExists ?? false])
		<?php
			$paddingTopExists = false;
		endif;
		?>

		<div class="container {{ (isset($topAdvertising) && !empty($topAdvertising)) ? 'mt-3' : 'mt-2' }}">
			<div class="row">
				<div class="col-md-12">
					
					<nav aria-label="breadcrumb" role="navigation" class="float-start">
						<ol class="breadcrumb">
							<li class="breadcrumb-item"><a href="{{ url('/') }}"><i class="fas fa-home"></i></a></li>
							<li class="breadcrumb-item"><a href="{{ url('/') }}">{{ config('country.name') }}</a></li>
							@if (isset($catBreadcrumb) && is_array($catBreadcrumb) && count($catBreadcrumb) > 0)
								@foreach($catBreadcrumb as $key => $value)
									<li class="breadcrumb-item">
										<a href="{{ $value->get('url') }}">
											{!! $value->get('name') !!}
										</a>
									</li>
								@endforeach
							@endif
							<li class="breadcrumb-item active">{{ str(data_get($post, 'title'))->limit(70) }}</li>
						</ol>
					</nav>
					
					<div class="float-end backtolist">
						<a href="{{ rawurldecode(url()->previous()) }}"><i class="fa fa-angle-double-left"></i> {{ t('back_to_results') }}</a>
					</div>
					
				</div>
			</div>
		</div>
		
		<div class="container">
			<div class="row">
				<div class="col-lg-9 page-content col-thin-right">
					<div class="inner inner-box items-details-wrapper pb-0">
						<h1 class="h4 fw-bold enable-long-words">
							<strong>
                                <a href="{{ \App\Helpers\UrlGen::post($post) }}" title="{{ data_get($post, 'title') }}">
                                    {{ data_get($post, 'title') }}
                                </a>
                            </strong>
							<small class="label label-default adlistingtype">{{ t('_type_job', ['type' => data_get($post, 'postType.name')]) }}</small>
							@if (data_get($post, 'featured') == 1 && !empty(data_get($post, 'latestPayment.package')))
								<i class="fas fa-check-circle"
								   style="color: {{ data_get($post, 'latestPayment.package.ribbon') }};"
								   data-bs-placement="bottom"
								   data-bs-toggle="tooltip"
								   title="{{ data_get($post, 'latestPayment.package.short_name') }}"
								></i>
							@endif
						</h1>
						<span class="info-row">
							@if (!config('settings.single.hide_dates'))
							<span class="date"{!! (config('lang.direction')=='rtl') ? ' dir="rtl"' : '' !!}>
								<i class="far fa-clock"></i> {!! data_get($post, 'created_at_formatted') !!}
							</span>&nbsp;
							@endif
							<span class="category"{!! (config('lang.direction')=='rtl') ? ' dir="rtl"' : '' !!}>
								<i class="bi bi-folder"></i> {{ data_get($post, 'category.parent.name', data_get($post, 'category.name')) }}
							</span>&nbsp;
							<span class="item-location"{!! (config('lang.direction')=='rtl') ? ' dir="rtl"' : '' !!}>
								<i class="bi bi-geo-alt"></i> {{ data_get($post, 'city.name') }}
							</span>&nbsp;
							<span class="category"{!! (config('lang.direction')=='rtl') ? ' dir="rtl"' : '' !!}>
								<i class="bi bi-eye"></i> {{
									\App\Helpers\Number::short(data_get($post, 'visits'))
									. ' '
									. trans_choice('global.count_views', getPlural(data_get($post, 'visits')), [], config('app.locale'))
									}}
							</span>
							<span class="category float-md-end"{!! (config('lang.direction')=='rtl') ? ' dir="rtl"' : '' !!}>
								{{ t('reference') }}: {{ hashId(data_get($post, 'id'), false, false) }}
							</span>
						</span>

						<div class="items-details">
							<div class="row pb-4">
								<div class="col-md-8 col-sm-12 col-12">
									<div class="items-details-info jobs-details-info enable-long-words from-wysiwyg">
										<h5 class="list-title"><strong>{{ t('ad_details') }}</strong></h5>
										
										{{-- Description --}}
										<div>
											{!! transformDescription(data_get($post, 'description')) !!}
										</div>
										
										@if (!empty(data_get($post, 'company_description')))
											{{-- Company Description --}}
											<h5 class="list-title mt-5"><strong>{{ t('Company Description') }}</strong></h5>
											<div>
												{!! nl2br(createAutoLink(strCleaner(data_get($post, 'company_description')))) !!}
											</div>
										@endif
										
										{{-- Tags --}}
										@if (!empty(data_get($post, 'tags')))
											<div class="row mt-3">
												<div class="col-12">
													<h5 class="my-3 list-title"><strong>{{ t('Tags') }}</strong></h5>
													@foreach(data_get($post, 'tags') as $iTag)
														<span class="d-inline-block border border-inverse bg-light rounded-1 py-1 px-2 my-1 me-1">
															<a href="{{ \App\Helpers\UrlGen::tag($iTag) }}">
																{{ $iTag }}
															</a>
														</span>
													@endforeach
												</div>
											</div>
										@endif
									</div>
								</div>
								
								<div class="col-md-4 col-sm-12 col-12">
									<aside class="panel panel-body panel-details job-summery">
										<ul>
											@if (!empty(data_get($post, 'start_date')))
											<li>
												<p class="no-margin">
													<strong>{{ t('Start Date') }}:</strong>&nbsp;
													{{ data_get($post, 'start_date') }}
												</p>
											</li>
											@endif
											<li>
												<p class="no-margin">
													<strong>{{ t('Company') }}:</strong>&nbsp;
													@if (!empty(data_get($post, 'company_id')))
														<a href="{!! \App\Helpers\UrlGen::company(null, data_get($post, 'company_id')) !!}">
															{{ data_get($post, 'company_name') }}
														</a>
													@else
														{{ data_get($post, 'company_name') }}
													@endif
												</p>
											</li>
											<li>
												<p class="no-margin">
													<strong>{{ t('Salary') }}:</strong>&nbsp;
													@if (data_get($post, 'salary_min') > 0 || data_get($post, 'salary_max') > 0)
														@if (data_get($post, 'salary_min') > 0)
															{!! \App\Helpers\Number::money(data_get($post, 'salary_min')) !!}
														@endif
														@if (data_get($post, 'salary_max') > 0)
															@if (data_get($post, 'salary_min') > 0)
																&nbsp;-&nbsp;
															@endif
															{!! \App\Helpers\Number::money(data_get($post, 'salary_max')) !!}
														@endif
													@else
														{!! \App\Helpers\Number::money('--') !!}
													@endif
													@if (!empty(data_get($post, 'salaryType')))
														{{ t('per') }} {{ data_get($post, 'salaryType.name') }}
													@endif
													
													@if (data_get($post, 'negotiable') == 1)
														<br><small class="label bg-success"> {{ t('negotiable') }}</small>
													@endif
												</p>
											</li>
											<li>
												@if (!empty(data_get($post, 'postType')))
												<p class="no-margin">
													<strong>{{ t('Job Type') }}:</strong>&nbsp;
													<a href="{{ \App\Helpers\UrlGen::searchWithoutQuery() . '?type[]=' . data_get($post, 'postType.id') }}">
														{{ data_get($post, 'postType.name') }}
													</a>
												</p>
												@endif
											</li>
											<li>
												<p class="no-margin">
													<strong>{{ t('location') }}:</strong>&nbsp;
													<a href="{!! \App\Helpers\UrlGen::city(data_get($post, 'city')) !!}">
														{{ data_get($post, 'city.name') }}
													</a>
												</p>
											</li>
										</ul>
									</aside>
									
									<div class="posts-action">
										<ul class="list-border">
											@if (!empty(data_get($post, 'company')))
												<li>
													<a href="{{ \App\Helpers\UrlGen::company(null, data_get($post, 'company.id')) }}">
														<i class="far fa-building"></i> {{ t('More jobs by company', ['company' => data_get($post, 'company.name')]) }}
													</a>
												</li>
											@endif
											
											@if (isset($user) && !empty($user))
												<li>
													<a href="{{ \App\Helpers\UrlGen::user($user) }}">
														<i class="bi bi-person-rolodex"></i> {{ t('More jobs by user', ['user' => data_get($user, 'name')]) }}
													</a>
												</li>
											@endif
											
											@if (
												!auth()->check()
												|| (auth()->check() && auth()->id() != data_get($post, 'user_id'))
											)
												@if (isVerifiedPost($post))
													<li id="{{ data_get($post, 'id') }}">
														<a class="make-favorite" href="javascript:void(0)">
														@if (!auth()->check())
															<i class="far fa-bookmark"></i> {{ t('Save Job') }}
														@endif
														@if (auth()->check() && in_array(auth()->user()->user_type_id, [2]))
															@if (!empty(data_get($post, 'savedByLoggedUser')))
																<i class="fas fa-bookmark"></i> {{ t('Saved Job') }}
															@else
																<i class="far fa-bookmark"></i> {{ t('Save Job') }}
															@endif
														@endif
														</a>
													</li>
													<li>
														<a href="{{ \App\Helpers\UrlGen::reportPost($post) }}">
															<i class="far fa-flag"></i> {{ t('Report abuse') }}
														</a>
													</li>
												@endif
											@endif
										</ul>
									</div>
								</div>
							</div>
							
							<div class="content-footer text-start">
								@if (auth()->check())
									@if (auth()->user()->id == data_get($post, 'user_id'))
										<a class="btn btn-default" href="{{ \App\Helpers\UrlGen::editPost($post) }}">
											<i class="far fa-edit"></i> {{ t('Edit') }}
										</a>
									@else
										@if (in_array(auth()->user()->user_type_id, [2]))
											{!! genEmailContactBtn($post) !!}
										@endif
									@endif
								@else
									{!! genEmailContactBtn($post) !!}
								@endif
								{!! genPhoneNumberBtn($post) !!}
								&nbsp;<small><?php /* or. Send your CV to: foo@bar.com */ ?></small>
							</div>
						</div>
					</div>
				</div>

				<div class="col-lg-3 page-sidebar-right">
					<aside>
						<div class="card sidebar-card card-contact-seller">
							<div class="card-header">{{ t('Company Information') }}</div>
							<div class="card-content user-info">
								<div class="card-body text-center">
									<div class="seller-info">
										<div class="company-logo-thumb mb20">
											@if (!empty(data_get($post, 'company')))
												<a href="{{ \App\Helpers\UrlGen::company(null, data_get($post, 'company.id')) }}">
													<img alt="Logo {{ data_get($post, 'company_name') }}" class="img-fluid" src="{{ data_get($post, 'logo_url') }}">
												</a>
											@else
												<img alt="Logo {{ data_get($post, 'company_name') }}" class="img-fluid" src="{{ data_get($post, 'logo_url') }}">
											@endif
										</div>
										@if (!empty(data_get($post, 'company')))
											<h3 class="no-margin">
												<a href="{{ \App\Helpers\UrlGen::company(null, data_get($post, 'company.id')) }}">
													{{ data_get($post, 'company.name') }}
												</a>
											</h3>
										@else
											<h3 class="no-margin">{{ data_get($post, 'company_name') }}</h3>
										@endif
										<p>
											{{ t('location') }}:&nbsp;
											<strong>
												<a href="{!! \App\Helpers\UrlGen::city(data_get($post, 'city')) !!}">
													{{ data_get($post, 'city.name') }}
												</a>
											</strong>
										</p>
										@if (!config('settings.single.hide_dates'))
											@if (isset($user) && !empty($user) && !empty(data_get($user, 'created_at_formatted')))
												<p>{{ t('Joined') }}: <strong>{!! data_get($user, 'created_at_formatted') !!}</strong></p>
											@endif
										@endif
										@if (!empty(data_get($post, 'company')))
											@if (!empty(data_get($post, 'company.website')))
												<p>
													{{ t('Web') }}:
													<strong>
														<a href="{{ data_get($post, 'company.website') }}" target="_blank" rel="nofollow">
															{{ getHostByUrl(data_get($post, 'company.website')) }}
														</a>
													</strong>
												</p>
											@endif
										@endif
									</div>
									<div class="user-posts-action">
										@if (auth()->check())
											@if (auth()->user()->id == data_get($post, 'user_id'))
												<a href="{{ \App\Helpers\UrlGen::editPost($post) }}" class="btn btn-default btn-block">
													<i class="far fa-edit"></i> {{ t('Update the details') }}
												</a>
												@if (config('settings.single.publication_form_type') == '1')
													@if (isset($countPackages) && isset($countPaymentMethods) && $countPackages > 0 && $countPaymentMethods > 0)
														<a href="{{ url('posts/' . data_get($post, 'id') . '/payment') }}" class="btn btn-success btn-block">
															<i class="far fa-check-circle"></i> {{ t('Make It Premium') }}
														</a>
													@endif
												@endif
												@if (empty(data_get($post, 'archived_at')) && isVerifiedPost($post))
													<a href="{{ url('account/posts/list/' . data_get($post, 'id') . '/offline') }}" class="btn btn-warning btn-block confirm-simple-action">
														<i class="fas fa-eye-slash"></i> {{ t('put_it_offline') }}
													</a>
												@endif
												@if (!empty(data_get($post, 'archived_at')))
													<a href="{{ url('account/posts/archived/' . data_get($post, 'id') . '/repost') }}" class="btn btn-info btn-block confirm-simple-action">
														<i class="fa fa-recycle"></i> {{ t('re_post_it') }}
													</a>
												@endif
											@else
												@if (in_array(auth()->user()->user_type_id, [2]))
													{!! genEmailContactBtn($post, true) !!}
												@endif
												{!! genPhoneNumberBtn($post, true) !!}
											@endif
											<?php
											try {
												if (auth()->user()->can(\App\Models\Permission::getStaffPermissions())) {
													$btnUrl = admin_url('blacklists/add') . '?';
													$btnQs = (!empty(data_get($post, 'email'))) ? 'email=' . data_get($post, 'email') : '';
													$btnQs = (!empty($btnQs)) ? $btnQs . '&' : $btnQs;
													$btnQs = (!empty(data_get($post, 'phone'))) ? $btnQs . 'phone=' . data_get($post, 'phone') : $btnQs;
													$btnUrl = $btnUrl . $btnQs;
													
													if (!isDemoDomain($btnUrl)) {
														$btnText = trans('admin.ban_the_user');
														$btnHint = $btnText;
														if (!empty(data_get($post, 'email')) && !empty(data_get($post, 'phone'))) {
															$btnHint = trans('admin.ban_the_user_email_and_phone', [
																'email' => data_get($post, 'email'),
																'phone' => data_get($post, 'phone'),
															]);
														} else {
															if (!empty(data_get($post, 'email'))) {
																$btnHint = trans('admin.ban_the_user_email', ['email' => data_get($post, 'email')]);
															}
															if (!empty(data_get($post, 'phone'))) {
																$btnHint = trans('admin.ban_the_user_phone', ['phone' => data_get($post, 'phone')]);
															}
														}
														$tooltip = ' data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . $btnHint . '"';
														
														$btnOut = '<a href="'. $btnUrl .'" class="btn btn-outline-danger btn-block confirm-simple-action"'. $tooltip .'>';
														$btnOut .= $btnText;
														$btnOut .= '</a>';
														
														echo $btnOut;
													}
												}
											} catch (\Throwable $e) {}
											?>
										@else
											{!! genEmailContactBtn($post, true) !!}
											{!! genPhoneNumberBtn($post, true) !!}
										@endif
									</div>
								</div>
							</div>
						</div>
						
						@if (config('settings.single.show_listing_on_googlemap'))
							<?php
							$mapHeight = 250;
							$mapPlace = (!empty(data_get($post, 'city')))
								? data_get($post, 'city.name') . ',' . config('country.name')
								: config('country.name');
							$mapUrl = getGoogleMapsEmbedUrl(config('services.googlemaps.key'), $mapPlace);
							?>
							<div class="card sidebar-card">
								<div class="card-header">{{ t('location_map') }}</div>
								<div class="card-content">
									<div class="card-body text-start p-0">
										<div class="posts-googlemaps">
											<iframe id="googleMaps" width="100%" height="{{ $mapHeight }}" src="{{ $mapUrl }}"></iframe>
										</div>
									</div>
								</div>
							</div>
						@endif
						
						@if (isVerifiedPost($post))
							@includeFirst([config('larapen.core.customizedViewPath') . 'layouts.inc.social.horizontal', 'layouts.inc.social.horizontal'])
						@endif

						<div class="card sidebar-card">
							<div class="card-header">{{ t('Tips for candidates') }}</div>
							<div class="card-content">
								<div class="card-body text-start">
									<ul class="list-check">
										<li> {{ t('Check if the offer matches your profile') }} </li>
                                        <li> {{ t('Check the start date') }} </li>
										<li> {{ t('Meet the employer in a professional location') }} </li>
									</ul>
                                    <?php $tipsLinkAttributes = getUrlPageByType('tips'); ?>
									@if (!str_contains($tipsLinkAttributes, 'href="#"') && !str_contains($tipsLinkAttributes, 'href=""'))
									<p>
										<a class="float-end" {!! $tipsLinkAttributes !!}>
											{{ t('Know more') }}
											<i class="fa fa-angle-double-right"></i>
										</a>
									</p>
                                    @endif
								</div>
							</div>
						</div>
					</aside>
				</div>
			</div>

		</div>
		
		@if (config('settings.single.similar_listings') == '1' || config('settings.single.similar_listings') == '2')
			<?php $widgetType = (config('settings.single.similar_listings_in_carousel') ? 'carousel' : 'normal') ?>
			@includeFirst([
					config('larapen.core.customizedViewPath') . 'search.inc.posts.widget.' . $widgetType,
					'search.inc.posts.widget.' . $widgetType
				],
				['widget' => ($widgetSimilarPosts ?? null), 'firstSection' => false]
			)
		@endif
		
		@includeFirst([config('larapen.core.customizedViewPath') . 'layouts.inc.advertising.bottom', 'layouts.inc.advertising.bottom'], ['firstSection' => false])
		
		@if (isVerifiedPost($post))
			@includeFirst([config('larapen.core.customizedViewPath') . 'layouts.inc.tools.facebook-comments', 'layouts.inc.tools.facebook-comments'], ['firstSection' => false])
		@endif
		
	</div>
@endsection
<?php
if (!session()->has('emailVerificationSent') && !session()->has('phoneVerificationSent')) {
	if (session()->has('message')) {
		session()->forget('message');
	}
}
?>

@section('modal_message')
	@if (auth()->check() || config('settings.single.guests_can_contact_authors')=='1')
		@includeFirst([config('larapen.core.customizedViewPath') . 'account.messenger.modal.create', 'account.messenger.modal.create'])
	@endif
@endsection

@section('after_styles')
@endsection

@section('after_scripts')
    @if (config('services.googlemaps.key'))
		{{-- More Info: https://developers.google.com/maps/documentation/javascript/versions --}}
        <script async src="https://maps.googleapis.com/maps/api/js?v=weekly&key={{ config('services.googlemaps.key') }}"></script>
    @endif
	
	<script>
		{{-- Favorites Translation --}}
        var lang = {
            labelSavePostSave: "{!! t('Save Job') !!}",
            labelSavePostRemove: "{{ t('Saved Job') }}",
            loginToSavePost: "{!! t('Please log in to save the Ads') !!}",
            loginToSaveSearch: "{!! t('Please log in to save your search') !!}"
        };
		
		$(document).ready(function () {
			@if (config('settings.single.show_listing_on_googlemap'))
				{{--
				let mapUrl = '{{ addslashes($mapUrl) }}';
				let iframe = document.getElementById('googleMaps');
				iframe.setAttribute('src', mapUrl);
				--}}
			@endif
		});
	</script>
@endsection