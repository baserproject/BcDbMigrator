<?php

namespace BcDbMigrator\Controller\Admin;

use BaserCore\Utility\BcUtil;

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
        $migratorClass = $this->migrator . 'Component';
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
                $this->BcMessage->setInfo('バックアップデータのマイグレーションが完了しました。ダウンロードボタンよりダウンロードしてください。');
                $this->redirect('index');
            } else {
                $this->BcMessage->setWarning('バックアップデータのマイグレーションが失敗しました。バックアップデータに問題があります。ログファイルを確認してください。');
                $this->redirect('index');
            }
        }
        if ($this->getRequest()->getSession()->read('BcDbMigrator.downloaded')) {
            $this->getRequest()->getSession()->delete('BcDbMigrator.file');
            $this->getRequest()->getSession()->delete('BcDbMigrator.downloaded');
            $Folder = new \Cake\Filesystem\Folder($this->_tmpPath);
            $Folder->delete();
        }
        
        if(isset($this->{$this->migrator})) {
			$message = $this->{$this->migrator}->getMessage();
		} else {
			$message = [];
		}
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
        $fileName = $this->getRequest()->getSession()->read('BcDbMigrator.file');
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
        if (empty($data['Migrator']['backup']['tmp_name'])) {
            return false;
        }
        // アップロードファイルを一時フォルダに解凍
        if (!$this->_unzipUploadFileToTmp($data)) {
            return false;
        }
        if (!$this->{$this->migrator}->migrate($data['Migrator']['encoding'])) {
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
        $targetPath = $this->_tmpPath . $data['Migrator']['backup']['name'];
        if (!move_uploaded_file($data['Migrator']['backup']['tmp_name'], $targetPath)) {
            return false;
        }
        // ZIPファイルを解凍する
        $BcZip = new \BaserCore\Utility\BcZip();
        if (!$BcZip->extract($targetPath, $this->_tmpPath)) {
            return false;
        }
        //		$Simplezip = new Simplezip();
        //		if (!$Simplezip->unzip($targetPath, $this->_tmpPath)) {
        //			return false;
        //		}
        $Folder = new \Cake\Filesystem\Folder($this->_tmpPath);
        $files = $Folder->read();
        if (empty($files[0])) {
            return false;
        }
        $valid = false;
        $directFolder = '';
        foreach ($files[0] as $file) {
            if ($file === 'plugin') {
                $valid = true;
            }
            $directFolder = $file;
        }
        if (!$valid) {
            $Folder = new \Cake\Filesystem\Folder($this->_tmpPath . DS . $directFolder);
            $files = $Folder->read();
            if (empty($files[0])) {
                return false;
            }
            foreach ($files[0] as $file) {
                $Folder = new \Cake\Filesystem\Folder();
                $Folder->move(['from' => $this->_tmpPath . DS . $directFolder . DS . $file, 'to' => $this->_tmpPath . DS . $file, 'chmod' => 777]);
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
