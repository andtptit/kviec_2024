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
$transactions = (array)data_get($apiResult, 'data');
$totalTransactions = (int)data_get($apiResult, 'meta.total', 0);
?>
@section('content')
	@include('common.spacer')
	<div class="main-container">
		<div class="container">
			<div class="row">
				
				<div class="col-md-3 page-sidebar">
					@include('account.inc.sidebar')
				</div>
				
				<div class="col-md-9 page-content">
					<div class="inner-box">
						<h2 class="title-2"><i class="fas fa-coins"></i> {{ t('Transactions') }} </h2>
						
						<div style="clear:both"></div>
						
						<div class="table-responsive">
							<table class="table table-bordered">
								<thead>
								<tr>
									<th><span>ID</span></th>
									<th>{{ t('Description') }}</th>
									<th>{{ t('Payment Method') }}</th>
									<th>{{ t('Value') }}</th>
									<th>{{ t('Date') }}</th>
									<th>{{ t('Status') }}</th>
								</tr>
								</thead>
								<tbody>
								<?php
								if (!empty($transactions) && $totalTransactions > 0):
									foreach($transactions as $key => $transaction):
								?>
								<tr>
									<td>#{{ data_get($transaction, 'id') }}</td>
									<td>
										<a href="{{ \App\Helpers\UrlGen::post(data_get($transaction, 'post')) }}">{{ data_get($transaction, 'post.title') }}</a><br>
										<strong>{{ t('Type') }}</strong> {{ data_get($transaction, 'package.short_name') }} <br>
										<strong>{{ t('Duration') }}</strong> {{ data_get($transaction, 'package.duration') }} {{ t('days') }}
									</td>
									<td>
										@if (data_get($transaction, 'active') == 1)
											@if (!empty(data_get($transaction, 'paymentMethod')))
												{{ t('Paid by') }} {{ data_get($transaction, 'paymentMethod.display_name') }}
											@else
												{{ t('Paid by') }} --
											@endif
										@else
											{{ t('Pending payment') }}
										@endif
									</td>
									<td>{!! data_get($transaction, 'package.currency.symbol') . data_get($transaction, 'package.price') !!}</td>
									<td>{!! data_get($transaction, 'created_at_formatted') !!}</td>
									<td>
										@if (data_get($transaction, 'active') == 1)
											<span class="badge bg-success">{{ t('Done') }}</span>
										@else
											<span class="badge badge-info">{{ t('Pending') }}</span>
										@endif
									</td>
								</tr>
								<?php endforeach; ?>
								<?php endif; ?>
								</tbody>
							</table>
						</div>
						
						<nav aria-label="">
							@include('vendor.pagination.api.bootstrap-4')
						</nav>
						
						<div style="clear:both"></div>
					
					</div>
				</div>
				
			</div>
		</div>
	</div>
@endsection

@section('after_scripts')
@endsection