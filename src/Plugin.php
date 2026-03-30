<?php

namespace justinholtweb\puppy;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\base\Element;
use craft\events\ModelEvent;
use craft\web\View;
use justinholtweb\puppy\assetbundles\puppy\PuppyAsset;
use justinholtweb\puppy\services\Trail;
use yii\base\Event;

/**
 * Puppy — a lightweight CP companion that follows editors through their session.
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'trail' => Trail::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        if (!Craft::$app->getRequest()->getIsCpRequest() || Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        $this->_registerAssetBundle();
        $this->_registerElementSaveListeners();
    }

    /**
     * Register the Puppy asset bundle on every CP page load.
     */
    private function _registerAssetBundle(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function () {
                $view = Craft::$app->getView();
                $view->registerAssetBundle(PuppyAsset::class);

                // Pass the action URL and CSRF token to the frontend
                $actionUrl = '/actions/puppy/session';
                $view->registerJs(
                    'window.PuppyConfig = ' . json_encode([
                        'actionUrl' => $actionUrl,
                        'csrfTokenName' => Craft::$app->getConfig()->getGeneral()->csrfTokenName,
                        'csrfTokenValue' => Craft::$app->getRequest()->getCsrfToken(),
                        'cpUrl' => Craft::$app->getRequest()->getPathInfo(),
                    ]) . ';',
                    View::POS_HEAD
                );
            }
        );
    }

    /**
     * Listen for element save events and record them in the trail.
     */
    private function _registerElementSaveListeners(): void
    {
        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Element $element */
                $element = $event->sender;

                // Skip revisions and drafts being auto-saved
                if ($element->getIsRevision()) {
                    return;
                }

                $action = $event->isNew ? 'created' : 'saved';

                $this->trail->recordEdit($element, $action);
            }
        );
    }
}
