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

namespace BcDbMigrator\Controller\Component;

use BaserCore\Service\PermissionGroupsServiceInterface;
use BaserCore\Utility\BcUtil;
use Cake\Utility\Hash;
use Cake\I18n\FrozenTime;
use Cake\Utility\Security;
use CakephpFixtureFactories\Error\PersistenceException;
use Cake\Core\Configure;
use Psr\Log\LogLevel;

/**
 * include files
 */

/**
 * BcDbMigrator5Component
 */
class BcDbMigrator5Component extends BcDbMigratorComponent implements BcDbMigratorInterface
{

	/**
	 * メッセージ
	 * @var array
	 */
	public $message = [
		'baserCMS 4.5.5 以上のバックアップデータの basrCMS 5系 への変換のみサポートしています。<br>また、プラグインは無効化された状態でデータが作成されますので、バックアップ復旧後、有効化する必要がありますのでご注意ください。',
	];

	/**
	 * サイトIDマッピング
	 * @var array
	 */
	private $_siteIdMap = [];

	/**
	 * 新しいパスワード
	 * @var string
	 */
	private $newPassword = '';

	/**
	 * 新しいパスワードを取得する
	 * @return string
	 */
	public function getNewPassword()
	{
		return $this->newPassword;
	}

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
		$this->writeNewSchema();
		// 不要なスキーマを削除
		$this->deleteSchema('Messages');
		$this->deleteSchema('MailMessages');
		$this->deleteSchema('SearchIndices');
		$this->deleteSchema('FeedConfigs');
		$this->deleteSchema('FeedDetails');
		$this->deleteSchema('BlogConfigs');
		return true;
	}

	/**
	 * データのマイグレーションを実行する
	 */
	public function migrateData()
	{
		$result = true;
		$this->_resetNewTable();
		// サイト
		$this->_updateSite();
		// ユーザーグループ
		if (!$this->_updateUserGroup()) $result = false;
		// コンテンツ
		if (!$this->_updateContent()) $result = false;
		// ページ
		if (!$this->_updatePage()) $result = false;
		// ブログ記事
		if (!$this->_updateBlogPost()) $result = false;
		// ブログカテゴリ
		if (!$this->_updateBlogCategory()) $result = false;
		// メール設定
		if (!$this->_updateMailConfig()) $result = false;
		// アクセスルールグループ
		if (!$this->_updatePermissionGroups()) $result = false;
		// アクセスルール
		if (!$this->_updatePermissions()) $result = false;
		// プラグイン
		if (!$this->_updatePlugin()) $result = false;
		// サイト設定
		if (!$this->_updateSiteConfig()) $result = false;
		// ユーザー
		if (!$this->_updateUser()) $result = false;
		// メールフィールド
		if (!$this->_updateMailField()) $result = false;
		// CSVを出力する
		$this->_writeNewCsv();
		// 不要なCSVを削除
		$this->_deleteCsv();
		return $result;
	}

	/**
	 * 不要なCSVを削除
	 */
	protected function _deleteCsv()
	{
		$this->deleteCsv('blog_configs');
		$this->deleteCsv('feed_configs');
		$this->deleteCsv('feed_details');
		$this->deleteCsv('mail_messages');
		$this->deleteCsv('dblogs');
	}

	/**
	 * 新しいデータのCSVを出力する
	 */
	protected function _writeNewCsv()
	{
		$this->writeCsv('blog_categories');
		$this->writeCsv('blog_posts');
		$this->writeCsv('contents');
		$this->writeCsv('mail_configs');
		$this->writeCsv('mail_fields');
		$this->writeCsv('pages');
		$this->writeCsv('permission_groups');
		$this->writeCsv('permissions');
		$this->writeCsv('plugins');
		$this->writeCsv('sites');
		$this->writeCsv('site_configs');
		$this->writeCsv('user_groups');
		$this->writeCsv('users');
		$this->writeCsv('users_user_groups');
		if (file_exists($this->tmpPath . 'search_indices.csv')) {
			rename($this->tmpPath . 'search_indices.csv', $this->tmpPath . 'search_indexes.csv');
		}
	}

	/**
	 * 新テーブルをリセットする
	 * CSVの構造変更が必要なものだけを対象とする
	 */
	protected function _resetNewTable()
	{
		$this->dbService->truncate('blog_categories', $this->newDbConfigKeyName);
		$this->dbService->truncate('blog_posts', $this->newDbConfigKeyName);
		$this->dbService->truncate('contents', $this->newDbConfigKeyName);
		$this->dbService->truncate('mail_configs', $this->newDbConfigKeyName);
		$this->dbService->truncate('mail_fields', $this->newDbConfigKeyName);
		$this->dbService->truncate('pages', $this->newDbConfigKeyName);
		$this->dbService->truncate('permissions', $this->newDbConfigKeyName);
		$this->dbService->truncate('plugins', $this->newDbConfigKeyName);
		$this->dbService->truncate('sites', $this->newDbConfigKeyName);
		$this->dbService->truncate('site_configs', $this->newDbConfigKeyName);
		$this->dbService->truncate('user_groups', $this->newDbConfigKeyName);
		$this->dbService->truncate('users', $this->newDbConfigKeyName);
		$this->dbService->truncate('users_user_groups', $this->newDbConfigKeyName);
	}

	/**
	 * Update Site
	 * @return bool
	 */
	protected function _updateSite()
	{
		$siteConfigs = $this->readCsv('site_configs');
		$title = $keyword = $description = $theme = $formalName = '';
		foreach($siteConfigs as $siteConfig) {
			if ($siteConfig['name'] === 'name') $title = $siteConfig['value'];
			if ($siteConfig['name'] === 'keyword') $keyword = $siteConfig['value'];
			if ($siteConfig['name'] === 'description') $description = $siteConfig['value'];
			if ($siteConfig['name'] === 'formal_name') $formalName = $siteConfig['value'];
		}
		$data = [
			'id' => 1,
			'display_name' => $formalName,
			'title' => $title,
			'theme' => Configure::read('BcApp.defaultFrontTheme'),
			'status' => true,
			'keyword' => $keyword,
			'description' => $description,
		];
		$sitesTable = $this->tableLocator->get('BaserCore.Sites');
		$site = $sitesTable->newEntity($data, ['validate' => false]);

		$eventListeners = BcUtil::offEvent($sitesTable->getEventManager(), 'Model.afterSave');
		try {
			$sitesTable->saveOrFail($site);
		} catch (PersistenceException $e) {
			$this->log($e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
			return false;
		} catch (\Throwable $e) {
			$this->log($e->getMessage(), LogLevel::ERROR, 'migrate_db');
			return false;
		}

		$sites = $this->readCsv('sites');
		foreach($sites as $site) {
            $siteId = (int)$site['id'] + 1;
            $this->_siteIdMap[$site['id']] = $siteId;
        }
		foreach($sites as $site) {
			$site['id'] = $this->getSiteId($site['id']);
			$site['main_site_id'] = $this->getSiteId($site['main_site_id']);
			$site['theme'] = Configure::read('BcApp.defaultFrontTheme');
			try {
				$site = $sitesTable->newEntity($site, ['validate' => false]);
				$sitesTable->saveOrFail($site);
			} catch (PersistenceException $e) {
				$this->log('sites: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('sites: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		BcUtil::onEvent($sitesTable->getEventManager(), 'Model.afterSave', $eventListeners);
		return true;
	}

	/**
	 * Update Contents
	 * @return bool
	 */
	protected function _updateContent()
	{
		$records = $this->readCsv('contents');
		$table = $this->tableLocator->get('BaserCore.Contents');
		$table->removeBehavior('Tree');
		BcUtil::offEvent($table->getEventManager(), 'Model.afterSave');
		foreach($records as $record) {
			$record['site_id'] = $this->getSiteId($record['site_id']);
			unset($record['deleted']);
			if($record['plugin'] === 'Core') {
				if($record['type'] === 'ContentLink') {
						$record['plugin'] = 'BcContentLink';
				} else {
						$record['plugin'] = 'BaserCore';
				}
			}
			if($record['plugin'] === 'Blog') $record['plugin'] = 'BcBlog';
			if($record['plugin'] === 'Mail') $record['plugin'] = 'BcMail';
			if($record['plugin'] === 'Uploader') $record['plugin'] = 'BcUploader';
			if (BcUtil::verpoint(BcUtil::getVersion()) <= BcUtil::verpoint('5.0.7')) {
				if (!empty($record['self_publish_begin'])) $record['self_publish_end'] = new FrozenTime($record['self_publish_begin']);
				if (!empty($record['self_publish_end'])) $record['self_publish_end'] = new FrozenTime($record['self_publish_end']);
				if (!empty($record['created_date'])) $record['created_date'] = new FrozenTime($record['created_date']);
				if (!empty($record['modified_date'])) $record['modified_date'] = new FrozenTime($record['modified_date']);
			}

			try {
				if(!$record['parent_id']) {
					$entity = $table->newEntity($record, ['validate' => false]);
				} else {
					$entity = $table->newEntity($record, ['validate' => false]);
				}
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('contents: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('contents: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * サイトIDを取得する
	 * @param $src
	 * @return int|mixed
	 */
	private function getSiteId($src)
	{
		if (!$src) return 1;
		if (isset($this->_siteIdMap[$src])) {
			return $this->_siteIdMap[$src];
		}
		return 1;
	}

	/**
	 * Update Plugin
	 */
	protected function _updatePlugin()
	{
		$table = $this->tableLocator->get('BaserCore.Plugins');
		$records = $this->readCsv('plugins');

		// コアプラグインを追加
		$targetPluginNames = Hash::extract($records, '{n}.name');
		$corePluginNames = \Cake\Core\Configure::read('BcApp.corePlugins');
		$corePlugins = [];
		foreach($corePluginNames as $key => $corePluginName) {
			if ($corePluginName === 'BcCustomContent') continue;
			if (in_array(preg_replace('/^Bc/', '', $corePluginName), $targetPluginNames)) continue;
			$plugin = $table->getPluginConfig($corePluginName);
			$corePlugins[] = [
				'name' => $corePluginName,
				'title' => $plugin? $plugin->title : $corePluginName,
				'db_inited' => true
			];
		}
		$records = array_merge($records, $corePlugins);

		$oldCorePlugins = ['Blog', 'Mail', 'Feed', 'Uploader'];
		foreach($records as $record) {
			if ($record['name'] === 'Feed') continue;
			if (in_array($record['name'], $oldCorePlugins)) {
				$record['name'] = 'Bc' . $record['name'];
			}
			if (in_array($record['name'], $corePluginNames)) {
				$record['version'] = \BaserCore\Utility\BcUtil::getVersion();
			}
			$record['db_init'] = $record['db_inited'];
			$record['status'] = false;
			$record['priority'] = $table->getMax('priority') + 1;
			unset($record['db_inited']);
			try {
				$entity = $table->patchEntity($table->newEmptyEntity(), $record, ['validate' => false]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('plugins: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('plugins: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * Update Page
	 */
	protected function _updatePage()
	{
		$table = $this->tableLocator->get('BaserCore.Pages');
		BcUtil::offEvent($table->getEventManager(), 'Model.afterMarshal');
		$table->searchIndexSaving = false;
		$records = $this->readCsv('pages');
		foreach($records as $record) {
			unset($record['code']);
			try {
				$entity = $table->newEntity($record, ['validate' => false]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('pages: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('pages: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * Update UserGroup
	 */
	protected function _updateUserGroup()
	{
		$userGroupsTable = $this->tableLocator->get('BaserCore.UserGroups');
		$userGroups = $this->readCsv('user_groups');
		$result = true;
		foreach($userGroups as $userGroup) {
			$authPrefixSettings = '';
			if ($userGroup['id'] !== '1') {
				$authPrefixSettings = '{"Admin":{"type":"2"},"Api/Admin":{"type":"2"}}';
			}
			$authPrefixArray = explode(',', $userGroup['auth_prefix']);
			$authPrefix = array_map('\Cake\Utility\Inflector::camelize', $authPrefixArray);
			$data = [
				'name' => $userGroup['name'],
				'title' => $userGroup['title'],
				'auth_prefix' => implode(',', $authPrefix),
				'use_move_contents' => $userGroup['use_move_contents'],
				'auth_prefix_settings' => $authPrefixSettings,
			];

			try {
				$entity = $userGroupsTable->newEntity($data, ['validate' => false]);
				$userGroupsTable->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('user_groups: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('user_groups: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return $result;
	}

	/**
	 * Update SiteConfig
	 */
	protected function _updateSiteConfig()
	{
		$records = $this->readCsv('site_configs');
		$table = $this->tableLocator->get('BaserCore.SiteConfigs');
		$excludes = [
			'mail_encode',
			'name',
			'keyword',
			'description',
			'theme',
			'use_universal_analytics',
			'category_permission',
			'main_site_display_name',
			'formal_name'
		];
		$newRecord = [];
		foreach($records as $record) {
			if(in_array($record['name'], $excludes)) continue;
			$newRecord[$record['name']] = ($record['value'])?? '';
		}
		$newRecord['version'] = BcUtil::getVersion();
		$newRecord['use_update_notice'] = true;
		$newRecord['outer_service_output_header'] = '';
		$newRecord['outer_service_output_footer'] = '';
		$newRecord['admin_theme'] = 'BcAdminThird';
		$newRecord['editor'] = 'BaserCore.BcCkeditor';

		if (!$table->saveKeyValue($newRecord)) {
			$this->log('site_configs のデータを保存できませんでした。', LogLevel::ERROR, 'migrate_db');
			return false;
		}
		return true;
	}

	/**
	 * ブログ記事
	 */
	protected function _updateBlogPost()
	{
		$table = $this->tableLocator->get('BcBlog.BlogPosts');
		BcUtil::offEvent($table->getEventManager(), 'Model.afterMarshal');
		$records = $this->readCsv('blog_posts');
		foreach($records as $record) {
			if (BcUtil::verpoint(BcUtil::getVersion()) > BcUtil::verpoint('5.0.7')) {
				$record['posted'] = $record['posts_date'];
			} else {
				if (!empty($record['posts_date'])) $record['posted'] = new FrozenTime($record['posts_date']);
				if (!empty($record['publish_begin'])) $record['publish_begin'] = new FrozenTime($record['publish_begin']);
				if (!empty($record['publish_end'])) $record['publish_end'] = new FrozenTime($record['publish_end']);
			}
			if (!$record['content']) $record['content'] = '';
			if (!$record['detail_draft']) $record['detail_draft'] = '';
			$record['title'] = $record['name'];
			unset($record['name'], $record['posts_date']);

			foreach($record as $key => $value) {
				if (is_null($value)) $record[$key] = '';
			}

			try {
				$entity = $table->patchEntity($table->newEmptyEntity(), $record, ['validate' => false]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('blog_posts: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('blog_posts: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * ブログカテゴリ
	 * @return bool
	 */
	protected function _updateBlogCategory()
	{
		$table = $this->tableLocator->get('BcBlog.BlogCategories');
		$records = $this->readCsv('blog_categories');
		foreach($records as $record) {
			unset($record['owner_id']);
			try {
			    $entity = $table->newEntity($record, ['validate' => false, 'accessibleFields' => ['id' => true]]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('blog_categories: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('blog_categories: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * メール設定
	 * @return bool
	 */
	protected function _updateMailConfig()
	{
		$table = $this->tableLocator->get('BcMail.MailConfigs');
		$records = $this->readCsv('mail_configs');
		$record = [];
		if(empty($records[0])) return true;
		foreach($records[0] as $key => $value) {
			if (in_array($key, ['id', 'modified', 'created'])) continue;
			$record[$key] = $value ?? '';
		}
		if (!$table->saveKeyValue($record)) {
			$this->log('mail_configs のデータを保存できませんでした。', LogLevel::ERROR, 'migrate_db');
			return false;
		}
		return true;
	}

	/**
	 * アクセスルールグループ
	 * @return bool
	 */
	protected function _updatePermissionGroups()
	{
		$service = $this->getService(PermissionGroupsServiceInterface::class);
		return $service->buildDefaultEtcRuleGroup('Admin', '管理システム');
	}

	/**
	 * アクセスルール
	 * @return bool
	 */
	protected function _updatePermissions()
	{
		$table = $this->tableLocator->get('BaserCore.Permissions');
		$records = $this->readCsv('permissions');
		foreach($records as $record) {
			if ($record['url'] === '/admin/*') continue;
			$record['permission_group_id'] = 1;
			$record['method'] = '*';
			$record['url'] = preg_replace('/^\/admin\/favorites\//', '/baser/admin/bc-favorite/favorites/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/editor_templates\//', '/baser/admin/bc-editor-template/editor_templates/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/pages\//', '/baser/admin/baser-core/pages/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/themes\//', '/baser/admin/baser-core/themes/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/content_folders\//', '/baser/admin/baser-core/content_folders/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/contents\//', '/baser/admin/baser-core/contents/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/dblogs\//', '/baser/admin/baser-core/dblogs/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/permissions\//', '/baser/admin/baser-core/permissions/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/plugins\//', '/baser/admin/baser-core/plugins/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/site_configs\//', '/baser/admin/baser-core/site_configs/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/sites\//', '/baser/admin/baser-core/sites/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/user_groups\//', '/baser/admin/baser-core/user_groups/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/users\//', '/baser/admin/baser-core/users/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/widget_areas\//', '/baser/admin/bc-widget-area/widget_areas/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/tools\//', '/baser/admin/baser-core/utilities/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/theme_files\//', '/baser/admin/bc-theme-file/theme_files/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/theme_folders\//', '/baser/admin/bc-theme-file/theme_folders/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/search_indices\//', '/baser/admin/bc-search-index/search_indexes/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/theme_configs\//', '/baser/admin/bc-theme-config/theme_configs/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/content_links\//', '/baser/admin/bc-content-link/content_links/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/blog\//', '/baser/admin/bc-blog/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/mail\//', '/baser/admin/bc-mail/', $record['url']);
			$record['url'] = preg_replace('/^\/admin\/uploader\//', '/baser/admin/bc-uploader/', $record['url']);
			try {
				$entity = $table->patchEntity($table->newEmptyEntity(), $record, ['validate' => false]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('permissions: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('permissions: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * Update User
	 * @return bool
	 */
	protected function _updateUser()
	{
		$this->tableLocator->get('BaserCore.UserGroups');
		$this->tableLocator->get('BaserCore.UsersUserGroups');
		$table = $this->tableLocator->get('BaserCore.Users');
		$records = $this->readCsv('users');
		foreach($records as $record) {
			$record['status'] = true;
			$record['user_groups']['_ids'] = [$record['user_group_id']];
			$this->newPassword = $record['password'] = Security::randomString(10);
			unset($record['user_group_id']);
			try {
				$entity = $table->patchEntity($table->newEmptyEntity(), $record, ['validate' => false]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('users: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('users: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}

	/**
	 * Update MailField
	 * @return bool
	 */
	protected function _updateMailField()
	{
		$table = $this->tableLocator->get('BcMail.MailFields');
		$records = $this->readCsv('mail_fields');
		foreach($records as $record) {
			$record['text_rows'] = $record['rows'];
			unset($record['rows']);
			if ($record['valid'] === 'VALID_NOT_EMPTY') $record['valid'] = 1;
			if ($record['valid'] === 'VALID_EMAIL') {
				$record['valid'] = 1;
				if (!empty($record['valid_ex'])) $record['valid_ex'] .= ',';
				$record['valid_ex'] .= 'VALID_EMAIL';
			}
			if ($record['valid'] === '/^(|[0-9]+)$/') {
				unset($record['valid']);
				if (!empty($record['valid_ex'])) $record['valid_ex'] .= ',';
				$record['valid_ex'] .= 'VALID_NUMBER';
			}
			if (!empty($record['valid']) && $record['valid'] === '/^([0-9]+)$/') {
				$record['valid'] = 1;
				if (!empty($record['valid_ex'])) $record['valid_ex'] .= ',';
				$record['valid_ex'] = 'VALID_NUMBER';
			}
			if (!empty($record['valid']) && $record['valid'] === '/^(|[0-9\-]+)$/') {
				unset($record['valid']);
				if (!empty($record['options'])) $record['options'] .= '|';
				$record['options'] .= 'regex|^(|[0-9\-]+)$';
			}
			if (!empty($record['valid']) && $record['valid'] === '/^([0-9\-]+)$/') {
				$record['valid'] = 1;
				if (!empty($record['options'])) $record['options'] .= '|';
				$record['options'] .= 'regex|^([0-9\-]+)$';
			}
			$validEx = $record['valid_ex']? explode(',', $record['valid_ex']) : [];
			if (in_array('VALID_NOT_UNCHECKED', $validEx)) {
				$record['valid'] = 1;
				$key = array_search('VALID_NOT_UNCHECKED', $validEx);
				if ($key !== false) unset($validEx[$key]);
				$record['valid_ex'] = implode(',', $validEx);
			}
			foreach($record as $key => $value) {
				if (is_null($value)) $record[$key] = '';
			}
			try {
				$entity = $table->patchEntity($table->newEmptyEntity(), $record, ['validate' => false]);
				$table->saveOrFail($entity);
			} catch (PersistenceException $e) {
				$this->log('mail_fields: ' . $e->getEntity()->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			} catch (\Throwable $e) {
				$this->log('mail_fields: ' . $e->getMessage(), LogLevel::ERROR, 'migrate_db');
				return false;
			}
		}
		return true;
	}
}
