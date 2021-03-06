<?php
/**
 * Remarry Twig Filter plugin for Craft CMS 3.x
 *
 * Replaces the space character with a non-breaking space between the last to words of the given content.
 *
 * @link      http://www.belniakmedia.com
 * @copyright Copyright (c) 2017 Belniak Media Inc.
 */

namespace belniakmedia\remarrytwigfilter;

use belniakmedia\remarrytwigfilter\twigextensions\RemarryTwigFilterTwigExtension;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;

use yii\base\Event;

/**
 * Class RemarryTwigFilter
 *
 * @author    Belniak Media Inc.
 * @package   RemarryTwigFilter
 * @since     1.0.0
 *
 */
class RemarryTwigFilter extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var RemarryTwigFilter
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Craft::$app->view->registerTwigExtension(new RemarryTwigFilterTwigExtension());

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'remarry-twig-filter',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
