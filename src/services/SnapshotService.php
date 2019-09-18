<?php

namespace wsydney76\snapshot\services;

use Craft;
use craft\base\Component;
use craft\errors\ShellCommandException;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use craft\volumes\Local;
use function file_exists;
use const DIRECTORY_SEPARATOR;
use function get_class;
use function is_dir;
use function strtolower;
use yii\base\Exception;

class SnapshotService extends Component
{
    /**
     *
     * @return bool
     * @throws \yii\base\ErrorException
     */
    public function createSnapshot(string $snapshotDir = null): bool
    {

        try {
            if (!$snapshotDir) {
                $snapshotDir = Craft::parseEnv('@root') . DIRECTORY_SEPARATOR . '_snapshot_' . date('Ymd-his');
                $this->stdout('Using directory ' . $snapshotDir);
            }

            $snapshotBackupFile = $snapshotDir . DIRECTORY_SEPARATOR . 'snapshot.sql';

            if (!is_dir($snapshotDir)) {
                FileHelper::createDirectory($snapshotDir);
            } else {
                FileHelper::cycle($snapshotBackupFile, 4);
            }

            $this->stdout('Backing up database...');
            Craft::$app->db->backupTo($snapshotBackupFile);

            $volumes = Craft::$app->volumes->getAllVolumes();
            /** @var Local $volume */
            foreach ($volumes as $volume) {
                if (get_class($volume) == 'craft\\volumes\\Local') {
                    $source = $volume->getRootPath();
                    $dest = $snapshotDir . DIRECTORY_SEPARATOR . strtolower($volume->handle);

                    if (is_dir($source)) {
                        if (!is_dir($dest)) {
                            FileHelper::createDirectory($dest);
                        } else {
                            FileHelper::clearDirectory($dest);
                        }

                        $this->stdout("Copying {$source} to {$dest} ...");
                        FileHelper::copyDirectory($source, $dest);
                    }
                }
            }

            $composer_file = Craft::parseEnv('@root') . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($composer_file)) {
                copy ($composer_file, $snapshotDir . DIRECTORY_SEPARATOR . 'composer.json');
            }

            $composer_file = Craft::parseEnv('@root') . DIRECTORY_SEPARATOR . 'composer.lock';
            if (file_exists($composer_file)) {
                copy ($composer_file, $snapshotDir . DIRECTORY_SEPARATOR . 'composer.lock');
            }

        } catch (ShellCommandException $e) {
            $this->stdout('Error ' . $e->getMessage());
            Craft::error($e->getMessage(), 'snapshot');
            return false;
        } catch (Exception $e) {
            $this->stdout('Error ' . $e->getMessage());
            Craft::error($e->getMessage(), 'snapshot');
            return false;
        }

        Craft::info('Snapshot successfully created', 'snapshot');
        return true;
    }

    /**
     * @param string $snapshotDir
     * @param string $snapshotBackupFile
     * @return bool
     * @throws \Throwable
     */
    public function restoreSnapshot(string $snapshotDir, string $snapshotBackupFile): bool
    {
        try {
            $this->stdout('Restoring database ...');
            Craft::$app->db->restore($snapshotBackupFile);

            $volumes = Craft::$app->volumes->getAllVolumes();
            /** @var Local $volume */
            foreach ($volumes as $volume) {
                if (get_class($volume) == 'craft\\volumes\\Local') {

                    $source = $snapshotDir . DIRECTORY_SEPARATOR . strtolower($volume->handle);
                    $dest = $volume->getRootPath();

                    if (is_dir($source)) {
                        if (!is_dir($dest)) {
                            FileHelper::createDirectory($dest);
                        }

                        $this->stdout("Copying {$source} to {$dest} ...");
                        FileHelper::copyDirectory($source, $dest);
                    }
                }
            }

            if (Craft::$app->config->general->useProjectConfigFile) {
                $projectConfig = Craft::$app->getProjectConfig();
                $this->stdout('Rebuilding the project config from the current state ... ');

                $projectConfig->rebuild();
            }

            $this->stdout('Please manually copy composer.json and composer.lock from '. $snapshotDir . ' if needed.');

        } catch (ShellCommandException $e) {
            $this->stdout('Error ' . $e->getMessage());
            Craft::error($e->getMessage(), 'snapshot');
            return false;
        } catch (Exception $e) {
            $this->stdout('Error ' . $e->getMessage());
            Craft::error($e->getMessage(), 'snapshot');
            return false;
        }
        return true;
    }

    private function stdout($text)
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            Console::stdout($text . "\n");
        } else {
            Craft::info($text, 'snapshot');
        }
    }
}
