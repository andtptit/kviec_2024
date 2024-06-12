<?php
$sectionOptions = $getFeaturedPostsCompaniesOp ?? [];
$sectionData ??= [];
$featuredCompanies = (array)data_get($sectionData, 'featuredCompanies');
$companies = (array)data_get($featuredCompanies, 'companies');

$hideOnMobile = (data_get($sectionOptions, 'hide_on_mobile') == '1') ? ' hidden-sm' : '';
?>
@if (!empty($featuredCompanies))
	@if (!empty($companies))
		@includeFirst([config('larapen.core.customizedViewPath') . 'home.inc.spacer', 'home.inc.spacer'], ['hideOnMobile' => $hideOnMobile])
		<div class="container{{ $hideOnMobile }}">
			<div class="col-lg-12 content-box layout-section">
				<div class="row row-featured row-featured-category row-featured-company">
					<div class="col-lg-12  box-title no-border">
						<div class="inner">
							<h2>
								<span class="title-3">{!! data_get($featuredCompanies, 'title') !!}</span>
								<a class="sell-your-item" href="{{ data_get($featuredCompanies, 'link') }}">
									{{ t('View more') }}
									<i class="fas fa-bars"></i>
								</a>
							</h2>
						</div>
					</div>
					
					@foreach($companies as $key => $iCompany)
						<?php
							$companyUrl = \App\Helpers\UrlGen::company(null, data_get($iCompany, 'id'));
						?>
						<div class="col-lg-2 col-md-3 col-sm-3 col-4 f-category">
							<a href="{{ $companyUrl }}">
								<img src="{{ data_get($iCompany, 'logo_url') }}" class="img-fluid" alt="{{ data_get($iCompany, 'name') }}">
								<h6> {{ t('Jobs at') }}
									<span class="company-name">{{ data_get($iCompany, 'name') }}</span>
									<span class="jobs-count text-muted">({{ data_get($iCompany, 'posts_count') ?? 0 }})</span>
								</h6>
							</a>
						</div>
					@endforeach
			
				</div>
			</div>
		</div>
	@endif
@endif
