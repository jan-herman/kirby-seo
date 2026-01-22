<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Page;
use JanHerman\Seo\Meta\Metadata;
use JanHerman\Seo\Schema;
use JanHerman\Utils\Translation;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('jan-herman/seo', [
    'options' => [
        'settingsPage' => [
            'query' => null,
            'ogImage' => [
                'query' => 'model.images',
                'parent' => 'site',
            ]
        ],
        'meta' => [
            'default' => [
                'metaTemplate' => '{{ page.metadata.get("metaTitle") }} – {{ site.title }}',
                'metaTitle' => '{{ page.title }}',
                'metaDescription' => null,
                'ogTemplate' => '{{ page.metadata.get("ogTitle") }}',
                'ogTitle' => '{{ page.metadata.get("metaTitle") }}',
                'ogDescription' => '{{ page.metadata.get("metaDescription") }}',
                'ogImage' => null,
                'ogType' => 'website',
                'ogSiteName' => '{{ site.title }}',
                'locale' => 'en_US',
            ],
            'fallback' => [],
            'homepage' => [
                'default' => [],
                'fallback' => [
                    'metaTemplate' => '{{ page.metadata.get("metaTitle") }}',
                    'metaTitle' => '{{ site.title }}',
                ],
            ],
            'ogImage' => [
                'query' => 'model.images',
                'template' => 'image',
                'parent' => null,
                'crop' => true,
            ],
        ],
        'sitemap' => [
            'active' => true,
            'lang' => 'en',
            'generator' => require __DIR__ . '/config/sitemap-generator.php',
            'changefreq' => 'weekly',
            'groupByTemplate' => false,
            'excludeTemplates' => ['error'],
            'priority' => fn (Page $page) => number_format(($page->isHomePage()) ? 1 : max(1 - 0.2 * $page->depth(), 0.2), 1),
        ],
        'robots' => [
            'active' => true,
            'index' => fn (Page $page) => $page->isListed(),
            'content' => [],
        ],
        'schema' => [
            'active' => true,
        ],
    ],
    'pageMethods' => [
        'metadata' => fn () => new Metadata($this),
        'schema'   => fn ($type) => Schema::getInstance($type, $this),
	    'schemas'  => fn () => Schema::getInstances($this),
    ],
    'siteMethods' => [
        'schema'  => fn ($type) => Schema::getInstance($type),
	    'schemas' => fn () => Schema::getInstances(),
    ],
    'blueprints' => [
        'seo/tabs/settings'    => __DIR__ . '/blueprints/tabs/settings.yml',
        'seo/tabs/page'        => __DIR__ . '/blueprints/tabs/page.yml',
        'seo/sections/meta'    => __DIR__ . '/blueprints/sections/meta.yml',
        'seo/sections/og-meta' => __DIR__ . '/blueprints/sections/og-meta.yml',
        'seo/sections/preview' => __DIR__ . '/blueprints/sections/preview.yml',
        'seo/sections/links'   => require __DIR__ . '/blueprints/sections/links.php',
        'seo/fields/og-image'  => require __DIR__ . '/blueprints/fields/og-image.php',
        // for tobimori/kirby-seo compatibility
        'seo/site'             => __DIR__ . '/blueprints/tabs/settings.yml',
        'seo/page'             => __DIR__ . '/blueprints/tabs/page.yml',
    ],
    'templates' => [
		'sitemap'     => __DIR__ . '/templates/sitemap.php',
		'sitemap.xml' => __DIR__ . '/templates/sitemap.xml.php',
		'sitemap.xsl' => __DIR__ . '/templates/sitemap.xsl.php',
	],
    'snippets' => [
        'seo/head'       => __DIR__ . '/snippets/head.php',
        'seo/schemas'    => __DIR__ . '/snippets/schemas.php',
        'seo/robots.txt' => __DIR__ . '/snippets/robots.txt.php',
    ],
    'routes' => require __DIR__ . '/config/routes.php',
    'hooks' => require __DIR__ . '/config/hooks.php',
    'translations' => Translation::loadDir(__DIR__ . '/translations', 'seo')
]);
