<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use Carbon\CarbonImmutable;

/**
 * A poll as members see it: the question, its options and the member's own
 * answer. The tally is only available once closed, through PollResults.
 *
 * `threadUlid` links the thread the poll is about, while that thread is
 * visible. `myOptions` holds the option ulids the acting member picked.
 */
final readonly class Poll
{
    /**
     * @param  list<PollOption>  $options
     * @param  list<string>  $myOptions
     */
    public function __construct(
        public string $ulid,
        public string $question,
        public bool $isMultipleChoice,
        public bool $isOpen,
        public bool $isClosed,
        public ?CarbonImmutable $opensAt,
        public ?CarbonImmutable $closesAt,
        public ?string $threadUlid,
        public array $options,
        public array $myOptions,
        public ?CarbonImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'ulid'),
            Values::string($data, 'question'),
            Values::bool($data, 'is_multiple_choice'),
            Values::bool($data, 'is_open'),
            Values::bool($data, 'is_closed'),
            Values::date($data, 'opens_at'),
            Values::date($data, 'closes_at'),
            Values::nullableString($data, 'thread_ulid'),
            array_map(PollOption::fromArray(...), Values::items($data, 'options')),
            Values::strings($data, 'my_options'),
            Values::date($data, 'created_at'),
        );
    }

    /**
     * Whether the acting member has answered the poll.
     */
    public function hasResponded(): bool
    {
        return $this->myOptions !== [];
    }

    /**
     * Whether the acting member picked this option.
     */
    public function picked(PollOption|string $option): bool
    {
        return in_array($option instanceof PollOption ? $option->ulid : $option, $this->myOptions, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'question' => $this->question,
            'is_multiple_choice' => $this->isMultipleChoice,
            'is_open' => $this->isOpen,
            'is_closed' => $this->isClosed,
            'opens_at' => Values::iso($this->opensAt),
            'closes_at' => Values::iso($this->closesAt),
            'thread_ulid' => $this->threadUlid,
            'options' => array_map(fn (PollOption $option): array => $option->toArray(), $this->options),
            'my_options' => $this->myOptions,
            'created_at' => Values::iso($this->createdAt),
        ];
    }
}
