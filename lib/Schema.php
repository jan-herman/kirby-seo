<?php

namespace JanHerman\Seo;

use Kirby\Cms\Page;
use Spatie\SchemaOrg\Schema as SchemaBuilder;

class Schema
{
	private static $instances = [];

	private function __construct()
	{
	}

	public static function getInstance(string $type, Page|null $page = null): mixed
	{
		if (!isset(self::$instances[$page?->id() ?? 'default'][$type])) {
			self::$instances[$page?->id() ?? 'default'][$type] = SchemaBuilder::{$type}();
		}

		return self::$instances[$page?->id() ?? 'default'][$type];
	}

	public static function getInstances(Page|null $page = null): array
	{
		return self::$instances[$page?->id() ?? 'default'] ?? [];
	}
}
