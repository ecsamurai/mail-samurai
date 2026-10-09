<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ms-wrap">
	<h1><span class="dashicons dashicons-email-alt ms-h1-icon" aria-hidden="true"></span>メルマガ</h1>

	<?php if ( isset( $_GET['queued'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo (int) $_GET['queued']; ?>名へのメルマガを送信キューに追加しました。既存のcronが5分ごとに、設定した送信上限の範囲で配信します。</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['test'] ) ) : ?>
		<?php if ( 'ok' === $_GET['test'] ) : ?>
			<div class="notice notice-success is-dismissible"><p>テストメールを <strong><?php echo esc_html( $test_email ); ?></strong> に送りました。見た目、商品などへのリンク、解除リンクを確認してください。(テストでは開封・クリックは計測しません)</p></div>
		<?php elseif ( 'bademail' === $_GET['test'] ) : ?>
			<div class="notice notice-error is-dismissible"><p>テスト送信先のメールアドレスが正しくありません。</p></div>
		<?php else : ?>
			<div class="notice notice-error is-dismissible"><p>テスト送信に失敗しました。SMTPプラグインの設定を確認してください。</p>
			<?php $ms_err = get_option( 'ms_last_mail_error' ); if ( ! empty( $ms_err['message'] ) ) : ?><p>エラー内容：<code><?php echo esc_html( $ms_err['message'] ); ?></code></p><?php endif; ?></div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p>メルマガを作成できませんでした。配信リストと件名を確認してください。</p></div>
	<?php endif; ?>

	<p class="ms-muted">この画面は、選んだ配信リストの「現在購読中」の方へ一度だけ送るメール用です。解除済み・バウンス済みの方には送りません。配信解除リンクと送信者情報はメール末尾に自動で入り、設定で有効にした開封・クリックを計測します。</p>

	<?php if ( empty( $sequences ) ) : ?>
		<div class="notice notice-warning inline"><p>先に「シーケンス」画面から配信リストを作成してください。</p></div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ms-step-form">
			<input type="hidden" name="action" value="ms_send_broadcast">
			<?php wp_nonce_field( 'ms_send_broadcast' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="ms-broadcast-sequence">配信リスト</label></th>
					<td><select id="ms-broadcast-sequence" name="sequence_id">
						<?php foreach ( $sequences as $sequence ) : ?>
							<option value="<?php echo (int) $sequence->id; ?>" <?php selected( (int) $draft['sequence_id'], (int) $sequence->id ); ?>><?php echo esc_html( $sequence->name ); ?><?php echo 'paused' === $sequence->status ? ' (停止中)' : ''; ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th><label for="ms-broadcast-subject">件名</label></th>
					<td><input type="text" id="ms-broadcast-subject" name="subject" class="large-text" required value="<?php echo esc_attr( $draft['subject'] ); ?>"></td>
				</tr>
				<tr>
					<th>本文</th>
					<td><?php wp_editor( $draft['body_html'], 'ms_broadcast_body', array( 'textarea_name' => 'body_html', 'textarea_rows' => 16, 'media_buttons' => false ) ); ?>
					<p class="description">差し込みタグ: <code>{name}</code> / <code>{unsubscribe_url}</code></p></td>
				</tr>
			</table>
			<div class="ms-test-box">
				<h3>送る前に確認する</h3>
				<p class="ms-muted">実際に届くメールと同じ見た目で確認できます。テストでは開封・クリックを計測せず、リンクはそのまま本来のページに飛びます。解除リンクを押しても誰も解除されません。</p>
				<p>
					<button type="submit" name="ms_mode" value="preview" class="button" formtarget="_blank">プレビューを見る(新しいタブ)</button>
				</p>
				<p class="ms-test-row">
					<label for="ms-test-email">テスト送信先</label>
					<input type="email" id="ms-test-email" name="ms_test_email" class="regular-text" value="<?php echo esc_attr( $test_email ); ?>">
					<button type="submit" name="ms_mode" value="test" class="button">このアドレスにテスト送信</button>
				</p>
				<p class="ms-muted">入力した内容は下書きとして保存されるので、テスト送信のあとも消えません。</p>
			</div>
			<p><button type="submit" name="ms_mode" value="send" class="button button-primary button-large" onclick="return confirm('現在購読中の全員へ送信キューを作成します。よろしいですか?');">この内容で全員に送信する</button></p>
		</form>
	<?php endif; ?>

	<h2>最近のメルマガ</h2>
	<table class="widefat striped ms-table">
		<thead><tr><th>作成日時</th><th>配信リスト</th><th>件名</th><th>状態</th><th>送信状況</th><th>開封率 ※</th><th>CTR</th></tr></thead>
		<tbody>
		<?php if ( empty( $broadcasts ) ) : ?><tr><td colspan="7">まだメルマガはありません。</td></tr><?php endif; ?>
		<?php foreach ( $broadcasts as $broadcast ) :
			$sequence = MS_DB::get_sequence( $broadcast->sequence_id );
			$sent = (int) $broadcast->sent;
			$open_rate = $sent ? round( 100 * (int) $broadcast->opened / $sent, 1 ) : null;
			$click_rate = $sent ? round( 100 * (int) $broadcast->clicked / $sent, 1 ) : null; ?>
			<tr>
				<td><?php echo esc_html( mysql2date( 'Y/n/j H:i', $broadcast->created_at ) ); ?></td>
				<td><?php echo $sequence ? esc_html( $sequence->name ) : '削除済み'; ?></td>
				<td><?php echo esc_html( $broadcast->subject ); ?></td>
				<td><?php echo 'completed' === $broadcast->status ? '完了' : '送信待ち'; ?></td>
				<td><?php echo (int) $broadcast->sent; ?> / <?php echo (int) $broadcast->recipients; ?> 通<?php echo $broadcast->failed ? ' (失敗 ' . (int) $broadcast->failed . ')' : ''; ?></td>
				<td><?php echo null === $open_rate ? '—' : esc_html( $open_rate ) . '%'; ?></td>
				<td><?php echo null === $click_rate ? '—' : esc_html( $click_rate ) . '%'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="ms-muted">※ 開封率はメールアプリの自動画像読み込みで高めに出ることがあります。リンクを含む回は、クリック率も併せて判断してください。</p>
</div>
