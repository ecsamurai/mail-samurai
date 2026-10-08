<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ms-wrap">
	<h1><span class="dashicons dashicons-email-alt ms-h1-icon" aria-hidden="true"></span>購読者(全 <?php echo (int) $total; ?> 件)</h1>

	<form method="get" class="ms-filter">
		<input type="hidden" name="page" value="ms-subscribers">
		<input type="search" name="s" placeholder="メールアドレスで検索" value="<?php echo esc_attr( $search ); ?>">
		<select name="status">
			<option value="">すべての状態</option>
			<option value="subscribed"   <?php selected( $status, 'subscribed' ); ?>>購読中</option>
			<option value="unsubscribed" <?php selected( $status, 'unsubscribed' ); ?>>解除</option>
			<option value="bounced"      <?php selected( $status, 'bounced' ); ?>>バウンス</option>
		</select>
		<button class="button">絞り込む</button>
	</form>

	<table class="widefat striped ms-table">
		<thead>
			<tr><th>メールアドレス</th><th>状態</th><th>登録日</th><th>流入元</th><th>進捗</th><th>最終反応</th></tr>
		</thead>
		<tbody>
		<?php if ( empty( $subscribers ) ) : ?>
			<tr><td colspan="6">購読者がいません。</td></tr>
		<?php endif; ?>
		<?php foreach ( $subscribers as $sub ) :
			$p = isset( $progress[ $sub->id ] ) ? $progress[ $sub->id ] : null;
			$last = ( $p && $p->last_reaction && '1970-01-01 00:00:00' !== $p->last_reaction )
				? mysql2date( 'n/j H:i', $p->last_reaction ) . ( $p->has_click ? ' 🔗' : ' 👀' )
				: '—';
			$label = array( 'subscribed' => '購読中', 'unsubscribed' => '<span class="ms-muted">解除</span>', 'bounced' => '<span class="ms-danger">バウンス</span>' );
		?>
			<tr>
				<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ms-subscribers&view=' . (int) $sub->id ) ); ?>"><?php echo esc_html( $sub->email ); ?></a></td>
				<td><?php echo isset( $label[ $sub->status ] ) ? $label[ $sub->status ] : esc_html( $sub->status ); ?></td>
				<td><?php echo esc_html( mysql2date( 'Y/n/j', $sub->created_at ) ); ?></td>
				<td><?php echo $sub->source ? esc_html( $sub->source ) : '—'; ?></td>
				<td><?php echo $p ? '#' . (int) $p->sent_count . ' / ' . (int) $p->total_count : '—'; ?></td>
				<td><?php echo $last; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php
	$pages = (int) ceil( $total / $per );
	if ( $pages > 1 ) :
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo paginate_links( array(
			'base'    => add_query_arg( 'paged', '%#%' ),
			'format'  => '',
			'current' => $paged,
			'total'   => $pages,
		) );
		echo '</div></div>';
	endif;
	?>
	<p class="ms-muted">👀 = 開封 / 🔗 = クリック。リスト清掃(60〜90日無反応者の停止)はクリック・開封ともにない人を対象にしてください。</p>
</div>
