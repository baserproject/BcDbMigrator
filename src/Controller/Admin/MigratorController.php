<?php

namespace BcDbMigrator\Controller\Admin;

/**
 * MigrationController
 */
class MigratorController extends \BaserCore\Controller\Admin\BcAdminAppController
{
    /**
     * マイグレーション画面 
     */
    public function index()
    {
        $this->setTitle('baserCMS DBマイグレーター');
        if ($this->getRequest()->getData()) {
            if ($this->_migrate($this->getRequest()->getData())) {
                $version = str_replace(' ', '_', $this->getBaserVersion());
                $this->Session->write('BcDbMigrator.file', 'baserbackup_' . $version . '_' . date('Ymd_His'));
                $this->BcMessage->setInfo('バックアップデータのマイグレーションが完了しました。ダウンロードボタンよりダウンロードしてください。');
                $this->redirect('index');
            } else {
                $this->BcMessage->setWarning('バックアップデータのマイグレーションが失敗しました。バックアップデータに問題があります。ログファイルを確認してください。');
                $this->redirect('index');
            }
        }
        if ($this->Session->read('BcDbMigrator.downloaded')) {
            $this->Session->delete('BcDbMigrator.file');
            $this->Session->delete('BcDbMigrator.downloaded');
            $Folder = new \Cake\Filesystem\Folder($this->_tmpPath);
            $Folder->delete();
        }
        $message = $this->{$this->migrator}->getMessage();
        if (!empty($message[0])) {
            $this->set('noticeMessage', $message);
        }
    }
    /**
     * ダウンロード 
     */
    public function download()
    {
        $this->autoRender = false;
        $fileName = $this->Session->read('BcDbMigrator.file');
        if (!$fileName || !is_dir($this->_tmpPath)) {
            $this->notFound();
        }
        // ZIP圧縮
        $Simplezip = new Simplezip();
        $Simplezip->addFolder($this->_tmpPath);
        // ダウンロード
        $Simplezip->download($fileName);
        $Folder = new \Cake\Filesystem\Folder();
        $Folder->delete($this->_tmpPath);
        $this->Session->write('BcDbMigrator.downloaded', true);
    }
}