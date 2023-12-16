<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @since         5.0.7
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace BcDbMigrator\Controller\Admin;

use BaserCore\Utility\BcUtil;
use BaserCore\Utility\BcZip;
use Cake\Filesystem\File;
use Psr\Log\LogLevel;

/**
 * MigrationController
 */
class MigratorController extends \BaserCore\Controller\Admin\BcAdminAppController
{

    /**
     * beforeFilter
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->_tmpPath = TMP . 'dbmigrator' . DS;
        $this->migrator = 'BcDbMigrator' . $this->getMajorVersion();
        $migratorClass = '\\BcDbMigrator\\Controller\\Component\\' . $this->migrator . 'Component';
        if (class_exists($migratorClass)) {
            $this->loadComponent('BcDbMigrator.' . $this->migrator);
        } else {
            $this->BcMessage->setWarning('このプラグインは、このバージョンのbaserCMSに対応していません。');
        }
    }

    /**
     * マイグレーション画面
     */
    public function index()
    {
        $this->setTitle('baserCMS DBマイグレーター');
        if ($this->getRequest()->is('post')) {
            if ($this->_migrate($this->getRequest()->getData())) {
                $version = str_replace(' ', '_', BcUtil::getVersion());
                $this->getRequest()->getSession()->write('BcDbMigrator.file', 'baserbackup_' . $version . '_' . date('Ymd_His'));
                $this->getRequest()->getSession()->delete('BcDbMigrator.downloaded');
                $this->BcMessage->setInfo('バックアップデータのマイグレーションが完了しました。ダウンロードボタンよりダウンロードしてください。');
                $password = $this->{$this->migrator}->getNewPassword();
                if ($password) {
                    $this->BcMessage->setInfo('残念ながらパスワードの移行はできません。すべてのユーザーのパスワードは、「' . $password . '」にセットされています。ログイン後のパスワードの変更をお願いします。');
                }
                $this->redirect(['action' => 'index']);
            } else {
                $this->BcMessage->setError('バックアップデータのマイグレーションが失敗しました。バックアップデータに問題があります。ログファイルを確認してください。');
                $this->redirect(['action' => 'index']);
            }
        }
        if ($this->getRequest()->getSession()->read('BcDbMigrator.downloaded')) {
            $this->getRequest()->getSession()->delete('BcDbMigrator.file');
            $this->getRequest()->getSession()->delete('BcDbMigrator.downloaded');
            $Folder = new \Cake\Filesystem\Folder($this->_tmpPath);
            $Folder->delete();
        }

        if (isset($this->{$this->migrator})) {
            $message = $this->{$this->migrator}->getMessage();
        } else {
            $message = [];
        }
        if (!empty($message[0])) {
            $this->set('noticeMessage', $message);
        }
        $file = new File(LOGS . 'migrate_db.log', true);
        $this->set('log', $file->read());
    }

    /**
     * ダウンロード
     */
    public function download()
    {
        $this->autoRender = false;
        $fileName = $this->getRequest()->getSession()->read('BcDbMigrator.file');
        if (!$fileName || !is_dir($this->_tmpPath)) {
            $this->notFound();
        }
        // ZIP圧縮
        $distPath = TMP . 'baserbackup_' . BcUtil::getVersion() . '_' . date('Ymd_His') . '.zip';

        $bcZip = new BcZip();
        $bcZip->create($this->_tmpPath, $distPath);
        header("Cache-Control: no-store");
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=" . basename($distPath) . ";");
        header("Content-Length: " . filesize($distPath));
        while(ob_get_level()) {
            ob_end_clean();
        }
        echo readfile($distPath);

        // ダウンロード
        $Folder = new \Cake\Filesystem\Folder();
        $Folder->delete($this->_tmpPath);
        $this->getRequest()->getSession()->write('BcDbMigrator.downloaded', true);
    }

    /**
     * マイグレーション実行
     *
     * @param array $data リクエストデータ
     * @return bool
     */
    protected function _migrate($data)
    {
        if (LOGS . 'migrate_db.log') unlink(LOGS . 'migrate_db.log');
        if (empty($data['backup']['tmp_name'])) {
            return false;
        }
        // アップロードファイルを一時フォルダに解凍
        if (!$this->_unzipUploadFileToTmp($data)) {
            return false;
        }
        if (!$this->{$this->migrator}->migrate($data['encoding'])) {
            return false;
        }
        return true;
    }

    /**
     * アップロードしたファイルを一時フォルダに解凍する
     *
     * @param array $data リクエストデータ
     * @return bool
     */
    public function _unzipUploadFileToTmp($data)
    {
        $Folder = new \Cake\Filesystem\Folder();
        $Folder->delete($this->_tmpPath);
        $Folder->create($this->_tmpPath, 0777);
        $targetPath = $this->_tmpPath . $data['backup']['name'];
        if (!move_uploaded_file($data['backup']['tmp_name'], $targetPath)) {
            return false;
        }
        // ZIPファイルを解凍する
        $BcZip = new \BaserCore\Utility\BcZip();
        if (!$BcZip->extract($targetPath, $this->_tmpPath)) {
            return false;
        }
        $Folder = new \Cake\Filesystem\Folder($this->_tmpPath);
        $files = $Folder->read();
        if (empty($files[0])) {
            $this->log('バックアップファイルに問題があります。バージョンが違う可能性があります。', LogLevel::ERROR, 'migrate_db');
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
            $Folder = new \Cake\Filesystem\Folder($this->_tmpPath . DS . $directFolder);
            $files = $Folder->read();
            if (empty($files[0])) {
                $this->log('バックアップファイルに問題があります。バージョンが違う可能性があります。', LogLevel::ERROR, 'migrate_db');
                return false;
            }
            foreach($files[0] as $file) {
                $Folder = new \Cake\Filesystem\Folder();
                $Folder->move($this->_tmpPath . DS . $file, ['from' => $this->_tmpPath . DS . $directFolder . DS . $file, 'chmod' => 777]);
            }
            $Folder->delete($this->_tmpPath . DS . $directFolder);
        }
        @unlink($targetPath);
        return true;
    }

    /**
     * baserCMSのメジャーバージョンを取得
     *
     * @return string
     */
    public function getMajorVersion()
    {
        return preg_replace('/([0-9])\\..+/', "\$1", \BaserCore\Utility\BcUtil::getVersion());
    }

}
