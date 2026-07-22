<?php

namespace justinholtweb\puppytests\unit;

use Codeception\Stub;
use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\models\Section;
use craft\test\TestCase;
use justinholtweb\puppy\Plugin;
use ReflectionObject;
use UnitTester;
use yii\base\Event;

/**
 * Tests the Element::EVENT_AFTER_SAVE listener registered by the plugin.
 *
 * The plugin only registers the listener on authenticated CP requests, which
 * isn't the case during a unit test bootstrap — so the test registers it the
 * same way `Plugin::init()` does and tears it down afterwards.
 */
class ElementSaveListenerTest extends TestCase
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        parent::_before();

        Craft::$app->getSession()->removeAll();

        $plugin = Plugin::getInstance();
        $method = (new ReflectionObject($plugin))->getMethod('_registerElementSaveListeners');
        $method->setAccessible(true);
        $method->invoke($plugin);
    }

    protected function _after(): void
    {
        Event::offAll();
        Craft::$app->getUser()->setIdentity(null);

        parent::_after();
    }

    public function testSaveIsRecordedForLoggedInUsers(): void
    {
        $this->login();

        $this->triggerSave($this->entry(), isNew: false);

        $edits = Plugin::getInstance()->trail->getEdits();

        self::assertCount(1, $edits);
        self::assertSame('saved', $edits[0]['action']);
        self::assertSame('Homepage', $edits[0]['label']);
        self::assertSame('entry', $edits[0]['type']);
    }

    public function testNewElementsAreRecordedAsCreated(): void
    {
        $this->login();

        $this->triggerSave($this->entry(), isNew: true);

        self::assertSame('created', Plugin::getInstance()->trail->getEdits()[0]['action']);
    }

    public function testSavesAreIgnoredForGuests(): void
    {
        $this->triggerSave($this->entry(), isNew: false);

        self::assertSame([], Plugin::getInstance()->trail->getEdits());
    }

    public function testRevisionsAreIgnored(): void
    {
        $this->login();

        $this->triggerSave($this->entry(['getIsRevision' => true]), isNew: false);

        self::assertSame([], Plugin::getInstance()->trail->getEdits());
    }

    public function testDraftsAreIgnored(): void
    {
        $this->login();

        // Craft autosaves a provisional draft every few seconds while an entry
        // is open in the CP; each one fires EVENT_AFTER_SAVE.
        $this->triggerSave($this->entry(['getIsDraft' => true, 'isProvisionalDraft' => true]), isNew: false);

        self::assertSame([], Plugin::getInstance()->trail->getEdits());
    }

    public function testExplicitDraftsAreIgnored(): void
    {
        $this->login();

        $this->triggerSave($this->entry(['getIsDraft' => true, 'draftId' => 12]), isNew: true);

        self::assertSame([], Plugin::getInstance()->trail->getEdits());
    }

    public function testPropagatedSavesAreIgnored(): void
    {
        $this->login();

        // On a multi-site install the canonical save is followed by one
        // propagated save per supported site — all for the same edit.
        $this->triggerSave($this->entry(), isNew: false);
        $this->triggerSave($this->entry(['propagating' => true]), isNew: false);
        $this->triggerSave($this->entry(['propagating' => true]), isNew: false);

        self::assertCount(1, Plugin::getInstance()->trail->getEdits());
    }

    public function testBulkResavesAreIgnored(): void
    {
        $this->login();

        // Resave jobs (e.g. after a section change) would otherwise flood the
        // edits list with elements the user never touched.
        $this->triggerSave($this->entry(['resaving' => true]), isNew: false);

        self::assertSame([], Plugin::getInstance()->trail->getEdits());
    }

    private function triggerSave(Element $element, bool $isNew): void
    {
        $event = new ModelEvent(['isNew' => $isNew]);
        $event->sender = $element;

        Event::trigger(Element::class, Element::EVENT_AFTER_SAVE, $event);
    }

    private function entry(array $overrides = []): Entry
    {
        return Stub::make(Entry::class, $overrides + [
            'id' => 5,
            'title' => 'Homepage',
            'getCpEditUrl' => '/admin/entries/pages/5',
            'getSection' => new Section(['name' => 'Pages']),
            'getIsRevision' => false,
            'getIsDraft' => false,
        ]);
    }

    private function login(): void
    {
        $user = Stub::make(User::class, [
            'id' => 1,
            'username' => 'tester',
            'getAuthKey' => 'test-auth-key',
        ]);

        Craft::$app->getUser()->setIdentity($user);
    }
}
