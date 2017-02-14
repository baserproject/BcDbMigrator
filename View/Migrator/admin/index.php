<script>
$(function(){
	$("#BtnUpload").click(function(){
		$.bcUtil.showLoader();
	});
});
</script>

<?php if(!empty($noticeMessage[0])): ?>
	<div class="panel-box">
		<h2>注意事項</h2>
		<ul>
			<li><?php echo implode('</li><li>', $noticeMessage) ?></li>
		</ul>
	</div>
<?php endif ?>

<?php echo $this->BcForm->create('Migrator', array('type' => 'file')) ?>

<table cellpadding="0" cellspacing="0" class="list-table" id="ListTable">
	<tr>
		<th class="col-head"><span class="required">*</span>&nbsp;<?php echo $this->BcForm->label('Migrator.backup', 'バックアップファイル') ?></th>
		<td class="col-input">
			<?php echo $this->BcForm->input('Migrator.backup', array('type' => 'file')) ?>
			<?php echo $this->BcForm->error('Migrator.backup') ?>
		</td>
	</tr>
</table>

<div class="submit">
	<?php echo $this->BcForm->submit('アップロード', array('div' => false, 'class' => 'button', 'id' => 'BtnUpload')) ?>
<?php if($this->Session->read('BcDbMigrator.file')): ?>
	　<?php echo $this->BcBaser->link('ダウンロード', array('action' => 'download'), array('class' => 'button')) ?>
<?php endif ?>
</div>

<?php echo $this->BcForm->end() ?>