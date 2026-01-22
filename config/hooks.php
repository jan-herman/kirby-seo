<?php

use Kirby\Cms\Page;

return [
	'page.render:before' => function (string $contentType, array $data, Page $page) {
		if (!option('jan-herman.seo.schema.active', true)) {
			return;
		}

        $page->schema('WebSite')
            ->url($page->url())
            ->copyrightYear(date('Y'))
            ->description($page->metadata()->get('metaDescription')->toString())
            ->name($page->metadata()->get('metaTitle')->toString());
	},
];
