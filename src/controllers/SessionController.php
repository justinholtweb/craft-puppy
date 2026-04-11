<?php

namespace justinholtweb\puppy\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\puppy\Plugin;
use yii\web\Response;

/**
 * Session controller — handles trail data endpoints for the Puppy frontend.
 */
class SessionController extends Controller
{
    /**
     * All actions require a logged-in CP user.
     */
    protected array|int|bool $allowAnonymous = false;

    /**
     * GET /actions/puppy/session/get-trail
     *
     * Returns the current session trail and edits.
     */
    public function actionGetTrail(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();

        $data = Plugin::getInstance()->trail->getSessionData();

        return $this->asJson($data);
    }

    /**
     * POST /actions/puppy/session/record-visit
     *
     * Records a page visit from the frontend.
     */
    public function actionRecordVisit(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $url = $request->getRequiredBodyParam('url');
        $label = $request->getRequiredBodyParam('label');
        $type = $request->getBodyParam('type', 'route');
        $elementId = $request->getBodyParam('elementId');
        $context = $request->getBodyParam('context');

        Plugin::getInstance()->trail->recordVisit(
            $url,
            $label,
            $type,
            $elementId ? (int)$elementId : null,
            $context,
        );

        return $this->asJson(['success' => true]);
    }

    /**
     * POST /actions/puppy/session/clear
     *
     * Clears the current session trail.
     */
    public function actionClear(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        Plugin::getInstance()->trail->clearSession();

        return $this->asJson(['success' => true]);
    }
}
