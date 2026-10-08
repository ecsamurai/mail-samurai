<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 公開エンドポイント: ?ms_track=open|click|unsub&t=トークン
 */
class MS_Endpoints {

	public function __construct() {
		add_action( 'init', array( $this, 'route' ) );
	}

	public function route() {
		if ( ! isset( $_GET['ms_track'], $_GET['t'] ) ) {
			return;
		}

		$action = sanitize_key( $_GET['ms_track'] );
		$token  = preg_replace( '/[^a-f0-9]/', '', (string) $_GET['t'] );

		switch ( $action ) {

			case 'open':
				MS_Tracking::record_open( $token );
				header( 'Content-Type: image/gif' );
				header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
				// 1px透明GIF
				echo base64_decode( 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
				exit;

			case 'click':
				// $_GET は既にURLデコード済み。再デコードすると %E3%81%82 のような
				// 日本語URLが壊れて署名が一致しなくなるため、そのまま使う。
				$url = isset( $_GET['url'] ) ? wp_unslash( (string) $_GET['url'] ) : '';
				$sig = isset( $_GET['sig'] ) ? sanitize_key( $_GET['sig'] ) : '';

				// オープンリダイレクト対策:
				// (1) HMAC署名が一致しない転送先は拒否(このサイトが発行したリンクのみ通す)
				// (2) スキームはhttp(s)のみ許可
				$parsed = wp_parse_url( $url );
				$valid  = MS_Tracking::verify_url_sig( $token, $url, $sig )
					&& ! empty( $parsed['scheme'] )
					&& in_array( $parsed['scheme'], array( 'http', 'https' ), true );

				if ( $valid ) {
					// v1.4.0以前に送ったメールのリンクは &amp; を含んだまま署名されているため、
					// 転送時にエンティティを戻す(新しいリンクでは何も変わらない)。
					$url = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					MS_Tracking::record_click( $token, $url );
				} else {
					$url = home_url( '/' );
				}
				wp_redirect( $url, 302 );
				exit;

			case 'unsub':
				$sub = MS_Subscriber::unsubscribe_by_token( $token );
				$msg = $sub
					? '<h2>配信を解除しました</h2><p>' . esc_html( $sub->email ) . ' 宛の配信を停止しました。これまでお読みいただきありがとうございました。</p>'
					: '<h2>お手続きできませんでした</h2><p>リンクが無効です。お手数ですが配信メールに記載の解除リンクからもう一度お試しください。</p>';

				wp_die(
					'<div style="max-width:480px; margin:60px auto; font-family:sans-serif; line-height:1.9; text-align:center;">' . $msg . '</div>',
					'配信解除',
					array( 'response' => 200 )
				);
		}
	}
}
