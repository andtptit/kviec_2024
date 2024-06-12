<?php
$posts ??= [];
$totalPosts ??= 0;
?>
@if (!empty($posts) && $totalPosts > 0)
	<?php
	foreach($posts as $key => $post):
		// Get Package Info
		$premiumClass = '';
		$premiumBadge = '';
		if (data_get($post, 'featured') == 1) {
			if (!empty(data_get($post, 'latestPayment.package'))) {
				$premiumClass = ' premium-post';
				$premiumBadge = ' <span class="badge bg-dark float-end">' . data_get($post, 'latestPayment.package.short_name') . '</span>';
			}
		}
		?>
		<div class="item-list job-item{{ $premiumClass }}">
			<div class="row">
				<div class="col-md-1 col-sm-2 no-padding photobox">
					<div class="add-image">
						<a href="{{ \App\Helpers\UrlGen::post($post) }}">
							<img class="img-thumbnail no-margin" src="{{ data_get($post, 'logo_url') }}" alt="{{ data_get($post, 'company_name') }}">
						</a>
					</div>
				</div>
				
				<div class="col-md-11 col-sm-10 add-desc-box">
					<div class="add-details jobs-item">
						<h5 class="company-title">
							@if (!empty(data_get($post, 'company_id')))
								<a href="{{ \App\Helpers\UrlGen::company(null, data_get($post, 'company_id')) }}">
									{{ data_get($post, 'company_name') }}
								</a>
							@else
								{{ data_get($post, 'company_name') }}
							@endif
						</h5>
						<h4 class="job-title">
							<a href="{{ \App\Helpers\UrlGen::post($post) }}">{{ str(data_get($post, 'title'))->limit(70) }}</a>{!! $premiumBadge !!}
						</h4>
						<span class="info-row">
							@if (!config('settings.list.hide_dates'))
								<span class="date">
									<i class="far fa-clock"></i> {!! data_get($post, 'created_at_formatted') !!}
								</span>
							@endif
							<span class="category">
								<i class="bi bi-folder"></i>&nbsp;
								@if (!empty(data_get($post, 'category.parent')))
									<a href="{!! \App\Helpers\UrlGen::category(data_get($post, 'category.parent'), null, $city ?? null) !!}">
										{{ data_get($post, 'category.parent.name') }}
									</a>&nbsp;&raquo;&nbsp;
								@endif
								<a href="{!! \App\Helpers\UrlGen::category(data_get($post, 'category'), null, $city ?? null) !!}">
									{{ data_get($post, 'category.name') }}
								</a>
							</span>
							<span class="item-location">
								<i class="bi bi-geo-alt"></i>&nbsp;
								<a href="{!! \App\Helpers\UrlGen::city(data_get($post, 'city'), null, $cat ?? null) !!}">
									{{ data_get($post, 'city.name') }}
								</a> {{ (!empty(data_get($post, 'distance'))) ? '- ' . round(data_get($post, 'distance'), 2) . getDistanceUnit() : '' }}
							</span>
							<span class="post_type">
								<i class="bi bi-tag"></i> {{ data_get($post, 'postType.name') }}
							</span>
							<span class="salary">
								<i class="bi bi-cash-coin"></i>&nbsp;
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
							</span>
						</span>
	
						<div class="jobs-desc">
							{!! str(strCleaner(data_get($post, 'description')))->limit(180) !!}
						</div>
	
						<div class="job-actions">
							<ul class="list-unstyled list-inline">
								@if (!auth()->check())
									<li id="{{ data_get($post, 'id') }}">
										<a class="save-job" id="save-{{ data_get($post, 'id') }}" href="javascript:void(0)">
											<span class="far fa-bookmark"></span> {{ t('Save Job') }}
										</a>
									</li>
								@endif
								@if (auth()->check() && in_array(auth()->user()->user_type_id, [2]))
									@if (!empty(data_get($post, 'savedByLoggedUser')))
										<li class="saved-job" id="{{ data_get($post, 'id') }}">
											<a class="saved-job" id="saved-{{ data_get($post, 'id') }}" href="javascript:void(0)">
												<span class="fas fa-bookmark"></span> {{ t('Saved Job') }}
											</a>
										</li>
									@else
										<li id="{{ data_get($post, 'id') }}">
											<a class="save-job" id="save-{{ data_get($post, 'id') }}" href="javascript:void(0)">
												<span class="far fa-bookmark"></span> {{ t('Save Job') }}
											</a>
										</li>
									@endif
								@endif
								<li>
									<a class="email-job" data-bs-toggle="modal" data-id="{{ data_get($post, 'id') }}" href="#sendByEmail" id="email-{{ data_get($post, 'id') }}">
										<i class="far fa-envelope"></i>
										{{ t('Email Job') }}
									</a>
								</li>
							</ul>
						</div>
	
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
@else
	<div class="p-4" style="width: 100%;">
		@if (str_contains(\Route::currentRouteAction(), 'Search\CompanyController'))
			{{ t('No jobs were found for this company') }}
		@else
			{{ t('no_result_refine_your_search') }}
		@endif
	</div>
@endif

@section('modal_location')
	@parent
	@include('layouts.inc.modal.send-by-email')
@endsection

@section('after_scripts')
	@parent
	<script>
		/* Favorites Translation */
		var lang = {
			labelSavePostSave: "{!! t('Save Job') !!}",
			labelSavePostRemove: "{{ t('Saved Job') }}",
			loginToSavePost: "{!! t('Please log in to save the Ads') !!}",
			loginToSaveSearch: "{!! t('Please log in to save your search') !!}"
		};
		
		$(document).ready(function ()
		{
			/* Get Post ID */
			$('.email-job').click(function(){
				let postId = $(this).attr("data-id");
				$('input[type=hidden][name=post_id]').val(postId);
			});
			
			@if (isset($errors) && $errors->any())
				@if (old('sendByEmailForm')=='1')
					{{-- Re-open the modal if error occured --}}
					let sendByEmail = new bootstrap.Modal(document.getElementById('sendByEmail'), {});
					sendByEmail.show();
				@endif
			@endif
		})
	</script>
@endsection
