# v1.6.0 更新と検証

既存のメール侍をv1.6.0のZIPで上書き更新してください。プラグインの「削除」は行わないでください。新しい履歴テーブルを自動で追加し、既存の購読者・配信予約・初回反応を保持します。

「メール侍 → 購読者」でメールアドレスをクリックすると行動履歴を確認できます。「設定」で開封/クリック計測と保存期間を選べます。履歴は更新後の行動から記録し、過去の反復行動と操作者は推測しません。

## 検証

WordPress Playground（SQLite連携）で検証しました。メール送信は差し替え、外部へ実メールは送信していません。

- PHP 7.4.33 / WordPress 6.8.11：36項目合格、全24 PHPファイルの構文解析に成功。
- PHP 8.5.10 / WordPress 7.1.3：匿名アクセス拒否を加えた最終37項目合格、全24 PHPファイルの構文解析に成功。
- v1.5.1からの自動更新、既存データ保持、反復開封・複数URLと再クリック、本人解除と管理者停止の区別、送信結果、フィルタとページ送り、保存期間を確認。
- WordPressが出力した検証用画面をブラウザで表示して確認。

本検証にはMySQL実機と本番SMTPの確認を含みません。開封・クリックにはメールアプリ等の自動アクセスを含みます。

## 最終テスト出力

```text
PASS: automatic schema upgrade
PASS: retention scheduled
PASS: upgrade preserves subscribers
PASS: upgrade preserves original open
PASS: no invented historical events
PASS: migration can run twice
PASS: new subscription
PASS: registration event
PASS: duplicate registration adds no event
PASS: repeated opens are separate events
PASS: first-open aggregate unchanged
PASS: all URLs and repeated clicks retained
PASS: click order and destinations
PASS: click does not invent open event
PASS: first-click aggregate unchanged
PASS: invalid/test tokens create no event
PASS: disabled tracking honors old mail links too
PASS: broadcast events have correct message
PASS: repeated unsubscribe is one state-change event
PASS: unsubscribe immediately skips broadcasts
PASS: resubscription preserves prior history
PASS: resubscription restores future steps
PASS: administrator stop distinct from reader unsubscribe
PASS: step send success logged
PASS: broadcast send success logged
PASS: final send failure logged once after retries
PASS: filter and pagination
PASS: pages do not overlap
PASS: subscriber histories isolated
PASS: out-of-range page clamped
PASS: history output escapes unsafe text and links
PASS: activity and legacy views render
PASS: anonymous users cannot access subscriber history
PASS: unlimited retention
PASS: expired activity removed
PASS: retention does not erase original aggregates
PASS: no final database error
OK: 37 checks / WordPress 7.1.3 / PHP 8.5.10
```
