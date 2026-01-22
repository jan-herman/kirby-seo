<?php

use Kirby\Cms\App;

return function (App $kirby) {
	// get the current page
	$url = $kirby->urls()->current();
	$page = null;

	if (preg_match('#/pages/([a-zA-Z0-9+\-]+)/?#', $url, $result)) {
		$page_slug = str_replace('+', '/', $result[1]);
		$page = page($page_slug);
	}

	// get options
	$parent   = option('jan-herman.seo.meta.ogImage.parent', null);
	$template = option('jan-herman.seo.meta.ogImage.template', 'image');
	$query    = option('jan-herman.seo.meta.ogImage.query', 'model.images');
	$crop     = option('jan-herman.seo.meta.ogImage.crop', true);

	// if settings page or site
	if ($page === null || $page->is($page->metadata()->settingsPage())) {
		$query = option('jan-herman.seo.settingsPage.ogImage.query', 'model.images');
		$parent = option('jan-herman.seo.settingsPage.ogImage.parent', 'site');
	}

	// build the blueprint
	$blueprint = [
		'type' => 'files',
		'multiple' => false,
		'layout' => 'cards',
		'size' => 'full',
		'image' => [
			'cover' => $crop,
			'ratio' => '1200/630',
		],
		'uploads' => [],
		'query' => $query,
	];

	if ($parent) {
		$blueprint['uploads']['parent'] = $parent;
	}

	if ($template) {
		$blueprint['uploads']['template'] = $template;
		$blueprint['query'] .= ".filterBy('template', '{$template}')";
	}

	return $blueprint;
};
