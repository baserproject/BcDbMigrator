<p>baserCMS 2.1.0 以上のバックアップデータの basrCMS 3.0.0 への変換のみサポート</p>

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
	<?php echo $this->BcForm->submit('アップロード', array('div' => false, 'class' => 'button')) ?>
<?php if($this->Session->read('BcDbMigrator.file')): ?>
	　<?php echo $this->BcBaser->link('ダウンロード', array('action' => 'download'), array('class' => 'button')) ?>
<?php endif ?>
</div>

<?php echo $this->BcForm->end() ?>