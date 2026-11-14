# 一斉配信の回帰テスト

実際の送信キュー処理を呼び出し、WordPress API・DB・メール配送を検証用の実装に置き換えて確認する。メールは外部に送信しない。宛先登録中に送信処理を割り込ませ、送信開始のタイミングと部分失敗時の挙動を検査する。行動履歴の保存自体はこのテストの対象外。

```sh
npm install --prefix /tmp/mail-samurai-tests @php-wasm/node@3.1.62
export PHP_WASM_PACKAGE_JSON=/tmp/mail-samurai-tests/package.json
node tests/run.mjs 7.4
node tests/run.mjs 8.3
node tests/run.mjs 7.4 lint
node tests/run.mjs 8.3 lint
```

ネイティブPHP（PDO SQLite・mbstring付き）では`php tests/broadcast-regression.php`でも実行できる。
