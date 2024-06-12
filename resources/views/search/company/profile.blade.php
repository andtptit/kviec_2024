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
$apiResult ??= [];
$apiExtra ??= [];
$count = (array)data_get($apiExtra, 'count');
$posts = (array)data_get($apiResult, 'data');
$totalPosts = (int)data_get($apiResult, 'meta.total', 0);
$tags = (array)data_get($apiExtra, 'tags');
$company ??= [];
?>
@section('search')
	@parent
	@includeFirst([config('larapen.core.customizedViewPath') . 'search.inc.form', 'search.inc.form'])
	@includeFirst([config('larapen.core.customizedViewPath') . 'search.inc.breadcrumbs', 'search.inc.breadcrumbs'])
	@includeFirst([config('larapen.core.customizedViewPath') . 'layouts.inc.advertising.top', 'layouts.inc.advertising.top'])
@endsection

@section('content')
	@includeFirst([config('larapen.core.customizedViewPath') . 'common.spacer', 'common.spacer'])
	<div class="main-container">
		<div class="container">
			
			<div class="section-content">
				<div class="inner-box">
					<div class="row">
						<?php
							$colDetails = 'col-12';
							$colContact = null;
							if (
								(!empty(data_get($company, 'address')))
								|| (!empty(data_get($company, 'phone')))
								|| (!empty(data_get($company, 'mobile')))
								|| (!empty(data_get($company, 'fax')))
							) {
								$colDetails = 'col-lg-8 col-md-6 col-sm-12';
								$colContact = 'col-lg-4 col-md-6 col-sm-12';
							}
						?>
						<div class="{{ $colDetails }}">
							<div class="seller-info seller-profile">
								<div class="seller-profile-img">
									<a><img src="{{ data_get($company, 'logo_url') }}" class="img-fluid img-thumbnail" alt="img"></a>
								</div>
								<h3 class="no-margin no-padding link-color uppercase">
									@if (auth()->check())
										@if (auth()->user()->id == data_get($company, 'user_id'))
											<a href="{{ url('account/companies/' . data_get($company, 'id') . '/edit') }}" class="btn btn-default">
												<i class="far fa-edit"></i> {{ t('Edit') }}
											</a>
										@endif
									@endif
									{{ data_get($company, 'name') }}
								</h3>
								
								<div class="text-muted">
									{!! data_get($company, 'description') !!}
								</div>
								
								<div class="seller-social-list">
									<ul class="share-this-post">
										@if (!empty(data_get($company, 'linkedin')))
											<li><a href="{{ data_get($company, 'linkedin') }}" target="_blank"><i class="fa icon-linkedin-rect"></i></a></li>
										@endif
										@if (!empty(data_get($company, 'facebook')))
											<li><a class="facebook" href="{{ data_get($company, 'facebook') }}" target="_blank"><i class="fa fa-facebook"></i></a></li>
										@endif
										@if (!empty(data_get($company, 'twitter')))
											<li><a href="{{ data_get($company, 'twitter') }}" target="_blank"><i class="fa fa-twitter"></i></a></li>
										@endif
										@if (!empty(data_get($company, 'pinterest')))
											<li><a class="pinterest" href="{{ data_get($company, 'pinterest') }}" target="_blank"><i class="fa fa-pinterest"></i></a></li>
										@endif
									</ul>
								</div>
							</div>
						</div>
						
						@if (!empty($colContact))
							<div class="{{ $colContact }}">
								<div class="seller-contact-info mt5">
									<h3 class="no-margin"> {{ t('Contact Information') }} </h3>
									<dl class="dl-horizontal">
										@if (!empty(data_get($company, 'address')))
											<dt>{{ t('Address') }}:</dt>
											<dd class="contact-sensitive">{!! data_get($company, 'address') !!}</dd>
										@endif
										
										@if (!empty(data_get($company, 'phone')))
											<dt>{{ t('phone') }}:</dt>
											<dd class="contact-sensitive">{{ data_get($company, 'phone') }}</dd>
										@endif
										
										@if (!empty(data_get($company, 'mobile')))
											<dt>{{ t('Mobile Phone') }}:</dt>
											<dd class="contact-sensitive">{{ data_get($company, 'mobile') }}</dd>
										@endif
										
										@if (!empty(data_get($company, 'fax')))
											<dt>{{ t('Fax') }}:</dt>
											<dd class="contact-sensitive">{{ data_get($company, 'fax') }}</dd>
										@endif
										
										@if (!empty(data_get($company, 'website')))
											<dt>{{ t('Website') }}:</dt>
											<dd class="contact-sensitive">
												<a href="{!! data_get($company, 'website') !!}" target="_blank">
													{!! data_get($company, 'website') !!}
												</a>
											</dd>
										@endif
									</dl>
								</div>
							</div>
						@endif
					</div>
				</div>
				
				<div class="section-block mt-3">
					<div class="category-list">
						<div class="tab-box clearfix">
							
							{{-- Nav tabs --}}
							<div class="col-lg-12 box-title no-border">
								<div class="inner">
									<h2 class="mx-3">
										<small>{{ data_get($count, '0') }} {{ t('Jobs Found') }}</small>
									</h2>
								</div>
							</div>
							
							{{-- Mobile Filter bar --}}
							<div class="mobile-filter-bar col-lg-12"></div>
							<div class="menu-overly-mask"></div>
							
							{{-- Tab Filter --}}
							<div class="tab-filter hide-xs"></div>
						
						</div>
						
						<div class="listing-filter hidden-xs">
							<div class="float-start col-sm-10 col-12">
								<div class="breadcrumb-list text-center-xs">
									{!! (isset($htmlTitle)) ? $htmlTitle : '' !!}
								</div>
							</div>
							<div class="float-end col-sm-2 col-12 text-end text-center-xs listing-view-action">
								@if (!empty(request()->all()))
									<a class="clear-all-button text-muted" href="{!! \App\Helpers\UrlGen::searchWithoutQuery() !!}">{{ t('Clear all') }}</a>
								@endif
							</div>
							<div style="clear:both;"></div>
						</div>
						<!--/.listing-filter-->
						
						<div class="posts-wrapper jobs-list">
							@includeFirst([config('larapen.core.customizedViewPath') . 'search.inc.posts.template.list', 'search.inc.posts.template.list'])
						</div>
						<!--/.posts-wrapper-->
						
						<div class="tab-box save-search-bar text-center">
							@if (request()->filled('q') && request()->get('q') != '' && $count->get('all') > 0)
								<a id="saveSearch"
								   data-name="{!! request()->fullUrlWithoutQuery(['_token', 'location']) !!}"
								   data-count="{{ data_get($count, '0') }}"
								>
									<i class="icon-star-empty"></i> {{ t('Save Search') }}
								</a>
							@else
								<a href="#"> &nbsp; </a>
							@endif
						</div>
					</div>
		
					<div class="pagination-bar text-center">
						@include('vendor.pagination.api.bootstrap-4')
					</div>
				</div>
				
				<div style="clear:both;"></div>
				
				{{-- Advertising --}}
				@includeFirst([config('larapen.core.customizedViewPath') . 'layouts.inc.advertising.bottom', 'layouts.inc.advertising.bottom'])
			</div>
		
		</div>
	</div>
@endsection