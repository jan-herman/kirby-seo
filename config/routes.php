<?php

use Kirby\Cms\Page;
use Kirby\Http\Response;
use JanHerman\Seo\Sitemap\SitemapIndex;

return [
    [
		'pattern' => 'robots.txt',
		'method' => 'GET|HEAD',
		'action' => function () {
			if (!option('jan-herman.seo.robots.active', true)) {
                $this->next();
			}

            $content = snippet('seo/robots.txt', [], true);
			return new Response($content, 'text/plain', 200);
		}
	],
	[
		'pattern' => 'robots.txt',
		'method' => 'OPTIONS',
		'action' => function () {
			if (!option('jan-herman.seo.robots.active', true)) {
                $this->next();
			}

			return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'robots.txt',
		'method' => 'ALL',
		'action' => function () {
			if (!option('jan-herman.seo.robots.active', true)) {
				$this->next();

			}

			return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'sitemap',
		'method' => 'GET|HEAD',
		'action' => function () {
            if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			go('/sitemap.xml');
		}
	],
	[
		'pattern' => 'sitemap',
		'method' => 'OPTIONS',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'sitemap',
		'method' => 'ALL',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'sitemap.xsl',
		'method' => 'GET',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			kirby()->response()->type('text/xsl');

			$lang = option('jan-herman.seo.sitemap.lang', 'en');
			if (is_callable($lang)) {
				$lang = $lang();
			}
			kirby()->setCurrentTranslation($lang);

			return Page::factory([
				'slug' => 'sitemap',
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('seo.sitemap.title'),
				],
			])->render(contentType: 'xsl');
		}
	],
	[
		'pattern' => 'sitemap.xsl',
		'method' => 'OPTIONS',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('', 'text/plain', 204, ['Allow' => 'GET']);
		}
	],
	[
		'pattern' => 'sitemap.xsl',
		'method' => 'ALL',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET']);
		}
	],
	[
		'pattern' => 'sitemap.xml',
		'method' => 'GET|HEAD',
		'action' => function () {
            if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			SitemapIndex::instance()->generate();
			kirby()->response()->type('text/xml');
			return Page::factory([
				'slug' => 'sitemap',
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('seo.sitemap.title'),
					'index' => null,
				],
			])->render(contentType: 'xml');
		}
	],
	[
		'pattern' => 'sitemap.xml',
		'method' => 'OPTIONS',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'sitemap.xml',
		'method' => 'ALL',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'GET|HEAD',
		'action' => function (string $index) {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			SitemapIndex::instance()->generate();
			if (!SitemapIndex::instance()->isValidIndex($index)) {
				$this->next();
			}

			kirby()->response()->type('text/xml');
			return Page::factory([
				'slug' => 'sitemap-' . $index,
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('seo.sitemap.title'),
					'index' => $index,
				],
			])->render(contentType: 'xml');
		}
	],
	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'OPTIONS',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
		}
	],
	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'ALL',
		'action' => function () {
			if (!option('jan-herman.seo.sitemap.active', true)) {
				$this->next();
			}

			return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
		}
	],
];
