<?php
/**
 * snapshot plugin for Craft CMS 3.x
 *
 * Creates and restores a snapshot of the applications data
 *
 * @link      https://github.com/wsydney76
 * @copyright Copyright (c) 2019 wsydney76
 */

namespace wsydney76\snapshot;


use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\console\Application as ConsoleApplication;

use wsydney76\snapshot\services\SnapshotService;
use yii\base\Event;

/**
 * Class Snapshot
 *
 * @author    wsydney76
 * @package   Snapshot
 * @since     1.0.0
 *
 */
class Snapshot extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Snapshot
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app instanceof ConsoleApplication) {
            $this->setComponents([
                'snapshot' => SnapshotService::class
            ]);

            $this->controllerNamespace = 'wsydney76\snapshot\console\controllers';
        }

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
                'snapshot',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
