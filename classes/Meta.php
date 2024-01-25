<?php

namespace tobimori\Seo;

use Kirby\Content\Field;
use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\A;


/**
 * The Meta class is responsible for handling the meta data & cascading
 */
class Meta
{
  /** 
   * These values will be handled as 'field is empty' 
   */
  const DEFAULT_VALUES = ['[]', 'default'];

  protected Page $page;
  protected ?string $lang;
  protected array $consumed = [];
  protected array $metaDefaults = [];
  protected array $metaArray = [];

  /**
   * Creates a new Meta instance
   */
  public function __construct(Page $page, ?string $lang = null)
  {
    $this->page = $page;
    $this->lang = $lang;

    if (method_exists($this->page, 'metaDefaults')) {
      $this->metaDefaults = $this->page->metaDefaults($this->lang);
    }
  }


  /**
   * Returns the meta array which maps meta tags to their fieldnames
   */
  protected function metaArray(): array
  {
    if ($this->metaArray) return $this->metaArray;

    /**
     * We have to specify field names and resolve them later, so we can use this 
     * function to resolve meta tags from field names in the programmatic function
     * 
     * Specify a callable if the value is not using the default cascade, so we know
     * whether it's a normal field in normalizeMetaArray
     */
    $meta =
      [
        'title' => 'title',
        'description' => 'metaDescription',
        'date' => fn () => $this->page->modified($this->dateFormat()),
        'canonical' => 'canonicalUrl',
        'og:title' => 'ogTitle',
        'og:description' => 'ogDescription',
        'og:url' => 'canonicalUrl',
        'og:site_name' => 'ogSiteName',
        'og:image' => 'ogImage',
        'og:image:width' => fn () => $this->ogImage() ? 1200 : null,
        'og:image:height' => fn () => $this->ogImage() ? 630 : null,
        'og:image:alt' => fn () => $this->get('ogImage')->toFile()?->alt(),
        'og:type' => 'ogType',
      ];


    // Multi-lang
    if (kirby()->languages()->count() > 1 && kirby()->language() !== null) {
      foreach (kirby()->languages() as $lang) {
        $meta['alternate'][] = [
          'hreflang' => fn () => $lang->code(),
          'href' => fn () => $this->page->url($lang->code()),
        ];

        if ($lang !== kirby()->language()) {
          $meta['og:locale:alternate'][] = fn () => $lang->code();
        }
      }

      $meta['alternate'][] = [
        'hreflang' => fn () => 'x-default',
        'href' => fn () => $this->page->url(kirby()->language()->code()),
      ];
      $meta['og:locale'] = fn () => kirby()->language()->locale(LC_ALL);
    } else {
      $meta['og:locale'] = fn () => $this->locale(LC_ALL);
    }


    // Twitter tags "opt-in"
    if (option('tobimori.seo.twitter', true)) {
      $meta = array_merge($meta, [
        'twitter:card' => 'twitterCardType',
        'twitter:title' => 'ogTitle',
        'twitter:description' => 'ogDescription',
        'twitter:image' => 'ogImage',
        'twitter:site' => 'twitterSite',
        'twitter:creator' => 'twitterCreator',
      ]);
    }

    // Robots
    if (option('tobimori.seo.robots.active')) {
      $meta['robots'] = fn () => $this->robots();
    }

    // This array will be normalized for use in the snippet in $this->normalizeMetaArray()
    return $this->metaArray = $meta;
  }

  /**
   * This array defines what HTML tag the corresponding meta tags are using including the attributes,
   * so everything is a bit more elegant when defining programmatic content (supports regex)
   */
  const TAG_TYPE_MAP = [
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

  /**
   * Normalize the meta array and remaining meta defaults to be used in the snippet,
   * also resolve the content, if necessary
   */
  public function snippetData(array $raw = null): array
  {
    $meta = [];
    foreach (($raw ?? $this->metaArray()) as $tag => $valueOrKey) {
      if (is_callable($valueOrKey)) {
        $meta[$tag] = $valueOrKey();
        continue;
      }

      if (is_string($valueOrKey)) {
        $meta[$tag] = $this->$valueOrKey();
      }
    }

    $tags = [];
    foreach ($meta as $name => $value) {
      $tag = $this->resolveTag($name);

      foreach ((is_array($value) ? $value : [$value]) as $value) {
        if (is_a($value, 'Kirby\Content\Field') && $value->isEmpty()) continue;
        if (!$value) continue;

        $tags[] = [
          'tag' => $tag['tag'],
          'attributes' => isset($tag['attributes']) ? [
            $tag['attributes']['name'] => $name,
            $tag['attributes']['content'] => $value,
          ] : null,
          'content' => !isset($tag['attributes']) ? $value : null,
        ];
      }
    }

    // Add the remaining meta defaults, if they are not consumed
    if ($raw === null) {
      $tags = array_merge($tags, $this->snippetData(array_diff($this->metaDefaults, $this->consumed)));
    }

    return $tags;
  }

  /**
   * Resolves the tag type from the meta array
   */
  protected function resolveTag(string $tag): array
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

  /**
   * Magic method to get a meta value by calling the method name
   */
  public function __call($name, $args = null): mixed
  {
    if (method_exists($this, $name)) {
      return $this->$name($args);
    }

    return $this->get($name);
  }

  /**
   * Get the meta value for a given key
   */
  public function get(string $key, array $exclude = []): Field
  {
    $cascade = option('tobimori.seo.cascade');
    if (count(array_intersect(get_class_methods($this), $cascade)) !== count($cascade)) {
      throw new InvalidArgumentException('[kirby-seo] Invalid cascade method in config. Please check your options for `tobimori.seo.cascade`.');
    }

    // Track consumed keys, so we don't output legacy field values
    if (A::has($this->metaDefaults, $key)) {
      $this->consumed[] = $key;
    }

    foreach (array_diff($cascade, $exclude) as $method) {
      if ($field = $this->$method($key)) {
        return $field;
      }
    }

    return new Field($this->page, $key, '');
  }

  /**
   * Get the meta value for a given key from the page's fields
   */
  protected function fields(string $key): Field|null
  {
    if (($field = $this->page->content($this->lang)->get($key))) {
      if (Str::contains($key, 'robots') && !option('tobimori.seo.robots.pageSettings')) return null;

      if ($field->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $field->value())) {
        return $field;
      }
    }

    return null;
  }

  /**
   * Maps Open Graph fields to Meta fields for fallbackFields 
   * cascade method
   */
  const FALLBACK_MAP = [
    'ogTitle' => 'metaTitle',
    'ogDescription' => 'metaDescription',
    'ogTemplate' => 'metaTemplate',
  ];

  /**
   * We only allow the following cascade methods for fallbacks,
   * because we don't want to fallback to the config defaults for
   * Meta fields, because we most likely already have those set 
   * for the Open Graph fields
   */
  const FALLBACK_CASCADE = [
    'fields',
    'programmatic',
    'parent',
    'site'
  ];

  /**
   * Get the meta value for a given key using the fallback fields
   * defined above (usually Open Graph > Meta Fields)
   */
  protected function fallbackFields(string $key): Field|null
  {
    if (array_key_exists($key, self::FALLBACK_MAP)) {
      $fallback = self::FALLBACK_MAP[$key];
      $cascade = option('tobimori.seo.cascade');

      foreach (array_intersect($cascade, self::FALLBACK_CASCADE) as $method) {
        if ($field = $this->$method($fallback)) {
          return $field;
        }
      }
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the page's meta
   * array, which can be set in the page's model metaDefaults method
   */
  protected function programmatic(string $key): Field|null
  {
    if (!$this->metaDefaults) {
      return null;
    }

    // Check if the key (field name) is in the array syntax
    if (array_key_exists($key, $this->metaDefaults)) {
      $val = $this->metaDefaults[$key];
    }

    /* If there is no programmatic value for the key, 
     * try looking it up in the meta array
     * maybe it is a meta tag and not a field name? 
     */
    if (!isset($val) && ($key = array_search($key, $this->metaArray())) && array_key_exists($key, $this->metaDefaults)) {
      $val = $this->metaDefaults[$key];
    }

    if (isset($val)) {
      if (is_callable($val)) {
        $val = $val($this->page);
      }

      if (is_array($val)) {
        $val = $val['content'] ?? $val['href'] ?? null;

        // Last sanity check, if the array syntax doesn't have a supported key
        if ($val === null) {
          // Remove the key from the consumed array, so it doesn't get filtered out
          // (we can assume the entry is a custom meta tag that uses different attributes)
          $this->consumed = array_filter($this->consumed, fn ($item) => $item !== $key);
          return null;
        }
      }

      if (is_a($val, 'Kirby\Content\Field')) {
        return $val;
      }

      return new Field($this->page, $key, $val);
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the page's parent,
   * if the page is allowed to inherit the value
   */
  protected function parent(string $key): Field|null
  {
    if ($this->canInherit($key)) {
      $parent = $this->page->parent();
      $parentMeta = new Meta($parent, $this->lang);
      if ($value = $parentMeta->get($key)) {
        return $value;
      }
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the 
   * site's meta blueprint & content
   */
  protected function site(string $key): Field|null
  {
    if (($site = $this->page->site()->content($this->lang)->get($key)) && ($site->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $site->value))) {
      return $site;
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the 
   * config.php options
   */
  protected function options(string $key): Field|null
  {
    if ($option = option("tobimori.seo.default.{$key}")) {
      if (is_callable($option)) {
        $option = $option($this->page);
      }

      if (is_a($option, 'Kirby\Content\Field')) {
        return $option;
      }

      return new Field($this->page, $key, $option);
    }

    return null;
  }

  /**
   * Checks if the page can inherit a meta value from its parent
   */
  private function canInherit(string $key): bool
  {
    $parent = $this->page->parent();
    if (!$parent) return false;

    $inherit = $parent->metaInherit()->split();
    if (Str::contains($key, 'robots') && A::has($inherit, 'robots')) return true;
    return A::has($inherit, $key);
  }

  /**
   * Applies the title template, and returns the correct title 
   */
  public function title()
  {
    $title = $this->metaTitle();
    $template = $this->metaTemplate();

    $useTemplate = $this->page->useTitleTemplate();
    $useTemplate = $useTemplate->isEmpty() ? true : $useTemplate->toBool();

    $string = $title->value();
    if ($useTemplate) {
      $string = $this->page->toString(
        $template,
        ['title' => $title]
      );
    }

    return new Field(
      $this->page,
      'metaTitle',
      $string
    );
  }

  /**
   * Applies the OG title template, and returns the OG Title 
   */
  public function ogTitle()
  {
    $title = $this->metaTitle();
    $template = $this->ogTemplate();

    $useTemplate = $this->page->useOgTemplate();
    $useTemplate = $useTemplate->isEmpty() ? true : $useTemplate->toBool();

    $string = $title->value();
    if ($useTemplate) {
      $string = $this->page->toString(
        $template,
        ['title' => $title]
      );
    }

    return new Field(
      $this->page,
      'ogTitle',
      $string
    );
  }

  /**
   * Gets the canonical url for the page
   */
  public function canonicalUrl()
  {
    return $this->page->site()->canonicalFor($this->page->url());
  }

  /**
   * Get the Twitter username from an account url set in the site options
   */
  public function twitterSite()
  {
    $accs = $this->page->site()->socialMediaAccounts()->toObject();
    $username = '';

    if ($accs->twitter()->isNotEmpty()) {
      // tries to match all twitter urls, and extract the username
      $matches = [];
      preg_match('/^(https?:\/\/)?(www\.)?twitter\.com\/(#!\/)?@?(?<name>[^\/\?]*)$/', $accs->twitter()->value(), $matches);
      if (isset($matches['name'])) $username = $matches['name'];
    }

    return new Field($this->page, 'twitter', $username);
  }

  /**
   * Gets the date format for modified meta tags, based on the registered date handler
   */
  public function dateFormat(): string
  {
    if ($custom = option('tobimori.seo.dateFormat')) {
      if (is_callable($custom)) {
        return $custom($this->page);
      }

      return $custom;
    }

    switch (option('date.handler')) {
      case 'strftime':
        return '%Y-%m-%d';
      case 'intl':
        return 'yyyy-MM-dd';
      case 'date':
      default:
        return 'Y-m-d';
    }
  }

  /**
   * Get the pages' robots rules as string
   */
  public function robots()
  {
    $robots = [];
    foreach (option('tobimori.seo.robots.types') as $type) {
      if (!$this->get('robots' . Str::ucfirst($type))->toBool()) {
        $robots[] = 'no' . Str::lower($type);
      }
    };

    if (A::count($robots) === 0) {
      $robots = ['all'];
    }

    return A::join($robots, ',');
  }

  /**
   * Get the og:image url
   */
  public function ogImage(): string|null
  {
    $field = $this->get('ogImage');

    if ($ogImage = $field->toFile()?->thumb([
      'width' => 1200,
      'height' => 630,
      'crop' => true,
    ])) {
      return $ogImage->url();
    }

    if ($field->isNotEmpty()) {
      return $field->value();
    }

    return null;
  }
}
