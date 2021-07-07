# RESTfm
FileMaker Server 用の RESTful な Webサービス

RESTfm はあなたの FileMaker Server を RESTful な Webサービス へと変身させます。REST アーキテクチャに基づいた HTTPメソッド による平易な API を用いて FileMaker Server にアクセスすることができるのです。

**公式 Webサイト:**
http://restfm.com/

**インストールマニュアル および API マニュアル:**
http://restfm.com/manual

RESTfm の権利は Goya Pty Ltd に属します（(c) 2011～2017）。ライセンス形式は MIT ライセンスです。全ての権利およびライセンス関連の情報は LICENSE ファイルをご覧ください。

-----------------------------------

### ダウンロード方法
リリース版のアーカイブ（tar あるいは zip 形式）をダウンロードして頂ければすぐにご利用できます。ダウンロード先は GitHub のリポジトリのトップページから「releases」のリンクをたどってください:
https://github.com/GoyaPtyLtd/RESTfm/releases

*注意:* 公式のアーカイブのファイル名は、`RESTfm-{version}.zip` または `RESTfm-{version}.tgz` という名称になっています。「Source code」と書かれているリンクからのダウンロードは避けてください。なぜなら、それらのファイルはビルドされていないため、動作させるためには追加の設定が必要だからです。

### インストールマニュアル
インストールの手順はオンラインマニュアルに記載されています:
http://www.restfm.com/restfm-manual/install

### サポート
開発への支援を頂いた方にはサポートを提供しております:
http://restfm.com/help

----------------------------------------

## GitHub の master ブランチの利用について
GitHub の master ブランチは安定版であり、後にリリース版としてビルドされるコードです。設定内容をカスタマイズしたい場合は master ブランチを直接 clone してご利用ください。

### インストール方法詳細
#### 動作環境
  * 書き込み権限を備えた Webサーバ（Apache 2.2 以上、または IIS 7.0 以上）
  * FileMaker Server 11 以上（RESTfm と同じマシン上にインストールされている必要はありません）
  * PHP 5.3 以上
  * Webサーバ に Apache を用いている場合は`.htaccess`ファイルが適用されるように、RESTfm ディレクトリに対して`AllowOverride All`とディレクティブを設定すること

#### OS X および Linux でのインストール例
    cd /<your web doc dir>
    git clone https://github.com/GoyaPtyLtd/RESTfm.git
    cd RESTfm
    cp RESTfm.ini.php.dist RESTfm.ini.php
    cp .htaccess.dist .htaccess
    cp -a FileMaker.dist FileMaker
  * IIS で利用する場合は`web.config.dist`を`web.config`にリネームしてコピーしてください
  * RESTfm の詳細な設定情報を確認するためには次のアドレスにアクセスしてください（example.com/RESTfm にインストールした場合）: http://example.com/RESTfm/report.php
  * RESTfm の詳しいマニュアルを見るためには次のアドレスを参照してください: http://restfm.com/manual

----------------------------------------

### バグについて
バグレポートについては GitHub の Issues にてお待ちしております:
https://github.com/GoyaPtyLtd/RESTfm/issues
