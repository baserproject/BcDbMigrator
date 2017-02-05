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
		$this->_newDb->truncate('contents');
		$this->_newDb->truncate('content_folders');
		$this->_newDb->truncate('sites');
		
		// サイト名を取得
		$Site = ClassRegistry::init('Site');
		$siteConfigs =  $this->readCsv(false, 'site_configs');
		$siteName = '';
		foreach($siteConfigs as $siteConfig) {
			if($siteConfig['name'] == 'name') {
				$siteName = $siteConfig['value'];
				break;
			}
		}
		
		// content_folders
		$ContentFolder = ClassRegistry::init('ContentFolder');
		$data = [
			'Content' => [
				"name" => "",
				"plugin" => "Core",
				"type" => "ContentFolder",
				"site_id" => 0,
				"parent_id" => null,
				"title" => $siteName,
				"description" => "",
				"author_id" => "1",
				"layout_template" => "",
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

		// page_categories
		$PageCategory = ClassRegistry::init('PageCategory');
		$this->_setDbConfigToModel($PageCategory, $this->oldDbConfigKeyName);
		$pageCategories = $PageCategory->find('all', ['order' => 'lft', 'recursive' => -1]);
		$parentIdMap = [];
		foreach($pageCategories as $pageCategory) {
			$pageCategory = $pageCategory['PageCategory'];
			if(in_array($pageCategory['name'], ['mobile', 'smartphone'])) {
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
						"parent_id" => (isset($parentIdMap[$pageCategory['parent_id']]))? $parentIdMap[$pageCategory['parent_id']]: 1,
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
				$parentIdMap[$pageCategory['id']] = $ContentFolder->Content->id;
			}
		}
		
		// pages
		$Page = ClassRegistry::init('Page');
		$pages = $this->readCsv(false, 'pages');
		foreach($pages as $page) {
			$data = [
				'Page' => [
					'contents' => $page['contents'],
					'code' => $page['code']
				],
				'Content' => [
					"name" => $page['name'],
					"plugin" => "Core",
					"type" => "Page",
					"site_id" => 0,
					"parent_id" => (isset($parentIdMap[$page['page_category_id']]))? $parentIdMap[$page['page_category_id']]: 1,
					"title" => $page['title'],
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
		
		return true;
	}
	
/**
 * データのマイグレーションを実行する
 */
	public function migrateData() {
		return true;
	}
	
}