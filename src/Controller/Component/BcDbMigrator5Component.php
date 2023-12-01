<?php

namespace BcDbMigrator\Controller\Component;

use BaserCore\Utility\BcUtil;
use CakephpFixtureFactories\Error\PersistenceException;

/**
 * include files
 */

/**
 * BcDbMigrator4Component
 */
class BcDbMigrator5Component extends BcDbMigratorComponent implements BcDbMigratorInterface
{
	
	/**
	 * メッセージ
	 * @var array
	 */
	public $message = [
		'baserCMS 3.0.9 以上のバックアップデータの basrCMS 4系 への変換のみサポートしています。<br>また、プラグインは無効化された状態でデータが作成されますので、バックアップ復旧後、有効化する必要がありますのでご注意ください。',
	];
	
	/**
	 * 親IDマッピング
	 *
	 * @var array
	 */
	protected $_parentIdMap = [];
	
	/**
	 * サイトIDマッピング
	 * @var array 
	 */
	private $_siteIdMap = [];
	
	/**
	 * メッセージを取得する
	 *
	 * @return array
	 */
	public function getMessage()
	{
		return $this->message;
	}
	
	/**
	 * スキーマのマイグレーションを実行
	 */
	public function migrateSchema()
	{
		// メッセージテーブルの生成
//		$this->_convertMessageSchema();
		// 不要なスキーマを削除
		$this->deleteSchema(true, 'Messages');
		$this->deleteSchema(true, 'MailMessages');
		// 新しいスキーマを一時フォルダに保存
		$this->writeNewSchema();
		return true;
	}
	
	/**
	 * データのマイグレーションを実行する
	 */
	public function migrateData()
	{
		$result = true;
		$this->_resetNewTable();
		// サイト名取得
		$siteName = $this->_getSiteName();
		
		// サイト
		$this->_updateSite();
		
		// トップフォルダ生成
//		$this->_addTopFolder($siteName);
		// ユーザーグループ
		if (!$this->_updateUserGroup()) $result = false;
//		// ページカテゴリ
//		if (!$this->_updatePageCategory($siteName)) $result = false;
		// コンテンツ
		if (!$this->_updateContent()) $result = false;
//		// ページ
		if (!$this->_updatePage()) $result = false;
//		// ブログコンテンツ
		if (!$this->_updateBlogContent()) $result = false;
//		// ブログ記事
//		if (!$this->_updateBlogPost()) $result = false;
//		// メールコンテンツ
//		if (!$this->_updateMailContent()) $result = false;
//		// プラグイン
//		if (!$this->_updatePlugin()) $result = false;
//		// サイト設定
//		if (!$this->_updateSiteConfig()) $result = false;
//		// メッセージテーブル
//		$this->_convertMessageData();
//		// CSVを出力する
//		$this->_writeNewCsv();
//		// 不要なCSVを削除
//		$this->_deleteCsv();
//		// フォルダ名変更
//		$Folder = new \Cake\Filesystem\Folder();
//		$Folder->move($this->tmpPath . 'core', [
//			'from' => $this->tmpPath . 'baser'
//		]);
		return $result;
	}
	
	/**
	 * 不要なCSVを削除
	 */
	protected function _deleteCsv()
	{
		$this->deleteCsv(false, 'menus');
		$this->deleteCsv(false, 'global_menus');
		$this->deleteCsv(false, 'page_categories');
		$this->deleteCsv(false, 'plugin_contents');
		$this->deleteCsv(true, 'messages');
	}
	
	/**
	 * 新しいデータのCSVを出力する
	 */
	protected function _writeNewCsv()
	{
		$this->writeCsv(false, 'pages');
		$this->writeCsv(false, 'content_folders');
		$this->writeCsv(false, 'contents');
		$this->writeCsv(false, 'sites');
		$this->writeCsv(false, 'user_groups');
		$this->writeCsv(false, 'site_configs');
		$this->writeCsv(false, 'content_links');
		$this->writeCsv(false, 'plugins');
		$this->writeCsv(true, 'blog_contents');
		$this->writeCsv(true, 'mail_contents');
	}
	
	/**
	 * 新テーブルをリセットする
	 */
	protected function _resetNewTable()
	{
		$this->dbService->truncate('contents', $this->newDbConfigKeyName);
		$this->dbService->truncate('content_folders', $this->newDbConfigKeyName);
		$this->dbService->truncate('user_groups', $this->newDbConfigKeyName);
		$this->dbService->truncate('sites', $this->newDbConfigKeyName);
		$this->dbService->truncate('blog_contents', $this->newDbConfigKeyName);
		$this->dbService->truncate('mail_contents', $this->newDbConfigKeyName);
		$this->dbService->truncate('pages', $this->newDbConfigKeyName);
		$this->dbService->truncate('site_configs', $this->newDbConfigKeyName);
		$this->dbService->truncate('plugins', $this->newDbConfigKeyName);
		$this->dbService->truncate('blog_posts', $this->newDbConfigKeyName);
	}
	
	/**
	 * サイト名を取得する
	 *
	 * @return string
	 */
	protected function _getSiteName()
	{
		$siteConfigs = $this->readCsv(false, 'site_configs');
		$siteName = '';
		foreach($siteConfigs as $siteConfig) {
			if ($siteConfig['name'] == 'name') {
				$siteName = $siteConfig['value'];
				break;
			}
		}
		return $siteName;
	}
	
	/**
	 * Update Site
	 * @return bool
	 */
	protected function _updateSite()
	{
		$siteConfigs = $this->readCsv(false, 'site_configs');
		$title = $keyword = $description = $theme = $formalName = '';
		foreach($siteConfigs as $siteConfig) {
			if($siteConfig['name'] === 'name') $title = $siteConfig['value'];
			if($siteConfig['name'] === 'keyword') $keyword = $siteConfig['value'];
			if($siteConfig['name'] === 'description') $description = $siteConfig['value'];
			if($siteConfig['name'] === 'theme') $theme = $siteConfig['value'];
			if($siteConfig['name'] === 'formal_name') $formalName = $siteConfig['value'];
		}
		$data = [
			'id' => 1,
			'display_name' => $formalName,
			'title' => $title,
			'theme' => $theme,
			'status' => true,
			'keyword' => $keyword,
			'description' => $description,
		];
		$sitesTable = $this->tableLocator->get('BaserCore.Sites', ['connectionName' => $this->newDbConfigKeyName]);
		$site = $sitesTable->newEntity($data, ['validate' => false]);
		
		$eventListeners = BcUtil::offEvent($sitesTable->getEventManager(), 'Model.afterSave');
		$sitesTable->save($site);
		BcUtil::onEvent($sitesTable->getEventManager(), 'Model.afterSave', $eventListeners);
		
		$sites = $this->readCsv(false, 'sites');
		foreach($sites as $site) {
			$siteId = $site['id']++;
			$this->_siteIdMap[$site['id']] = $siteId;
			$site['id'] = $siteId;
			try {
				$site = $sitesTable->newEntity($site);
				$sitesTable->save($site);
			} catch(PersistenceException $e) {
				$this->log($e->getEntity()->getMessage());
				return false;
			} catch(\Throwable $e) {
				$this->log($e->getMessage());
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Update Contents
	 * @return bool
	 */
	protected function _updateContent()
	{
		$records = $this->readCsv(false, 'contents');
		$this->tableLocator->remove('BaserCore.Contents');
		$table = $this->tableLocator->get('BaserCore.Contents', ['connectionName' => $this->newDbConfigKeyName]);
		$table->removeBehavior('Tree');
		BcUtil::offEvent($table->getEventManager(), 'Model.afterSave');
		foreach($records as $record) {
			$record['site_id'] = $this->getSiteId($record['site_id']);
			unset($record['deleted']);
			try {
				$entity = $table->newEntity($record);
				$table->save($entity);
			} catch(PersistenceException $e) {
				$this->log($e->getEntity()->getMessage());
				return false;
			} catch(\Throwable $e) {
				$this->log($e->getMessage());
				return false;
			}
		}
		$this->tableLocator->remove('BaserCore.Contents');
		return true;
	}
	
	/**
	 * サイトIDを取得する
	 * @param $src
	 * @return int|mixed
	 */
	private function getSiteId($src) {
		if(!$src) return 1;
		if(isset($this->_siteIdMap[$src])) {
			return $this->_siteIdMap[$src];
		}
		return 1;
	}
	
	/**
	 * Update Plugin
	 */
	protected function _updatePlugin()
	{
		$Plugin = $this->tableLocator->get('Plugin');
		$plugins = $this->readCsv(false, 'plugins');
		$corePlugins = \Cake\Core\Configure::read('BcApp.corePlugins');
		$result = true;
		foreach($plugins as $plugin) {
			if (in_array($plugin['name'], $corePlugins)) {
				$plugin['version'] = \BaserCore\Utility\BcUtil::getVersion();
			}
			$plugin['status'] = false;
			$Plugin->create($plugin);
			if (!$Plugin->save()) {
				$this->log($Plugin->validationErrors);
				$result = false;
			}
		}
		return $result;
	}
	
	/**
	 * Add Top Folder
	 *
	 * @param string $siteName
	 */
	protected function _addTopFolder($siteName)
	{
		$ContentFolder = $this->tableLocator->get('ContentFolder');
		$data = [
			'ContentFolder' => [
				'folder_template' => "",
				'page_template' => ""
			],
			'Content' => [
				"name" => "",
				"plugin" => "Core",
				"type" => "ContentFolder",
				"site_id" => 0,
				"parent_id" => null,
				"title" => $siteName,
				"description" => "",
				"author_id" => "1",
				"layout_template" => "default",
				"status" => true,
				"self_status" => true,
				"exclude_search" => false,
				"created_date" => date('Y-m-d'),
				"modified_date" => date('Y-m-d'),
				"site_root" => true,
				"deleted" => false,
				"exclude_menu" => false,
				"blank_link" => false
			]
		];
		$ContentFolder->create($data);
		if (!$ContentFolder->save()) {
			$this->log($ContentFolder->validationErrors);
			return false;
		}
		return true;
	}
	
	/**
	 * Update PageCategory
	 *
	 * @param string $siteName
	 */
	protected function _updatePageCategory($siteName)
	{
		$ContentFolder = $this->tableLocator->get('ContentFolder');
		$Site = $this->tableLocator->get('Site');
		$PageCategory = $this->tableLocator->get('PageCategory');
		$this->_setDbConfigToModel($PageCategory, $this->oldDbConfigKeyName);
		$pageCategories = $PageCategory->find('all', ['order' => 'lft', 'recursive' => -1]);
		$this->_parentIdMap = [];
		$result = true;
		foreach($pageCategories as $pageCategory) {
			$pageCategory = $pageCategory['PageCategory'];
			if (in_array($pageCategory['name'], ['mobile', 'smartphone'])) {
				$data = [
					'Site' => [
						"main_site_id" => 0,
						"name" => $pageCategory['name'],
						"display_name" => $pageCategory['title'],
						"title" => $siteName,
						"alias" => ($pageCategory['name'] == 'mobile')? 'm' : 's',
						"theme" => "",
						"status" => false,
						"use_subdomain" => false,
						"relate_main_site" => false,
						"device" => $pageCategory['name'],
						"lang" => "",
						"same_main_url" => false,
						"auto_redirect" => true,
						"auto_link" => false,
						"domain_type" => 0
					]
				];
				$Site->create($data);
				if (!$Site->save()) {
					$this->log($Site->validationErrors);
					$result = false;
				}
			} else {
				$data = [
					'ContentFolder' => [
						'page_template' => $pageCategory['content_template']
					],
					'Content' => [
						"name" => $pageCategory['name'],
						"plugin" => "Core",
						"type" => "ContentFolder",
						"site_id" => 0,
						"parent_id" => (isset($this->_parentIdMap[$pageCategory['parent_id']]))? $this->_parentIdMap[$pageCategory['parent_id']] : 1,
						"title" => $pageCategory['title'],
						"description" => "「" . $pageCategory['title'] . "」のコンテンツ一覧",
						"author_id" => "1",
						"layout_template" => $pageCategory['layout_template'],
						"status" => true,
						"self_status" => true,
						"exclude_search" => false,
						"created_date" => $pageCategory['created'],
						"modified_date" => $pageCategory['modified'],
						"site_root" => false,
						"deleted" => false,
						"exclude_menu" => false,
						"blank_link" => false
					]
				];
				$ContentFolder->create($data);
				if (!$ContentFolder->save()) {
					$this->log($ContentFolder->validationErrors);
					$result = false;
				}
			}
			$this->_parentIdMap[$pageCategory['id']] = $ContentFolder->Content->id;
		}
		return $result;
	}
	
	/**
	 * Update Page
	 */
	protected function _updatePage()
	{
		$this->tableLocator->remove('BaserCore.Pages');
		$table = $this->tableLocator->get('BaserCore.Pages', ['connectionName' => $this->newDbConfigKeyName]);
		BcUtil::offEvent($table->getEventManager(), 'Model.afterMarshal');
		$table->searchIndexSaving = false;
		$records = $this->readCsv(false, 'pages');
		foreach($records as $record) {
			unset($record['code']);
			try {
				$entity = $table->newEntity($record);
				$table->saveOrFail($entity);
			} catch(PersistenceException $e) {
				$this->log($e->getEntity()->getMessage());
				return false;
			} catch(\Throwable $e) {
				$this->log($e->getMessage());
				return false;
			}
		}
		$this->tableLocator->remove('BaserCore.Pages');
		return true;
	}
	
	/**
	 * Update UserGroup
	 */
	protected function _updateUserGroup()
	{
		$this->tableLocator->remove('BaserCore.UserGroups');
		$userGroupsTable = $this->tableLocator->get('BaserCore.UserGroups', ['connectionName' => $this->newDbConfigKeyName]);
		$userGroups = $this->readCsv(false, 'user_groups');
		$result = true;
		foreach($userGroups as $userGroup) {
			$authPrefixSettings = '';
			if($userGroup['id'] !== '1') {
				$authPrefixSettings = '{"Admin":{"type":"2"},"Api/Admin":{"type":"2"}}';
			}
			$authPrefixArray = explode(',', $userGroup['auth_prefix']);
			$authPrefix = array_map('\Cake\Utility\Inflector::camelize', $authPrefixArray);
			$data = [
				'name' => $userGroup['name'],
				'title' => $userGroup['title'],
				'auth_prefix' => $authPrefix,
				'use_move_contents' => $userGroup['use_move_contents'],
				'auth_prefix_settings' => $authPrefixSettings,
			];
			
			try {
				$entity = $userGroupsTable->newEntity($data);
				$userGroupsTable->save($entity);	
			} catch(PersistenceException $e) {
				$this->log($e->getEntity()->getMessage());
				return false;
			} catch(\Throwable $e) {
				$this->log($e->getMessage());
				return false;
			}
		}
		$this->tableLocator->remove('BaserCore.UserGroups');
		return $result;
	}
	
	/**
	 * Update BlogContent
	 */
	protected function _updateBlogContent()
	{
		$this->tableLocator->remove('BcBlog.BlogContents');
		$table = $this->tableLocator->get('BcBlog.BlogContents', ['connectionName' => $this->newDbConfigKeyName]);
		BcUtil::offEvent($table->getEventManager(), 'Model.afterMarshal');
		$records = $this->readCsv(true, 'blog_contents');
		foreach($records as $record) {
			try {
				$entity = $table->newEntity($record);
				$table->saveOrFail($entity);
			} catch(PersistenceException $e) {
				$this->log($e->getEntity()->getMessage());
				return false;
			} catch(\Throwable $e) {
				$this->log($e->getMessage());
				return false;
			}
		}
		$this->tableLocator->remove('BcBlog.BlogContents');
		return true;
	}
	
	/**
	 * Update MailContent
	 */
	protected function _updateMailContent()
	{
		$MailContent = $this->tableLocator->get('MailContent');
		$mailContents = $this->readCsv(true, 'mail_contents');
		$result = true;
		foreach($mailContents as $mailContent) {
			$data = [
				'MailContent' => [
					'id' => $mailContent['id'],
					'description' => $mailContent['description'],
					'sender_1' => $mailContent['sender_1'],
					'sender_2' => $mailContent['sender_2'],
					'sender_name' => $mailContent['sender_name'],
					'subject_user' => $mailContent['subject_user'],
					'subject_admin' => $mailContent['subject_admin'],
					'form_template' => $mailContent['form_template'],
					'mail_template' => $mailContent['mail_template'],
					'redirect_url' => $mailContent['redirect_url'],
					'auth_captcha' => $mailContent['auth_captcha'],
					'widget_area' => $mailContent['widget_area'],
					'ssl_on' => $mailContent['ssl_on'],
					'publish_begin' => $mailContent['publish_begin'],
					'publish_end' => $mailContent['publish_end']
				],
				'Content' => [
					"name" => $mailContent['name'],
					"plugin" => "Mail",
					"type" => "MailContent",
					"site_id" => 0,
					"parent_id" => 1,
					"title" => $mailContent['title'],
					"description" => "",
					"author_id" => 1,
					"layout_template" => $mailContent['layout_template'],
					"status" => $mailContent['status'],
					"publish_begin" => null,
					"publish_end" => null,
					"self_status" => $mailContent['status'],
					"self_publish_begin" => null,
					"self_publish_end" => null,
					"exclude_search" => $mailContent['exclude_search'],
					"created_date" => $mailContent['created'],
					"modified_date" => $mailContent['modified'],
					"site_root" => false,
					"deleted" => false,
					"exclude_menu" => false,
					"blank_link" => false
				]
			];
			$MailContent->create($data);
			if (!$MailContent->save()) {
				$this->log($MailContent->validationErrors);
				$result = false;
			}
		}
		return $result;
	}
	
	/**
	 * Update SiteConfig
	 */
	protected function _updateSiteConfig()
	{
		$SiteConfig = $this->tableLocator->get('SiteConfig');
		$this->_setDbConfigToModel($SiteConfig, $this->oldDbConfigKeyName);
		$siteConfig = $SiteConfig->findExpanded();
		$this->_setDbConfigToModel($SiteConfig, $this->newDbConfigKeyName);
		$data = [
			"name" => $siteConfig['name'],
			"keyword" => $siteConfig['keyword'],
			"description" => $siteConfig['description'],
			"address" => $siteConfig['address'],
			"theme" => $siteConfig['theme'],
			"email" => $siteConfig['email'],
			"widget_area" => $siteConfig['widget_area'],
			"maintenance" => $siteConfig['maintenance'],
			"mail_encode" => $siteConfig['mail_encode'],
			"smtp_host" => $siteConfig['smtp_host'],
			"smtp_user" => $siteConfig['smtp_user'],
			"smtp_password" => $siteConfig['smtp_password'],
			"smtp_port" => $siteConfig['smtp_port'],
			"formal_name" => $siteConfig['formal_name'],
			"mobile" => @$siteConfig['mobile'],
			"admin_list_num" => $siteConfig['admin_list_num'],
			"google_analytics_id" => $siteConfig['google_analytics_id'],
			"content_types" => $siteConfig['content_types'],
			"category_permission" => $siteConfig['category_permission'],
			"admin_theme" => $siteConfig['admin_theme'],
			"login_credit" => $siteConfig['login_credit'],
			"first_access" => @$siteConfig['first_access'],
			"editor" => $siteConfig['editor'],
			"editor_styles" => $siteConfig['editor_styles'],
			"editor_enter_br" => $siteConfig['editor_enter_br'],
			"admin_side_banner" => $siteConfig['admin_side_banner'],
			"use_universal_analytics" => @$siteConfig['use_universal_analytics'],
			"google_maps_api_key" => @$siteConfig['google_maps_api_key'],
			"main_site_display_name" => "パソコン",
			"use_site_device_setting" => true,
			"use_site_lang_setting" => false,
			"smtp_tls" => $siteConfig['smtp_tls'],
			"contents_sort_last_modified" => ""
		];
		if (!$SiteConfig->saveKeyValue($data)) {
			$this->log($SiteConfig->validationErrors);
			return false;
		}
		return true;
	}
	
	protected function _getNewMessageTableName($oldName)
	{
		// コンテンツ名を抽出
		if (preg_match('/^(.+)_messages$/', $oldName, $maches)) {
			$contentName = $maches[1];
		} else {
			$contentName = 'messages';
		}
		// コンテンツ名からIDを取得
		$mailContents = $this->readCsv(true, 'mail_contents');
		$id = "";
		foreach($mailContents as $mailContent) {
			if ($mailContent['name'] == $contentName) {
				$id = $mailContent['id'];
				break;
			}
		}
		// 新しいテーブル名を決定
		if ($id) {
			return 'mail_message_' . $id;
		} else {
			return false;
		}
	}
	
	/**
	 * Convert Message Schema
	 */
	protected function _convertMessageSchema()
	{
		$Folder = new \Cake\Filesystem\Folder($this->tmpPath . 'plugin');
		$files = $Folder->read(true, true, false);
		foreach($files[1] as $file) {
			if (preg_match('/messages\.php$/', $file)) {
				$oldName = basename($file, '.php');
				$newName = $this->_getNewMessageTableName($oldName);
				if ($newName) {
					// リネーム
					rename(
						$this->tmpPath . 'plugin' . DS . $file,
						$this->tmpPath . 'plugin' . DS . $newName . '.php'
					);
					$File = new \Cake\Filesystem\File($this->tmpPath . 'plugin' . DS . $newName . '.php');
					$oldClass = \Cake\Utility\Inflector::camelize(basename($file, '.php'));
					$newClass = \Cake\Utility\Inflector::camelize($newName);
					
					$contents = $File->read();
					$contents = preg_replace('/class ' . preg_quote($oldClass, '/') . 'Schema/', 'class ' . $newClass . 'Schema', $contents);
					$contents = preg_replace('/\$' . preg_quote($oldName, '/') . ' =/', '$' . $newName . ' =', $contents);
					$contents = preg_replace('/\$file = \'' . preg_quote($oldName, '/') . '\.php/', '$file = \'' . $newName . '.php', $contents);
					$contents = preg_replace('/\$connection = \'plugin\'/', '$connection = \'default\'', $contents);
					$File->write($contents);
					$File->close();
				}
			}
		}
	}
	
	/**
	 * Convert Message Data
	 */
	protected function _convertMessageData()
	{
		$Folder = new \Cake\Filesystem\Folder($this->tmpPath . 'plugin');
		$files = $Folder->read(true, true, false);
		foreach($files[1] as $file) {
			if (preg_match('/messages\.csv/', $file)) {
				$newName = $this->_getNewMessageTableName(basename($file, '.csv'));
				if ($newName) {
					// リネーム
					rename(
						$this->tmpPath . 'plugin' . DS . $file,
						$this->tmpPath . 'plugin' . DS . $newName . '.csv'
					);
				}
			}
		}
	}
	
	/**
	 * ブログ記事
	 */
	protected function _updateBlogPost()
	{
		$BlogPost = $this->tableLocator->get('BlogPost');
		$BlogPost->searchIndexSaving = false;
		$blogPosts = $this->readCsv(true, 'blog_posts');
		$result = true;
		foreach($blogPosts as $blogPost) {
			if (empty($blogPost['posts_date'])) {
				$blogPost['posts_date'] = date('Y-m-d', 0);
			}
			$BlogPost->create($blogPost);
			if (!$BlogPost->save()) {
				$this->log($BlogPost->validationErrors);
				$result = false;
			}
		}
		return $result;
	}
}
