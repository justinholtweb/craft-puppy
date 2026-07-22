<?php

namespace justinholtweb\puppytests\unit\controllers;

use Craft;
use craft\test\TestCase;
use craft\web\Controller as CraftController;
use justinholtweb\puppy\controllers\SessionController;
use justinholtweb\puppy\Plugin;
use ReflectionProperty;
use UnitTester;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Tests the SessionController endpoints that back the Puppy frontend.
 */
class SessionControllerTest extends TestCase
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        parent::_before();

        Craft::$app->getSession()->removeAll();
    }

    public function testActionsAreNotAnonymous(): void
    {
        $property = new ReflectionProperty(SessionController::class, 'allowAnonymous');
        $property->setAccessible(true);

        // Craft normalizes `false` to ALLOW_ANONYMOUS_NEVER during init().
        self::assertSame(
            CraftController::ALLOW_ANONYMOUS_NEVER,
            $property->getValue($this->controller()),
        );
    }

    public function testGetTrailReturnsSessionData(): void
    {
        Plugin::getInstance()->trail->recordVisit('/admin/entries', 'Entries');

        $response = $this->controller()->actionGetTrail();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::FORMAT_JSON, $response->format);
        self::assertSame(['trail', 'edits'], array_keys($response->data));
        self::assertCount(1, $response->data['trail']);
        self::assertSame('/admin/entries', $response->data['trail'][0]['url']);
    }

    public function testGetTrailRequiresACpRequest(): void
    {
        $controller = $this->controller(['getIsCpRequest' => false]);

        $this->expectException(BadRequestHttpException::class);
        $controller->actionGetTrail();
    }

    public function testGetTrailRequiresJson(): void
    {
        $controller = $this->controller(['getAcceptsJson' => false, 'getIsOptions' => false]);

        $this->expectException(BadRequestHttpException::class);
        $controller->actionGetTrail();
    }

    public function testRecordVisitStoresTheVisit(): void
    {
        $controller = $this->controller([
            'getIsPost' => true,
            'getBodyParams' => [
                'url' => '/admin/entries/pages/5',
                'label' => 'About',
                'type' => 'entry',
                'elementId' => '5',
                'context' => 'Pages',
            ],
        ]);

        $response = $controller->actionRecordVisit();

        self::assertSame(['success' => true], $response->data);

        $item = Plugin::getInstance()->trail->getTrail()[0];

        self::assertSame('/admin/entries/pages/5', $item['url']);
        self::assertSame('About', $item['label']);
        self::assertSame('entry', $item['type']);
        self::assertSame(5, $item['elementId'], 'elementId should be cast to an int');
        self::assertSame('Pages', $item['context']);
    }

    public function testRecordVisitDefaultsOptionalParams(): void
    {
        $controller = $this->controller([
            'getIsPost' => true,
            'getBodyParams' => [
                'url' => '/admin/dashboard',
                'label' => 'Dashboard',
            ],
        ]);

        $controller->actionRecordVisit();

        $item = Plugin::getInstance()->trail->getTrail()[0];

        self::assertSame('route', $item['type']);
        self::assertNull($item['elementId']);
        self::assertNull($item['context']);
    }

    public function testRecordVisitRequiresPost(): void
    {
        $controller = $this->controller([
            'getIsPost' => false,
            'getBodyParams' => ['url' => '/admin', 'label' => 'Admin'],
        ]);

        $this->expectException(MethodNotAllowedHttpException::class);
        $controller->actionRecordVisit();
    }

    public function testRecordVisitRequiresUrl(): void
    {
        $controller = $this->controller([
            'getIsPost' => true,
            'getBodyParams' => ['label' => 'Dashboard'],
        ]);

        $this->expectException(BadRequestHttpException::class);
        $controller->actionRecordVisit();
    }

    public function testRecordVisitRequiresLabel(): void
    {
        $controller = $this->controller([
            'getIsPost' => true,
            'getBodyParams' => ['url' => '/admin/dashboard'],
        ]);

        $this->expectException(BadRequestHttpException::class);
        $controller->actionRecordVisit();
    }

    public function testClearEmptiesTheSession(): void
    {
        Plugin::getInstance()->trail->recordVisit('/admin/entries', 'Entries');

        $controller = $this->controller(['getIsPost' => true]);
        $response = $controller->actionClear();

        self::assertSame(['success' => true], $response->data);
        self::assertSame([], Plugin::getInstance()->trail->getTrail());
        self::assertSame([], Plugin::getInstance()->trail->getEdits());
    }

    public function testClearRequiresPost(): void
    {
        $controller = $this->controller(['getIsPost' => false]);

        $this->expectException(MethodNotAllowedHttpException::class);
        $controller->actionClear();
    }

    /**
     * Builds the controller against a request stubbed as a JSON CP request.
     */
    private function controller(array $requestMethods = []): SessionController
    {
        $this->tester->mockCraftMethods('request', $requestMethods + [
            'getIsCpRequest' => true,
            'getAcceptsJson' => true,
            'getIsOptions' => false,
            'getIsPost' => false,
            'getBodyParams' => [],
        ]);

        return new SessionController('session', Plugin::getInstance());
    }
}
