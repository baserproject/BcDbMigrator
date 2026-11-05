<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace BcDbMigrator\Test\TestCase\Controller\Component;

use BaserCore\TestSuite\BcTestCase;
use BcDbMigrator\Controller\Component\BcDbMigrator5Component;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Http\ServerRequest;

/**
 * BcDbMigrator5Component のテスト
 */
class BcDbMigrator5ComponentTest extends BcTestCase
{

    /**
     * @var BcDbMigrator5Component
     */
    public $BcDbMigrator5;

    /**
     * set up
     */
    public function setUp(): void
    {
        parent::setUp();
        $controller = new Controller(new ServerRequest());
        $this->BcDbMigrator5 = new BcDbMigrator5Component(new ComponentRegistry($controller));
    }

    /**
     * tear down
     */
    public function tearDown(): void
    {
        unset($this->BcDbMigrator5);
        parent::tearDown();
    }

    /**
     * 移行先に seo_metas があるときは、そのまま使う
     */
    public function test_prepareSeoMetas_exists()
    {
        $this->assertTrue($this->hasSeoMetas());
        $this->assertTrue($this->execPrivateMethod($this->BcDbMigrator5, '_prepareSeoMetas'));
        $this->assertTrue($this->hasSeoMetas());
    }

    /**
     * 移行先に seo_metas が無いときは、BcSeo のマイグレーションを流して作る
     */
    public function test_prepareSeoMetas_create()
    {
        $this->dropSeoMetas();
        $this->assertFalse($this->hasSeoMetas());
        $this->assertTrue($this->execPrivateMethod($this->BcDbMigrator5, '_prepareSeoMetas'));
        $this->assertTrue($this->hasSeoMetas());
    }

    /**
     * startup：seo_metas が無くても作って、BcSeo を変換の対象にする
     */
    public function test_startup_withoutSeoMetas()
    {
        $this->dropSeoMetas();
        $this->BcDbMigrator5->startup(new Event('Controller.startup', $this->BcDbMigrator5->getController()));
        $this->assertTrue($this->hasSeoMetas());
        $plugins = $this->getPrivateProperty($this->BcDbMigrator5, '_defaultPlugins');
        $this->assertContains('BcSeo', $plugins);
    }

    /**
     * 移行先に seo_metas があるかどうか
     * @return bool
     */
    private function hasSeoMetas(): bool
    {
        $db = ConnectionManager::get('default');
        return in_array($db->config()['prefix'] . 'seo_metas', $db->getSchemaCollection()->listTables());
    }

    /**
     * seo_metas と BcSeo のマイグレーション履歴を消し、BcSeo を入れる前の状態にする
     *
     * 履歴を残したままだとマイグレーションが流れないため、両方消す。
     * テストの中で作り直すため、終了後も履歴と表は揃った状態に戻る。
     */
    private function dropSeoMetas(): void
    {
        $db = ConnectionManager::get('default');
        $prefix = $db->config()['prefix'];
        $tables = $db->getSchemaCollection()->listTables();
        if (in_array('bc_seo_phinxlog', $tables)) {
            $db->execute('DELETE FROM bc_seo_phinxlog');
        }
        $db->execute('DROP TABLE IF EXISTS ' . $prefix . 'seo_metas');
    }

}
