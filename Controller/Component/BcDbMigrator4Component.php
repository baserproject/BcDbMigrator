<?php
/**
 * include files
 */
App::uses('Component', 'Controller');
App::uses('BcDbMigratorInterface', 'BcDbMigrator.Controller/Component');

/**
 * BcDbMigrator4Component
 */
class BcDbMigrator4Component extends Component implements BcDbMigratorInterface {

/**
 * コントローラー
 * 
 * @var
 */
	protected $_Controller;
	
/**
 * メッセージ
 * @var array
 */
	public $message = [
		'baserCMS 3.0.9 以上のバックアップデータの basrCMS 4.0.2 への変換のみサポートしています。',
	];
	
/**
 * initialize
 * 
 * @param \Controller $controller
 */
	public function initialize(Controller $controller) {
		parent::initialize($controller);
		$this->_Controller = $controller;
	}
	
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
		return true;
	}
	
/**
 * データのマイグレーションを実行する
 */
	public function migrateData() {
		return true;
	}
	
}