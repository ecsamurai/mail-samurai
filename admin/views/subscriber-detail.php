<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ms-wrap">
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ms-subscribers' ) ); ?>">← 購読者一覧へ戻る</a></p>

	<?php if ( ! $subscriber ) : ?>
		<div class="notice notice-error"><p>購読者が見つかりません。</p></div>
	<?php else : ?>

	<h1><span class="dashicons dashicons-email-alt ms-h1-icon" aria-hidden="true"></span><?php echo esc_html( $subscriber->email ); ?></h1>
	<p>
		状態: <strong><?php echo 'subscribed' === $subscriber->status ? '購読中' : ( 'unsubscribed' === $subscriber->status ? '解除済み' : 'バウンス' ); ?></strong>
		/ お名前: <?php echo $subscriber->name ? esc_html( $subscriber->name ) : '—'; ?>
		/ 流入元: <?php echo $subscriber->source ? esc_html( $subscriber->source ) : '—'; ?>
		/ 登録: <?php echo esc_html( mysql2date( 'Y/n/j H:i', $subscriber->created_at ) ); ?>
	</p>

	<?php include MS_PLUGIN_DIR . 'admin/views/subscriber-activity.php'; ?>

	<h2>配信記録・初回反応</h2>
	<p class="ms-muted">更新前からの記録も表示します。開封・クリック欄はメールごとの最初の反応です。クリック時に初回開封が補完される場合があります。下の解除日時だけでは、本人の解除と管理者の停止を区別できません。</p>
	<table class="widefat striped ms-table">
		<thead><tr><th>日時</th><th>通</th><th>件名</th><th>状態</th><th>開封</th><th>クリック</th></tr></thead>
		<tbody>
			<tr>
				<td><?php echo esc_html( mysql2date( 'n/j H:i', $subscriber->created_at ) ); ?></td>
				<td colspan="5"><strong>✅ 登録</strong></td>
			</tr>
			<?php foreach ( $timeline as $t ) :
			$status_map = array( 'pending' => '⏳ 配信予定', 'sending' => '📨 送信中', 'sent' => '📤 送信済み', 'failed' => '❌ 失敗', 'skipped' => '⏭ スキップ' );
			?>
			<tr>
				<td><?php echo esc_html( mysql2date( 'n/j H:i', $t->sent_at ? $t->sent_at : $t->scheduled_at ) ); ?></td>
				<td><?php echo 'broadcast' === $t->mail_type ? 'メルマガ' : '#' . (int) $t->step_no; ?></td>
				<td><?php echo esc_html( $t->subject ); ?></td>
				<td><?php echo isset( $status_map[ $t->status ] ) ? $status_map[ $t->status ] : esc_html( $t->status ); ?></td>
				<td><?php echo $t->opened_at ? '👀 ' . esc_html( mysql2date( 'n/j H:i', $t->opened_at ) ) : '—'; ?></td>
				<td><?php echo $t->clicked_at ? '🔗 ' . esc_html( mysql2date( 'n/j H:i', $t->clicked_at ) ) . '<br><span class="ms-muted">' . esc_html( $t->clicked_url ) . '</span>' : '—'; ?></td>
			</tr>
			<?php endforeach; ?>
			<?php if ( $subscriber->unsubscribed_at ) : ?>
			<tr>
				<td><?php echo esc_html( mysql2date( 'n/j H:i', $subscriber->unsubscribed_at ) ); ?></td>
				<td colspan="5"><strong class="ms-muted">🚪 配信解除</strong></td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( 'subscribed' === $subscriber->status ) : ?>
		<p>
			<a class="button ms-delete" onclick="return confirm('この購読者への配信を停止しますか?');"
			   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ms_unsub_subscriber&subscriber_id=' . (int) $subscriber->id ), 'ms_unsub_subscriber' ) ); ?>">
				このアドレスを配信停止する
			</a>
		</p>
	<?php endif; ?>

	<?php endif; ?>
</div>
