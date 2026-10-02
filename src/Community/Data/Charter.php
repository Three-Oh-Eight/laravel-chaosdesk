<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

/**
 * A board's charter and whether the acting member accepted its current version.
 *
 * `locale` is the language ChaosDesk served the markdown in; null when the
 * default body was served or the API does not report it.
 */
final readonly class Charter
{
    public function __construct(
        public ?string $markdown,
        public int $version,
        public bool $accepted,
        public ?string $locale = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::nullableString($data, 'markdown'),
            Values::int($data, 'version', 1),
            Values::bool($data, 'accepted'),
            Values::nullableString($data, 'locale'),
        );
    }

    /**
     * The `locale` key is only present when the API reported one.
     *
     * @return array{markdown: string|null, version: int, accepted: bool, locale?: string}
     */
    public function toArray(): array
    {
        return [
            'markdown' => $this->markdown,
            'version' => $this->version,
            'accepted' => $this->accepted,
        ] + ($this->locale === null ? [] : ['locale' => $this->locale]);
    }
}
