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
use App\Models\Post\LatestOrPremium;
use App\Models\Post\SimilarByCategory;
use App\Models\Post\SimilarByLocation;
use App\Models\Scopes\LocalizedScope;
use App\Models\Scopes\VerifiedScope;
use App\Models\Scopes\ReviewedScope;
use App\Models\Traits\CountryTrait;
use App\Observers\PostObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Panel\Library\Traits\Models\Crud;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

class Post extends BaseModel implements Feedable
{
	use Crud, CountryTrait, Notifiable, HasFactory, LatestOrPremium, SimilarByCategory, SimilarByLocation;
	
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'posts';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'id';
	protected $appends = ['slug', 'phone_intl', 'created_at_formatted', 'logo_url', 'logo_url_small', 'logo_url_big', 'user_photo_url'];
	
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
		'country_code',
		'user_id',
		'company_id',
		'company_name',
		'logo',
		'company_description',
		'category_id',
		'post_type_id',
		'title',
		'description',
		'tags',
		'salary_min',
		'salary_max',
		'salary_type_id',
		'negotiable',
		'start_date',
		'application_url',
		'contact_name',
		'auth_field',
		'email',
		'phone',
		'phone_national',
		'phone_country',
		'phone_hidden',
		'city_id',
		'lat',
		'lon',
		'address',
		'ip_addr',
		'visits',
		'tmp_token',
		'email_token',
		'phone_token',
		'email_verified_at',
		'phone_verified_at',
		'accept_terms',
		'accept_marketing_offers',
		'reviewed_at',
		'featured',
		'archived',
		'archived_at',
		'archived_manually_at',
		'deletion_mail_sent_at',
		'partner',
		'created_at',
		'updated_at',
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
	protected $dates = ['created_at', 'updated_at', 'deleted_at'];
	
	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'email_verified_at' => 'datetime',
		'phone_verified_at' => 'datetime',
		'reviewed_at'       => 'datetime',
		'archived_at'       => 'datetime',
	];
	
	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	protected static function boot()
	{
		parent::boot();
		
		Post::observe(PostObserver::class);
		
		static::addGlobalScope(new VerifiedScope());
		static::addGlobalScope(new ReviewedScope());
		static::addGlobalScope(new LocalizedScope());
	}
	
	public function routeNotificationForMail()
	{
		return $this->email;
	}
	
	public function routeNotificationForVonage()
	{
		$phone = phoneE164($this->phone, $this->phone_country);
		
		return setPhoneSign($phone, 'vonage');
	}
	
	public function routeNotificationForTwilio()
	{
		$phone = phoneE164($this->phone, $this->phone_country);
		
		return setPhoneSign($phone, 'twilio');
	}
	
	/**
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public static function getFeedItems()
	{
		$postsPerPage = (int)config('settings.list.items_per_page', 50);
		
		$posts = Post::reviewed()->unarchived();
		
		if (request()->filled('country') || config('plugins.domainmapping.installed')) {
			$countryCode = config('country.code');
			if (!config('plugins.domainmapping.installed')) {
				if (request()->filled('country')) {
					$countryCode = request()->get('country');
				}
			}
			$posts = $posts->where('country_code', $countryCode);
		}
		
		return $posts->take($postsPerPage)->orderByDesc('id')->get();
	}
	
	public function toFeedItem(): FeedItem
	{
		$title = $this->title;
		$title .= (isset($this->city) && !empty($this->city)) ? ' - ' . $this->city->name : '';
		$title .= (isset($this->country) && !empty($this->country)) ? ', ' . $this->country->name : '';
		// $summary = str_limit(str_strip(strip_tags($this->description)), 5000);
		$summary = transformDescription($this->description);
		$link = UrlGen::postUri($this, true);
		
		return FeedItem::create()
			->id($link)
			->title($title)
			->summary($summary)
			->category((!empty($this->category)) ? $this->category->name : '')
			->updated($this->updated_at)
			->link($link)
			->authorName($this->contact_name);
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
		
		if (!$disk->exists($value)) {
			$value = config('larapen.core.picture.default');
		}
		
		return $value;
	}
	
	public function getTitleHtml(): string
	{
		$out = '';
		
		// $post = self::find($this->id);
		$out .= getPostUrl($this);
		if (isset($this->archived_at) && !empty($this->archived_at)) {
			$out .= '<br>';
			$out .= '<span class="badge bg-secondary">';
			$out .= trans('admin.Archived');
			$out .= '</span>';
		}
		
		return $out;
	}
	
	public function getLogoHtml(): string
	{
		$style = ' style="width:auto; max-height:90px;"';
		
		// Get logo
		$out = '<img src="' . imgUrl($this->logo, 'small') . '" data-bs-toggle="tooltip" title="' . $this->title . '"' . $style . '>';
		
		// Add link to the Ad
		$url = dmUrl($this->country_code, UrlGen::postPath($this));
		
		return '<a href="' . $url . '" target="_blank">' . $out . '</a>';
	}
	
	public function getPictureHtml(): string
	{
		// Get ad URL
		$url = url(UrlGen::postUri($this));
		
		$style = ' style="width:auto; max-height:90px;"';
		// Get first picture
		$out = '';
		if ($this->pictures->count() > 0) {
			foreach ($this->pictures as $picture) {
				$url = dmUrl($picture->post->country_code, UrlGen::postPath($this));
				$out .= '<img src="' . imgUrl($picture->filename, 'small') . '" data-bs-toggle="tooltip" title="' . $this->title . '"' . $style . ' class="img-rounded">';
				break;
			}
		} else {
			// Default picture
			$out .= '<img src="' . imgUrl(config('larapen.core.picture.default'), 'small') . '" data-bs-toggle="tooltip" title="' . $this->title . '"' . $style . ' class="img-rounded">';
		}
		
		// Add link to the Ad
		return '<a href="' . $url . '" target="_blank">' . $out . '</a>';
	}
	
	public function getCompanyNameHtml(): string
	{
		$out = '';
		
		// Company Name
		$out .= $this->company_name;
		
		// User Name
		$out .= '<br>';
		$out .= '<small>';
		$out .= trans('admin.By_') . ' ';
		if (isset($this->user) && !empty($this->user)) {
			$url = admin_url('users/' . $this->user->getKey() . '/edit');
			$tooltip = ' data-bs-toggle="tooltip" title="' . $this->user->name . '"';
			
			$out .= '<a href="' . $url . '"' . $tooltip . '>';
			$out .= $this->contact_name;
			$out .= '</a>';
		} else {
			$out .= $this->contact_name;
		}
		$out .= '</small>';
		
		return $out;
	}
	
	public function getCityHtml()
	{
		$out = $this->getCountryHtml();
		$out .= ' - ';
		if (isset($this->city) && !empty($this->city)) {
			$out .= '<a href="' . UrlGen::city($this->city) . '" target="_blank">' . $this->city->name . '</a>';
		} else {
			$out .= $this->city_id;
		}
		
		return $out;
	}
	
	public function getReviewedHtml(): string
	{
		return ajaxCheckboxDisplay($this->{$this->primaryKey}, $this->getTable(), 'reviewed_at', $this->reviewed_at);
	}
	
	public function getFeaturedHtml()
	{
		$out = '-';
		if (config('plugins.offlinepayment.installed')) {
			$opTool = '\extras\plugins\offlinepayment\app\Helpers\OpTools';
			if (class_exists($opTool)) {
				$out = $opTool::featuredCheckboxDisplay($this->{$this->primaryKey}, $this->getTable(), 'featured', $this->featured);
			}
		}
		
		return $out;
	}
	
	/*
	|--------------------------------------------------------------------------
	| QUERIES
	|--------------------------------------------------------------------------
	*/
	
	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function postType()
	{
		return $this->belongsTo(PostType::class, 'post_type_id');
	}
	
	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}
	
	public function city()
	{
		return $this->belongsTo(City::class, 'city_id');
	}
	
	public function latestPayment()
	{
		return $this->hasOne(Payment::class, 'post_id')->orderByDesc('id');
	}
	
	public function payments()
	{
		return $this->hasMany(Payment::class, 'post_id');
	}
	
	public function pictures()
	{
		return $this->hasMany(Picture::class, 'post_id')->orderBy('position')->orderByDesc('id');
	}
	
	public function savedByLoggedUser()
	{
		$userId = (auth()->check()) ? auth()->user()->id : '-1';
		
		return $this->hasMany(SavedPost::class, 'post_id')->where('user_id', $userId);
	}
	
	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}
	
	public function company()
	{
		return $this->belongsTo(Company::class, 'company_id');
	}
	
	public function salaryType()
	{
		return $this->belongsTo(SalaryType::class, 'salary_type_id');
	}
	
	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	public function scopeVerified($builder)
	{
		$builder->where(function ($query) {
			$query->whereNotNull('email_verified_at')->whereNotNull('phone_verified_at');
		});
		
		if (config('settings.single.listings_review_activation')) {
			$builder->whereNotNull('reviewed_at');
		}
		
		return $builder;
	}
	
	public function scopeUnverified($builder)
	{
		$builder->where(function ($query) {
			$query->whereNull('email_verified_at')->orWhereNull('phone_verified_at');
		});
		
		if (config('settings.single.listings_review_activation')) {
			$builder->orWhereNull('reviewed_at');
		}
		
		return $builder;
	}
	
	public function scopeArchived($builder)
	{
		return $builder->whereNotNull('archived_at');
	}
	
	public function scopeUnarchived($builder)
	{
		return $builder->whereNull('archived_at');
	}
	
	public function scopeReviewed($builder)
	{
		if (config('settings.single.listings_review_activation')) {
			return $builder->whereNotNull('reviewed_at');
		} else {
			return $builder;
		}
	}
	
	public function scopeUnreviewed($builder)
	{
		if (config('settings.single.listings_review_activation')) {
			return $builder->whereNull('reviewed_at');
		} else {
			return $builder;
		}
	}
	
	public function scopeWithCountryFix($builder)
	{
		// Check the Domain Mapping plugin
		if (config('plugins.domainmapping.installed')) {
			return $builder->where('country_code', config('country.code'));
		} else {
			return $builder;
		}
	}
	
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
	
	protected function deletedAt(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (empty($value)) {
					return null;
				}
				
				$value = new Carbon($value);
				$value->timezone(Date::getAppTimeZone());
				
				return $value;
			},
		);
	}
	
	protected function createdAtFormatted(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$createdAt = $this->attributes['created_at'] ?? null;
				if (empty($createdAt)) {
					return null;
				}
				
				$value = new Carbon($createdAt);
				$value->timezone(Date::getAppTimeZone());
				
				return Date::formatFormNow($value);
			},
		);
	}
	
	/*
	protected function archivedAt(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (empty($value)) {
					return null;
				}
				
				$value = new Carbon($value);
				$value->timezone(Date::getAppTimeZone());
				
				return $value;
			},
		);
	}
	*/
	
	protected function deletionMailSentAt(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (empty($value)) {
					return null;
				}
				
				$value = new Carbon($value);
				$value->timezone(Date::getAppTimeZone());
				
				return $value;
			},
		);
	}
	
	protected function userPhotoUrl(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				// Default Photo
				$defaultPhotoUrl = imgUrl(config('larapen.core.avatar.default'));
				
				// Photo from User's account
				$userPhotoUrl = null;
				if (!empty($this->user) && isset($this->user->photo_url) && !empty($this->user->photo_url)) {
					$userPhotoUrl = $this->user->photo_url;
				}
				
				return (!empty($userPhotoUrl) && $userPhotoUrl != $defaultPhotoUrl)
					? $userPhotoUrl
					: $defaultPhotoUrl;
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
								if (isset($this->phone_token)) {
									if ($this->phone_token == 'demoFaker') {
										return $value;
									}
								}
								$value = hidePartOfEmail($value);
							}
						}
					}
				}
				
				return $value;
			},
		);
	}
	
	protected function phoneCountry(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$countryCode = $this->country_code ?? config('country.code');
				
				return !empty($value) ? $value : $countryCode;
			},
		);
	}
	
	protected function phone(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				return phoneE164($value, $this->phone_country);
			},
		);
	}
	
	protected function phoneNational(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$value = !empty($value) ? $value : $this->phone;
				
				return phoneNational($value, $this->phone_country);
			},
		);
	}
	
	protected function phoneIntl(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$value = (isset($this->phone_national) && !empty($this->phone_national))
					? $this->phone_national
					: $this->phone;
				
				if ($this->phone_country == config('country.code')) {
					return phoneNational($value, $this->phone_country);
				}
				
				return phoneIntl($value, $this->phone_country);
			},
		);
	}
	
	protected function title(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$value = mb_ucfirst($value);
				
				if (!isAdminPanel()) {
					if (!empty($this->user)) {
						if (!$this->user->hasAllPermissions(Permission::getStaffPermissions())) {
							$value = RemoveFromString::contactInfo($value, false, true);
						}
					} else {
						$value = RemoveFromString::contactInfo($value, false, true);
					}
				}
				
				return $value;
			},
		);
	}
	
	protected function slug(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$value = (is_null($value) && isset($this->title)) ? $this->title : $value;
				
				$value = stripNonUtf($value);
				$value = slugify($value);
				
				// To prevent 404 error when the slug starts by a banned slug/prefix,
				// Add a tilde (~) as prefix to it.
				$bannedSlugs = regexSimilarRoutesPrefixes();
				foreach ($bannedSlugs as $bannedSlug) {
					if (str_starts_with($value, $bannedSlug)) {
						$value = '~' . $value;
						break;
					}
				}
				
				return $value;
			},
		);
	}
	
	protected function contactName(): Attribute
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
	
	protected function tags(): Attribute
	{
		return Attribute::make(
			get: fn ($value) => tagCleaner($value, true),
			set: function ($value) {
				if (is_array($value)) {
					$value = implode(',', $value);
				}
				
				return (!empty($value)) ? mb_strtolower($value) : $value;
			},
		);
	}
	
	protected function companyName(): Attribute
	{
		return Attribute::make(
			get: fn ($value) => mb_ucwords($value),
		);
	}
	
	protected function companyDescription(): Attribute
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
			set: fn ($value) => $this->setLogo($value),
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
	private function setLogo($value)
	{
		// Don't make an upload for Post->logo for logged users
		if (!str_contains(Route::currentRouteAction(), 'Admin\PostController')) {
			if (auth()->check()) {
				return $value;
			}
		}
		
		if (!is_string($value)) {
			return $value;
		}
		
		if ($value == url('/')) {
			return null;
		}
		
		// Retrieve current value without upload a new file
		if (str_starts_with($value, config('larapen.core.logo'))) {
			return null;
		}
		
		// Extract the value's country code
		$tmp = [];
		preg_match('#files/([A-Za-z]{2})/[\d]+#i', $value, $tmp);
		$valueCountryCode = (isset($tmp[1]) && !empty($tmp[1])) ? $tmp[1] : null;
		
		// Extract the value's ID
		$tmp = [];
		preg_match('#files/[A-Za-z]{2}/([\d]+)#i', $value, $tmp);
		$valueId = (isset($tmp[1]) && !empty($tmp[1])) ? $tmp[1] : null;
		
		// Extract the value's filename
		$tmp = [];
		preg_match('#files/[A-Za-z]{2}/[\d]+/(.+)#i', $value, $tmp);
		$valueFilename = (isset($tmp[1]) && !empty($tmp[1])) ? $tmp[1] : null;
		
		// Destination Path
		if (empty($this->attributes['id']) || empty($this->attributes['country_code'])) {
			return null;
		}
		$destPath = 'files/' . strtolower($this->attributes['country_code']) . '/' . $this->attributes['id'];
		
		if (!empty($valueCountryCode) && !empty($valueId) && !empty($valueFilename)) {
			// Value's Path
			$valueDestinationPath = 'files/' . strtolower($valueCountryCode) . '/' . $valueId;
			if ($valueDestinationPath != $destPath) {
				$oldFilePath = $valueDestinationPath . '/' . $valueFilename;
				$newFilePath = $destPath . '/' . $valueFilename;
				
				// Copy the file
				$disk = StorageDisk::getDisk();
				$disk->copy($oldFilePath, $newFilePath);
				
				return $newFilePath;
			}
		}
		
		if (!str_starts_with($value, 'files/')) {
			$value = $destPath . last(explode($destPath, $value));
		}
		
		return $value;
	}
	
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
