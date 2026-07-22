<?php

namespace justinholtweb\puppy\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\User;
use justinholtweb\puppy\models\TrailItem;

/**
 * Trail service — manages session-based tracking of CP navigation and edits.
 */
class Trail extends Component
{
    private const SESSION_KEY_TRAIL = 'puppy.trail';
    private const SESSION_KEY_EDITS = 'puppy.edits';
    private const MAX_TRAIL_ITEMS = 100;
    private const MAX_EDIT_ITEMS = 50;

    // Mirrors the validation rules on TrailItem. Items are stored in the session
    // rather than validated one by one, so the limits are enforced here to keep
    // an oversized label or URL from bloating the session.
    private const MAX_LABEL_LENGTH = 255;
    private const MAX_URL_LENGTH = 2048;
    private const MAX_CONTEXT_LENGTH = 255;

    /**
     * Record a page visit from the frontend.
     */
    public function recordVisit(string $url, string $label, string $type = 'route', ?int $elementId = null, ?string $context = null): void
    {
        // A truncated URL would be a broken link, so overlong ones are dropped
        // rather than trimmed.
        if (mb_strlen($url) > self::MAX_URL_LENGTH) {
            return;
        }

        $item = new TrailItem();
        $item->type = $type;
        $item->action = 'visited';
        $item->label = $this->_truncate($label, self::MAX_LABEL_LENGTH);
        $item->url = $url;
        $item->elementId = $elementId;
        $item->timestamp = time();
        $item->context = $context !== null
            ? $this->_truncate($context, self::MAX_CONTEXT_LENGTH)
            : null;

        $trail = $this->getTrail();

        // Don't duplicate the most recent item if it's the same URL
        if (!empty($trail) && $trail[0]['url'] === $url) {
            return;
        }

        array_unshift($trail, $item->toArray());
        $trail = array_slice($trail, 0, self::MAX_TRAIL_ITEMS);

        $this->_setSession(self::SESSION_KEY_TRAIL, $trail);
    }

    /**
     * Record an element save/create from backend event listeners.
     */
    public function recordEdit(Element $element, string $action = 'saved'): void
    {
        $item = new TrailItem();
        $item->type = $this->_resolveElementType($element);
        $item->action = $action;
        $item->label = $this->_truncate($this->_resolveElementLabel($element), self::MAX_LABEL_LENGTH);
        $item->url = $this->_resolveElementCpUrl($element);
        $item->elementId = $element->id;
        $item->timestamp = time();

        $context = $this->_resolveElementContext($element);
        $item->context = $context !== null
            ? $this->_truncate($context, self::MAX_CONTEXT_LENGTH)
            : null;

        if (empty($item->label) || empty($item->url) || mb_strlen($item->url) > self::MAX_URL_LENGTH) {
            return;
        }

        $edits = $this->getEdits();
        array_unshift($edits, $item->toArray());
        $edits = array_slice($edits, 0, self::MAX_EDIT_ITEMS);

        $this->_setSession(self::SESSION_KEY_EDITS, $edits);
    }

    /**
     * Get the current session trail (visited pages).
     */
    public function getTrail(): array
    {
        return $this->_getSession(self::SESSION_KEY_TRAIL) ?? [];
    }

    /**
     * Get the current session edits.
     */
    public function getEdits(): array
    {
        return $this->_getSession(self::SESSION_KEY_EDITS) ?? [];
    }

    /**
     * Get full session data for the frontend.
     */
    public function getSessionData(): array
    {
        return [
            'trail' => $this->getTrail(),
            'edits' => $this->getEdits(),
        ];
    }

    /**
     * Clear the session trail and edits.
     */
    public function clearSession(): void
    {
        $session = Craft::$app->getSession();
        $session->remove(self::SESSION_KEY_TRAIL);
        $session->remove(self::SESSION_KEY_EDITS);
    }

    private function _resolveElementType(Element $element): string
    {
        return match (true) {
            $element instanceof Entry => 'entry',
            $element instanceof Asset => 'asset',
            $element instanceof Category => 'category',
            $element instanceof GlobalSet => 'globalset',
            $element instanceof User => 'user',
            default => 'element',
        };
    }

    private function _resolveElementLabel(Element $element): string
    {
        if ($element instanceof GlobalSet) {
            return $element->name;
        }

        return (string)$element;
    }

    private function _resolveElementCpUrl(Element $element): string
    {
        return $element->getCpEditUrl() ?? '';
    }

    private function _resolveElementContext(Element $element): ?string
    {
        if ($element instanceof Entry) {
            $section = $element->getSection();
            return $section ? $section->name : null;
        }

        if ($element instanceof Asset) {
            $volume = $element->getVolume();
            return $volume->name;
        }

        if ($element instanceof Category) {
            $group = $element->getGroup();
            return $group->name;
        }

        return null;
    }

    /**
     * Trims a value to a maximum number of characters, multibyte-safely.
     */
    private function _truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength
            ? mb_substr($value, 0, $maxLength)
            : $value;
    }

    private function _getSession(string $key): ?array
    {
        return Craft::$app->getSession()->get($key);
    }

    private function _setSession(string $key, array $data): void
    {
        Craft::$app->getSession()->set($key, $data);
    }
}
