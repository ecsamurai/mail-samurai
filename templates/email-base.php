<?php
/**
 * HTMLメール共通レイアウト。
 * 変数: $content(本文HTML)/ $unsub_url / $from_name / $from_email
 * フッターの送信者情報+解除リンクは特定電子メール法の必須要件のため、
 * 本文側の記載有無にかかわらず常に出力する。
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:8px;">
				<tr>
					<td style="padding:32px 28px; font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',Meiryo,sans-serif; font-size:16px; line-height:1.9; color:#333333;">
						<?php echo $content; // ステップ保存時に wp_kses_post でサニタイズ済みのHTML(管理者入力) ?>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 28px; border-top:1px solid #eeeeee; font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',Meiryo,sans-serif; font-size:12px; line-height:1.8; color:#999999;">
						配信者: <?php echo esc_html( $from_name ); ?>(<?php echo esc_html( $from_email ); ?>)<br>
						このメールはご登録いただいた方に配信しています。<br>
						配信の停止をご希望の方は <a href="<?php echo esc_url( $unsub_url ); ?>" style="color:#999999;">こちら(配信解除)</a> からお手続きください。
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
