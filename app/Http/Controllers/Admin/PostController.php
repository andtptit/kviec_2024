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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Web\Auth\Traits\VerificationTrait;
use App\Models\PostType;
use App\Models\Category;
use App\Models\SalaryType;
use App\Http\Controllers\Admin\Panel\PanelController;
use App\Http\Requests\Admin\PostRequest as StoreRequest;
use App\Http\Requests\Admin\PostRequest as UpdateRequest;

class PostController extends PanelController
{
	use VerificationTrait;
	
	public function setup()
	{
		/*
		|--------------------------------------------------------------------------
		| BASIC CRUD INFORMATION
		|--------------------------------------------------------------------------
		*/
		$this->xPanel->setModel('App\Models\Post');
		$this->xPanel->with([
			'user',
			'city',
			'latestPayment',
			'latestPayment.package',
			'country',
		]);
		$this->xPanel->setRoute(admin_uri('posts'));
		$this->xPanel->setEntityNameStrings(trans('admin.ad'), trans('admin.ads'));
		$this->xPanel->denyAccess(['create']);
		if (!request()->input('order')) {
			if (config('settings.single.listings_review_activation')) {
				$this->xPanel->orderBy('reviewed_at', 'ASC');
			}
			$this->xPanel->orderBy('created_at', 'DESC');
		}
		
		$this->xPanel->addButtonFromModelFunction('top', 'bulk_approval_btn', 'bulkApprovalBtn', 'end');
		$this->xPanel->addButtonFromModelFunction('top', 'bulk_disapproval_btn', 'bulkDisapprovalBtn', 'end');
		$this->xPanel->addButtonFromModelFunction('top', 'bulk_deletion_btn', 'bulkDeletionBtn', 'end');
		
		// Hard Filters
		if (request()->filled('active')) {
			if (request()->get('active') == 0) {
				$this->xPanel->addClause('where', function ($query) {
					$query->where(function ($query) {
						$query->whereNull('email_verified_at');
					});
				});
				$this->xPanel->addClause('orWhere', function ($query) {
					$query->where(function ($query) {
						$query->whereNull('phone_verified_at');
					});
				});
				if (config('settings.single.listings_review_activation')) {
					$this->xPanel->addClause('orWhere', function ($query) {
						$query->where(function ($query) {
							$query->whereNull('reviewed_at');
						});
					});
				}
			}
			if (request()->get('active') == 1) {
				$this->xPanel->addClause('where', function ($query) {
					$query->whereNotNull('email_verified_at')->whereNotNull('phone_verified_at');
					if (config('settings.single.listings_review_activation')) {
						$query->whereNotNull('reviewed_at');
					}
				});
			}
		}
		
		// Filters
		// -----------------------
		$this->xPanel->disableSearchBar();
		// -----------------------
		$this->xPanel->addFilter([
			'name'  => 'id',
			'type'  => 'text',
			'label' => 'ID/' . t('reference'),
		],
			false,
			function ($value) {
				$value = hashId($value, true) ?? $value;
				$this->xPanel->addClause('where', 'id', '=', $value);
			});
		// -----------------------
		$this->xPanel->addFilter([
			'name'  => 'from_to',
			'type'  => 'date_range',
			'label' => trans('admin.Date range'),
		],
			false,
			function ($value) {
				$dates = json_decode($value);
				if (strlen($dates->from) <= 10) {
					$dates->from = $dates->from . ' 00:00:00';
				}
				if (strlen($dates->to) <= 10) {
					$dates->to = $dates->to . ' 23:59:59';
				}
				$this->xPanel->addClause('where', 'created_at', '>=', $dates->from);
				$this->xPanel->addClause('where', 'created_at', '<=', $dates->to);
			});
		// -----------------------
		$this->xPanel->addFilter([
			'name'  => 'title',
			'type'  => 'text',
			'label' => trans('admin.Title'),
		],
			false,
			function ($value) {
				$this->xPanel->addClause('where', 'title', 'LIKE', "%$value%");
			});
		// -----------------------
		$this->xPanel->addFilter([
			'name'  => 'country',
			'type'  => 'select2',
			'label' => trans('admin.Country'),
		],
			getCountries(),
			function ($value) {
				$this->xPanel->addClause('where', 'country_code', '=', $value);
			});
		// -----------------------
		$this->xPanel->addFilter([
			'name'  => 'city',
			'type'  => 'text',
			'label' => trans('admin.city'),
		],
			false,
			function ($value) {
				$this->xPanel->query = $this->xPanel->query->whereHas('city', function ($query) use ($value) {
					$query->where('name', 'LIKE', "%$value%");
				});
			});
		// -----------------------
		$filterValues = [
			1 => trans('admin.non_premium'),
			2 => trans('admin.premium') . ' (' . trans('admin.active') . ')',
			3 => trans('admin.premium') . ' (' . trans('admin.Expired') . ')',
		];
		if (config('plugins.offlinepayment.installed')) {
			$filterValues[4] = trans('admin.featured') . ' (' . trans('admin.pushed') . ')';
		}
		$this->xPanel->addFilter([
			'name'  => 'featured',
			'type'  => 'dropdown',
			'label' => trans('admin.premium'),
		],
			$filterValues,
			function ($value) {
				if ($value == 1) {
					$this->xPanel->addClause('where', 'featured', '=', 0);
				}
				if ($value == 2) {
					$this->xPanel->addClause('where', 'featured', '=', 1);
					$this->xPanel->addClause('whereHas', 'payments', function ($query) use ($value) {
						$query->where(function ($query) {
							$query->where('transaction_id', '!=', 'featured')->orWhereNull('transaction_id');
						});
						$query->where('active', 1);
					});
				}
				if ($value == 3) {
					$this->xPanel->addClause('where', 'featured', '=', 0);
					$this->xPanel->addClause('whereHas', 'payments', function ($query) use ($value) {
						$query->where(function ($query) {
							$query->where('transaction_id', '!=', 'featured')->orWhereNull('transaction_id');
						});
						$query->where('active', 1);
					});
				}
				if ($value == 4) {
					$this->xPanel->addClause('where', 'featured', '=', 1);
					$this->xPanel->addClause('whereHas', 'payments', function ($query) use ($value) {
						$query->where('transaction_id', 'featured');
						$query->where('active', 1);
					});
				}
			});
		// -----------------------
		$this->xPanel->addFilter([
			'name'  => 'status',
			'type'  => 'dropdown',
			'label' => trans('admin.Status'),
		], [
			1 => trans('admin.Activated'),
			2 => trans('admin.Unactivated'),
		], function ($value) {
			if ($value == 1) {
				$this->xPanel->addClause('where', function ($query) {
					$query->whereNotNull('email_verified_at')->whereNotNull('phone_verified_at');
					if (config('settings.single.listings_review_activation')) {
						$query->whereNotNull('reviewed_at');
					}
				});
			}
			if ($value == 2) {
				$this->xPanel->addClause('where', function ($query) {
					$query->whereNull('email_verified_at')->orWhereNull('phone_verified_at');
					if (config('settings.single.listings_review_activation')) {
						$query->orWhereNull('reviewed_at');
					}
				});
			}
		});
		
		$isPhoneVerificationEnabled = (config('settings.sms.phone_verification') == 1);
		
		/*
		|--------------------------------------------------------------------------
		| COLUMNS AND FIELDS
		|--------------------------------------------------------------------------
		*/
		// COLUMNS
		$this->xPanel->addColumn([
			'name'      => 'id',
			'label'     => '',
			'type'      => 'checkbox',
			'orderable' => false,
		]);
		$this->xPanel->addColumn([
			'name'  => 'created_at',
			'label' => trans('admin.Date'),
			'type'  => 'datetime',
		]);
		$this->xPanel->addColumn([
			'name'          => 'title',
			'label'         => trans('admin.Title'),
			'type'          => 'model_function',
			'function_name' => 'getTitleHtml',
		]);
		$this->xPanel->addColumn([
			'name'          => 'logo', // Put unused field column
			'label'         => trans('admin.Logo'),
			'type'          => 'model_function',
			'function_name' => 'getLogoHtml',
		]);
		$this->xPanel->addColumn([
			'name'          => 'company_name',
			'label'         => trans('admin.company_name'),
			'type'          => 'model_function',
			'function_name' => 'getCompanyNameHtml',
		]);
		$this->xPanel->addColumn([
			'name'          => 'city_id',
			'label'         => trans('admin.city'),
			'type'          => 'model_function',
			'function_name' => 'getCityHtml',
		]);
		if (config('plugins.offlinepayment.installed')) {
			$this->xPanel->addColumn([
				'name'          => 'featured',
				'label'         => mb_ucfirst(trans('offlinepayment::messages.featured')),
				'type'          => 'model_function',
				'function_name' => 'getFeaturedHtml',
			]);
		}
		$this->xPanel->addColumn([
			'name'          => 'email_verified_at',
			'label'         => trans('admin.Verified Email'),
			'type'          => 'model_function',
			'function_name' => 'getVerifiedEmailHtml',
		]);
		if ($isPhoneVerificationEnabled) {
			$this->xPanel->addColumn([
				'name'          => 'phone_verified_at',
				'label'         => trans('admin.Verified Phone'),
				'type'          => 'model_function',
				'function_name' => 'getVerifiedPhoneHtml',
			]);
		}
		if (config('settings.single.listings_review_activation')) {
			$this->xPanel->addColumn([
				'name'          => 'reviewed_at',
				'label'         => trans('admin.Reviewed'),
				'type'          => "model_function",
				'function_name' => 'getReviewedHtml',
			]);
		}
		
		$entity = $this->xPanel->getModel()->find(request()->segment(3));
		
		// FIELDS
		$this->xPanel->addField([
			'label'       => trans('admin.Category'),
			'name'        => 'category_id',
			'type'        => 'select2_from_array',
			'options'     => Category::selectBoxTree(0),
			'allows_null' => false,
		]);
		$this->xPanel->addField([
			'name'       => 'company_name',
			'label'      => trans('admin.company_name'),
			'type'       => 'text',
			'attributes' => [
				'placeholder' => trans('admin.company_name'),
			],
		]);
		$this->xPanel->addField([
			'name'   => 'logo',
			'label'  => trans('admin.Logo') . ' (Supported file extensions: jpg, jpeg, png, gif)',
			'type'   => 'image',
			'upload' => true,
			'disk'   => 'public',
		]);
		$this->xPanel->addField([
			'name'       => 'company_description',
			'label'      => trans('admin.Company Description'),
			'type'       => 'textarea',
			'attributes' => [
				'placeholder' => trans('admin.Company Description'),
				'rows'        => 10,
			],
		]);
		$this->xPanel->addField([
			'name'       => 'title',
			'label'      => trans('admin.Title'),
			'type'       => 'text',
			'attributes' => [
				'placeholder' => trans('admin.Title'),
			],
		]);
		$wysiwygEditor = config('settings.single.wysiwyg_editor');
		$wysiwygEditorViewPath = '/views/admin/panel/fields/' . $wysiwygEditor . '.blade.php';
		$this->xPanel->addField([
			'name'       => 'description',
			'label'      => trans('admin.Description'),
			'type'       => ($wysiwygEditor != 'none' && file_exists(resource_path() . $wysiwygEditorViewPath))
				? $wysiwygEditor
				: 'textarea',
			'attributes' => [
				'placeholder' => trans('admin.Description'),
				'id'          => 'description',
				'rows'        => 10,
			],
		]);
		$this->xPanel->addField([
			'name'              => 'salary_min',
			'label'             => trans('admin.salary_min'),
			'type'              => 'number',
			'attributes'        => [
				'min'         => 0,
				'step'        => getInputNumberStep((int)config('currency.decimal_places', 2)),
				'placeholder' => trans('admin.salary_min'),
			],
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'              => 'salary_max',
			'label'             => trans('admin.salary_max'),
			'type'              => 'number',
			'attributes'        => [
				'min'         => 0,
				'step'        => getInputNumberStep((int)config('currency.decimal_places', 2)),
				'placeholder' => trans('admin.salary_max'),
			],
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'label'             => trans('admin.Salary Type'),
			'name'              => 'salary_type_id',
			'type'              => 'select2_from_array',
			'options'           => $this->salaryType(),
			'allows_null'       => false,
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'              => 'negotiable',
			'label'             => trans('admin.Negotiable Salary'),
			'type'              => 'checkbox_switch',
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		
		$this->xPanel->addField([
			'name'              => 'contact_name',
			'label'             => trans('admin.User Name'),
			'type'              => 'text',
			'attributes'        => [
				'placeholder' => trans('admin.User Name'),
			],
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'              => 'auth_field',
			'label'             => t('notifications_channel'),
			'type'              => 'select2_from_array',
			'options'           => getAuthFields(),
			'allows_null'       => true,
			'default'           => getAuthField($entity),
			'hint'              => t('notifications_channel_hint'),
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'              => 'email',
			'label'             => trans('admin.User Email'),
			'type'              => 'text',
			'attributes'        => [
				'placeholder' => trans('admin.User Email'),
			],
			'prefix'            => '<i class="far fa-envelope"></i>',
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$phoneCountry = (!empty($entity) && isset($entity->phone_country)) ? strtolower($entity->phone_country) : 'us';
		$this->xPanel->addField([
			'name'              => 'phone',
			'label'             => trans('admin.User Phone'),
			'type'              => 'intl_tel_input',
			'phone_country'     => $phoneCountry,
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'              => 'phone_hidden',
			'label'             => trans('admin.Hide contact phone'),
			'type'              => 'checkbox_switch',
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'label'             => trans('admin.Post Type'),
			'name'              => 'post_type_id',
			'type'              => 'select2_from_array',
			'options'           => $this->postType(),
			'allows_null'       => false,
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$tags = (!empty($entity) && isset($entity->tags)) ? (array)$entity->tags : [];
		$this->xPanel->addField([
			'name'              => 'tags',
			'label'             => trans('admin.Tags'),
			'type'              => 'select2_tagging_from_array',
			'options'           => $tags,
			'allows_multiple'   => true,
			'hint'              => t('tags_hint', [
				'limit' => (int)config('settings.single.tags_limit', 15),
				'min'   => (int)config('settings.single.tags_min_length', 2),
				'max'   => (int)config('settings.single.tags_max_length', 30),
			]),
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'  => 'empty_line_01',
			'type'  => 'custom_html',
			'value' => '<div style="clear: both;"></div>',
		]);
		$this->xPanel->addField([
			'name'              => 'email_verified_at',
			'label'             => trans('admin.Verified Email'),
			'type'              => 'checkbox_switch',
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		$this->xPanel->addField([
			'name'              => 'phone_verified_at',
			'label'             => trans('admin.Verified Phone'),
			'type'              => 'checkbox_switch',
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		if (config('settings.single.listings_review_activation')) {
			$this->xPanel->addField([
				'name'              => 'reviewed_at',
				'label'             => trans('admin.Reviewed'),
				'type'              => 'checkbox_switch',
				'wrapperAttributes' => [
					'class' => 'col-md-6',
				],
			]);
		}
		$this->xPanel->addField([
			'name'              => 'archived_at',
			'label'             => trans('admin.Archived'),
			'type'              => 'checkbox_switch',
			'wrapperAttributes' => [
				'class' => 'col-md-6',
			],
		]);
		if (!empty($entity)) {
			$ipLink = config('larapen.core.ipLinkBase') . $entity->ip_addr;
			$this->xPanel->addField([
				'name'  => 'ip_addr',
				'type'  => 'custom_html',
				'value' => '<h5><strong>IP:</strong> <a href="' . $ipLink . '" target="_blank">' . $entity->ip_addr . '</a></h5>',
			], 'update');
			if (!empty($entity->email) || !empty($entity->phone)) {
				$btnUrl = admin_url('blacklists/add') . '?';
				$btnQs = (!empty($entity->email)) ? 'email=' . $entity->email : '';
				$btnQs = (!empty($btnQs)) ? $btnQs . '&' : $btnQs;
				$btnQs = (!empty($entity->phone)) ? $btnQs . 'phone=' . $entity->phone : $btnQs;
				$btnUrl = $btnUrl . $btnQs;
				
				$btnText = trans('admin.ban_the_user');
				$btnHint = $btnText;
				if (!empty($entity->email) && !empty($entity->phone)) {
					$btnHint = trans('admin.ban_the_user_email_and_phone', ['email' => $entity->email, 'phone' => $entity->phone]);
				} else {
					if (!empty($entity->email)) {
						$btnHint = trans('admin.ban_the_user_email', ['email' => $entity->email]);
					}
					if (!empty($entity->phone)) {
						$btnHint = trans('admin.ban_the_user_phone', ['phone' => $entity->phone]);
					}
				}
				$tooltip = ' data-bs-toggle="tooltip" title="' . $btnHint . '"';
				
				$btnLink = '<a href="' . $btnUrl . '" class="btn btn-danger confirm-simple-action"' . $tooltip . '>' . $btnText . '</a>';
				$this->xPanel->addField([
					'name'              => 'ban_button',
					'type'              => 'custom_html',
					'value'             => $btnLink,
					'wrapperAttributes' => [
						'style' => 'text-align:center;',
					],
				], 'update');
			}
		}
	}
	
	public function store(StoreRequest $request)
	{
		return parent::storeCrud();
	}
	
	public function update(UpdateRequest $request)
	{
		return parent::updateCrud();
	}
	
	public function postType()
	{
		$entries = PostType::query()->get();
		
		$entries = collect($entries)->mapWithKeys(function ($item) {
			return [$item['id'] => $item['name']];
		})->toArray();
		
		return $entries;
	}
	
	public function salaryType()
	{
		$entries = SalaryType::query()->get();
		
		$entries = collect($entries)->mapWithKeys(function ($item) {
			return [$item['id'] => $item['name']];
		})->toArray();
		
		return $entries;
	}
}
