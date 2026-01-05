<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @since         5.0.0
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace BcDbMigrator\Command;

use BaserCore\Utility\BcFolder;
use BaserCore\Utility\BcZip;
use BcDbMigrator\Controller\Component\BcDbMigrator5Component;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Http\ServerRequest;

/**
 * BcDbMigratorCommand
 */
class BcDbMigratorCommand extends Command
{

    /**
     * buildOptionParser
     *
     * @param ConsoleOptionParser $parser
     * @return ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addArgument('path', [
            'help' => 'バックアップZipファイルのパス',
            'required' => true
        ]);
        return $parser;
    }

    /**
     * execute
     *
     * @param Arguments $args
     * @param ConsoleIo $io
     * @return int|void|null
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $path = $args->getArgument('path');

        if (!file_exists($path)) {
            $io->error('指定されたZipファイルが存在しません: ' . $path);
            return static::CODE_ERROR;
        }

        $tmpPath = TMP . 'dbmigrator' . DS;
        
        // クリーンアップ
        $folder = new BcFolder($tmpPath);
        $folder->delete();
        $folder->create(0777);

        // 解凍
        $io->out('Zipファイルを解凍しています...');
        $bcZip = new BcZip();
        if (!$bcZip->extract($path, $tmpPath)) {
            $io->error('Zipファイルの解凍に失敗しました。');
            return static::CODE_ERROR;
        }

        // フォルダ構造の正規化（Controllerロジックの移植）
        if (!$this->normalizeDirectory($tmpPath)) {
            $io->error('バックアップファイルに問題があります。バージョンが違う可能性があります。');
            return static::CODE_ERROR;
        }

        // ダミーコントローラーのセットアップ
        $request = new ServerRequest();
        $controller = new class($request) extends Controller {
            public $_tmpPath;
        };
        $controller->_tmpPath = $tmpPath;

        $registry = new ComponentRegistry($controller);
        $migrator = new BcDbMigrator5Component($registry);

        // コンポーネントの初期化
        // startupイベントを手動で発火させるか、直接呼ぶ
        $migrator->startup(new Event('Controller.startup', $controller));

        $io->out('マイグレーションを開始します...');
        // encodingはデフォルトでUTF-8と仮定
        if ($migrator->migrate('UTF-8')) {
            $io->success('マイグレーションが完了しました。');
            $newPassword = $migrator->getNewPassword();
            if (env('HASH_TYPE') === 'sha1') {
                $io->out('ユーザーのパスワードは以前のものを引き継いていますのでそのまま利用してください。');
            } elseif ($newPassword) {
                $io->out('すべてのユーザーのパスワードは、「' . $newPassword . '」にセットされています。');
            }

            // ZIP圧縮
            $io->out('変換ファイルを圧縮しています...');
            $version = str_replace(' ', '_', \BaserCore\Utility\BcUtil::getVersion());
            $distPath = TMP . 'baserbackup_' . $version . '_' . date('Ymd_His') . '.zip';
            $bcZip = new BcZip();
            $bcZip->create($tmpPath, $distPath);
            $io->success('変換ファイルを圧縮しました: ' . $distPath);

            // 作業フォルダの削除
            $folder = new BcFolder($tmpPath);
            $folder->delete();

            return static::CODE_SUCCESS;
        } else {
            $io->error('マイグレーションに失敗しました。ログを確認してください。');
            return static::CODE_ERROR;
        }
    }

    /**
     * ディレクトリ構成を正規化する
     * 
     * @param string $tmpPath
     * @return bool
     */
    protected function normalizeDirectory($tmpPath)
    {
        $folder = new BcFolder($tmpPath);
        $files = $folder->read();
        
        if (empty($files[0])) {
            return false;
        }

        $valid = false;
        $directFolder = '';
        foreach($files[0] as $file) {
            if ($file === 'plugin') {
                $valid = true;
            }
            $directFolder = $file;
        }

        if (!$valid) {
            $subFolderPath = $tmpPath . $directFolder . DS;
            if (!is_dir($subFolderPath)) {
                return false;
            }

            $folder = new BcFolder($subFolderPath);
            $files = $folder->read();
            if (empty($files[0])) {
                return false;
            }

            foreach($files[0] as $file) {
                $folder = new BcFolder();
                $folder->move($tmpPath . $file, ['from' => $subFolderPath . $file, 'chmod' => 0777]);
            }
            $folder->delete($subFolderPath);
        }
        return true;
    }
}
