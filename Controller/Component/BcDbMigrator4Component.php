<?php
/**
 * include files
 */
App::uses('Component', 'Controller');
App::uses('BcDbMigratorComponent', 'BcDbMigrator.Controller/Component');
App::uses('BcDbMigratorInterface', 'BcDbMigrator.Controller/Component');

/**
 * BcDbMigrator4Component
 */
class BcDbMigrator4Component extends BcDbMigratorComponent implements BcDbMigratorInterface {
	
/**
 * メッセージ
 * @var array
 */
	public $message = [
		'baserCMS 3.0.9 以上のバックアップデータの basrCMS 4.0.2 への変換のみサポートしています。',
	];

/**
 * 親IDマッピング
 * 
 * @var array
 */
	protected $_parentIdMap = [];
	
/**
 * メッセージを取得する
 * 
 * @return array
 */
	public function getMessage() {
		return $this->message;
	}

	public function startup(Controller $controller) {
		$this->coreFolder = 'baser';
		parent::startup($controller);
	}
	
/**
 * スキーマのマイグレーションを実行
 */
	public function migrateSchema() {
		// メッセージテーブルの生成
		$this->_convertMessageSchema();
		// 不要なスキーマを削除
		$this->deleteSchema(false, 'menus');
		$this->deleteSchema(false, 'page_categories');
		$this->deleteSchema(false, 'plugin_contents');
		$this->deleteSchema(false, 'global_menus');
		$this->deleteSchema(true, 'messages');
		// 新しいスキーマを一時フォルダに保存
		$this->writeNewSchema();
		return true;
	}
	
/**
 * データのマイグレーションを実行する
 */
	public function migrateData() {
		$this->_resetNewTable();
		// サイト名取得
		$siteName = $this->_getSiteName();
		// トップフォルダ生成
		$this->_addTopFolder($siteName);
		// ページカテゴリ
		$this->_updatePageCategory($siteName);
		// ページ
		$this->_updatePage();
		// ブログコンテンツ
		$this->_updateBlogContent();
		// メールコンテンツ
		$this->_updateMailContent();
		// プラグイン
		$this->_updatePlugin();
		// サイト設定
		$this->_updateSiteConfig();
		// メッセージテーブル
		$this->_convertMessageData();
		// CSVを出力する
		$this->_writeNewCsv();
		// 不要なCSVを削除
		$this->_deleteCsv();
		// フォルダ名変更
		$Folder = new Folder();
		$Folder->move([
			'from' => $this->_Controller->_tmpPath . 'baser',
			'to' => $this->_Controller->_tmpPath . 'core',
		]);
		return true;
	}

/**
 * 不要なCSVを削除 
 */
	protected function _deleteCsv() {
		$this->deleteCsv(false, 'menus');
		$this->deleteCsv(false, 'page_categories');
		$this->deleteCsv(false, 'plugin_contents');
		$this->deleteCsv(false, 'global_menus');
		$this->deleteCsv(true, 'messages');
	}
	
/**
 * 新しいデータのCSVを出力する
 */
	protected function _writeNewCsv() {
		$this->writeCsv(false, 'pages');
		$this->writeCsv(false, 'content_folders');
		$this->writeCsv(false, 'contents');
		$this->writeCsv(false, 'sites');
		$this->writeCsv(false, 'site_configs');
		$this->writeCsv(false, 'site_indices');
		$this->writeCsv(false, 'content_links');
		$this->writeCsv(false, 'plugins');
		$this->writeCsv(true, 'blog_contents');
		$this->writeCsv(true, 'mail_contents');
	}

/**
 * 新テーブルをリセットする 
 */
	protected function _resetNewTable() {
		$this->_newDb->truncate('contents');
		$this->_newDb->truncate('content_folders');
		$this->_newDb->truncate('sites');
		$this->_newDb->truncate('blog_contents');
		$this->_newDb->truncate('mail_contents');
		$this->_newDb->truncate('pages');
		$this->_newDb->truncate('site_configs');
		$this->_newDb->truncate('plugins');
	}

/**
 * サイト名を取得する
 * 
 * @return string
 */
	protected function _getSiteName() {
		$siteConfigs =  $this->readCsv(false, 'site_configs');
		$siteName = '';
		foreach($siteConfigs as $siteConfig) {
			if($siteConfig['name'] == 'name') {
				$siteName = $siteConfig['value'];
				break;
			}
		}
		return $siteName;
	}

/**
 * Update Plugin 
 */
	protected function _updatePlugin() {
		$Plugin = ClassRegistry::init('Plugin');
		$plugins =  $this->readCsv(false, 'plugins');
		foreach($plugins as $plugin) {
			$plugin['status'] = false;
			$Plugin->create($plugin);
			$Plugin->save();
		}
	}
/**
 * Add Top Folder
 * 
 * @param string $siteName
 */
	protected function _addTopFolder($siteName) {
		$ContentFolder = ClassRegistry::init('ContentFolder');
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
		$ContentFolder->save();
	}

/**
 * Update PageCategory
 * 
 * @param string $siteName
 */
	protected function _updatePageCategory($siteName) {
		$ContentFolder = ClassRegistry::init('ContentFolder');
		$Site = ClassRegistry::init('Site');
		$PageCategory = ClassRegistry::init('PageCategory');
		$this->_setDbConfigToModel($PageCategory, $this->oldDbConfigKeyName);
		$pageCategories = $PageCategory->find('all', ['order' => 'lft', 'recursive' => -1]);
		$this->_parentIdMap = [];
		foreach($pageCategories as $pageCategory) {
			$pageCategory = $pageCategory['PageCategory'];
			if(in_array($pageCategory['name'], ['mobile', 'smartphone'])) {
				$data = [
					'Site' => [
						"main_site_id" => 0,
						"name" => $pageCategory['name'],
						"display_name" => $pageCategory['title'],
						"title" => $siteName,
						"alias" => ($pageCategory['name'] == 'mobile') ? 'm' : 's',
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
				$Site->save();
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
						"parent_id" => (isset($this->_parentIdMap[$pageCategory['parent_id']])) ? $this->_parentIdMap[$pageCategory['parent_id']] : 1,
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
				$ContentFolder->save();
			}
			$this->_parentIdMap[$pageCategory['id']] = $ContentFolder->Content->id;
		}
	}

/**
 * Update Page
 */
	protected function _updatePage() {
		$Page = ClassRegistry::init('Page');
		$pages = $this->readCsv(false, 'pages');
		foreach($pages as $page) {
			$data = [
				'Page' => [
					'contents' => $page['contents'],
					'draft' => $page['draft'],
					'code' => $page['code']
				],
				'Content' => [
					"name" => $page['name'],
					"plugin" => "Core",
					"type" => "Page",
					"site_id" => 0,
					"parent_id" => (isset($this->_parentIdMap[$page['page_category_id']])) ? $this->_parentIdMap[$page['page_category_id']] : 1,
					"title" => ($page['title']) ? $page['title'] : "タイトルを入力してください。",
					"description" => $page['description'],
					"author_id" => $page['author_id'],
					"layout_template" => "",
					"status" => $page['status'],
					"publish_begin" => $page['publish_begin'],
					"publish_end" => $page['publish_end'],
					"self_status" => $page['status'],
					"self_publish_begin" => $page['publish_begin'],
					"self_publish_end" => $page['publish_end'],
					"exclude_search" => $page['exclude_search'],
					"created_date" => $page['created'],
					"modified_date" => $page['modified'],
					"site_root" => false,
					"deleted" => false,
					"exclude_menu" => false,
					"blank_link" => false
				]
			];
			$Page->create($data);
			$Page->save();
		}
	}

/**
 * Update BlogContent
 */
	protected function _updateBlogContent() {
		$BlogContent = ClassRegistry::init('BlogContent');
		$blogContents = $this->readCsv(true, 'blog_contents');
		foreach($blogContents as $blogContent) {
			$data = [
				'BlogContent' => [
					'id' => $blogContent['id'],
					'description' => $blogContent['description'],
					'template' => $blogContent['template'],
					'list_count' => $blogContent['list_count'],
					'list_direction' => $blogContent['list_direction'],
					'feed_count' => $blogContent['feed_count'],
					'tag_use' => $blogContent['tag_use'],
					'comment_use' => $blogContent['comment_use'],
					'comment_approve' => $blogContent['comment_approve'],
					'auth_captcha' => $blogContent['auth_captcha'],
					'widget_area' => $blogContent['widget_area'],
					'eye_catch_size' => $blogContent['eye_catch_size'],
					'use_content' => $blogContent['use_content']
				],
				'Content' => [
					"name" => $blogContent['name'],
					"plugin" => "Blog",
					"type" => "BlogContent",
					"site_id" => 0,
					"parent_id" => 1,
					"title" => $blogContent['title'],
					"description" => $blogContent['description'],
					"author_id" => 1,
					"layout_template" => $blogContent['layout'],
					"status" => $blogContent['status'],
					"publish_begin" => null,
					"publish_end" => null,
					"self_status" => $blogContent['status'],
					"self_publish_begin" => null,
					"self_publish_end" => null,
					"exclude_search" => $blogContent['exclude_search'],
					"created_date" => $blogContent['created'],
					"modified_date" => $blogContent['modified'],
					"site_root" => false,
					"deleted" => false,
					"exclude_menu" => false,
					"blank_link" => false
				]
			];
			$BlogContent->create($data);
			$data = $BlogContent->save();
			// 検索インデックス生成の為再度保存
			$BlogContent->set($data);
			$BlogContent->save();
		}
	}
	
/**
 * Update MailContent
 */
	protected function _updateMailContent() {
		$MailContent = ClassRegistry::init('MailContent');
		$mailContents = $this->readCsv(true, 'mail_contents');
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
			$data = $MailContent->save();
			// 検索インデックス生成の為再度保存
			$MailContent->set($data);
			$MailContent->save();
		}
	}

/**
 * Update SiteConfig
 */
	protected function _updateSiteConfig() {
		$SiteConfig = ClassRegistry::init('SiteConfig');
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
			"first_access" => $siteConfig['first_access'],
			"editor" => $siteConfig['editor'],
			"editor_styles" => $siteConfig['editor_styles'],
			"editor_enter_br" => $siteConfig['editor_enter_br'],
			"admin_side_banner" => $siteConfig['admin_side_banner'],
			"use_universal_analytics" => $siteConfig['use_universal_analytics'],
			"google_maps_api_key" => @$siteConfig['google_maps_api_key'],
			"main_site_display_name" => "パソコン",
			"use_site_device_setting" => true,
			"use_site_lang_setting" => false,
			"smtp_tls" => $siteConfig['smtp_tls'],
			"contents_sort_last_modified" => ""
		];
		$SiteConfig->saveKeyValue($data);
	}
	
	protected function _getNewMessageTableName($oldName) {
		// コンテンツ名を抽出
		if(preg_match('/^(.+)_messages$/', $oldName, $maches)) {
			$contentName = $maches[1];
		} else {
			$contentName = 'messages';
		}
		// コンテンツ名からIDを取得
		$mailContents = $this->readCsv(true, 'mail_contents');
		$id = "";
		foreach($mailContents as $mailContent) {
			if($mailContent['name'] == $contentName) {
				$id = $mailContent['id'];
				break;
			}
		}
		// 新しいテーブル名を決定
		if($id) {
			return 'mail_message_' . $id;	
		} else {
			return false;
		}
	}
	
/**
 * Convert Message Schema
 */
	protected function _convertMessageSchema() {
		$Folder = new Folder($this->_Controller->_tmpPath . 'plugin');
		$files = $Folder->read(true, true, false);
		foreach($files[1] as $file) {
			if(preg_match('/messages\.php$/', $file)) {
				$oldName = basename($file, '.php');
				$newName = $this->_getNewMessageTableName($oldName);
				if($newName) {
					// リネーム
					rename(
						$this->_Controller->_tmpPath . 'plugin' . DS . $file,
						$this->_Controller->_tmpPath . 'plugin' . DS . $newName . '.php'
					);
					$File = new File($this->_Controller->_tmpPath . 'plugin' . DS . $newName . '.php');
					$oldClass = Inflector::camelize(basename($file, '.php'));
					$newClass = Inflector::camelize($newName);
					
					$contents = $File->read();
					$contents = preg_replace('/class ' . preg_quote($oldClass, '/') . 'Schema/', 'class ' . $newClass . 'Schema', $contents);
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
	protected function _convertMessageData() {
		$Folder = new Folder($this->_Controller->_tmpPath . 'plugin');
		$files = $Folder->read(true, true, false);
		foreach($files[1] as $file) {
			if(preg_match('/messages\.csv/', $file)) {
				$newName = $this->_getNewMessageTableName(basename($file, '.csv'));
				if($newName) {
					// リネーム
					rename(
						$this->_Controller->_tmpPath . 'plugin' . DS . $file,
						$this->_Controller->_tmpPath . 'plugin' . DS . $newName . '.csv'
					);
				}
			}
		}
	}
}