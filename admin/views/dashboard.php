<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ms-wrap">
	<h1>📧 メール侍 ダッシュボード</h1>

	<form method="get" class="ms-filter">
		<input type="hidden" name="page" value="ms-dashboard">
		<select name="sequence_id" onchange="this.form.submit()">
			<?php foreach ( $sequences as $seq ) : ?>
				<option value="<?php echo (int) $seq->id; ?>" <?php selected( $sequence_id, (int) $seq->id ); ?>>
					<?php echo esc_html( $seq->name ); ?><?php echo 'paused' === $seq->status ? '(停止中)' : ''; ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="days" onchange="this.form.submit()">
			<option value="7"  <?php selected( $days, 7 ); ?>>直近7日</option>
			<option value="30" <?php selected( $days, 30 ); ?>>直近30日</option>
			<option value="90" <?php selected( $days, 90 ); ?>>直近90日</option>
		</select>
	</form>

	<div class="ms-kpi-row">
		<div class="ms-kpi-card">
			<span class="ms-kpi-label">新規登録</span>
			<span class="ms-kpi-value"><?php echo (int) $kpi['new_subs']; ?></span>
			<span class="ms-kpi-sub">前期間: <?php echo (int) $kpi['prev_subs']; ?></span>
		</div>
		<div class="ms-kpi-card">
			<span class="ms-kpi-label">平均開封率 ※</span>
			<span class="ms-kpi-value"><?php echo esc_html( $kpi['open_rate'] ); ?>%</span>
			<span class="ms-kpi-sub">送信 <?php echo (int) $kpi['sent']; ?> 通</span>
		</div>
		<div class="ms-kpi-card">
			<span class="ms-kpi-label">平均クリック率</span>
			<span class="ms-kpi-value"><?php echo esc_html( $kpi['click_rate'] ); ?>%</span>
			<span class="ms-kpi-sub">行動判定の主指標</span>
		</div>
		<div class="ms-kpi-card">
			<span class="ms-kpi-label">解除率</span>
			<span class="ms-kpi-value <?php echo $kpi['unsub_rate'] > 0.2 ? 'ms-danger' : ''; ?>"><?php echo esc_html( $kpi['unsub_rate'] ); ?>%</span>
			<span class="ms-kpi-sub">危険ライン 0.2%</span>
		</div>
	</div>

	<h2>ステップ通過レポート</h2>
	<table class="widefat striped ms-table">
		<thead>
			<tr>
				<th>#</th><th>配信タイミング</th><th>件名</th><th>状態</th>
				<th>送信</th><th>開封率</th><th>CTR</th><th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $report ) ) : ?>
			<tr><td colspan="8">まだステップがありません。「シーケンス」画面から作成してください。</td></tr>
		<?php endif; ?>
		<?php foreach ( $report as $r ) : ?>
			<tr class="<?php echo $r['warn'] ? 'ms-row-warn' : ''; ?>">
				<td><?php echo (int) $r['step_no']; ?></td>
				<td><?php echo esc_html( MS_Admin::timing_label( $r['send_offset'], $r['send_hour'] ) ); ?></td>
				<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $sequence_id . '&step_id=' . (int) $r['step_id'] ) ); ?>"><?php echo esc_html( $r['subject'] ); ?></a></td>
				<td><?php echo 'active' === $r['step_status'] ? '稼働' : '<span class="ms-muted">下書き</span>'; ?></td>
				<td><?php echo (int) $r['sent']; ?></td>
				<td><?php echo null === $r['open_rate'] ? '—' : esc_html( $r['open_rate'] ) . '%'; ?></td>
				<td><?php echo null === $r['click_rate'] ? '—' : esc_html( $r['click_rate'] ) . '%'; ?></td>
				<td><?php echo $r['warn'] ? '⚠ 前の通より開封率が10pt以上低下' : ''; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="ms-muted">※ 開封率はApple Mail等のプリフェッチによる水増しを含むため、絶対値ではなく通ごとの相対比較にのみ使用してください。エンゲージメント判定(リスト清掃等)はクリック基準で行います。</p>
</div>
