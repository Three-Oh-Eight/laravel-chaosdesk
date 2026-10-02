<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use Carbon\CarbonImmutable;

/**
 * The tally of a closed poll: per-option counts and distinct respondents.
 *
 * Every option carries its `responsesCount`. On a multiple-choice poll the
 * option counts add up to more than `respondentsCount`.
 */
final readonly class PollResults
{
    /**
     * @param  list<PollOption>  $options
     */
    public function __construct(
        public string $ulid,
        public string $question,
        public bool $isMultipleChoice,
        public ?CarbonImmutable $closesAt,
        public int $respondentsCount,
        public array $options,
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
            Values::date($data, 'closes_at'),
            Values::int($data, 'respondents_count'),
            array_map(
                fn (array $option): PollOption => PollOption::fromArray($option + ['responses_count' => 0]),
                Values::items($data, 'options'),
            ),
        );
    }

    /**
     * The share of respondents that picked an option, from 0 to 100.
     */
    public function percentage(PollOption $option): float
    {
        if ($this->respondentsCount === 0) {
            return 0.0;
        }

        return round(($option->responsesCount ?? 0) / $this->respondentsCount * 100, 1);
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
            'closes_at' => Values::iso($this->closesAt),
            'respondents_count' => $this->respondentsCount,
            'options' => array_map(fn (PollOption $option): array => $option->toArray(), $this->options),
        ];
    }
}
