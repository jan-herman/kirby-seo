<?php

namespace JanHerman\Seo\Meta;

use Kirby\Cms\Page;
use Kirby\Cms\Language;
use Kirby\Cms\FileVersion;
use Kirby\Content\Field;
use Kirby\Toolkit\Str;
use Kirby\Query\Query;
use Kirby\Exception\InvalidArgumentException;

class Metadata
{
    private Language $lang;
    private Page $page;
    private ?Page $settings_page;
    private array $meta_from_model = [];
    private array $meta_array = [];

    public const CASCADE = [
		'content',
        'model',
        'fallback',
        'settings',
        'config',
	];

    public function __construct(Page $page, ?Language $lang = null)
    {
        $this->page = $page;
        $this->lang = $lang ?? kirby()->language();

        if (method_exists($this->page, 'metaDefaults')) {
			$this->meta_from_model = $this->page->metaDefaults($this->lang?->code());
		}
    }

    public function __call(string $name, array $arguments = []): Field
	{
		return $this->get($name);
	}

    private function settingsPage(): ?Page
    {
        if (isset($this->settings_page)) {
            return $this->settings_page;
        }

        $option = option('jan-herman.seo.settingsPage.query');

        if ($option === null) {
            $settings_page = null;
        } elseif (is_callable($option)) {
            $settings_page = $option();
        } elseif (is_string($option)) {
            $settings_page = Query::factory($option)->resolve([
                'kirby' => $this->page->kirby(),
                'site' => $this->page->site(),
                'page' => $this->page,
            ]);
        } else {
            throw new InvalidArgumentException('\'jan-herman.seo.settingsPage.query\' must be a string or a callable');
        }

        if ($settings_page !== null && is_a($settings_page, 'Kirby\Cms\Page') === false) {
            throw new InvalidArgumentException('\'jan-herman.seo.settingsPage.query\' must return a page');
        }

        return $this->settings_page = $settings_page;
    }

    private function normalize(string $key, mixed $value): Field
    {
        if (is_callable($value)) {
            $value = $value($this->page);
        }

        if (is_a($value, 'Kirby\Content\Field')) {
            $value = $value->value();
        }

        if (is_string($value) && $value !== '') {
            $value = Str::template($value, [
                'kirby' => $this->page->kirby(),
                'site' => $this->page->site(),
                'page' => $this->page,
                'meta' => $this,
            ],
            [
                'fallback' => '',
            ]);
        }

        return new Field($this->page, $key, $value);
    }

    public function fromContent(string $key): Field
    {
        $field = $this->page->content($this->lang?->code())->{$key}();

        return $this->normalize($key, $field);
    }

    public function fromModel(string $key): Field
    {
		if (!$this->meta_from_model || !array_key_exists($key, $this->meta_from_model)) {
            return $this->normalize($key, null);
		}

        $value = $this->meta_from_model[$key];

        return $this->normalize($key, $value);
    }

    public function fromFallback(string $key): Field
    {
        if ($this->page->isHomePage()) {
            $value = option('jan-herman.seo.meta.homepage.fallback.' . $key);
        }

        $value = $value ?? option('jan-herman.seo.meta.fallback.' . $key);

        return $this->normalize($key, $value);
    }

    public function fromSettings(string $key): Field
    {
        $settings_page = $this->settingsPage() ?? site();
        $field = $settings_page->content($this->lang?->code())->{$key}();

        return $this->normalize($key, $field);
    }

    public function fromConfig(string $key): Field
    {
        if ($this->page->isHomePage()) {
            $value = option('jan-herman.seo.meta.homepage.default.' . $key);
        }

        $value = $value ?? option('jan-herman.seo.meta.default.' . $key);

        return $this->normalize($key, $value);
    }

    private function fromCascade(string $key, array $cascade): Field
    {
        foreach ($cascade as $cascade_key) {
            if (!in_array($cascade_key, self::CASCADE)) {
                continue;
            }

            $field = $this->{'from' . ucfirst($cascade_key)}($key);

            if ($field->isNotEmpty()) {
                return $field;
            }
        }

        return $this->normalize($key, null);
    }

    public function get(string $key): Field
    {
        // Cascade: Content > Model > Fallback > Settings (settings page/site) > Config (defaults)
        return $this->fromCascade($key, self::CASCADE);
    }

    public function placeholder(string $key): Field
    {
        if ($this->page->is($this->settingsPage())) {
            return $this->fromConfig($key);
        }

        return $this->fromCascade($key, array_slice(self::CASCADE, 1));
    }

    public function ogImageThumb(): FileVersion|null
    {
        $file = $this->get('ogImage')?->toFile();
        $crop = option('jan-herman.seo.meta.ogImage.crop', true);

        if ($file === null) {
            return null;
        }

        // Crop to 1200x630
        if ($crop) {
            return $file->thumb([
                'width' => 1200,
                'height' => 630,
                'crop' => true,
            ]);
        }

        // Resize to max 1500px on the longest side
        return $file->thumb([
            'width' => 1500,
            'height' => 1500,
            'upscale' => false,
        ]);
    }

    public function isIndexable(): bool
    {
        if ($this->fromModel('robots')->isNotEmpty()) {
            return str_contains($this->fromModel('robots')->toString(), 'noindex') === false;
        }

        $option = option('jan-herman.seo.robots.index');

        if (is_callable($option)) {
            return $option($this->page);
        }

        if (is_bool($option)) {
            return $option;
        }

        return $this->page->isListed();
    }

    private function languageAlternates(): array
    {
        $alternates = [];
        $og_locales = [];

        // Check if the current URL is canonical
		$current_url = kirby()->request()->url()->toString();
		$canonical_url = $this->page->url();
		$is_canonical = $current_url === $canonical_url;

        $kirby = kirby();

		// Multi-lang alternate tags
		// Skip hreflang tags if URL is not canonical (has query params, Kirby params, etc.)
		if ($kirby->languages()->count() > 1 && $this->lang !== null && $is_canonical) {
			foreach ($kirby->languages() as $lang) {
				if (!$this->page->translation($lang->code())->exists()) {
					continue;
				}

                if ($this->isIndexable()) {
                    $alternates[] = [
                        'hreflang' => Str::replace($lang->locale(LC_ALL), '_', '-'),
                        'href' => $this->page->url($lang->code()),
                        'rel' => 'alternate',
                    ];
                }

				if ($lang !== $this->lang) {
					$og_locales[] = $lang->locale(LC_ALL);
				}
			}

			// only add alternate tags if the page is indexable
            if ($this->isIndexable()) {
                $default_lang = $kirby->defaultLanguage();
                $alternates[] = [
                    'hreflang' => 'x-default',
                    'href' => $this->page->url($default_lang->code()),
                    'rel' => 'alternate',
                ];
            }

			$og_locale = $this->lang->locale(LC_ALL);
		} else {
			$og_locale = option('jan-herman.seo.meta.default.locale', 'en_US');
		}

        return [
            'alternate' => $alternates,
            'og:locale' => $og_locale,
            'og:locale:alternate' => $og_locales,
        ];
    }

    public function toArray(): array
    {
        if ($this->meta_array) {
			return $this->meta_array;
		}

        $og_image_thumb = $this->ogImageThumb();

        $meta = [
            'title'           => $this->get('metaTemplate')->toString() ?: null,
            'description'     => $this->get('metaDescription')->toString() ?: null,
            'og:title'        => $this->get('ogTemplate')->toString() ?: null,
            'og:description'  => $this->get('ogDescription')->toString() ?: null,
            'og:site_name'    => $this->get('ogSiteName')->toString() ?: null,
            'og:image'        => $og_image_thumb?->url(),
            'og:image:width'  => $og_image_thumb?->width() ?? null,
            'og:image:height' => $og_image_thumb?->height() ?? null,
            'og:image:alt'    => $this->get('ogImage')->toFile()?->alt()?->toString() ?: null,
            'og:type'         => $this->get('ogType')->toString() ?: null,
            'og:url'          => $this->page->url(),
            'canonical'       => $this->page->url(),
        ];

        // Robots
        if ($this->fromModel('robots')->isNotEmpty()) {
            $meta['robots'] = $this->fromModel('robots')->toString();
        } elseif ($this->isIndexable() === false) {
            $meta['robots'] = 'noindex,nofollow,noarchive,noimageindex,nosnippet';
        }

        // Language alternate tags
        $meta = array_merge($meta, $this->languageAlternates());

        return $this->meta_array = $meta;
    }

    public function toHtml(): string
    {
        $meta = $this->toArray();
        return MetaTags::renderToString($meta);
    }
}
