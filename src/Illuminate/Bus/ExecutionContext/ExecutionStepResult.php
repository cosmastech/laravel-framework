<?php

namespace Illuminate\Bus\ExecutionContext;

use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExecutionStepResult
{
    use SerializesAndRestoresModelIdentifiers;

    public function __construct(
        public string $name,
        public Carbon $completedAt,
        public mixed $result,
    ) {
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'completedAt' => $this->completedAt,
            'result' => $this->serializeValue($this->result),
        ];
    }

    public function __unserialize(array $values): void
    {
        $this->name = $values['name'];
        $this->completedAt = $values['completedAt'];
        $this->result = $this->restoreValue($values['result']);
    }

    protected function serializeValue(mixed $value): mixed
    {
        $value = $this->getSerializedPropertyValue($value);

        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->serializeValue($nestedValue);
            }

            return $value;
        }

        if ($value instanceof Collection) {
            return $value->map(fn ($nestedValue) => $this->serializeValue($nestedValue));
        }

        return $value;
    }

    protected function restoreValue(mixed $value): mixed
    {
        $value = $this->getRestoredPropertyValue($value);

        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->restoreValue($nestedValue);
            }

            return $value;
        }

        if ($value instanceof Collection) {
            return $value->map(fn ($nestedValue) => $this->restoreValue($nestedValue));
        }

        return $value;
    }
}
