<?php

namespace justinholtweb\puppy\assetbundles\puppy;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Asset bundle that loads Puppy's JS and CSS into the Craft CP.
 */
class PuppyAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@justinholtweb/puppy/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/puppy.js',
        ];

        $this->css = [
            'css/puppy.css',
        ];

        parent::init();
    }
}
