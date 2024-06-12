<?php
$adminsDivisions = $adminsDivisions ?? [];
?>
{{-- Modal Change City --}}
<div class="modal fade" id="browseAdminCities" tabindex="-1" role="dialog" aria-labelledby="adminCitiesModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			
			<div class="modal-header px-3">
				<h4 class="modal-title" id="adminCitiesModalLabel">
					<i class="far fa-map"></i> {{ t('Select your region') }}
				</h4>
				
				<button type="button" class="close" data-bs-dismiss="modal">
					<span aria-hidden="true">&times;</span>
					<span class="sr-only">{{ t('Close') }}</span>
				</button>
			</div>
			
			<div class="modal-body">
				<div class="row">
					<div class="col-12">
						<p id="selectedAdmin">{{ t('Popular cities in') }} <strong>{{ config('country.name') }}</strong></p>
						<div style="clear:both"></div>
						
						<div class="col-8 no-padding">
							<form id="modalAdminForm" name="modalAdminForm" method="POST">
								<input type="hidden"
									   id="currSearch"
									   name="curr_search"
									   value="{{ base64_encode(serialize(request()->all())) }}"
								>
								<select class="form-select" id="modalAdminField" name="admin_code">
									<option selected value="">{{ t('All regions') }}</option>
									@if (count($adminsDivisions) > 0)
										@foreach($adminsDivisions as $admin1)
											<option value="{{ data_get($admin1, 'code') }}">{{ data_get($admin1, 'name') }}</option>
										@endforeach
									@endif
								</select>
								{!! csrf_field() !!}
							</form>
						</div>
						<div style="clear:both"></div>
						
						<hr class="border-0 bg-secondary">
					</div>
					<div class="col-12" id="adminCities"></div>
				</div>
			</div>
			
		</div>
	</div>
</div>

@section('after_scripts')
	@parent
	<script>
		{{-- Modal Default Admin1 Code --}}
		var modalDefaultAdminCode = '{{ data_get($city, 'subadmin1_code') ?? data_get($admin, 'code') ?? 0 }}';
		var loadingWd = '{{ t('loading_wd') }}';
	</script>
	<script src="{{ url('assets/js/app/load.cities.js') . vTime() }}"></script>
@endsection