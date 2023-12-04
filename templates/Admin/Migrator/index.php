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

<section class="bca-section">
	<?php if (!empty($noticeMessage[0])): ?>
			<div class="panel-box">
				<h2>注意事項</h2>
				<ul>
					<li><?php echo implode('</li><li>', $noticeMessage) ?></li>
				</ul>
			</div>
	<?php endif ?>
</section>

<section class="bca-section">
	<?php echo $this->BcAdminForm->create(null, ['type' => 'file']) ?>
	
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
	<?php echo $this->BcAdminForm->control('log', [
		'type' => 'textarea', 
		'rows' => 10, 'value' => $log,
		'readonly' => 'readonly'
	]) ?>
</section>
		
