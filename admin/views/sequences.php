<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ms-wrap">
	<h1><span class="dashicons dashicons-email-alt ms-h1-icon" aria-hidden="true"></span>シーケンス</h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['warn'] ) && 'unsub' === $_GET['warn'] ) : ?>
		<div class="notice notice-warning is-dismissible"><p>⚠ 本文に {unsubscribe_url} がありません。フッターに解除リンクは自動挿入されますが、本文内への明示挿入を推奨します(特定電子メール法)。</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['test'] ) ) : ?>
		<div class="notice <?php echo 'ok' === $_GET['test'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
			<p><?php echo 'ok' === $_GET['test'] ? 'テストメールを送信しました。受信箱を確認してください(SMTPプラグイン未設定の場合は届きません)。' : 'テスト送信に失敗しました。SMTPプラグインの設定を確認してください。'; ?></p>
			<?php $ms_err = get_option( 'ms_last_mail_error' ); if ( 'ok' !== $_GET['test'] && ! empty( $ms_err['message'] ) ) : ?>
				<p>エラー内容：<code><?php echo esc_html( $ms_err['message'] ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>ステップを削除しました(送信済みログは保持されます)。</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['seqdelete'] ) ) : ?>
		<div class="notice <?php echo 'ok' === $_GET['seqdelete'] ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo 'ok' === $_GET['seqdelete'] ? '空のシーケンスを削除しました。' : 'このシーケンスは空ではないため削除できません。'; ?></p></div>
	<?php endif; ?>

	<div class="ms-seq-header">
		<form method="get" class="ms-filter">
			<input type="hidden" name="page" value="ms-sequences">
			<select name="sequence_id" onchange="this.form.submit()">
				<?php foreach ( $sequences as $seq ) : ?>
					<option value="<?php echo (int) $seq->id; ?>" <?php selected( $sequence_id, (int) $seq->id ); ?>>
						<?php echo esc_html( $seq->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>

		<?php if ( $sequence ) : ?>
			<span class="ms-seq-status <?php echo 'active' === $sequence->status ? 'ms-status-active' : 'ms-status-paused'; ?>">
				<?php echo 'active' === $sequence->status ? '● 稼働中' : '■ 一時停止中(新規登録は受付・配信は停止)'; ?>
			</span>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ms_toggle_sequence&sequence_id=' . (int) $sequence_id ), 'ms_toggle_sequence' ) ); ?>">
				<?php echo 'active' === $sequence->status ? '一時停止する' : '稼働させる'; ?>
			</a>
			<?php if ( $can_delete_sequence ) : ?>
				<a class="button ms-delete" onclick="return confirm('この空のシーケンスを削除しますか?');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ms_delete_sequence&sequence_id=' . (int) $sequence_id ), 'ms_delete_sequence' ) ); ?>">このシーケンスを削除</a>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ms-inline-form">
			<input type="hidden" name="action" value="ms_add_sequence">
			<?php wp_nonce_field( 'ms_add_sequence' ); ?>
			<input type="text" name="sequence_name" placeholder="新しいシーケンス名" required>
			<button class="button">+ シーケンス追加</button>
		</form>
	</div>

	<table class="widefat striped ms-table">
		<thead>
			<tr><th>#</th><th>配信タイミング</th><th>件名</th><th>状態</th><th>成績(開封/CTR)</th><th>操作</th></tr>
		</thead>
		<tbody>
		<?php if ( empty( $steps ) ) : ?>
			<tr><td colspan="6">まだステップがありません。下のフォームから1通目を作成してください。</td></tr>
		<?php endif; ?>
		<?php foreach ( $steps as $step ) :
			$st = isset( $stats_by_id[ $step->id ] ) ? $stats_by_id[ $step->id ] : null; ?>
			<tr>
				<td><?php echo (int) $step->step_no; ?></td>
				<td><?php echo esc_html( MS_Admin::timing_label( (int) $step->send_offset, $step->send_hour ) ); ?></td>
				<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $sequence_id . '&step_id=' . (int) $step->id ) ); ?>"><?php echo esc_html( $step->subject ); ?></a></td>
				<td><?php echo 'active' === $step->status ? '稼働' : '<span class="ms-muted">下書き</span>'; ?></td>
				<td>
					<?php echo ( $st && null !== $st['open_rate'] ) ? esc_html( $st['open_rate'] ) . '% / ' . esc_html( $st['click_rate'] ) . '%' : '—'; ?>
				</td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ms_test_send&step_id=' . (int) $step->id . '&sequence_id=' . (int) $sequence_id ), 'ms_test_send' ) ); ?>">自分宛にテスト送信</a>
					<a class="button button-small ms-delete" onclick="return confirm('このステップを削除しますか?(送信済みログは残ります)');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ms_delete_step&step_id=' . (int) $step->id . '&sequence_id=' . (int) $sequence_id ), 'ms_delete_step' ) ); ?>">削除</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php
	// 編集フォーム(既存編集 or 新規追加)
	$is_edit  = ( null !== $edit_step );
	$offset   = $is_edit ? (int) $edit_step->send_offset : 24;
	$def_days = floor( $offset / 24 );
	$def_hrs  = $offset % 24;
	?>
	<h2><?php echo $is_edit ? 'ステップ編集: #' . (int) $edit_step->step_no : '+ ステップを追加'; ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ms-step-form">
		<input type="hidden" name="action" value="ms_save_step">
		<input type="hidden" name="sequence_id" value="<?php echo (int) $sequence_id; ?>">
		<input type="hidden" name="step_id" value="<?php echo $is_edit ? (int) $edit_step->id : 0; ?>">
		<?php wp_nonce_field( 'ms_save_step' ); ?>

		<table class="form-table">
			<tr>
				<th>配信タイミング</th>
				<td>
					登録から
					<input type="number" name="send_offset_days" min="0" max="365" value="<?php echo (int) $def_days; ?>" class="small-text"> 日
					<input type="number" name="send_offset_hours" min="0" max="23" value="<?php echo (int) $def_hrs; ?>" class="small-text"> 時間後
					&nbsp;/&nbsp; 配信時刻に丸める:
					<select name="send_hour">
						<option value="">丸めない(オフセット通り)</option>
						<?php for ( $h = 0; $h < 24; $h++ ) : ?>
							<option value="<?php echo $h; ?>" <?php echo ( $is_edit && null !== $edit_step->send_hour && (int) $edit_step->send_hour === $h ) ? 'selected' : ''; ?>>
								<?php echo $h; ?>:00
							</option>
						<?php endfor; ?>
					</select>
					<p class="description">例:「3日0時間後」+「8:00に丸める」= 登録から3日後の朝8時に配信(深夜登録者への深夜配信を防止)</p>
				</td>
			</tr>
			<tr>
				<th>件名</th>
				<td><input type="text" name="subject" class="large-text" required value="<?php echo $is_edit ? esc_attr( $edit_step->subject ) : ''; ?>">
				<p class="description">最重要要素を先頭に、13文字前後を目安に。</p></td>
			</tr>
			<tr>
				<th>本文</th>
				<td>
					<?php
					wp_editor(
						$is_edit ? $edit_step->body_html : '',
						'body_html',
						array( 'textarea_name' => 'body_html', 'textarea_rows' => 16, 'media_buttons' => false )
					);
					?>
					<p class="description">差し込みタグ: <code>{name}</code>(お名前) / <code>{unsubscribe_url}</code>(解除URL)。フッターの送信者情報+解除リンクは自動挿入されます。</p>
				</td>
			</tr>
			<tr>
				<th>状態</th>
				<td>
					<label><input type="radio" name="step_status" value="draft" <?php checked( ! $is_edit || 'draft' === $edit_step->status ); ?>> 下書き(配信しない)</label>&nbsp;&nbsp;
					<label><input type="radio" name="step_status" value="active" <?php checked( $is_edit && 'active' === $edit_step->status ); ?>> 稼働(配信する)</label>
				</td>
			</tr>
		</table>

		<p>
			<button class="button button-primary button-large"><?php echo $is_edit ? '保存する' : 'ステップを追加する'; ?></button>
			<?php if ( $is_edit ) : ?>
				<a class="button button-large" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ms_test_send&step_id=' . (int) $edit_step->id . '&sequence_id=' . (int) $sequence_id ), 'ms_test_send' ) ); ?>">自分宛にテスト送信</a>
				<a class="button button-large" href="<?php echo esc_url( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $sequence_id ) ); ?>">新規追加に戻る</a>
			<?php endif; ?>
		</p>
	</form>

	<p class="ms-muted">登録フォームの設置：<a href="<?php echo esc_url( admin_url( 'admin.php?page=ms-form&sequence_id=' . (int) $sequence_id ) ); ?>">「登録フォーム」画面</a>でショートコードを作ってコピーし、固定ページやLPに貼り付けてください。</p>
</div>
