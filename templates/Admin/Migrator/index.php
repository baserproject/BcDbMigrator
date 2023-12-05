<?php
/**
 * @var \BaserCore\View\BcAdminAppView $this
 * @var string $log
 */
?>


<script>
  $(function () {
    $("#BtnUpload").click(function () {
      $.bcUtil.showLoader();
    });
  });
</script>

<p>利用方法については、<a href="https://baserproject.github.io/5/migration_db_from_ver4" target="_blank">baserCMS４のデータベースを変換</a>をご覧ください。
<?php if (!empty($noticeMessage[0])): ?>
  <section class="bca-section">
    <p><?php echo implode('</li><li>', $noticeMessage) ?></p>
  </section>
<?php endif ?>

<?php echo $this->BcAdminForm->create(null, ['type' => 'file']) ?>

<section class="bca-section">
  <table class="bca-form-table" id="ListTable">
    <tr>
      <th class="bca-form-table__label">
        <?php echo $this->BcAdminForm->label('encoding', '文字コード') ?>
      </th>
      <td class="bca-form-table__input">
        <?php echo $this->BcAdminForm->control('encoding', ['type' => 'radio', 'options' => ['auto' => '自動判別', 'UTF-8' => 'UTF-8', 'SJIS-win' => 'SJIS'], 'value' => 'auto']) ?>
        <?php echo $this->BcAdminForm->error('encoding') ?>
      </td>
    </tr>
    <tr>
      <th class="bca-form-table__label">
        <?php echo $this->BcAdminForm->label('backup', 'バックアップファイル') ?>
      </th>
      <td class="bca-form-table__input">
        <?php echo $this->BcAdminForm->control('backup', ['type' => 'file']) ?>
        <?php echo $this->BcAdminForm->error('backup') ?>
      </td>
    </tr>
  </table>
</section>

<section class="bca-actions">
  <div class="bca-actions__main">
    <?php echo $this->BcAdminForm->submit('アップロード', [
      'div' => false,
      'class' => 'bca-btn bca-actions__item',
      'id' => 'BtnUpload',
      'data-bca-btn-type' => "save",
      'data-bca-btn-size' => "lg",
      'data-bca-btn-width' => "lg"
    ]) ?>
  </div>
  <div class="bca-actions__sub">
    <?php if ($this->getRequest()->getSession()->read('BcDbMigrator.file')): ?>
      　<?php $this->BcBaser->link('ダウンロード', ['action' => 'download'], ['class' => 'bca-btn']) ?>
    <?php endif ?>
  </div>
</section>

<?php echo $this->BcAdminForm->end() ?>

<section class="bca-section">
  <h2 class="bca-main__heading" data-bca-heading-size="lg">マイグレーションログ</h2>
</section>

<section class="bca-section">
  <?php echo $this->BcAdminForm->control('log', [
    'type' => 'textarea',
    'rows' => 10, 'value' => $log,
    'readonly' => 'readonly',
  ]) ?>
</section>

