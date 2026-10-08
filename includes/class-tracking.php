<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Tracking {

	/**
	 * 送信直前の本文加工パイプライン(仕様書5章)。
	 * 1. 差し込みタグ置換({name} {unsubscribe_url})
	 * 2. クリックトラッキング:全リンクをリダイレクトURLに書き換え
	 * 3. 共通テンプレート適用(フッターに送信者情報+解除リンクを常時自動挿入=特電法の保険)
	 * 4. 開封ピクセル挿入
	 */
	public static function prepare_body( $body_html, $ctx ) {
		$name        = ! empty( $ctx['name'] ) ? $ctx['name'] : 'あなた';
		$unsub_url   = self::unsubscribe_url( $ctx['sub_token'] );
		$track_token = $ctx['track_token'];

		// 1. 差し込みタグ
		$body = str_replace(
			array( '{name}', '{unsubscribe_url}' ),
			array( esc_html( $name ), esc_url( $unsub_url ) ),
			$body_html
		);

		// 段落整形(wp_editorのテキストをHTML化)
		$body = wpautop( $body );

		// 2. クリックトラッキング
		if ( $track_token && get_option( 'ms_track_click', 1 ) ) {
			$body = self::rewrite_links( $body, $track_token, $unsub_url );
		}

		// 3. テンプレート適用
		$body = self::apply_template( $body, $unsub_url );

		// 4. 開封ピクセル
		if ( $track_token && get_option( 'ms_track_open', 1 ) ) {
			$pixel = sprintf(
				'<img src="%s" width="1" height="1" alt="" style="display:none;" />',
				esc_url( add_query_arg( array( 'ms_track' => 'open', 't' => $track_token ), home_url( '/' ) ) )
			);
			$body = str_replace( '</body>', $pixel . '</body>', $body );
		}

		return $body;
	}

	/**
	 * 本文中の全<a href>をトラッキングリダイレクトに書き換える。
	 * 解除リンクだけは書き換えない(解除動線にワンクッション挟まない)。
	 */
	public static function rewrite_links( $html, $track_token, $unsub_url ) {
		return preg_replace_callback(
			'/href=["\']([^"\']+)["\']/i',
			function ( $m ) use ( $track_token, $unsub_url ) {
				// href属性の値はHTMLエンティティ化されている(例: & → &amp;)。
				// 署名・転送先には実際のURLを使う(そのままだと ?a=1&amp;b=2 に飛んでしまう)。
				$url = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				// 解除リンク・アンカー・mailto はそのまま
				if ( $url === $unsub_url || 0 === strpos( $url, '#' ) || 0 === strpos( $url, 'mailto:' ) ) {
					return $m[0];
				}
				if ( false !== strpos( $url, 'ms_track=' ) ) {
					return $m[0];
				}

				$tracked = add_query_arg( array(
					'ms_track' => 'click',
					't'        => $track_token,
					'url'      => rawurlencode( $url ),
					'sig'      => self::sign_url( $track_token, $url ),
				), home_url( '/' ) );

				return 'href="' . esc_url( $tracked ) . '"';
			},
			$html
		);
	}

	/**
	 * クリックリダイレクトURLのHMAC署名(オープンリダイレクト対策)。
	 * 署名はこのサイトのソルトでしか生成できないため、
	 * 第三者が任意の転送先URLを組み立てることを防ぐ。
	 */
	public static function sign_url( $track_token, $url ) {
		return substr( hash_hmac( 'sha256', $track_token . '|' . $url, wp_salt( 'auth' ) ), 0, 16 );
	}

	public static function verify_url_sig( $track_token, $url, $sig ) {
		return is_string( $sig ) && hash_equals( self::sign_url( $track_token, $url ), $sig );
	}

	public static function unsubscribe_url( $sub_token ) {
		return add_query_arg( array( 'ms_track' => 'unsub', 't' => $sub_token ), home_url( '/' ) );
	}

	public static function apply_template( $content, $unsub_url ) {
		$from_name  = get_option( 'ms_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'ms_from_email', get_option( 'admin_email' ) );

		ob_start();
		include MS_PLUGIN_DIR . 'templates/email-base.php';
		return ob_get_clean();
	}

	/** 開封記録(初回のみ)。ステップメールと一斉メールのどちらのトークンでも受け付ける。 */
	public static function record_open( $token ) {
		self::record( $token, null );
	}

	/** クリック記録(初回のみ)。クリック=確実に開封しているので、開封漏れも補完する。 */
	public static function record_click( $token, $url ) {
		self::record( $token, $url );
	}

	/**
	 * 開封・クリックの記録本体。$url が null なら開封のみ。
	 * トークンはまずステップメール、なければ一斉メールの送信記録から探す。
	 */
	private static function record( $token, $url ) {
		global $wpdb;

		$row   = MS_DB::get_delivery_by_token( $token );
		$table = MS_DB::deliveries();
		if ( ! $row ) {
			$row   = MS_DB::get_broadcast_recipient_by_token( $token );
			$table = MS_DB::broadcast_recipients();
		}
		if ( ! $row ) {
			return;
		}

		$now    = current_time( 'mysql' );
		$update = array();
		if ( null === $row->opened_at ) {
			$update['opened_at'] = $now;
		}
		if ( null !== $url && null === $row->clicked_at ) {
			$update['clicked_at']  = $now;
			$update['clicked_url'] = esc_url_raw( substr( $url, 0, 500 ) );
		}
		if ( $update ) {
			$wpdb->update( $table, $update, array( 'id' => $row->id ) );
		}
	}
}
