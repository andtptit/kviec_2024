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

namespace App\Helpers\Search\Traits\Filters;

trait CompanyFilter
{
	protected function applyCompanyFilter()
	{
		if (!isset($this->posts)) {
			return;
		}
		
		$companyId = null;
		if (request()->filled('companyId')) {
			$companyId = request()->get('companyId');
		}
		
		if (empty($companyId)) {
			return;
		}
		
		$this->posts->where('company_id', $companyId);
	}
}
