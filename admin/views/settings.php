<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$last_cron = (int) get_option( 'ms_last_cron', 0 );
$cron_ok   = $last_cron && ( time() - $last_cron ) < 15 * MINUTE_IN_SECONDS;
$cron_ago  = $last_cron ? human_time_diff( $last_cron, time() ) . '前' : 'まだ実行されていません';
?>
<div class="wrap ms-wrap">
	<h1>📧 メール侍 設定</h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>
	<?php endif; ?>

	<div class="ms-cron-status <?php echo $cron_ok ? 'ms-cron-ok' : 'ms-cron-ng'; ?>">
		<strong>Cron状態: <?php echo $cron_ok ? '● 正常' : '● 停止の可能性'; ?></strong>
		(最終実行: <?php echo esc_html( $cron_ago ); ?>)
		<?php if ( ! $cron_ok ) : ?>
			<p>配信キューが動いていない可能性があります。サイトへのアクセスがあれば動き出しますが、確実な定時配信のためには下記の「サーバーcron推奨設定」を行ってください。</p>
		<?php endif; ?>
	</div>

	<?php if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) : ?>
		<div class="notice notice-warning inline">
			<p><strong>⚠ サーバーcron推奨設定(未設定)</strong><br>
			WP-Cronはサイトにアクセスがあった時しか動かず、深夜などは配信が数時間遅延します。締切ファネル運用では致命的なため、以下を推奨します。</p>
			<ol>
				<li><code>wp-config.php</code> に <code>define( 'DISABLE_WP_CRON', true );</code> を追記</li>
				<li>サーバーパネルのcron設定で5分おきに実行:<br>
				<code>cd <?php echo esc_html( ABSPATH ); ?>; php wp-cron.php > /dev/null 2>&1</code><br>
				(Xserverの場合: サーバーパネル > Cron設定。最短間隔が長い場合は可能な最短で設定)</li>
			</ol>
		</div>
	<?php else : ?>
		<div class="notice notice-success inline"><p>✅ DISABLE_WP_CRON 設定済み。サーバーcronで wp-cron.php が5分おきに実行されていることを確認してください。</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ms_save_settings">
		<?php wp_nonce_field( 'ms_save_settings' ); ?>

		<h2>送信設定</h2>
		<p class="ms-muted">SMTP接続そのものはこのプラグインでは行いません。<strong>WP Mail SMTP / FluentSMTP 等の送信プラグイン</strong>をインストールし、そちらでSMTPサーバー(Xserver / SendGrid / Resend等)を設定してください。送信インフラの乗り換えはそちらの設定変更だけで完了します。</p>

		<table class="form-table">
			<tr>
				<th><label for="ms_from_name">送信元名</label></th>
				<td><input type="text" id="ms_from_name" name="ms_from_name" class="regular-text" value="<?php echo esc_attr( get_option( 'ms_from_name' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ms_from_email">送信元アドレス</label></th>
				<td><input type="email" id="ms_from_email" name="ms_from_email" class="regular-text" value="<?php echo esc_attr( get_option( 'ms_from_email' ) ); ?>">
				<p class="description">SPF/DKIM/DMARCを設定した独自ドメインのアドレスを使用してください(到達率の生命線)。</p></td>
			</tr>
			<tr>
				<th><label for="ms_batch_size">バッチサイズ</label></th>
				<td><input type="number" id="ms_batch_size" name="ms_batch_size" min="1" max="500" class="small-text" value="<?php echo esc_attr( get_option( 'ms_batch_size', 50 ) ); ?>"> 通 / 5分
				<p class="description">既定50通(=最大600通/時)。Xserverの上限1,500通/時に対して安全マージンを確保しつつ、連続大量送信によるスパム判定を回避します。</p></td>
			</tr>
			<tr>
				<th>トラッキング</th>
				<td>
					<label><input type="checkbox" name="ms_track_open" value="1" <?php checked( get_option( 'ms_track_open', 1 ), 1 ); ?>> 開封トラッキング(1pxピクセル)</label><br>
					<label><input type="checkbox" name="ms_track_click" value="1" <?php checked( get_option( 'ms_track_click', 1 ), 1 ); ?>> クリックトラッキング(リンク書き換え)</label>
				</td>
			</tr>
		</table>

		<p><button class="button button-primary button-large">設定を保存</button></p>
	</form>
</div>
