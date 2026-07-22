<?php

namespace justinholtweb\puppytests\unit\services;

use Craft;
use craft\test\TestCase;
use justinholtweb\puppy\models\TrailItem;
use justinholtweb\puppy\services\Trail;
use UnitTester;

/**
 * Tests the Trail service's session-backed visit tracking.
 */
class TrailTest extends TestCase
{
    protected UnitTester $tester;
    protected Trail $trail;

    protected function _before(): void
    {
        parent::_before();

        $this->trail = new Trail();
        Craft::$app->getSession()->removeAll();
    }

    public function testTrailStartsEmpty(): void
    {
        self::assertSame([], $this->trail->getTrail());
    }

    public function testEditsStartEmpty(): void
    {
        self::assertSame([], $this->trail->getEdits());
    }

    public function testSessionDataStartsEmpty(): void
    {
        self::assertSame(['trail' => [], 'edits' => []], $this->trail->getSessionData());
    }

    public function testRecordVisitStoresItem(): void
    {
        $before = time();
        $this->trail->recordVisit('/admin/entries', 'Entries');

        $trail = $this->trail->getTrail();

        self::assertCount(1, $trail);
        self::assertSame('/admin/entries', $trail[0]['url']);
        self::assertSame('Entries', $trail[0]['label']);
        self::assertSame('route', $trail[0]['type']);
        self::assertSame('visited', $trail[0]['action']);
        self::assertNull($trail[0]['elementId']);
        self::assertNull($trail[0]['context']);
        self::assertGreaterThanOrEqual($before, $trail[0]['timestamp']);
    }

    public function testRecordVisitStoresOptionalArguments(): void
    {
        $this->trail->recordVisit('/admin/entries/pages/5', 'About', 'entry', 5, 'Pages');

        $item = $this->trail->getTrail()[0];

        self::assertSame('entry', $item['type']);
        self::assertSame(5, $item['elementId']);
        self::assertSame('Pages', $item['context']);
    }

    public function testNewestVisitIsFirst(): void
    {
        $this->trail->recordVisit('/admin/one', 'One');
        $this->trail->recordVisit('/admin/two', 'Two');
        $this->trail->recordVisit('/admin/three', 'Three');

        self::assertSame(
            ['/admin/three', '/admin/two', '/admin/one'],
            array_column($this->trail->getTrail(), 'url'),
        );
    }

    public function testConsecutiveDuplicateUrlIsIgnored(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries');
        $this->trail->recordVisit('/admin/entries', 'Entries again');

        self::assertCount(1, $this->trail->getTrail());
        self::assertSame('Entries', $this->trail->getTrail()[0]['label']);
    }

    public function testRepeatedUrlIsRecordedWhenNotConsecutive(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries');
        $this->trail->recordVisit('/admin/assets', 'Assets');
        $this->trail->recordVisit('/admin/entries', 'Entries');

        self::assertSame(
            ['/admin/entries', '/admin/assets', '/admin/entries'],
            array_column($this->trail->getTrail(), 'url'),
        );
    }

    public function testDeduplicationIsUrlSensitive(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries');
        $this->trail->recordVisit('/admin/entries?page=2', 'Entries page 2');

        self::assertCount(2, $this->trail->getTrail());
    }

    public function testTrailIsCappedAt100Items(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->trail->recordVisit("/admin/page/$i", "Page $i");
        }

        $trail = $this->trail->getTrail();

        self::assertCount(100, $trail);
        self::assertSame('/admin/page/119', $trail[0]['url']);
        self::assertSame('/admin/page/20', $trail[99]['url']);
    }

    public function testClearSessionEmptiesTrailAndEdits(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries');
        Craft::$app->getSession()->set('puppy.edits', [['label' => 'x']]);

        $this->trail->clearSession();

        self::assertSame([], $this->trail->getTrail());
        self::assertSame([], $this->trail->getEdits());
    }

    public function testClearSessionLeavesOtherSessionKeysAlone(): void
    {
        Craft::$app->getSession()->set('unrelated', 'keep me');
        $this->trail->recordVisit('/admin/entries', 'Entries');

        $this->trail->clearSession();

        self::assertSame('keep me', Craft::$app->getSession()->get('unrelated'));
    }

    public function testSessionDataReflectsRecordedVisits(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries');

        $data = $this->trail->getSessionData();

        self::assertSame(['trail', 'edits'], array_keys($data));
        self::assertCount(1, $data['trail']);
        self::assertSame([], $data['edits']);
    }

    public function testTrailIsSharedThroughTheSession(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries');

        // A second service instance must see the same session-backed data.
        self::assertCount(1, (new Trail())->getTrail());
    }

    public function testOverlongLabelIsTruncated(): void
    {
        $this->trail->recordVisit('/admin/entries', str_repeat('a', 300));

        self::assertSame(255, strlen($this->trail->getTrail()[0]['label']));
    }

    public function testMaxLengthLabelIsPreserved(): void
    {
        $label = str_repeat('a', 255);
        $this->trail->recordVisit('/admin/entries', $label);

        self::assertSame($label, $this->trail->getTrail()[0]['label']);
    }

    public function testOverlongContextIsTruncated(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries', 'route', null, str_repeat('b', 300));

        self::assertSame(255, strlen($this->trail->getTrail()[0]['context']));
    }

    public function testTruncationIsMultibyteSafe(): void
    {
        $this->trail->recordVisit('/admin/entries', str_repeat('é', 300));

        $label = $this->trail->getTrail()[0]['label'];

        self::assertSame(255, mb_strlen($label));
        self::assertSame(str_repeat('é', 255), $label, 'Truncation must not split a character');
    }

    public function testOverlongUrlIsNotRecorded(): void
    {
        // Truncating a URL would store a broken link, so the visit is dropped.
        $this->trail->recordVisit('/' . str_repeat('a', 2048), 'Entries');

        self::assertSame([], $this->trail->getTrail());
    }

    public function testMaxLengthUrlIsRecorded(): void
    {
        $url = '/' . str_repeat('a', 2047);
        $this->trail->recordVisit($url, 'Entries');

        self::assertSame($url, $this->trail->getTrail()[0]['url']);
    }

    public function testStoredItemsSatisfyTheModelValidationRules(): void
    {
        $this->trail->recordVisit(
            '/admin/entries',
            str_repeat('a', 300),
            'route',
            null,
            str_repeat('b', 300),
        );

        $item = TrailItem::fromArray($this->trail->getTrail()[0]);

        self::assertTrue($item->validate(), print_r($item->getErrors(), true));
    }

    public function testStoredItemsAreJsonSerialisable(): void
    {
        $this->trail->recordVisit('/admin/entries', 'Entries & "quotes"', 'route', null, 'Ünïcødé');

        $json = json_encode($this->trail->getSessionData());

        self::assertIsString($json);
        self::assertSame(
            'Entries & "quotes"',
            json_decode($json, true)['trail'][0]['label'],
        );
    }
}
