<?php

namespace justinholtweb\puppy\models;

use craft\base\Model;

/**
 * Represents a single item in the Puppy session trail.
 */
class TrailItem extends Model
{
    /** @var string The type of item: 'entry', 'asset', 'category', 'globalset', 'user', 'route', etc. */
    public string $type = 'route';

    /** @var string The action: 'visited', 'saved', 'created', 'updated' */
    public string $action = 'visited';

    /** @var string Human-readable label for display */
    public string $label = '';

    /** @var string The CP URL path */
    public string $url = '';

    /** @var int|null The element ID, if applicable */
    public ?int $elementId = null;

    /** @var int Unix timestamp */
    public int $timestamp = 0;

    /** @var string|null Section name, volume name, etc. */
    public ?string $context = null;

    /**
     * Create a TrailItem from an array.
     */
    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->type = $data['type'] ?? 'route';
        $item->action = $data['action'] ?? 'visited';
        $item->label = $data['label'] ?? '';
        $item->url = $data['url'] ?? '';
        $item->elementId = $data['elementId'] ?? null;
        $item->timestamp = $data['timestamp'] ?? time();
        $item->context = $data['context'] ?? null;
        return $item;
    }

    /**
     * Serialize to array for JSON/session storage.
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'label' => $this->label,
            'url' => $this->url,
            'elementId' => $this->elementId,
            'timestamp' => $this->timestamp,
            'context' => $this->context,
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['type', 'action', 'label', 'url', 'timestamp'], 'required'],
            ['type', 'string'],
            ['action', 'in', 'range' => ['visited', 'saved', 'created', 'updated']],
            ['label', 'string', 'max' => 255],
            ['url', 'string', 'max' => 2048],
            ['elementId', 'integer'],
            ['timestamp', 'integer'],
            ['context', 'string', 'max' => 255],
        ];
    }
}
