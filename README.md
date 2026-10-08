# メール侍 (Mail Samurai)

日本語で簡単に使える、WordPress用ステップメール配信プラグインです。

登録日を起点に「登録直後 → 1日後 → 3日後の朝8時…」と、購読者一人ひとりのタイムラインでメールを自動配信します。外部SaaSに依存せず、自分のWordPressとサーバーだけで完結します。

*(English summary below)*

## 特徴

- **ステップメール(シーケンス配信)に特化** — 登録日起点のオフセット指定+配信時刻の丸め(深夜登録者への深夜配信を防止)
- **日本語の管理画面** — シーケンス編集・購読者管理・KPIダッシュボード(開封率・クリック率・解除率・ステップ別ボトルネック警告)
- **開封・クリック計測** — トラッキングピクセルとHMAC署名付きリダイレクトによるクリック計測
- **メルマガ(一斉メール)** — 配信リストの購読中メンバーへ、開封・クリック計測と解除リンク付きの単発メールをキュー配信
- **特定電子メール法対応の設計** — 全メールのフッターに送信者情報+配信解除リンクを自動挿入(本文に入れ忘れても違反にならない保険)
- **登録フォーム** — 管理画面の「登録フォーム」でショートコードを作ってコピーし、ページに貼るだけ（例：`[mailsamurai_form sequence="1" name="no" button="無料で受け取る"]`）。プレビュー付き。ハニーポットによるbot対策付き
- **送信はSMTPプラグインに委譲** — WP Mail SMTP等で設定したSMTP経由で `wp_mail()` 送信。送信インフラを乗り換えてもプラグイン側の変更は不要
- **タイマーはリセットされない** — 解除→再登録しても配信タイムラインは復活しません(エバーグリーンファネルの締切設計と同じ思想)

## 動作要件

- WordPress 6.x / PHP 7.4以上
- SMTP設定済みのメール送信環境(WP Mail SMTP等の併用を推奨)
- サーバーcron推奨(後述)

## インストール

1. このリポジトリのZIPをダウンロード
2. WordPress管理画面 → プラグイン → 新規追加 → プラグインのアップロード
3. 有効化すると「メール侍」メニューが追加され、初期シーケンス「メイン配信」が作成されます

### 導入手順(推奨の順序)

1. **先にSMTPプラグインを設定**し、そちらのテスト送信が届くことを確認する
2. メール侍の設定画面で差出人名・差出人アドレスを設定する
3. シーケンス画面でステップ#1を作成し「自分宛にテスト送信」で受信を確認する
4. 「メール侍 → 登録フォーム」でショートコードをコピーし、固定ページ等の「ショートコード」ブロックに貼る
5. 自分で本番登録し、登録直後のメール(Day0)が届くことを確認する

### サーバーcronの設定(推奨)

WordPress標準のWP-Cronはサイトへのアクセスがないと動きません。配信時刻の正確性が必要な場合は、`wp-config.php` に `define( 'DISABLE_WP_CRON', true );` を追加し、サーバーのcronで5分間隔で `wp-cron.php` を実行してください。設定画面にcronの死活インジケータがあります。

## バウンス(配信エラー)について【重要】

バウンスとは、存在しないメールアドレスなどに送った際に発生する「配信不能」のことです(未開封とは別物です)。バウンスしたアドレスに送り続けると、Gmail等の受信プロバイダから送信者評価を下げられ、**正常なアドレス宛のメールまで迷惑メール扱いになる**リスクがあります。

**本プラグインにはバウンスの自動検知・自動配信停止の機能はありません。** 以下の運用を前提としています。

- 想定リスト規模:**〜200件程度**(自分のLPから登録された、素性の分かるリスト)
- サーバーの受信箱に戻るエラーメール(Mail Delivery Failed 等)を定期的に確認し、該当アドレスを購読者一覧から手動で配信停止してください
- 送信ドメインに **SPF / DKIM / DMARC** を必ず設定してください(未設定だとGmail宛はほぼ届きません)
- リストが数百件を超えたら、バウンス自動処理を備えた MailPoet 等への移行を推奨します

## MailPoetとの住み分け

| | メール侍 | MailPoet |
|---|---|---|
| 向いている規模 | 〜200件の小規模リスト | 数百件〜 |
| ステップメール | 特化(登録日起点+時刻丸め) | 可能(設定はやや複雑) |
| バウンス自動処理 | なし(手動運用) | あり(MSS利用時) |
| 管理画面 | 日本語 | 英語ベース |
| 費用 | 無料(GPL) | 無料枠あり・上位は有料 |

「小さく始めるならメール侍、リストが育ったらMailPoetへ」という卒業ルートを想定しています。作者自身がこの道を辿りました。

## 制限事項(v1)

- バウンス自動処理なし(上記参照)
- セグメント配信・A/Bテスト・LINE連携は未対応(ロードマップ項目)
- 日本語UIのみ(国際化は今後の課題)

## 導入サポート

SPF/DKIM/DMARCの設定、サーバーcronの設定、シーケンス設計を含む導入代行を有償で承っています。詳細は [aitanuki.com](https://aitanuki.com) をご覧ください。

## 更新履歴

- **1.4.0** — 「登録フォーム」画面を追加（ショートコードの作成・コピー・設置手順・プレビュー、登録直後のステップが無いときの警告）。管理画面の見出しアイコンを絵文字からアイコンフォントに変更
- **1.3.0** — 初回公開

## ライセンス

GPLv2 or later

---

## English Summary

**Mail Samurai** is a lightweight, self-hosted drip email (autoresponder) plugin for WordPress with a Japanese-language admin UI. Each subscriber gets their own timeline starting from their signup date (e.g., immediately → Day 1 → Day 3 at 8:00 AM), with send-hour rounding to avoid middle-of-the-night delivery. It includes open/click tracking (HMAC-signed redirects), a signup form shortcode with honeypot spam protection, and automatic sender-info/unsubscribe footers designed for Japanese anti-spam law compliance. Sending is delegated to your SMTP plugin (e.g., WP Mail SMTP) via `wp_mail()`.

**Important limitation:** there is no automatic bounce handling. This plugin targets small lists (~200 subscribers) where bounced addresses can be removed manually; configure SPF/DKIM/DMARC on your sending domain, and migrate to a service with bounce processing (e.g., MailPoet) as your list grows.

License: GPLv2 or later.

## 作者

ecさむらい（ec侍）・三浦 — [aitanuki.com](https://aitanuki.com)

Author: ecsamurai (Miura)
