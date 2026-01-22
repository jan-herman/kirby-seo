<?php

use Kirby\Cms\App;

return function (App $kirby) {
	// get the current page
	$current_url = $kirby->urls()->current();
	$page = null;

	if (preg_match('#/pages/([a-zA-Z0-9+\-]+)/?#', $current_url, $result)) {
		$page_slug = str_replace('+', '/', $result[1]);
		$page = page($page_slug);
	}

    // check if the current page is the settings page or null (site/unpublished page)
	$is_settings_page = $page === null || $page->is($page->metadata()->settingsPage());
    $page_url = $is_settings_page ? '{{ site.url }}' : '{{ page.url }}';

    // construct the html
    $text = '<div class="jh-seo-debug-links">';

    // Google
    $text .= '<a href="https://search.google.com/search-console" target="_blank"><svg class="k-icon"><use xlink:href="#icon-google"></use></svg>Google Search Console</a>';

    // MetaTags.io
    $text .= '<a href="https://metatags.io/?url=' . $page_url . '" target="_blank"><svg class="k-icon"><use xlink:href="#icon-preview"></use></svg>Preview (Using MetaTags.io)</a>';

    // Facebook
    $text .= '<a href="https://developers.facebook.com/tools/debug/?q=' . $page_url . '" target="_blank"><svg class="k-icon"><use xlink:href="#icon-facebook"></use></svg>Facebook Sharing Debugger</a>';

    // LinkedIn
    $text .= '<a href="https://www.linkedin.com/post-inspector/" target="_blank"><svg class="k-icon"><use xlink:href="#icon-linkedin"></use></svg>LinkedIn Post Inspector</a>';

    $text .= '</div>';

    // return the blueprint
	return [
        'label' => t('seo.sections.links.label'),
        'type' => 'info',
        'theme' => 'none',
        'text' => $text,
    ];
};
