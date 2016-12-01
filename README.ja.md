# RESTfm
FileMaker Server 用の RESTful な Webサービス

RESTfm はあなたの FileMaker Server を RESTful な Webサービス へと変身させます。REST アーキテクチャに基づいた HTTPメソッド による平易な API を用いて FileMaker Server にアクセスすることができるのです。

**公式 Webサイト:**
http://restfm.com/

**インストールマニュアル および API マニュアル:**
http://restfm.com/manual

RESTfm の権利は Goya Pty Ltd に属します（2011年～2016年）。ライセンス形式は MIT ライセンスです。全ての権利およびライセンス関連の情報は LICENSE ファイルをご覧ください。

-----------------------------------

## RESTfm を本番環境で用いることについて
GitHub の master ブランチ上のコードは開発中のものであり、バグが潜んでいる可能性がありますので、本番環境で利用することは推奨しません。

### パッケージ版をダウンロードする
「パッケージ版」を本番環境としてご利用ください。GitHub のリポジトリのトップページから「releases」リンクをたどれば、tar および zip ファイルをダウンロードすることができます:
https://github.com/GoyaPtyLtd/RESTfm/releases

### サポート
開発への支援を頂いた方にはサポートを提供しております:
http://restfm.com/help

----------------------------------------

## 開発版を利用する
RESTfm の開発を行うことや最新の試作機能に興味がある方は、GitHub のリポジトリから直接コードをダウンロードして利用するのもいいでしょう。

### インストール方法

#### 動作環境
* 書き込み権限を備えた Webサーバ（Apache 2.2 以上 または IIS 7.0 以上）
* FileMaker Server 11 以上（RESTfm と同じマシン上にインストールされている必要はありません）
* PHP 5.3 以上
* Webサーバ に Apache を用いている場合は`.htaccess`ファイルが適用されるように、RESTfm ディレクトリに`AllowOverride All`のようにディレクティブを設定すること

#### OS X および Linux でのインストール例
    cd /<your web doc dir>
    git clone https://github.com/GoyaPtyLtd/RESTfm.git
    cd RESTfm
    cp RESTfm.ini.php.dist RESTfm.ini.php
    cp .htaccess.dist .htaccess
    cp -a FileMaker.dist FileMaker
* IIS で利用する場合は`web.config.dist`を`web.config`にリネームしてコピーしてください
* RESTfm の詳細な設定情報を確認するためには次のアドレスにアクセスしてください: http://example.com/RESTfm/report.php
* RESTfm の詳しいマニュアルを見るためには次のアドレスを参照してください: http://restfm.com/manual

### バグについて
GitHub 上の開発コードはバグを含んでいる可能性がとても高く、サポートを行っておりません。バグレポートについては GitHub の Issues にてお待ちしております。
