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
		'baserCMS 3.0.9 以上のバックアップデータの basrCMS 4系 への変換のみサポートしています。<br>また、プラグインは無効化された状態でデータが作成されますので、バックアップ復旧後、有効化する必要がありますのでご注意ください。',
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
		$this->deleteSchema(true, 'mail_messages');
		return true;
	}
	
/**
 * データのマイグレーションを実行する
 */
	public function migrateData() {
		$result = true;
		$this->_resetNewTable();
		// サイト名取得
		$siteName = $this->_getSiteName();
		// トップフォルダ生成
		$this->_addTopFolder($siteName);
		// ユーザーグループ
		if(!$this->_updateUserGroup()) $result = false;
		// ページカテゴリ
		if(!$this->_updatePageCategory($siteName)) $result = false;
		// ページ
		if(!$this->_updatePage()) $result = false;
		// ブログコンテンツ
		if(!$this->_updateBlogContent()) $result = false;
		// ブログ記事
		if(!$this->_updateBlogPost()) $result = false;
		// メールコンテンツ
		if(!$this->_updateMailContent()) $result = false;
		// プラグイン
		if(!$this->_updatePlugin()) $result = false;
		// サイト設定
		if(!$this->_updateSiteConfig()) $result = false;
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
		return $result;
	}

/**
 * 不要なCSVを削除 
 */
	protected function _deleteCsv() {
		$this->deleteCsv(false, 'menus');
		$this->deleteCsv(false, 'page_categories');
		$this->deleteCsv(false, 'plugin_contents');
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
	protected function _resetNewTable() {
		$this->_newDb->truncate('contents');
		$this->_newDb->truncate('content_folders');
		$this->_newDb->truncate('user_groups');
		$this->_newDb->truncate('sites');
		$this->_newDb->truncate('blog_contents');
		$this->_newDb->truncate('mail_contents');
		$this->_newDb->truncate('pages');
		$this->_newDb->truncate('site_configs');
		$this->_newDb->truncate('plugins');
		$this->_newDb->truncate('blog_posts');
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
		$corePlugins = Configure::read('BcApp.corePlugins');
		$result = true;
		foreach($plugins as $plugin) {if (in_array($plugin['name'], $corePlugins)) {
				$plugin['version'] = getVersion();
			}
			$plugin['status'] = false;
			$Plugin->create($plugin);
			if(!$Plugin->save()) {
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
		if(!$ContentFolder->save()) {
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
	protected function _updatePageCategory($siteName) {
		$ContentFolder = ClassRegistry::init('ContentFolder');
		$Site = ClassRegistry::init('Site');
		$PageCategory = ClassRegistry::init('PageCategory');
		$this->_setDbConfigToModel($PageCategory, $this->oldDbConfigKeyName);
		$pageCategories = $PageCategory->find('all', ['order' => 'lft', 'recursive' => -1]);
		$this->_parentIdMap = [];
		$result = true;
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
				if(!$Site->save()) {
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
				if(!$ContentFolder->save()) {
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
	protected function _updatePage() {
		$Page = ClassRegistry::init('Page');
		$Page->searchIndexSaving = false;
		$Page->fileSave = false;
		$pages = $this->readCsv(false, 'pages');
		$result = true;
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
					"publish_begin" => ($page['publish_begin'] === '0000-00-00 00:00:00')? NULL : $page['publish_begin'],
					"publish_end" => ($page['publish_end'] === '0000-00-00 00:00:00')? NULL : $page['publish_begin'],
					"self_status" => $page['status'],
					"self_publish_begin" => ($page['publish_begin'] === '0000-00-00 00:00:00')? NULL : $page['publish_begin'],
					"self_publish_end" => ($page['publish_end'] === '0000-00-00 00:00:00')? NULL : $page['publish_begin'],
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
			if(!$Page->save()) {
				$this->log($Page->validationErrors);
				$result = false;
			}
		}
		return $result;
	}

/**
 * Update UserGroup
 */
	protected function _updateUserGroup() {
		$UserGroup = ClassRegistry::init('UserGroup');
		$userGroups = $this->readCsv(false, 'user_groups');
		$result = true;
		foreach($userGroups as $userGroup) {
			$useMoveContents = false;
			if($userGroup['id'] == 1) {
				$useMoveContents = true;
			}
			$data = [
				'UserGroup' => [
					'name' => $userGroup['name'],
					'title' => $userGroup['title'],
					'auth_prefix' => $userGroup['auth_prefix'],
					'use_admin_globalmenu' => $userGroup['use_admin_globalmenu'],
					'default_favorites' => $userGroup['default_favorites'],
					'use_move_contents' => $useMoveContents
				],
			];
			$UserGroup->create($data);
			if(!$UserGroup->save()) {
				$this->log($UserGroup->validationErrors);
				$result = false;
			}
		}
		return $result;
	}

/**
 * Update BlogContent
 */
	protected function _updateBlogContent() {
		$BlogContent = ClassRegistry::init('BlogContent');
		$blogContents = $this->readCsv(true, 'blog_contents');
		$result = true;
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
			if(!$BlogContent->save()) {
				$this->log($BlogContent->validationErrors);
				$result = false;
			}
		}
		return $result;
	}
	
/**
 * Update MailContent
 */
	protected function _updateMailContent() {
		$MailContent = ClassRegistry::init('MailContent');
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
			if(!$MailContent->save()) {
				$this->log($MailContent->validationErrors);
				$result = false;
			}
		}
		return $result;
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
		if(!$SiteConfig->saveKeyValue($data)) {
			$this->log($SiteConfig->validationErrors);
			return false;
		}
		return true;
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

/**
 * ブログ記事
 */
	protected function _updateBlogPost() {
		$BlogPost = ClassRegistry::init('BlogPost');
		$BlogPost->searchIndexSaving = false;
		$blogPosts = $this->readCsv(true, 'blog_posts');
		$result = true;
		foreach($blogPosts as $blogPost) {
			if(empty($blogPost['posts_date'])) {
				$blogPost['posts_date'] = date('Y-m-d', 0);
			}
			$BlogPost->create($blogPost);
			if(!$BlogPost->save()) {
				$this->log($BlogPost->validationErrors);
				$result = false;
			}
		}
		return $result;
	}
}