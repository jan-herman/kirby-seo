<?php

namespace JanHerman\Seo\Meta;

use Kirby\Cms\Html;
use Kirby\Toolkit\Str;

class MetaTags
{
    public const TAG_TYPE_MAP = [
		[
			'tag' => 'title',
			'tags' => [
				'title'
			]
		],
		[
			'tag' => 'link',
			'attributes' => [
				'name' => 'rel',
				'content' => 'href',
			],
			'tags' => [
				'me',
				'canonical',
				'alternate',
			]
		],
		[
			'tag' => 'meta',
			'attributes' => [
				'name' => 'property',
				'content' => 'content',
			],
			'tags' => [
				'/og:.+/'
			]
		]
	];

    private static function resolveTag(string $tag): array
	{
		foreach (self::TAG_TYPE_MAP as $type) {
			foreach ($type['tags'] as $regexOrString) {
				// Check if the supplied tag is a regex or a normal tag name
				if (Str::startsWith($regexOrString, '/') && Str::endsWith($regexOrString, '/') ?
					Str::match($tag, $regexOrString) : $tag === $regexOrString
				) {
					return $type;
				}
			}
		}

		return [
			'tag' => 'meta',
			'attributes' => [
				'name' => 'name',
				'content' => 'content',
			]
		];
	}

    public static function metaToTags(array $metadata): array
    {
        $tags = [];

        foreach ($metadata as $name => $value) {
            if ($value === null) {
                continue;
            }

			$tag = self::resolveTag($name);

			if (is_array($value)) {
				foreach ($value as $attributes) {
					$tags[] = [
						'tag' => $tag['tag'],
						'attributes' => $attributes,
						'content' => null,
					];
				}
				continue;
			}

            $tags[] = [
				'tag' => $tag['tag'],
				'attributes' => isset($tag['attributes']) ? [
					$tag['attributes']['name'] => $name,
					$tag['attributes']['content'] => $value,
				] : null,
				'content' => !isset($tag['attributes']) ? $value : null,
			];
        }

        return $tags;
    }

    public static function renderToString(array $metadata): string
    {
        $tags = self::metaToTags($metadata);
        $html = '';

        foreach ($tags as $tag) {
            $html .= Html::tag($tag['tag'], $tag['content'] ?? null, $tag['attributes'] ?? []) . PHP_EOL;
        }

        return $html;
    }

    public static function render(array $metadata): void
    {
        echo self::renderToString($metadata);
    }
}
