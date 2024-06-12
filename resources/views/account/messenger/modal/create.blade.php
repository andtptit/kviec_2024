<?php
$post ??= [];
$resumes ??= [];
$totalResumes ??= 0;
$lastResume ??= [];
?>
<div class="modal fade" id="applyJob" tabindex="-1" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			
			<div class="modal-header px-3">
				<h4 class="modal-title">
					<i class="fas fa-envelope"></i> {{ t('Contact Employer') }}
				</h4>
				
				<button type="button" class="close" data-bs-dismiss="modal">
					<span aria-hidden="true">&times;</span>
					<span class="sr-only">{{ t('Close') }}</span>
				</button>
			</div>
			
			<form role="form" method="POST" action="{{ url('account/messages/posts/' . data_get($post, 'id')) }}" enctype="multipart/form-data">
				{!! csrf_field() !!}
				<div class="modal-body">

					@if (isset($errors) && $errors->any() && old('messageForm')=='1')
						<div class="alert alert-danger alert-dismissible">
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ t('Close') }}"></button>
							<ul class="list list-check">
								@foreach($errors->all() as $error)
									<li>{{ $error }}</li>
								@endforeach
							</ul>
						</div>
					@endif
					
					@php
						$authUser = auth()->check() ? auth()->user() : null;
						$isNameCanBeHidden = (!empty($authUser));
						$isEmailCanBeHidden = (!empty($authUser) && !empty($authUser->email));
						$isPhoneCanBeHidden = (!empty($authUser) && !empty($authUser->phone));
						$authFieldValue = data_get($post, 'auth_field', getAuthField());
					@endphp
					
					{{-- name --}}
					@if ($isNameCanBeHidden)
						<input type="hidden" name="name" value="{{ $authUser->name ?? null }}">
					@else
						@php
							$fromNameError = (isset($errors) && $errors->has('name')) ? ' is-invalid' : '';
						@endphp
						<div class="mb-3 required">
							<label class="control-label" for="name">{{ t('Name') }} <sup>*</sup></label>
							<div class="input-group">
								<input id="fromName" name="name"
									   type="text"
									   class="form-control{{ $fromNameError }}"
									   placeholder="{{ t('your_name') }}"
									   value="{{ old('name', $authUser->name ?? null) }}"
								>
							</div>
						</div>
					@endif
					
					{{-- email --}}
					@if ($isEmailCanBeHidden)
						<input type="hidden" name="email" value="{{ $authUser->email ?? null }}">
					@else
						@php
							$fromEmailError = (isset($errors) && $errors->has('email')) ? ' is-invalid' : '';
						@endphp
						<div class="mb-3 required">
							<label class="control-label" for="email">{{ t('email') }}
								@if ($authFieldValue == 'email')
									<sup>*</sup>
								@endif
							</label>
							<div class="input-group">
								<span class="input-group-text"><i class="far fa-envelope"></i></span>
								<input id="fromEmail" name="email"
									   type="text"
									   class="form-control{{ $fromEmailError }}"
									   placeholder="{{ t('eg_email') }}"
									   value="{{ old('email', $authUser->email ?? null) }}"
								>
							</div>
						</div>
					@endif
					
					{{-- phone --}}
					@if ($isPhoneCanBeHidden)
						<input type="hidden" name="phone" value="{{ $authUser->phone ?? null }}">
						<input name="phone_country" type="hidden" value="{{ $authUser->phone_country ?? config('country.code') }}">
					@else
						@php
							$fromPhoneError = (isset($errors) && $errors->has('phone')) ? ' is-invalid' : '';
							$phoneValue = $authUser->phone ?? null;
							$phoneCountryValue = $authUser->phone_country ?? config('country.code');
							$phoneValue = phoneE164($phoneValue, $phoneCountryValue);
							$phoneValueOld = phoneE164(old('phone', $phoneValue), old('phone_country', $phoneCountryValue));
						@endphp
						<div class="mb-3 required">
							<label class="control-label" for="phone">{{ t('phone_number') }}
								@if ($authFieldValue == 'phone')
									<sup>*</sup>
								@endif
							</label>
							<input id="fromPhone" name="phone"
								   type="tel"
								   maxlength="60"
								   class="form-control m-phone{{ $fromPhoneError }}"
								   placeholder="{{ t('phone_number') }}"
								   value="{{ $phoneValueOld }}"
							>
							<input name="phone_country" type="hidden" value="{{ old('phone_country', $phoneCountryValue) }}">
						</div>
					@endif
					
					{{-- auth_field --}}
					<input name="auth_field" type="hidden" value="{{ $authFieldValue }}">
					
					{{-- body --}}
					<?php $bodyError = (isset($errors) && $errors->has('body')) ? ' is-invalid' : ''; ?>
					<div class="mb-3 required">
						<label class="control-label" for="body">
							{{ t('Message') }} <span class="text-count">(500 max)</span> <sup>*</sup>
						</label>
						<textarea id="body" name="body"
							rows="5"
							class="form-control required{{ $bodyError }}"
							style="height: 150px;"
							placeholder="{{ t('your_message_here') }}"
						>{{ old('body') }}</textarea>
					</div>
					
					{{-- filename --}}
					<?php $resumeIdError = (isset($errors) && $errors->has('resume_id')) ? ' is-invalid' : ''; ?>
					<div class="mb-2">
						<label class="control-label" for="filename">{{ t('Resume') }} </label>
						<div class="form-text text-muted">{!! t('Select a Resume') !!}</div>
						<div id="resumeId" class="mb-2">
							<?php
							$selectedResume = 0;
							?>
							@if (!empty($resumes) && $totalResumes > 0)
								@foreach ($resumes as $iResume)
									@continue(!$pDisk->exists(data_get($iResume, 'filename')))
									<?php
									$iResume = $iResume ?? [];
									$iResumeId = data_get($iResume, 'id');
									$selectedResume = (old('resume_id', 0) == $iResumeId)
										? $iResumeId
										: (!empty($lastResume) ? data_get($lastResume, 'id') : 0);
									?>
									<div class="form-check pt-2">
										<input id="resumeId{{ $iResumeId }}" name="resume_id"
											   value="{{ $iResumeId }}"
											   type="radio"
											   class="form-check-input{{ $resumeIdError }}"
												{{ ($selectedResume == $iResumeId) ? 'checked="checked"' : '' }}
										>
										<label class="form-check-label" for="resumeId{{ $iResumeId }}">
											{{ data_get($iResume, 'name') }} - <a href="{{ privateFileUrl(data_get($iResume, 'filename')) }}" target="_blank">{{ t('Download') }}</a>
										</label>
									</div>
								@endforeach
							@endif
							<div class="form-check pt-2">
								<input id="resumeId0"
									   name="resume_id"
									   value="0"
									   type="radio"
									   class="form-check-input{{ $resumeIdError }}"
										{{ ($selectedResume == 0) ? 'checked="checked"' : '' }}
								>
								<label class="form-check-label" for="resumeId0">
									{{ '[+] ' . t('New Resume') }}
								</label>
							</div>
						</div>
					</div>
					
					<div class="mb-3">
						@includeFirst([config('larapen.core.customizedViewPath') . 'account.resume._form', 'account.resume._form'], ['originForm' => 'message'])
					</div>
					
					@include('layouts.inc.tools.captcha', ['label' => true])
					
					<input type="hidden" name="country_code" value="{{ config('country.code') }}">
					<input type="hidden" name="post_id" value="{{ data_get($post, 'id') }}">
					<input type="hidden" name="messageForm" value="1">
				</div>
				
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary float-end">{{ t('send_message') }}</button>
					<button type="button" class="btn btn-default" data-bs-dismiss="modal">{{ t('Cancel') }}</button>
				</div>
			</form>
			
		</div>
	</div>
</div>
@section('after_styles')
	@parent
	<link href="{{ url('assets/plugins/bootstrap-fileinput/css/fileinput.min.css') }}" rel="stylesheet">
	@if (config('lang.direction') == 'rtl')
		<link href="{{ url('assets/plugins/bootstrap-fileinput/css/fileinput-rtl.min.css') }}" rel="stylesheet">
	@endif
	<style>
		.krajee-default.file-preview-frame:hover:not(.file-preview-error) {
			box-shadow: 0 0 5px 0 #666666;
		}
	</style>
@endsection

@section('after_scripts')
    @parent
	
	<script src="{{ url('assets/plugins/bootstrap-fileinput/js/plugins/sortable.min.js') }}" type="text/javascript"></script>
	<script src="{{ url('assets/plugins/bootstrap-fileinput/js/fileinput.min.js') }}" type="text/javascript"></script>
	<script src="{{ url('assets/plugins/bootstrap-fileinput/themes/fas/theme.js') }}" type="text/javascript"></script>
	<script src="{{ url('common/js/fileinput/locales/' . config('app.locale') . '.js') }}" type="text/javascript"></script>
	
	<script>
		@if (auth()->check())
			phoneCountry = '{{ old('phone_country', ($phoneCountryValue ?? '')) }}';
		@endif
		
		{{-- Resume --}}
		@php
			$lastResumeExists = (!empty(data_get($lastResume, 'filename')) && $pDisk->exists(data_get($lastResume, 'filename')));
			$lastResumeId = $lastResumeExists ? data_get($lastResume, 'id', 0) : 0;
			$lastResumeId = old('resume_id', $lastResumeId);
		@endphp
		var lastResumeId = {{ $lastResumeId }};
		
		$(document).ready(function () {
			@if (isset($errors) && $errors->any())
				@if ($errors->any() && old('messageForm')=='1')
					{{-- Re-open the modal if error occured --}}
					let quickLogin = new bootstrap.Modal(document.getElementById('applyJob'), {});
					quickLogin.show();
				@endif
			@endif
			
			{{-- Resume --}}
			getResume(lastResumeId);
			$('#resumeId input').bind('click, change', function() {
				getResume($(this).val());
			});
		});
	</script>
@endsection
