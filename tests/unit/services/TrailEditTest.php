<?php

namespace justinholtweb\puppytests\unit\services;

use Codeception\Stub;
use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\User;
use craft\models\CategoryGroup;
use craft\models\Section;
use craft\models\Volume;
use craft\test\mockclasses\elements\ExampleElement;
use craft\test\TestCase;
use justinholtweb\puppy\models\TrailItem;
use justinholtweb\puppy\services\Trail;
use UnitTester;

/**
 * Tests Trail::recordEdit() — how saved elements are resolved into trail items.
 */
class TrailEditTest extends TestCase
{
    protected UnitTester $tester;
    protected Trail $trail;

    protected function _before(): void
    {
        parent::_before();

        $this->trail = new Trail();
        Craft::$app->getSession()->removeAll();
    }

    public function testRecordEditStoresEntryDetails(): void
    {
        $before = time();
        $this->trail->recordEdit($this->entry());

        $edits = $this->trail->getEdits();

        self::assertCount(1, $edits);
        self::assertSame('entry', $edits[0]['type']);
        self::assertSame('saved', $edits[0]['action']);
        self::assertSame('Homepage', $edits[0]['label']);
        self::assertSame('/admin/entries/pages/5', $edits[0]['url']);
        self::assertSame(5, $edits[0]['elementId']);
        self::assertSame('Pages', $edits[0]['context']);
        self::assertGreaterThanOrEqual($before, $edits[0]['timestamp']);
    }

    public function testActionIsRecorded(): void
    {
        $this->trail->recordEdit($this->entry(), 'created');

        self::assertSame('created', $this->trail->getEdits()[0]['action']);
    }

    public function testEditsDoNotLeakIntoTheTrail(): void
    {
        $this->trail->recordEdit($this->entry());

        self::assertSame([], $this->trail->getTrail());
        self::assertCount(1, $this->trail->getEdits());
    }

    public function testNewestEditIsFirst(): void
    {
        $this->trail->recordEdit($this->entry(1, 'First'));
        $this->trail->recordEdit($this->entry(2, 'Second'));

        self::assertSame(
            ['Second', 'First'],
            array_column($this->trail->getEdits(), 'label'),
        );
    }

    public function testEditsAreCappedAt50Items(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->trail->recordEdit($this->entry($i, "Entry $i"));
        }

        $edits = $this->trail->getEdits();

        self::assertCount(50, $edits);
        self::assertSame('Entry 59', $edits[0]['label']);
        self::assertSame('Entry 10', $edits[49]['label']);
    }

    public function testElementWithoutCpEditUrlIsSkipped(): void
    {
        $entry = Stub::make(Entry::class, [
            'id' => 5,
            'title' => 'Homepage',
            'getCpEditUrl' => null,
            'getSection' => new Section(['name' => 'Pages']),
        ]);

        $this->trail->recordEdit($entry);

        self::assertSame([], $this->trail->getEdits());
    }

    public function testElementWithoutLabelIsSkipped(): void
    {
        $globalSet = Stub::make(GlobalSet::class, [
            'id' => 9,
            'name' => '',
            'getCpEditUrl' => '/admin/globals/footer',
        ]);

        $this->trail->recordEdit($globalSet);

        self::assertSame([], $this->trail->getEdits());
    }

    public function testEntryWithoutSectionHasNoContext(): void
    {
        $entry = Stub::make(Entry::class, [
            'id' => 5,
            'title' => 'Orphan',
            'getCpEditUrl' => '/admin/entries/5',
            'getSection' => null,
        ]);

        $this->trail->recordEdit($entry);

        self::assertNull($this->trail->getEdits()[0]['context']);
    }

    public function testAssetIsResolved(): void
    {
        $asset = Stub::make(Asset::class, [
            'id' => 12,
            'title' => 'photo',
            'filename' => 'photo.jpg',
            'getCpEditUrl' => '/admin/assets/12',
            'getVolume' => new Volume(['name' => 'Images']),
        ]);

        $this->trail->recordEdit($asset);
        $edit = $this->trail->getEdits()[0];

        self::assertSame('asset', $edit['type']);
        self::assertSame('Images', $edit['context']);
        self::assertNotSame('', $edit['label']);
    }

    public function testCategoryIsResolved(): void
    {
        $category = Stub::make(Category::class, [
            'id' => 3,
            'title' => 'Design',
            'getCpEditUrl' => '/admin/categories/topics/3',
            'getGroup' => new CategoryGroup(['name' => 'Topics']),
        ]);

        $this->trail->recordEdit($category);
        $edit = $this->trail->getEdits()[0];

        self::assertSame('category', $edit['type']);
        self::assertSame('Design', $edit['label']);
        self::assertSame('Topics', $edit['context']);
    }

    public function testGlobalSetUsesItsNameAsLabel(): void
    {
        $globalSet = Stub::make(GlobalSet::class, [
            'id' => 9,
            'name' => 'Footer',
            'handle' => 'footer',
            'getCpEditUrl' => '/admin/globals/footer',
        ]);

        $this->trail->recordEdit($globalSet);
        $edit = $this->trail->getEdits()[0];

        self::assertSame('globalset', $edit['type']);
        self::assertSame('Footer', $edit['label']);
        self::assertNull($edit['context']);
    }

    public function testUserIsResolved(): void
    {
        $user = Stub::make(User::class, [
            'id' => 7,
            'username' => 'editor',
            'getCpEditUrl' => '/admin/users/7',
        ]);

        $this->trail->recordEdit($user);
        $edit = $this->trail->getEdits()[0];

        self::assertSame('user', $edit['type']);
        self::assertSame(7, $edit['elementId']);
        self::assertNull($edit['context']);
    }

    public function testUnknownElementTypeFallsBackToElement(): void
    {
        $element = Stub::make(ExampleElement::class, [
            'id' => 42,
            'title' => 'Something',
            'getCpEditUrl' => '/admin/something/42',
        ]);

        $this->trail->recordEdit($element);
        $edit = $this->trail->getEdits()[0];

        self::assertSame('element', $edit['type']);
        self::assertNull($edit['context']);
    }

    public function testOverlongElementLabelIsTruncated(): void
    {
        $this->trail->recordEdit($this->entry(5, str_repeat('a', 300)));

        self::assertSame(255, strlen($this->trail->getEdits()[0]['label']));
    }

    public function testOverlongElementContextIsTruncated(): void
    {
        $entry = Stub::make(Entry::class, [
            'id' => 5,
            'title' => 'Homepage',
            'getCpEditUrl' => '/admin/entries/pages/5',
            'getSection' => new Section(['name' => str_repeat('b', 300)]),
        ]);

        $this->trail->recordEdit($entry);

        self::assertSame(255, strlen($this->trail->getEdits()[0]['context']));
    }

    public function testElementWithOverlongCpUrlIsSkipped(): void
    {
        $entry = Stub::make(Entry::class, [
            'id' => 5,
            'title' => 'Homepage',
            'getCpEditUrl' => '/' . str_repeat('a', 2048),
            'getSection' => new Section(['name' => 'Pages']),
        ]);

        $this->trail->recordEdit($entry);

        self::assertSame([], $this->trail->getEdits());
    }

    public function testStoredEditsSatisfyTheModelValidationRules(): void
    {
        $this->trail->recordEdit($this->entry(5, str_repeat('a', 300)));

        $item = TrailItem::fromArray($this->trail->getEdits()[0]);

        self::assertTrue($item->validate(), print_r($item->getErrors(), true));
    }

    public function testEditsAreSharedThroughTheSession(): void
    {
        $this->trail->recordEdit($this->entry());

        self::assertCount(1, (new Trail())->getEdits());
    }

    private function entry(int $id = 5, string $title = 'Homepage'): Entry
    {
        return Stub::make(Entry::class, [
            'id' => $id,
            'title' => $title,
            'getCpEditUrl' => '/admin/entries/pages/5',
            'getSection' => new Section(['name' => 'Pages']),
        ]);
    }
}
