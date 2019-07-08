<?php
/**
 * snapshot plugin for Craft CMS 3.x
 *
 * Creates and restores a snapshot of the applications data
 *
 * @link      https://github.com/wsydney76
 * @copyright Copyright (c) 2019 wsydney76
 */

namespace wsydney76\snapshot\console\controllers;

use wsydney76\snapshot\Snapshot;
use yii\console\Controller;
use yii\console\ExitCode;
use function is_dir;
use function is_file;
use const DIRECTORY_SEPARATOR;

/**
 * Snapshot Command
 *
 * @author    wsydney76
 * @package   Snapshot
 * @since     1.0.0
 */
class SnapshotController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Creates a snapshot of the current content in _snapshot
     * Works only for local volume types
     *
     * @param string =  The path to the snapshot directory Defaults to _snapshot .
     * @return bool
     *
     * Convention: the volume handle must match the root folder name,
     * e.g: handle: images   root: @webroot/images
     */
    public function actionCreate(string $snapshotDir = '')
    {
        if (!Snapshot::$plugin->snapshot->createSnapshot($snapshotDir)) {
            $this->stderr('Could not create snapshot');
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout('Snapshot created');
        return ExitCode::OK;
    }

    /**
     * Restores a snapshot of the current content in _snapshot
     * Works only for local volume types
     *
     * @param string|null The path to the snapshot directory.
     * @return bool
     *
     * Convention: the volume handle must match the root folder name,
     * e.g: handle: images   root: @webroot/images
     */
    public function actionRestore(string $snapshotDir = null)
    {
        if (!is_dir($snapshotDir)) {
            $this->stderr('Restore directory doesn\'t exist: ' . $snapshotDir);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $snapshotBackupFile = $snapshotDir . DIRECTORY_SEPARATOR . 'snapshot.sql';
        if (!is_file($snapshotBackupFile)) {
            $this->stderr('Restore database file doesn\'t exist: ' . $snapshotBackupFile);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!Snapshot::$plugin->snapshot->restoreSnapshot($snapshotDir, $snapshotBackupFile)) {
            $this->stderr('Could not restore snapshot');
        }
        $this->stdout('Snapshot restored');
        return ExitCode::OK;
    }


}
