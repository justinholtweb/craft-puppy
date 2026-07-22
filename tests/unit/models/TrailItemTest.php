<?php

namespace justinholtweb\puppytests\unit\models;

use craft\test\TestCase;
use justinholtweb\puppy\models\TrailItem;
use UnitTester;

/**
 * Tests the TrailItem model: defaults, (de)serialisation and validation rules.
 */
class TrailItemTest extends TestCase
{
    protected UnitTester $tester;

    public function testDefaults(): void
    {
        $item = new TrailItem();

        self::assertSame('route', $item->type);
        self::assertSame('visited', $item->action);
        self::assertSame('', $item->label);
        self::assertSame('', $item->url);
        self::assertNull($item->elementId);
        self::assertSame(0, $item->timestamp);
        self::assertNull($item->context);
    }

    public function testToArrayExposesAllFields(): void
    {
        $item = new TrailItem();
        $item->type = 'entry';
        $item->action = 'saved';
        $item->label = 'Homepage';
        $item->url = '/admin/entries/pages/1';
        $item->elementId = 1;
        $item->timestamp = 1700000000;
        $item->context = 'Pages';

        self::assertSame([
            'type' => 'entry',
            'action' => 'saved',
            'label' => 'Homepage',
            'url' => '/admin/entries/pages/1',
            'elementId' => 1,
            'timestamp' => 1700000000,
            'context' => 'Pages',
        ], $item->toArray());
    }

    public function testToArrayKeysAreStable(): void
    {
        // The frontend reads these keys directly; changing them is a breaking change.
        self::assertSame(
            ['type', 'action', 'label', 'url', 'elementId', 'timestamp', 'context'],
            array_keys((new TrailItem())->toArray()),
        );
    }

    public function testFromArrayHydratesEveryField(): void
    {
        $item = TrailItem::fromArray([
            'type' => 'asset',
            'action' => 'created',
            'label' => 'photo.jpg',
            'url' => '/admin/assets/12',
            'elementId' => 12,
            'timestamp' => 1700000001,
            'context' => 'Images',
        ]);

        self::assertSame('asset', $item->type);
        self::assertSame('created', $item->action);
        self::assertSame('photo.jpg', $item->label);
        self::assertSame('/admin/assets/12', $item->url);
        self::assertSame(12, $item->elementId);
        self::assertSame(1700000001, $item->timestamp);
        self::assertSame('Images', $item->context);
    }

    public function testFromArrayFallsBackToDefaults(): void
    {
        $before = time();
        $item = TrailItem::fromArray([]);

        self::assertSame('route', $item->type);
        self::assertSame('visited', $item->action);
        self::assertSame('', $item->label);
        self::assertSame('', $item->url);
        self::assertNull($item->elementId);
        self::assertNull($item->context);
        self::assertGreaterThanOrEqual($before, $item->timestamp);
    }

    public function testRoundTripsThroughArray(): void
    {
        $data = [
            'type' => 'category',
            'action' => 'updated',
            'label' => 'News',
            'url' => '/admin/categories/topics/4',
            'elementId' => 4,
            'timestamp' => 1700000002,
            'context' => 'Topics',
        ];

        self::assertSame($data, TrailItem::fromArray($data)->toArray());
    }

    public function testValidItemPassesValidation(): void
    {
        $item = TrailItem::fromArray([
            'type' => 'entry',
            'action' => 'saved',
            'label' => 'Homepage',
            'url' => '/admin/entries/pages/1',
            'timestamp' => 1700000000,
        ]);

        self::assertTrue($item->validate(), print_r($item->getErrors(), true));
    }

    public function testRequiredFieldsAreEnforced(): void
    {
        $item = new TrailItem();
        $item->timestamp = 1700000000;

        self::assertFalse($item->validate());
        self::assertArrayHasKey('label', $item->getErrors());
        self::assertArrayHasKey('url', $item->getErrors());
    }

    /**
     * @dataProvider validActionsProvider
     */
    public function testValidActionsAreAccepted(string $action): void
    {
        $item = $this->validItem();
        $item->action = $action;

        self::assertTrue($item->validate(['action']), print_r($item->getErrors(), true));
    }

    public static function validActionsProvider(): array
    {
        return [['visited'], ['saved'], ['created'], ['updated']];
    }

    public function testUnknownActionIsRejected(): void
    {
        $item = $this->validItem();
        $item->action = 'deleted';

        self::assertFalse($item->validate(['action']));
        self::assertArrayHasKey('action', $item->getErrors());
    }

    public function testOverlongLabelIsRejected(): void
    {
        $item = $this->validItem();
        $item->label = str_repeat('a', 256);

        self::assertFalse($item->validate(['label']));
    }

    public function testMaxLengthLabelIsAccepted(): void
    {
        $item = $this->validItem();
        $item->label = str_repeat('a', 255);

        self::assertTrue($item->validate(['label']));
    }

    public function testOverlongUrlIsRejected(): void
    {
        $item = $this->validItem();
        $item->url = '/' . str_repeat('a', 2048);

        self::assertFalse($item->validate(['url']));
    }

    public function testOverlongContextIsRejected(): void
    {
        $item = $this->validItem();
        $item->context = str_repeat('a', 256);

        self::assertFalse($item->validate(['context']));
    }

    private function validItem(): TrailItem
    {
        return TrailItem::fromArray([
            'type' => 'entry',
            'action' => 'saved',
            'label' => 'Homepage',
            'url' => '/admin/entries/pages/1',
            'timestamp' => 1700000000,
        ]);
    }
}
