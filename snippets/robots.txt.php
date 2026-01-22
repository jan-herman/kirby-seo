<?php

use Kirby\Toolkit\A;

if ($content = option('jan-herman.seo.robots.content')) {
	if (is_callable($content)) {
		$content = $content();
	}

	if (is_array($content)) {
		$str = [];

		foreach ($content as $ua => $data) {
			$str[] = 'User-agent: ' . $ua;
			foreach ($data as $type => $values) {
				foreach ($values as $value) {
					$str[] = $type . ': ' . $value;
				}
			}
		}

		$content = A::join($str, PHP_EOL);
	}

	echo $content;
} else {
	// output default
	echo "User-agent: *\n";
	echo 'Allow: /';
	echo "\nDisallow: /" . option('panel.slug', 'panel');
}

if (option('jan-herman.seo.sitemap.active')) {
	$sitemap = site()->url() . '/sitemap.xml';

	if ($sitemap) {
		echo "\n\nSitemap: {$sitemap}";
	}
}
