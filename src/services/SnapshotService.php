<?php

namespace wsydney76\snapshot\services;

use Craft;
use craft\base\Component;
use craft\errors\ShellCommandException;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use craft\volumes\Local;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use yii\base\ErrorException;
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
                $snapshotDir = Craft::parseEnv('@root') . DIRECTORY_SEPARATOR . 'snapshot_' . date('Ymd-his');
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
                        $gitignoreFile = $dest . DIRECTORY_SEPARATOR . '.gitignore';
                        if (file_exists($gitignoreFile)) {
                            unlink($gitignoreFile);
                            $this->stdout('Deleted .gitignore');
                        }
                    }
                }
            }

            // Copy project config
            $source = Craft::parseEnv('@root/config/project');
            $dest = $snapshotDir . DIRECTORY_SEPARATOR . 'project';
            if (!is_dir($dest)) {
                FileHelper::createDirectory($dest);
            } else {
                FileHelper::clearDirectory($dest);
            }
            $this->stdout("Copying project config ...");
            FileHelper::copyDirectory($source, $dest);

            $this->stdout("Start deleting image transforms");
            $dirs = $this->_getTransFormDirs($snapshotDir);

            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    try {
                        FileHelper::clearDirectory($dir);
                        rmdir($dir);
                    } catch (ErrorException $e) {
                        $this->stdout("Error deleting " . $dir . ": " . $e->getMessage());
                    }
                }
            }
            $this->stdout("Deleted image transforms");

            $composer_file = Craft::parseEnv('@root') . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($composer_file)) {
                copy($composer_file, $snapshotDir . DIRECTORY_SEPARATOR . 'composer.json');
            }

            $composer_file = Craft::parseEnv('@root') . DIRECTORY_SEPARATOR . 'composer.lock';
            if (file_exists($composer_file)) {
                copy($composer_file, $snapshotDir . DIRECTORY_SEPARATOR . 'composer.lock');
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

            $this->stdout('Please manually copy composer.json and composer.lock from ' . $snapshotDir . ' if needed.');
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

    /**
     * @param $text
     */
    private function stdout($text)
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            Console::stdout($text . "\n");
        } else {
            Craft::info($text, 'snapshot');
        }
    }

    /**
     * @param $path
     * @return array
     */
    private function _getTransFormDirs($path): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        $dirs = [];
        foreach ($rii as $dir) {
            if ($dir->isDir()) {
                if (strpos($dir->getPathname(), DIRECTORY_SEPARATOR . '_') &&
                    !strpos($dir->getPathname(), '..')) {
                    $dirs[] = $dir->getPathname();
                }
            }
        }

        return $dirs;
    }
}
