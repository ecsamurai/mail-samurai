<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ms-wrap">
	<h1><span class="dashicons dashicons-feedback ms-h1-icon" aria-hidden="true"></span>登録フォーム</h1>
	<p class="ms-lead">登録フォームは、下で作った<strong>ショートコード（1行）</strong>を固定ページ・投稿・LPに貼るだけで表示できます。</p>

	<?php if ( ! $sequences ) : ?>
		<div class="notice notice-warning inline"><p>シーケンスがありません。先に「シーケンス」画面で作成してください。</p></div>
	<?php else : ?>

	<div class="ms-form-grid">
		<div class="ms-card">
			<h2 class="ms-card-title">1. フォームの中身を決める</h2>
			<form method="get" id="ms-form-builder">
				<input type="hidden" name="page" value="ms-form">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ms-fb-sequence">登録先</label></th>
						<td>
							<select id="ms-fb-sequence" name="sequence_id">
								<?php foreach ( $sequences as $seq ) : ?>
									<option value="<?php echo (int) $seq->id; ?>" <?php selected( $sequence_id, (int) $seq->id ); ?>>
										<?php echo esc_html( $seq->name ); ?><?php echo 'paused' === $seq->status ? '(停止中)' : ''; ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">登録した人に、このシーケンスのステップメールが届きます。</p>
						</td>
					</tr>
					<tr>
						<th scope="row">お名前欄</th>
						<td>
							<label><input type="radio" name="name" value="no" <?php checked( 'no', $fb_name ); ?>> 出さない（メールアドレスだけ）</label><br>
							<label><input type="radio" name="name" value="yes" <?php checked( 'yes', $fb_name ); ?>> 出す</label>
							<p class="description">入力欄が少ないほど登録率は上がりやすくなります。</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ms-fb-button">ボタンの文字</label></th>
						<td><input type="text" id="ms-fb-button" name="button" class="regular-text" value="<?php echo esc_attr( $fb_button ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ms-fb-source">登録元の目印（任意）</label></th>
						<td>
							<input type="text" id="ms-fb-source" name="source" class="regular-text" value="<?php echo esc_attr( $fb_source ); ?>" placeholder="例：top / lp-a / blog">
							<p class="description">どのページから登録されたかが「購読者」画面でわかります。半角英数で入れてください。</p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button">プレビューを更新</button></p>
			</form>
		</div>

		<div class="ms-card">
			<h2 class="ms-card-title">2. ショートコードをコピーする</h2>
			<div class="ms-shortcode-box">
				<textarea id="ms-fb-code" class="large-text code" rows="2" readonly><?php echo esc_textarea( $shortcode ); ?></textarea>
				<button type="button" class="button button-primary" id="ms-fb-copy">コピー</button>
			</div>
			<p class="ms-copy-msg" id="ms-fb-copy-msg" aria-live="polite"></p>

			<h2 class="ms-card-title">3. ページに貼る</h2>
			<ol class="ms-steps">
				<li>「固定ページ」または「投稿」を開く（新規でも既存でもOK）</li>
				<li>ブロックの追加（＋）で「<strong>ショートコード</strong>」ブロックを選ぶ<br><span class="ms-muted">クラシックエディタの場合は、本文にそのまま貼り付けます</span></li>
				<li>コピーした1行を貼り付けて、公開（更新）する</li>
			</ol>

			<?php if ( ! $has_day0 ) : ?>
				<div class="notice notice-warning inline ms-notice">
					<p><strong>このシーケンスには「登録直後」に届くステップがまだありません。</strong><br>
					登録しても最初のメールが届かないので、「シーケンス」画面で <strong>0日 0時間後</strong>・<strong>稼働</strong> のステップを1通作ってください。
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $sequence_id ) ); ?>">シーケンスを開く</a></p>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="ms-card">
		<h2 class="ms-card-title">プレビュー</h2>
		<p class="ms-muted">実際のページでの見た目は、テーマのデザインによって少し変わります。プレビューからは登録されません。</p>
		<div class="ms-preview">
			<?php echo $preview_html; // render() の出力(エスケープ済み) ?>
		</div>
	</div>

	<?php endif; ?>
</div>

<script>
(function () {
	var form = document.getElementById('ms-form-builder');
	var out  = document.getElementById('ms-fb-code');
	var btn  = document.getElementById('ms-fb-copy');
	var msg  = document.getElementById('ms-fb-copy-msg');
	if (!form || !out || !btn) return;

	function clean(v) { return String(v).replace(/["\[\]]/g, '').trim(); }
	function build() {
		var seq  = form.querySelector('[name="sequence_id"]').value;
		var name = (form.querySelector('[name="name"]:checked') || {}).value || 'no';
		var b    = clean(form.querySelector('[name="button"]').value);
		var src  = clean(form.querySelector('[name="source"]').value).replace(/[^A-Za-z0-9_\-]/g, '');
		var code = '[mailsamurai_form sequence="' + seq + '"';
		if (name === 'no') code += ' name="no"';
		if (b) code += ' button="' + b + '"';
		if (src) code += ' source="' + src + '"';
		out.value = code + ']';
	}
	form.addEventListener('input', build);
	form.addEventListener('change', build);

	btn.addEventListener('click', function () {
		var done = function () { msg.textContent = 'コピーしました。ページのショートコードブロックに貼り付けてください。'; };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(out.value).then(done, function () { out.select(); document.execCommand('copy'); done(); });
		} else {
			out.select(); document.execCommand('copy'); done();
		}
	});
})();
</script>
