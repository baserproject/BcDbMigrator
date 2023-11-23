<?php
namespace BcDbMigrator\Controller\Component;
/**
 * include files
 */

/**
 * BcDbMigrator4Component
 */
class BcDbMigrator3Component extends BcDbMigratorComponent implements BcDbMigratorInterface {
	
/**
 * メッセージ
 * @var array
 */
	public $message = [
		'baserCMS 2.1.0 以上のバックアップデータの basrCMS 3.0.0 への変換のみサポート',
	];
	
/**
 * メッセージを取得する
 * 
 * @return array
 */
	public function getMessage() {
		return $this->message;
	}

/**
 * スキーマのマイグレーションを実行
 */
	public function migrateSchema() {
		$tmpPath = $this->_Controller->_tmpPath;
		$sourcePath = BASER_CONFIGS . 'sql';
		$Folder = new \Cake\Filesystem\Folder($sourcePath);
		$files = $Folder->read(true, true, true);
		foreach($files[1] as $file) {
			copy($file, $tmpPath . 'baser' . DS . basename($file));
		}
		$plugins = array('Blog', 'Mail', 'Feed');
		foreach($plugins as $plugin) {
			$sourcePath = BASER_PLUGINS . $plugin . DS . 'Config' . DS . 'sql';
			$Folder = new \Cake\Filesystem\Folder($sourcePath);
			$files = $Folder->read(true, true, true);
			foreach($files[1] as $file) {
				if(basename($file) != 'contact_messages.php' && basename($file) != 'messages.php')
					copy($file, $tmpPath . 'plugin' . DS . basename($file));
			}
		}
		@unlink($tmpPath . 'baser' . DS . 'global_menus.php');
	}
	
/**
 * データのマイグレーションを実行する
 */
	public function migrateData() {
		$tmpPath = $this->_Controller->_tmpPath;

		// menus
		@rename($tmpPath . 'baser' . DS . 'global_menus.csv', $tmpPath . 'baser' . DS . 'menus.csv');

		// theme_configs
		copy(BASER_CONFIGS . 'data' . DS . 'default' . DS . 'theme_configs.csv', $tmpPath . 'baser' . DS . 'theme_configs.csv');

		// site_configs.csv
		$File = new \Cake\Filesystem\File($tmpPath . DS . 'baser' . DS . 'site_configs.csv');
		$data = $File->read();
		$data = preg_replace('/"version","2\.1\.[0-9]"/', '"version","3.0.0"', $data);
		$data .= '"","editor","BcCkeditor","",""';
		$File->write($data);
		$File->close();

		// pages.csv
		$File = new \Cake\Filesystem\File($tmpPath . DS . 'baser' . DS . 'pages.csv');
		$data = $File->read();
		$data = str_replace('$bcBaser', '$this->BcBaser', $data);
		$data = preg_replace("/src=\"\"\/themed\//is", 'src=""/theme/', $data);
		$File->write($data);
		$File->close();

		// plugins.csv
		$File = new \Cake\Filesystem\File($tmpPath . DS . 'baser' . DS . 'plugins.csv');
		$data = $File->read();
		$data = preg_replace("/\n\"([0-9]+)\",\"([a-zA-Z_\-]+)\",\"(.*?)\",\"([0-9\.]+)\",\"([01]*?)\",\"([01]*?)\",\"(.*?)\",\"(.*?)\"/is",
			"\n\"$1\",\"$2\",\"$3\",\"$4\",\"0\",\"$6\",\"$7\",\"$8\"", $data);
		$data = preg_replace("/\"blog\",\"(.*?)\",\"([0-9\.]+)\"/is", "\"blog\",\"$1\",\"3.0.0\"", $data);
		$data = preg_replace("/\"mail\",\"(.*?)\",\"([0-9\.]+)\"/is", "\"mail\",\"$1\",\"3.0.0\"", $data);
		$data = preg_replace("/\"feed\",\"(.*?)\",\"([0-9\.]+)\"/is", "\"feed\",\"$1\",\"3.0.0\"", $data);
		$File->write($data);
		$File->close();
	}
	
}