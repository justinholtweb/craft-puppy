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
 *
 * @property-read Trail $trail
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

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || !$request->getIsCpRequest()) {
            return;
        }

        $this->_registerAssetBundle();
        $this->_registerElementSaveListeners();
    }

    /**
     * Register the Puppy asset bundle on CP page loads for authenticated users only.
     */
    private function _registerAssetBundle(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function() {
                // Skip login, forgot-password, set-password, and any other pre-auth CP pages
                if (Craft::$app->getUser()->getIsGuest()) {
                    return;
                }

                $view = Craft::$app->getView();
                $view->registerAssetBundle(PuppyAsset::class);

                $view->registerJs(
                    'window.PuppyConfig = ' . json_encode([
                        'actionUrl' => '/actions/puppy/session',
                        'csrfTokenName' => Craft::$app->getConfig()->getGeneral()->csrfTokenName,
                        'csrfTokenValue' => Craft::$app->getRequest()->getCsrfToken(),
                        'cpUrl' => Craft::$app->getRequest()->getPathInfo(),
                    ]) . ';',
                    View::POS_HEAD,
                );
            },
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
            function(ModelEvent $event) {
                // Only record edits for logged-in CP users
                if (Craft::$app->getUser()->getIsGuest()) {
                    return;
                }

                /** @var Element $element */
                $element = $event->sender;

                // Skip revisions
                if ($element->getIsRevision()) {
                    return;
                }

                $action = $event->isNew ? 'created' : 'saved';

                $this->trail->recordEdit($element, $action);
            },
        );
    }
}
