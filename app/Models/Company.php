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

namespace App\Models;

use App\Helpers\Date;
use App\Helpers\Files\Storage\StorageDisk;
use App\Helpers\RemoveFromString;
use App\Helpers\UrlGen;
use App\Models\Scopes\LocalizedScope;
use App\Observers\CompanyObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Admin\Panel\Library\Traits\Models\Crud;

class Company extends BaseModel
{
	use Crud, HasFactory;
	
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'companies';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	// protected $primaryKey = 'id';
	protected $appends = ['logo_url', 'logo_url_small', 'logo_url_big'];
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var boolean
	 */
	public $timestamps = true;
	
	/**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'user_id',
		'name',
		'logo',
		'description',
		'country_code',
		'city_id',
		'address',
		'phone',
		'fax',
		'email',
		'website',
		'facebook',
		'twitter',
		'linkedin',
		'pinterest',
	];
	
	/**
	 * The attributes that should be hidden for arrays
	 *
	 * @var array
	 */
	// protected $hidden = [];
	
	/**
	 * The attributes that should be mutated to dates.
	 *
	 * @var array
	 */
	protected $dates = ['created_at', 'updated_at'];
	
	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	protected static function boot()
	{
		parent::boot();
		
		Company::observe(CompanyObserver::class);
		static::addGlobalScope(new LocalizedScope());
	}
	
	public static function getLogo($value)
	{
		if (empty($value)) {
			return $value;
		}
		
		$disk = StorageDisk::getDisk();
		
		// OLD PATH
		$oldBase = 'pictures/';
		$newBase = 'files/';
		if (str_contains($value, $oldBase)) {
			$value = $newBase . last(explode($oldBase, $value));
		}
		
		// NEW PATH
		if (str_ends_with($value, '/')) {
			return $value;
		}
		
		if (empty($value) || !$disk->exists($value)) {
			$value = config('larapen.core.picture.default');
		}
		
		return $value;
	}
	
	public function getNameHtml(): string
	{
		$company = self::find($this->id);
		
		$out = '';
		if (!empty($company)) {
			$out .= '<a href="' . UrlGen::company(null, $company->id) . '" target="_blank">';
			$out .= $company->name;
			$out .= '</a>';
			$out .= ' <span class="label label-default">' . $company->posts()->count() . ' ' . trans('admin.jobs') . '</span>';
		} else {
			$out .= '--';
		}
		
		return $out;
	}
	
	public function getLogoHtml(): string
	{
		$style = ' style="width:auto; max-height:90px;"';
		
		// Get logo
		$out = '<img src="' . imgUrl($this->logo, 'small') . '" data-bs-toggle="tooltip" title="' . $this->name . '"' . $style . '>';
		
		// Add link to the Ad
		$url = UrlGen::company(null, $this->id);
		
		return '<a href="' . $url . '" target="_blank">' . $out . '</a>';
	}
	
	public function getCountryHtml()
	{
		$iconPath = 'images/flags/16/' . strtolower($this->country_code) . '.png';
		if (file_exists(public_path($iconPath))) {
			return '<img src="' . url($iconPath) . getPictureVersion() . '" data-bs-toggle="tooltip" title="' . $this->country_code . '">';
		} else {
			return $this->country_code;
		}
	}
	
	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function posts()
	{
		return $this->hasMany(Post::class, 'company_id');
	}
	
	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}
	
	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	
	/*
	|--------------------------------------------------------------------------
	| ACCESSORS | MUTATORS
	|--------------------------------------------------------------------------
	*/
	protected function createdAt(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$value = new Carbon($value);
				$value->timezone(Date::getAppTimeZone());
				
				return $value;
			},
		);
	}
	
	protected function updatedAt(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$value = new Carbon($value);
				$value->timezone(Date::getAppTimeZone());
				
				return $value;
			},
		);
	}
	
	protected function email(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (isAdminPanel()) {
					if (
						isDemoDomain()
						&& request()->segment(2) != 'password'
					) {
						if (auth()->check()) {
							if (auth()->user()->getAuthIdentifier() != 1) {
								$value = hidePartOfEmail($value);
							}
						}
						
						return $value;
					}
				}
				
				return $value;
			},
		);
	}
	
	protected function phone(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$countryCode = config('country.code');
				if (isset($this->country_code) && !empty($this->country_code)) {
					$countryCode = $this->country_code;
				}
				
				return phoneE164($value, $countryCode);
			},
		);
	}
	
	protected function name(): Attribute
	{
		return Attribute::make(
			get: fn ($value) => mb_ucwords($value),
		);
	}
	
	protected function description(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (isAdminPanel()) {
					return $value;
				}
				
				if (!empty($this->user)) {
					if (!$this->user->hasAllPermissions(Permission::getStaffPermissions())) {
						$value = RemoveFromString::contactInfo($value, false, true);
					}
				} else {
					$value = RemoveFromString::contactInfo($value, false, true);
				}
				
				return $value;
			},
		);
	}
	
	protected function website(): Attribute
	{
		return Attribute::make(
			get: fn ($value) => addHttp($value),
		);
	}
	
	protected function logo(): Attribute
	{
		return Attribute::make(
			get: function ($value, $attributes) {
				if (empty($value)) {
					if (isset($attributes['logo'])) {
						$value = $attributes['logo'];
					}
				}
				
				// OLD PATH
				$value = $this->getLogoFromOldPath($value);
				
				// NEW PATH
				$disk = StorageDisk::getDisk();
				if (empty($value) || !$disk->exists($value)) {
					$value = config('larapen.core.picture.default');
				}
				
				return $value;
			},
		);
	}
	
	protected function logoUrl(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				return imgUrl(self::getLogo($this->logo), 'medium');
			},
		);
	}
	
	protected function logoUrlSmall(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				return imgUrl(self::getLogo($this->logo), 'small');
			},
		);
	}
	
	protected function logoUrlBig(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				return imgUrl(self::getLogo($this->logo), 'big');
			},
		);
	}
	
	/*
	|--------------------------------------------------------------------------
	| OTHER PRIVATE METHODS
	|--------------------------------------------------------------------------
	*/
	private function getLogoFromOldPath($value): ?string
	{
		// Fix path
		$oldBase = 'pictures/';
		$newBase = 'files/';
		if (str_contains($value, $oldBase)) {
			$value = $newBase . last(explode($oldBase, $value));
		}
		
		return $value;
	}
}
