<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2 id="ms-activity">行動履歴</h2>
<p class="ms-muted">新しい順に表示します。v1.6.0への更新後の行動を記録します。開封は画像読み込み、クリックは計測リンクへのアクセスの記録で、メールアプリやセキュリティソフトの自動アクセスも含まれます。</p>
<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="page" value="ms-subscribers">
	<input type="hidden" name="view" value="<?php echo (int) $subscriber->id; ?>">
	<label for="ms-activity-type">行動の種類</label>
	<select id="ms-activity-type" name="activity_type">
		<option value="">すべて</option>
		<?php foreach ( MS_Activity::labels() as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $activity_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="button">絞り込む</button>
</form>
<p><?php echo (int) $activity['total']; ?>件 / <?php echo (int) $activity['page']; ?>ページ目</p>
<table class="widefat striped ms-table">
	<thead><tr><th>日時</th><th>行動</th><th>対象メール</th><th>詳細</th></tr></thead>
	<tbody>
	<?php if ( ! $activity['rows'] ) : ?>
		<tr><td colspan="4">該当する行動履歴はまだありません。更新前の情報は下の「配信記録・初回反応」で確認できます。</td></tr>
	<?php endif; ?>
	<?php foreach ( $activity['rows'] as $event ) : ?>
		<tr>
			<td><?php echo esc_html( mysql2date( 'Y/n/j H:i:s', $event->created_at ) ); ?></td>
			<td><?php echo esc_html( MS_Activity::labels()[ $event->event_type ] ?? $event->event_type ); ?></td>
			<td><?php
				if ( $event->mail_type ) {
					echo 'broadcast' === $event->mail_type ? 'メルマガ' : 'ステップメール';
					echo '<br>' . esc_html( $event->subject ? $event->subject : '（件名なし）' );
				} else {
					echo '—';
				}
			?></td>
			<td style="overflow-wrap:anywhere;"><?php
				if ( $event->url ) {
					echo '<a href="' . esc_url( $event->url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $event->url ) . '</a>';
				} elseif ( 'admin_stopped' === $event->event_type ) {
					$actor = get_userdata( (int) $event->actor_id );
					echo $actor ? esc_html( $actor->display_name ) : '管理者（ユーザーID ' . (int) $event->actor_id . '）';
				} elseif ( 'sent' === $event->event_type ) {
					echo '送信処理が成功しました（受信完了の確認ではありません）';
				} else {
					echo '—';
				}
			?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<div class="tablenav"><div class="tablenav-pages">
	<?php
	$base = array( 'page' => 'ms-subscribers', 'view' => (int) $subscriber->id, 'activity_type' => $activity_filter );
	if ( $activity['page'] > 1 ) {
		echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base, array( 'activity_page' => $activity['page'] - 1 ) ), admin_url( 'admin.php' ) ) ) . '#ms-activity">← 前のページ</a> ';
	}
	if ( $activity['page'] < $activity['pages'] ) {
		echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base, array( 'activity_page' => $activity['page'] + 1 ) ), admin_url( 'admin.php' ) ) ) . '#ms-activity">次のページ →</a>';
	}
	?>
</div></div>
