<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

/**
 * One answer a poll offers.
 *
 * `responsesCount` is only set on PollResults, that is once the poll has
 * closed; an open poll never reveals its running tally.
 */
final readonly class PollOption
{
    public function __construct(
        public string $ulid,
        public string $label,
        public ?int $responsesCount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'ulid'),
            Values::string($data, 'label'),
            array_key_exists('responses_count', $data) ? Values::int($data, 'responses_count') : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $option = ['ulid' => $this->ulid, 'label' => $this->label];

        if ($this->responsesCount !== null) {
            $option['responses_count'] = $this->responsesCount;
        }

        return $option;
    }
}
