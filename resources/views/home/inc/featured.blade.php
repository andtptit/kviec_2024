<?php
$sectionOptions = $getSponsoredPostsOp ?? [];
$sectionData ??= [];
$widget = (array)data_get($sectionData, 'featured');
$widgetType = (data_get($sectionOptions, 'items_in_carousel') == '1') ? 'carousel' : 'normal';
?>
@includeFirst([
		config('larapen.core.customizedViewPath') . 'search.inc.posts.widget.' . $widgetType,
		'search.inc.posts.widget.' . $widgetType
	],
	['widget' => $widget, 'sectionOptions' => $sectionOptions]
)
