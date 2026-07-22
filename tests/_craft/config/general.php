<?php

use craft\helpers\App;

return [
    'devMode' => true,
    // Craft derives `Request::$cookieValidationKey` from this, which the CSRF
    // token in Puppy's frontend config depends on.
    'securityKey' => App::env('CRAFT_SECURITY_KEY') ?: 'puppy-test-security-key',
];
