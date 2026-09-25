<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 登録フォーム。
 * 使い方: [mailsamurai_form sequence="1" source="lp-a" button="無料で受け取る"]
 */
class MS_Form {

	public function __construct() {
		add_shortcode( 'mailsamurai_form', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_ms_subscribe', array( $this, 'handle_submit' ) );
		add_action( 'admin_post_ms_subscribe', array( $this, 'handle_submit' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array(
			'sequence' => 1,
			'source'   => '',
			'button'   => '登録する',
			'name'     => 'yes', // name="no" で名前欄を省略(入力項目削減=CVR約2倍の定石)
		), $atts );

		$result = isset( $_GET['ms_result'] ) ? sanitize_text_field( $_GET['ms_result'] ) : '';

		ob_start();
		?>
		<div class="ms-form-wrap">
			<?php if ( 'ok' === $result ) : ?>
				<p class="ms-form-success" style="color:#2e7d32; font-weight:bold;">ご登録ありがとうございます!メールをお送りしますので、受信箱をご確認ください。</p>
			<?php elseif ( 'already' === $result ) : ?>
				<p class="ms-form-notice" style="color:#f57f17;">すでにご登録いただいています。</p>
			<?php elseif ( 'error' === $result ) : ?>
				<p class="ms-form-error" style="color:#c62828;">登録に失敗しました。メールアドレスをご確認のうえ、もう一度お試しください。</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ms_subscribe">
				<input type="hidden" name="sequence_id" value="<?php echo esc_attr( (int) $atts['sequence'] ); ?>">
				<input type="hidden" name="source" value="<?php echo esc_attr( $atts['source'] ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>">

				<?php
				// 設計メモ: 公開登録フォームには意図的にnonceを使わない。
				// LPはページキャッシュされることが多く、キャッシュされたnonceは
				// 12〜24時間で失効して全登録が失敗する(実運用で頻発する事故)。
				// 登録は権限を伴う操作ではないためCSRF保護の実益はなく、
				// bot対策は下のハニーポットで行う。
				?>
				<?php // ハニーポット(botはこれを埋める) ?>
				<p style="position:absolute; left:-9999px;" aria-hidden="true">
					<input type="text" name="ms_website" tabindex="-1" autocomplete="off">
				</p>

				<?php if ( 'no' !== $atts['name'] ) : ?>
					<p><input type="text" name="ms_name" placeholder="お名前" style="width:100%; padding:12px;"></p>
				<?php endif; ?>
				<p><input type="email" name="ms_email" placeholder="メールアドレス" required style="width:100%; padding:12px;"></p>
				<p><button type="submit" style="width:100%; padding:14px; cursor:pointer;"><?php echo esc_html( $atts['button'] ); ?></button></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public function handle_submit() {
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : home_url( '/' );

		// ハニーポット(nonceはキャッシュ互換性のため不使用。フォーム側コメント参照)
		if ( ! empty( $_POST['ms_website'] ) ) {
			// bot:成功したフリをして捨てる
			wp_safe_redirect( add_query_arg( 'ms_result', 'ok', $redirect ) );
			exit;
		}

		$result = MS_Subscriber::subscribe(
			isset( $_POST['ms_email'] ) ? $_POST['ms_email'] : '',
			isset( $_POST['ms_name'] ) ? $_POST['ms_name'] : '',
			isset( $_POST['sequence_id'] ) ? (int) $_POST['sequence_id'] : 0,
			isset( $_POST['source'] ) ? $_POST['source'] : ''
		);

		if ( is_wp_error( $result ) ) {
			$code = ( 'already' === $result->get_error_code() ) ? 'already' : 'error';
			wp_safe_redirect( add_query_arg( 'ms_result', $code, $redirect ) );
			exit;
		}

		// Day0メール(send_offset=0)を即時発火させる。
		// 全キューではなくこの購読者の分だけを処理する
		// (訪問者のリクエスト中に他人宛の一斉送信を走らせない)。
		MS_Sender::process_queue( (int) $result );

		wp_safe_redirect( add_query_arg( 'ms_result', 'ok', $redirect ) );
		exit;
	}
}
