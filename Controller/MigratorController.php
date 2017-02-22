<?php
/**
 * MigrationController
 */
class MigratorController extends AppController {
	
/**
 * コンポーネント
 * 
 * @var array
 * @access public
 */
	public $components = ['BcAuth', 'Cookie', 'BcAuthConfigure', 'BcManager'];

/**
 * 一時フォルダ
 * @var string
 */
	protected $_tmpPath = null;

/**
 * マイグレーター名
 *
 * @var null
 */
	public $migrator = null;

/**
 * モデル
 * @var array
 */
	public $uses = [];
	
/**
 * beforeFilter
 */
	public function beforeFilter() {
		parent::beforeFilter();
		$this->_tmpPath = TMP . 'dbmigrator' . DS;
		$this->migrator = 'BcDbMigrator' . $this->getMajorVersion();
		$migratorClass = $this->migrator . 'Component';
		App::uses($migratorClass, 'BcDbMigrator.Controller/Component');
		if(class_exists($migratorClass)) {
			$this->{$this->migrator} = $this->Components->load('BcDbMigrator.' . $this->migrator);
		} else {
			$this->setMessage('このプラグインは、このバージョンのbaserCMSに対応していません。', true);
		}
	}
	
/**
 * マイグレーション画面 
 */
	public function admin_index() {
		$this->pageTitle = 'baserCMS DBマイグレーター';
		if($this->request->data) {
			set_time_limit(0);
			if($this->_migrate($this->request->data)) {
				$version = str_replace(' ', '_', $this->getBaserVersion());
				$this->Session->write('BcDbMigrator.file', 'baserbackup_' . $version . '_' . date('Ymd_His'));
				$this->setMessage('バックアップデータのマイグレーションが完了しました。ダウンロードボタンよりダウンロードしてください。');
				$this->redirect('index');
			} else {
				$this->setMessage('バックアップデータのマイグレーションが失敗しました。バックアップデータに問題があります。', true);
				$this->redirect('index');
			}
		}
		if($this->Session->read('BcDbMigrator.downloaded')) {
			$this->Session->delete('BcDbMigrator.file');
			$this->Session->delete('BcDbMigrator.downloaded');
			$Folder = new Folder($this->_tmpPath);
			$Folder->delete();
		}
		
		$message = $this->{$this->migrator}->getMessage();
		if(!empty($message[0])) {
			$this->set('noticeMessage', $message);	
		}
	}

/**
 * マイグレーション実行
 * 
 * @param array $data リクエストデータ
 * @return bool
 */
	protected function _migrate ($data) {
		
		App::uses('Simplezip', 'Vendor');
		
		if (empty($data['Migrator']['backup']['tmp_name'])) {
			return false;
		}
		
		// アップロードファイルを一時フォルダに解凍
		if(!$this->_unzipUploadFileToTmp($data)) {
			return false;
		}
		
		if(!$this->{$this->migrator}->migrate($data['Migrator']['encoding'])) {
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
	public function _unzipUploadFileToTmp($data) {
		$Folder = new Folder();
		$Folder->delete($this->_tmpPath);
		$Folder->create($this->_tmpPath, 0777);

		$targetPath = $this->_tmpPath . $data['Migrator']['backup']['name'];
		if (!move_uploaded_file($data['Migrator']['backup']['tmp_name'], $targetPath)) {
			return false;
		}

		// ZIPファイルを解凍する
		$Simplezip = new Simplezip();
		if (!$Simplezip->unzip($targetPath, $this->_tmpPath)) {
			return false;
		}

		@unlink($targetPath);
		return true;
	}

/**
 * ダウンロード 
 */
	public function admin_download() {
		$fileName = $this->Session->read('BcDbMigrator.file');
		
		if(!$fileName || !is_dir($this->_tmpPath)) {
			$this->notFound();
		}
		
		App::uses('Simplezip', 'Vendor');
		// ZIP圧縮
		$Simplezip = new Simplezip();
		$Simplezip->addFolder($this->_tmpPath);
		
		// ダウンロード
		$Simplezip->download($fileName);

		$Folder = new Folder();
		$Folder->delete($this->_tmpPath);
		
		$this->Session->write('BcDbMigrator.downloaded', true);
	}

/**
 * baserCMSのメジャーバージョンを取得
 *
 * @return string
 */
	public function getMajorVersion() {
		return preg_replace('/([0-9])\..+/', "$1", getVersion());
	}

}
