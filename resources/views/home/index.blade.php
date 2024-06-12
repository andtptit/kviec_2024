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

@section('search')
	@parent
@endsection

@section('content')
	<div class="main-container" id="homepage">
		
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
		@endif
		
		@if (!empty($sections))
			@foreach($sections as $section)
				<?php
				 $section ??= [];
				$sectionView = data_get($section, 'view');
				$sectionData = (array)data_get($section, 'data');
				?>
				@if (!empty($sectionView) && view()->exists($sectionView))
					@includeFirst(
						[
							config('larapen.core.customizedViewPath') . $sectionView,
							$sectionView
						],
						[
							'sectionData' => $sectionData,
							'firstSection' => $loop->first
						]
					)
				@endif
			@endforeach
		@endif
		
	</div>
@endsection

@section('after_scripts')
@endsection
