<?php

namespace justinholtweb\puppytests\unit;

use Codeception\Stub;
use Craft;
use craft\elements\User;
use craft\test\TestCase;
use craft\web\View;
use justinholtweb\puppy\assetbundles\puppy\PuppyAsset;
use justinholtweb\puppy\Plugin;
use ReflectionObject;
use UnitTester;
use yii\base\Event;

/**
 * Tests the asset bundle and the PuppyConfig payload handed to the frontend.
 */
class AssetBundleConfigTest extends TestCase
{
    protected UnitTester $tester;

    protected function _after(): void
    {
        Event::offAll();
        Craft::$app->getUser()->setIdentity(null);

        parent::_after();
    }

    public function testAssetBundleRegistersItsFiles(): void
    {
        $bundle = new PuppyAsset();

        self::assertSame(['js/puppy.js'], $bundle->js);
        self::assertSame(['css/puppy.css'], $bundle->css);
    }

    public function testAssetBundleSourcePathResolves(): void
    {
        // AssetBundle::init() resolves the alias, so this asserts the alias is registered.
        $path = (new PuppyAsset())->sourcePath;

        self::assertDirectoryExists($path);
        self::assertFileExists($path . '/js/puppy.js');
        self::assertFileExists($path . '/css/puppy.css');
    }

    public function testAssetBundleDependsOnTheCpBundle(): void
    {
        self::assertContains(\craft\web\assets\cp\CpAsset::class, (new PuppyAsset())->depends);
    }

    public function testConfigIsNotRegisteredForGuests(): void
    {
        $this->registerListener();

        Event::trigger(View::class, View::EVENT_BEFORE_RENDER_TEMPLATE);

        self::assertSame('', $this->registeredConfigJs());
    }

    public function testConfigIsRegisteredForLoggedInUsers(): void
    {
        $this->login();
        $this->registerListener();

        Event::trigger(View::class, View::EVENT_BEFORE_RENDER_TEMPLATE);

        $config = $this->decodeConfig();

        self::assertSame(
            ['actionUrl', 'csrfTokenName', 'csrfTokenValue', 'cpUrl'],
            array_keys($config),
        );
        self::assertSame(
            Craft::$app->getConfig()->getGeneral()->csrfTokenName,
            $config['csrfTokenName'],
        );
        self::assertNotSame('', $config['csrfTokenValue']);
    }

    /**
     * The action URL must be derived from Craft's config rather than hardcoded,
     * so it keeps working when `actionTrigger` is customized.
     */
    public function testActionUrlHonoursACustomActionTrigger(): void
    {
        Craft::$app->getConfig()->getGeneral()->actionTrigger = 'go';

        $this->login();
        $this->registerListener();

        Event::trigger(View::class, View::EVENT_BEFORE_RENDER_TEMPLATE);

        $actionUrl = $this->decodeConfig()['actionUrl'];

        self::assertStringContainsString('go/puppy/session', $actionUrl);
        self::assertStringNotContainsString('actions/puppy/session', $actionUrl);
    }

    private function decodeConfig(): array
    {
        $js = $this->registeredConfigJs();

        self::assertNotSame('', $js, 'No PuppyConfig was registered.');

        $json = rtrim(str_replace('window.PuppyConfig = ', '', $js), ';');

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function registeredConfigJs(): string
    {
        foreach (Craft::$app->getView()->js[View::POS_HEAD] ?? [] as $js) {
            if (str_starts_with($js, 'window.PuppyConfig')) {
                return $js;
            }
        }

        return '';
    }

    /**
     * Registers the plugin's view listener the same way `Plugin::init()` does.
     * The plugin skips this during a unit test bootstrap because the test
     * request isn't a CP request.
     */
    private function registerListener(): void
    {
        $plugin = Plugin::getInstance();
        $method = (new ReflectionObject($plugin))->getMethod('_registerAssetBundle');
        $method->setAccessible(true);
        $method->invoke($plugin);
    }

    private function login(): void
    {
        Craft::$app->getUser()->setIdentity(Stub::make(User::class, [
            'id' => 1,
            'username' => 'tester',
            'getAuthKey' => 'test-auth-key',
        ]));
    }
}
