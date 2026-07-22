<?php

namespace justinholtweb\puppytests\unit;

use Craft;
use craft\test\TestCase;
use justinholtweb\puppy\Plugin;
use justinholtweb\puppy\services\Trail;
use UnitTester;

/**
 * Tests the plugin shell: installation, component wiring and config.
 */
class PluginTest extends TestCase
{
    protected UnitTester $tester;

    public function testCraftIsInstalled(): void
    {
        self::assertTrue(Craft::$app->getIsInstalled());
    }

    public function testPluginIsInstalledAndAvailable(): void
    {
        self::assertInstanceOf(Plugin::class, Plugin::getInstance());
    }

    public function testConfigRegistersTrailComponent(): void
    {
        $config = Plugin::config();

        self::assertArrayHasKey('components', $config);
        self::assertSame(Trail::class, $config['components']['trail']);
    }

    public function testTrailComponentIsInstantiable(): void
    {
        self::assertInstanceOf(Trail::class, Plugin::getInstance()->trail);
    }

    public function testTrailComponentIsASingleton(): void
    {
        self::assertSame(
            Plugin::getInstance()->trail,
            Plugin::getInstance()->trail,
        );
    }

    public function testSchemaVersionIsSet(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            Plugin::getInstance()->schemaVersion,
        );
    }

    public function testPluginHandleMatchesComposerConfig(): void
    {
        self::assertSame('puppy', Plugin::getInstance()->handle);
    }
}
