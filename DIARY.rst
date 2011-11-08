※書き忘れたけどとりあえず分かりやすいところでホーム画面見てる。別のアクション選ぶのもなんか恣意的な感じになっちゃうかもだしね。

2011/10/24 - 1
==============

一気に読み込む系のレコードオブジェクトをシンプルなオブジェクトにすげ替える。

Before
------

::

    Total Incl. Wall Time (microsec):   3,025,688 microsecs
    Total Incl. CPU (microsecs):    2,412,582 microsecs
    Total Incl. MemUse (bytes): 77,204,496 bytes
    Total Incl. PeakMemUse (bytes): 77,831,064 bytes
    Number of Function Calls:   185,982

After
-----

::

    Total Incl. Wall Time (microsec):   3,025,673 microsecs
    Total Incl. CPU (microsecs):    2,156,077 microsecs
    Total Incl. MemUse (bytes): 75,915,536 bytes
    Total Incl. PeakMemUse (bytes): 76,540,088 bytes
    Number of Function Calls:   161,456

感想
----

* あまり変わらない (というか元々が微妙)
* sfDatabaseManager::getDatabase() でガッツリメモリ消費している
    * ああこれ小川さんがなんか言ってなかった？　database.yml の設定名を doctrine -> master にして試した::

        Overall Summary
        Total Incl. Wall Time (microsec):   2,257,548 microsecs
        Total Incl. CPU (microsecs):    2,101,561 microsecs
        Total Incl. MemUse (bytes): 75,675,040 bytes
        Total Incl. PeakMemUse (bytes): 76,301,872 bytes
        Number of Function Calls:   161,068

* データの総量が少ない状態だとむしろ ORM 以外のところでガッツリメモリ食っている感じ


2011/10/25 - 1
==============

ボトルネック調査しないでパフォーマンスチューニングとかヘソで茶がわくので今日は初心に返って調査しつつやる。

factory 周り改善するアイディアはあるんだけどそれより後のコントローラ周りのコストのほうが大きい気がしてるんだよな。

ルーティング
------------

さて。

* opAlbumPluginRouting::listenToRoutingLoadConfigurationEvent()
* opMessagePluginRouting::listenToRoutingLoadConfigurationEvent()
* sfImageHandlerRouting::listenToRoutingLoadConfigurationEvent()

でたぶん巨大な配列を array_merge() しててここでガッツリメモリ喰ってる（2MB くらい）と思われ。とりあえず sfImageHandlerRouting::listenToRoutingLoadConfigurationEvent() を廃止して様子見てみるか。

Before
``````

::

    Total Incl. Wall Time (microsec):   2,156,831 microsecs
    Total Incl. CPU (microsecs):    2,065,553 microsecs
    Total Incl. MemUse (bytes): 75,674,016 bytes
    Total Incl. PeakMemUse (bytes): 76,301,080 bytes
    Number of Function Calls:   161,068

After
`````

::

    Total Incl. Wall Time (microsec):   2,132,015 microsecs
    Total Incl. CPU (microsecs):    2,058,108 microsecs
    Total Incl. MemUse (bytes): 75,647,288 bytes
    Total Incl. PeakMemUse (bytes): 76,274,048 bytes
    Number of Function Calls:   160,740

opAlbumPluginRouting 廃止
-------------------------

まあこいつら全部倒せばだいぶ違ってくるのかなーたぶんひとつだけ潰してもあまり意味ない。つーことで opAlbumPluginRouting もやっつける。

パッチは https://gist.github.com/1311946

After
`````

::

    Total Incl. Wall Time (microsec):   2,266,486 microsecs
    Total Incl. CPU (microsecs):    2,052,934 microsecs
    Total Incl. MemUse (bytes): 75,547,496 bytes
    Total Incl. PeakMemUse (bytes): 76,074,160 bytes
    Number of Function Calls:   158,538

opMessagePluginRouting 廃止
---------------------------

続けざまに。パッチは https://gist.github.com/1311972

After
`````

::

    Total Incl. Wall Time (microsec):   2,171,421 microsecs
    Total Incl. CPU (microsecs):    2,041,800 microsecs
    Total Incl. MemUse (bytes): 75,415,224 bytes
    Total Incl. PeakMemUse (bytes): 76,041,904 bytes
    Number of Function Calls:   157,314

とりあえず廃止しきったけども
----------------------------

期待ほど減らなかったなーと思ったら array_merge() は減ったけど unserialize() が増えた。まあこれは当然の結果か。 array_merge() しなくなったぶんがそのまま減るわけじゃないものな。

Excl. MemUse 順で見ていっても改善できそうなものはとりあえずなさそう（DQL 周りガッツリやったときのチューニングでまだ取り込んでないものとかがあればそのあたり改善できるかもしれないけどとりあえず置いておこう）。

2011/10/25 - 2
==============

で、次は Incl. MemUse 順で見ていくことにするよ。

気になるのはやっぱりフィルタチェーンと factory とコンポーネントだ。というぐらいの見解は去年くらいに示したことあったなたしか思い出した。で、忙殺されてそのままと。

まずコンポーネントのボトルネックを洗い出すぞと。 sfPartialView::render() と _call_component() がガッツリだなあ。 sfPartialView::render() はコンポーネントからきたデータでふくれあがってるんじゃないかと思うので、 _call_component() を見れば一目瞭然かな。なんか細かいところに入って行っちゃうな。まあいいか。でもデータほとんどない状態だけど。

Incl. MemUse の 1MB 越えは以下。

* defaultComponents::executeLanguageSelecterBox 4,513,856
* opMemberComponents::executeBirthdayBox 2,623,696
* applicationComponents::executeCautionAboutApplicationInvite 1,619,176
* opMessagePluginMessageComponents::executeUnreadMessage 1,580,088
* opCommunityComponents::executeCautionAboutChangeAdminRequest 1,043,432
* opCommunityTopicPluginTopicComponents::executeTopicCommentListBox 1,013,080

よし上から検証していくか。今日はこいつら検証したらこの作業終わりだな。

defaultComponents::executeLanguageSelecterBox
---------------------------------------------

opLanguageSelecterForm のコンストラクタでめっさメモリ消費している。その主要因は opToolkit::getCultureChoices() で、こいつだけで 3MB 使ってる！　そうか sfCultureInfo は ICU のデータを読み込むから……

opToolkit::getCultureChoices() が呼ばれる場面はここだけだが、 OpenPNE で sfCultureInfo を使う場面は意外とある。プロフィールの表示とか。だから opToolkit::getCultureChoices() をキャッシュだけしてお茶を濁すとかそういうことしてはいけない。

つーか sfCultureInfo を永続的に持つ理由はどこにもないんだ。なんでこんな実装になってるんだ。頻繁に使う可能性があるからか。そうか。でもなー。まあこのクラスは symfony 由来じゃないし。しかも sfCultureInfo::getInstance() が返すインスタンスって関数内の static 変数に格納されてるのかこれ……

普通に sfCultureInfo の実装がまずい気がしている。つーか国際化周りの実装は総じてひどいよね。 symfony 由来じゃない部分は特に分かりやすくひどいコードが多い。まあ 2005 年とかぐらいの前世紀のコードだからしょうがないかな。

とりあえず sfCultureInfo 使っているところは大いに改善に余地ありということで、ひとまずここのコンポーネントでは（というか opToolkit::getCultureChoices() で）どう対処するか考えることにしよう。

うん、つーか opToolkit::getCultureChoices() 呼ぶ必要ないね。 op_supported_languages が表示名を格納するようにしていれば解決じゃないのこれ。

ということで適当に改善コードしこんでやってみた::

    Total Incl. Wall Time (microsec):   2,103,188 microsecs
    Total Incl. CPU (microsecs):    2,019,964 microsecs
    Total Incl. MemUse (bytes): 72,243,960 bytes
    Total Incl. PeakMemUse (bytes): 72,871,192 bytes
    Number of Function Calls:   157,214

おし！　3 MB 近く減った！　これは効果あったか。

でもこの状態でも defaultComponents::executeLanguageSelecterBox() の Incl. MemUse が 1,341,592 なのが気になる。 sfForm あたりまで潜ってみるとオートロード周りが悪さをしているようなんだけれども、それはこのメソッドに限ったことではないはず。うーん……？

opMemberComponents::executeBirthdayBox
--------------------------------------

Doctrine_Core::getTable() が 1,415,696 で MemberProfileTable::getViewableProfileByMemberIdAndProfileName() が 1,145,912 か。ほう。

んー？　MemberProfileTable::getViewableProfileByMemberIdAndProfileName() は MemberProfile の単一レコード取ってきてるだけだよねえ。なんでこんなにメモリ喰う？

これを昨日やったシンプルなオブジェクトにすげ替えればマシにはなるんだろうけど、まあちょっと地道なアプローチでやってみるよ。そもそもアレを完全に適用する前提ならコード生成とか必要になるし。

ということで見たけど Doctrine::getTable() か……レコードオブジェクトのキャッシュがやっぱり無駄なんじゃないかな。もっとも MemberProfile とかのレコードが情報詰め込みすぎだったりするのかも知れないけど。

んん？　class_exists() が 10,026,720 だって？　Doctrine_Table::initDefinition() で呼ばれているぶんで 10,026,720 で Doctrine_Connection::getTable() で 7,534,688 とな。これは……

ためしに class_exists() のコールのコメントアウトを外すと、 sfAutoload::autoload() で同じくらいのメモリを消費する結果になった。

うん、もう充分でしょう。 sfAutoload::autoload() が大きな問題と見てほとんど間違いない。コンポーネントの調査は中断してオートローディングの原因調査に入ろう。j

2011/10/25 - 3
==============

オートローディング
------------------

さて。オートローダーは sfAutoload::loadClass() で 25,606,128 消費している。たとえばこのデータが APC とかに載っけられるとメモリ使用量が一気に下がるんだろうか。まあそれは今は置いておこう。

ただこいつの中身を見てみると、

* run_init::doctrine/SnsTerm.class.php 1,723,696
* run_init::doctrine/SnsTermTable.class.php 1,018,904
* run_init::util/opDoctrineConnectionMysql.class.php 880,264
* load::OpenPNE2/KtaiEmoji.php 609,536
* run_init::OpenPNE2/KtaiEmoji.php 591,608
* run_init::lib/myUser.class.php 552,760
* run_init::doctrine/MemberProfileTable.class.php 455,016

ということなので、うーん（ああ load があるな。これは APC のキャッシュミスだな。容量少ないのかな）。

しかし妙だなー。なんで doctrine/SnsTerm.class.php の run_init がこんなにメモリ喰うのか。特段巨大なファイルというわけでもないし、だいたいこのファイルの読み込み時点では SnsTerm はなんもやらないはずと思ってたけど。

ためしに SnsTerm.class.php のクラスの実装を空に（ただ BaseSnsTerm を継承するだけに）にしてみてもなんもかわらない。どういうことなんだこれは。

おっと sfAutoload::loadClass@1 は run_init::base/BaseSnsTerm.class.php に 1,606,600 かかってるな。さらに run_init::util/opDoctrineRecord.class.php に 1,421,752 かかり、 run_init::record/sfDoctrineRecord.class.php に 1,239,576 かかり……おっと Doctrine_Core::autoload に行き着くとは。このメソッドで Doctrine 関連の数々のファイルを一気にロードしている。そうか SnsTerm はリクエスト後初めて読み込まれるモデルファイルなんだな。

load が多く出ているから APC がキャッシュし切れていないのも原因の一つかー。ちょっと容量引き上げてリトライしますわ。

APC の設定値変更後
------------------

apc.shm_size をデフォルトの 32M から 128M に引き上げた。これで計測してみる::


    Total Incl. Wall Time (microsec):   1,799,921 microsecs
    Total Incl. CPU (microsecs):    1,727,108 microsecs
    Total Incl. MemUse (bytes): 42,782,800 bytes
    Total Incl. PeakMemUse (bytes): 42,907,152 bytes
    Number of Function Calls:   157,185

……あっちゃー。誠に申し訳ございませんでした……

でも sfAutoload::loadClass() は 14,740,584 とか消費してる。これをどう見るか。 load は確かにほとんど見当たらないが、 run_init で結構喰ってる。これはファイル数多いから仕方がないのか。

単純にファイル数を減らせばこれは改善するかな？　たとえば core_compile とかで。ちょっとやってみるか。まず Doctrine 関連だな。

Doctrine のコンパイル
---------------------

Doctrine がコンパイラを提供しているのはマニュアルに書いてあるとおりでまあ常識なんですが、たぶんこいつを使うのが一番簡単。 symfony のコンパイラ使うのもいいけどねー。

http://www.doctrine-project.org/projects/orm/1.2/docs/manual/improving-performance/en#compile

やってみたけどうえー 42M から 45M に増えた。あ、 DBMS 指定していないからか？

DBMS 指定したら 44M に。ちょっと待ってくださいよ−。

オートロード時の初期化コスト自体は減っているし、気持ち速くなったような気がしないでもないが……::

    Total Incl. Wall Time (microsec):   1,785,565 microsecs
    Total Incl. CPU (microsecs):    1,681,760 microsecs
    Total Incl. MemUse (bytes): 44,802,888 bytes
    Total Incl. PeakMemUse (bytes): 44,930,992 bytes
    Number of Function Calls:   155,626

sfAutoload::loadClass のメモリ消費は 13,395,864 になった。まあここで Doctrine 関連の数々のファイル読み込みをやらなくなったわけだから減って当然と。

ここで使わなかったクラスファイルを読み込むようになったから増えたってことだな。っておっと doctrine.compiled.php が APC に載ってない。増えるわけだ。ちなみに 4,327,960 とか消費してる。でかいなー

これどうすればいいの？　ウェブサーバ再起動すればいい？

再起動した。けどやっぱり doctrine.compiled.php が APC に載ってくれない。これは apc 確認用スクリプトの出番だな。どこにあったかな。

http://svn.php.net/viewvc/pecl/apc/trunk/apc.php?view=markup

ござった。システムキャッシュ見てみる。

> doctrine.compiled.php    1   14520656    2011/10/25 20:55:38 2011/10/25 20:47:51 2011/10/25 20:55:34

なんだよキャッシュしてるじゃーん。あれー？　アクセスのたびにヒット数も増えてるから読み込まれてるはず。よくわからんなー。

うーん今日はここまでかな……ちょっとこのあたりのことは後々調べることにして、いまは先に進もう。

次は以下をなんとかするところからはじめるってことで。

* sfContext::dispatch 21,558,488
* sfContext::createInstance 13,523,304
* sfProjectConfiguration::getApplicationConfiguration 9,220,680

2011/10/27 - 1
==============

まず小川さんからもらった変更を適用してやってみるかな。おおなんかルーティング周り似たような変更しているじゃない。

https://github.com/balibali/OpenPNE3/commit/242afba8475abf33572757c2b55597327704d97b

で、紹介されたのがこれ。ルーティングキャッシュからの unserialize のコストを下げると。これは期待できる。

小川前
------

::

    Total Incl. Wall Time (microsec):   1,800,108 microsecs
    Total Incl. CPU (microsecs):    1,691,822 microsecs
    Total Incl. MemUse (bytes): 44,804,632 bytes
    Total Incl. PeakMemUse (bytes): 44,932,688 bytes
    Number of Function Calls:   155,627

小川後
------

::

    Total Incl. Wall Time (microsec):   1,774,001 microsecs
    Total Incl. CPU (microsecs):    1,693,819 microsecs
    Total Incl. MemUse (bytes): 41,138,368 bytes
    Total Incl. PeakMemUse (bytes): 41,257,400 bytes
    Number of Function Calls:   157,191

おお 3MB 下がった（もっと下がるかなと思ったけどまあキャッシュのサイズ的にこんなものかな）

unserialize() のメモリ使用量は 7,304,248 Bytes から 3,222,376 Bytes まで下がった。コール回数は 409 回から 241 回まで減少し、そのうち 118 回は opLazyUnserializeRoutes::offsetGet() から呼ばれている。メモリ消費量は 2,879,984 Bytes だった。このあたりもう少しなんとかならないかなー。ちょっと詳しく見てみる。

2011/10/27 - 2
==============

opLazyUnserializeRoutes 読んだ。 unserialize() のコール機会自体を減らさないといけないということで理解。 sfPatternRouting::getRouteThatMatchesParameters() 内の foreach ループで多く呼ばれている。つまりルーティングルールの走査機会を減らすか速く終わるようにする必要がある。要するにデフォルトルールは悪だ。

……と思ったがホーム画面ではデフォルトルールは使ってないということが明らかになった。うーんそうか……

ということで反則っぽいかもしれないけど、つーかめっちゃ怖いけど、「明らかに違うルールは unserialize() せずに弾く」的なことをやってみることにした。とりあえず sfRoute の場合、最初に文字列比較を試してみる感じで::

    Total Incl. Wall Time (microsec):   1,751,201 microsecs
    Total Incl. CPU (microsecs):    1,683,343 microsecs
    Total Incl. MemUse (bytes): 39,084,712 bytes
    Total Incl. PeakMemUse (bytes): 39,203,880 bytes
    Number of Function Calls:   156,602

2MB 下がった。 ルーティング経由の unserialize() のコール回数は 25 回になった。メモリ使用量は 545,920 Bytes まで減った。

よく見てみると opSecurityUser::getMember() 経由の unserialize() がめっちゃ呼ばれているんだけどこれはなんだ。

2011/10/27 - 3
==============

opSecurityUser::getMember() が unserialize() しまくっているのは、 opSecurityUser のインスタンスが Member のインスタンス自身じゃなく、このインスタンスを serialize() した結果を毎回 unserialize() して返すようにしているからだった。これはいかがなものかと思うなー。循環参照とか警戒したのかなー？　opSecurityUser::getMember() の結果が変なデータをくっつけたまま使い続けられるのを避けたのかな−？　opSecurityUser は最後のほうまで生き残るから Member のインスタンスを持ち続けることは無駄だと思ったのかな−？

とりあえず一度取得した Member のインスタンスをそのまま返すようにした::

    Total Incl. Wall Time (microsec):   1,667,594 microsecs
    Total Incl. CPU (microsecs):    1,615,039 microsecs
    Total Incl. MemUse (bytes): 39,175,152 bytes
    Total Incl. PeakMemUse (bytes): 39,295,096 bytes
    Number of Function Calls:   149,520

そして Member のインスタンスの clone を返すようにもしてみた::

    Total Incl. Wall Time (microsec):   1,627,865 microsecs
    Total Incl. CPU (microsecs):    1,578,697 microsecs
    Total Incl. MemUse (bytes): 38,986,496 bytes
    Total Incl. PeakMemUse (bytes): 39,105,720 bytes
    Number of Function Calls:   149,520

おー。そうか、じゃあ unserialize() するようにした意図自体は正しかったわけだな。だが、その方法として unserialize() を選択したことが誤りだったと。

2011/10/31 - 1
==============

前回の恐ろしいやつにバグがあってキャッシュとかまっさらなときに動かなくなってたので直した。この状態での結果は以下::

    Total Incl. Wall Time (microsec):   2,223,929 microsecs
    Total Incl. CPU (microsecs):    1,634,598 microsecs
    Total Incl. MemUse (bytes): 40,804,208 bytes
    Total Incl. PeakMemUse (bytes): 40,922,728 bytes
    Number of Function Calls:   150,781

おかしいな増えてる。なんでだ。バグのせいということにしようか。まあ APC のキャッシュ具合とかにある程度左右されるのかもな。あまり気にしないでおく。とにかくこれが今日の基準ということで。

で、そういや opWebAPIPlugin 関連のルーティングルールが pc_frontend なのに大量に登録されているのが気になるので削ってみる::

    $ mv plugins/opWebAPIPlugin/config/ plugins/opWebAPIPlugin/apps/api

この結果は以下::

    Total Incl. Wall Time (microsec):   1,675,099 microsecs
    Total Incl. CPU (microsecs):    1,618,225 microsecs
    Total Incl. MemUse (bytes): 39,443,112 bytes
    Total Incl. PeakMemUse (bytes): 39,561,848 bytes
    Number of Function Calls:   148,778

2011/10/31 - 2
==============

さてコンポーネントなんとか軽くできないかなー。と思って調べたけれどもコンポーネントでメモリガッツリ食っているのは Doctrine のレコード取得周りっぽい。

いちいち多方面のレコードをキャッシュしているのが悪いのかなと思ってそのあたりいろいろいじったけれども予想に反して改善されない。主原因はレコードオブジェクトではないのかそれとも……

他で特にかかっているのは Doctrine のコンパイル済みクラスファイルのロードと、コンフィグハンドラの登録におけるオートローディングとコンパイル済み symfony クラスファイルのロードだった。うーんうーん……

2011/11/01 - 1
==============

詰まったので日を改めてみた。ついでに月も改まった。とりあえず直近の目標は、

* コンポーネント等で大量にメモリを消費している Doctrine のレコード取得周りの原因を探る
* オートローディング周りで大量にメモリを消費しているのでその原因を探る

といったところかなー。とりあえず前者は置いておいて後者を今日見てみることにする。

sfAutoload::loadClass() で 13,416,864 bytes も喰っているけれど、これは割とどうしようもないかも。 Doctrine のモデルクラスは定義が複雑なので、クラスの読み込みだけで相当なインパクトがある。で、ホーム画面は様々な種類のモデルファイルを読み込む必要があるから、必要なクラスファイルを読み込むだけで相当なダメージがある。これを解決するにはモデルの定義を限りなくシンプルにする（クラスファイルを読み込んでも大してパフォーマンスに影響が出ない程度のシンプルな定義にする）しかなく、たとえば Doctrine 2 のアプローチがかなり有効に効くはずと思う。

以前の「シンプルなオブジェクトにモデルをすげ替える」の効果が薄かったのは、 Doctrine_Table は指定されたレコードクラスのインスタンスを必要としてしまうから。 Gadget の例で行くと、シンプルなオブジェクトと比較したクラス読み込みにかかるコストの差は 178,680 - 1,120 = 177,560 bytes であり、レコードクラスのインスタンスをまったく読み込まない想定であればかなりパフォーマンスが減ることが期待できる。現状でも「シンプルなオブジェクトにモデルをすげ替える」を一部適用して 2MB ものメモリ使用量の増加が見られたため、あわせてレコードクラスのインスタンスを基本的に読み込まないようにコーディングできれば、大幅なパフォーマンスの改善が見込めるはず。本気でチャレンジしてみる？

さて、あとはコンパイル済みスクリプトの読み込みにも注目したい。以下の二点がポイントになると思う。

1. コンパイルすべきファイルが他にないかどうか？
2. 無駄なスクリプトの読み込みが発生していないかどうか？

まず一点目について検討したい。これを検討するには sfAutoload::loadClass() のコストを見るのが一番いいように思える。

sfAutoload::loadClass() が読み込むクラスファイルのうち、 Doctrine のレコードクラスおよびテーブルクラスを除外すると、以下のようなクラスファイルが読みこまれているのがわかる。

* load::util/opDoctrineQuery.class.php 107,448
* load::request/opWebRequest.class.php 67,704
* load::config/sfOpenPNEApplicationConfiguration.class.php 51,472
* load::database/sfDoctrineDatabase.class.php 43,136
* load::action/opMemberAction.class.php 40,632
* load::behavior/opCommunityTopicPluginImagesRecordGenerator.class.php 36,800
* load::routing/opDynamicAclRoute.class.php 35,360
* load::view/opView.class.php 33,776
* load::response/opWebResponse.class.php 32,632
* load::behavior/opCommunityTopicPluginImagesBehavior.class.php 31,808
* load::routing/opLazyUnserializeRoute.class.php 31,752
* load::behavior/opActivateBehavior.class.php 31,328
* load::lib/opMessagePluginObserver.class.php 29,752
* load::behavior/opActivityCascadingBehavior.class.php 28,544
* load::i18n/opI18N.class.php 26,656
* load::util/opDoctrineConnectionMysql.class.php 23,128
* load::routing/opPatternRouting.class.php 23,024
* load::lib/opAuthAdapterOpenID.class.php 20,224
* load::action/opDiaryPluginDiaryComponents.class.php 19,624

OpenPNE はほとんどのリクエストでデータベースを使用するため、たとえば opDoctrineQuery や sfDoctrineDatabase は常に読み込んでしまってもいいように思える。また、 opWebRequest, opView, opWebRequest, opI18N もタスクでない限りは使用するため、これらも常に読み込んで構わない。これらのクラスを core_compile の対象にし、どの程度パフォーマンスが改善しうるか観察してみることにする。

おっと、 opDoctrineQuery と opWebRequest でエラーになったのでこいつらはとりあえず対象から外すことにする。

これで計測すると、 Total Incl. MemUse (bytes): 39,441,376 bytes -> 39,420,616 bytes というなんとも雀の涙程度だが改善された。毎回読み込まれるファイルなのであれば、 core_compile の対象にすればするほど効果があると思うので、もうちょっと追加できるファイルがないか考えてみたい。

2011/11/01 - 2
==============

次に検討するべきは

> 2. 無駄なスクリプトの読み込みが発生していないかどうか？

これ。

まず core_compile の中身から点検する::

    $ grep "^\(abstract \|\)class" cache/_www/pc_frontend/prod/config/config_core_compile.yml.php
    class sfAutoload
    abstract class sfComponent
    abstract class sfAction extends sfComponent
    abstract class sfActions extends sfAction
    class sfActionStack
    class sfActionStackEntry
    abstract class sfController
    class sfDatabaseManager
    abstract class sfFilter
    class sfExecutionFilter extends sfFilter
    class sfRenderingFilter extends sfFilter
    class sfFilterChain
    abstract class sfLogger
    class sfNoLogger extends sfLogger
    abstract class sfRequest implements ArrayAccess
    abstract class sfResponse implements Serializable
    abstract class sfRouting
    abstract class sfStorage
    class sfUser implements ArrayAccess
    class sfNamespacedParameterHolder extends sfParameterHolder
    abstract class sfView
    class sfViewParameterHolder extends sfParameterHolder
    abstract class sfWebController extends sfController
    class sfFrontWebController extends sfWebController
    class sfWebRequest extends sfRequest
    class sfPatternRouting extends sfRouting
    class sfWebResponse extends sfResponse
    class sfSessionStorage extends sfStorage
    class sfPHPView extends sfView
    class sfOutputEscaperSafe extends ArrayIterator
    class sfDoctrineDatabase extends sfDatabase
    class opView extends sfPHPView
    class opWebRequest extends sfWebRequest
    class opI18N extends sfI18N

おっと、これは逆に少なすぎないか？　symfony のすべてのプロジェクトで使われるファイルっていうとこんなものか。これは OpenPNE における各クラスの使用状況にあわせてもっと改善できると思う。無駄なファイルは特に見当たらなかった。

次に Doctrine_Compiler の実装を見てみる。

ってうおおおおおおおおおいおいおいおいおいちょっと待てちょっと待て、 Doctrine の全ファイル読み込んでるのかこれ。いやいやいやいやそれはダメだわ。

これは……自分でよく使う Doctrine のクラスファイル群を列挙して core_compile.yml で定義するようにした方がいいな。

Doctrine 以外のファイルも含めて、どのクラスファイルがよく読み込まれうるかの統計を取りたい。どうすればいいのかな。リクエスト終了時に定義済みクラスの一覧を書き出せばいいんだろうか？　やってみるか。

ということでそのリクエストにおける定義済みクラスの一覧を /tmp/kani.classes に出力するようにして、 50 リクエストほど適当にブラウジングして、 cat /tmp/kani.classes | sort | uniq -c | sort -n -r して出現回数を調べてみた（Doctrine のコンパイル済みファイルは読み込まないようにした）。結果は DEFINED_CLASSES_COUNT.201101 に置いておく。

これを基に core_compile.yml の中身を決めていきたいところだが、とりあえず所感としては、

* なんか OpenID 系（Yadis も）のライブラリが毎回読み込まれているがこれは無駄じゃないか
* OpenPNE_KtaiEmoji 系のライブラリが毎回読み込まれているがこれは無駄じゃないか。しかもこのライブラリはサイズがデカイ
* Net_UserAgent_Mobile を pc_frontend で読み込む意味はないんじゃないか
* Net_IPv4 を pc_frontend で読み込む意味はないんじゃないか
* 使用していない Swift 系のライブラリが読み込まれているのは無駄
* PEAR を毎回読み込んでいるが、本当に必要なのかどうか疑わしい

というところがあるので、ちょっとまずこのあたりの見直しをやっていきたいところ。

2011/11/02 - 1
==============

つーわけで無駄なスクリプト読み込みの削減をやっていきますよと。

まず OpenID だな。見てみると opApplicationConfiguration::registerJanRainOpenID() でこの辺のライブラリを強制的に読み込んでいるっぽかった。へ？　なんで？　必要なときに読み込めばいいじゃない。ああしかもこいつらオートロードの対象に入ってるじゃん。じゃあここでの読み込みはまったく無駄だよ。（オートロードの対象から外すかどうかはひとまず置いておく。このあたりも segfault あたりで一悶着ありそうな）

で、 opApplicationConfiguration::registerJanRainOpenID() でスクリプト読み込みをおこなわないようにした状態で計測。まずは改善前::

    Total Incl. Wall Time (microsec):   1,753,644 microsecs
    Total Incl. CPU (microsecs):    1,642,585 microsecs
    Total Incl. MemUse (bytes): 39,693,648 bytes
    Total Incl. PeakMemUse (bytes): 39,812,416 bytes
    Number of Function Calls:   148,627

改善後::

    Total Incl. Wall Time (microsec):   1,693,539 microsecs
    Total Incl. CPU (microsecs):    1,609,223 microsecs
    Total Incl. MemUse (bytes): 39,409,872 bytes
    Total Incl. PeakMemUse (bytes): 39,528,728 bytes
    Number of Function Calls:   148,627

あらら思ったより改善しなかったな……と思いきや、まだ OpenID 関連のライブラリが読み込まれていたどうも opAuthOpenIDPlugin の初期化処理時点で読み込んでいるらしい。むーんこれは……

と思って opAuthAdapterOpenID::configure() を確認してみたら、ここでも必要なライブラリの require をやっているっぽかった。えー？　この require もいらないよもう。つーことで以下のパッチで解決::

    diff --git a/lib/opAuthAdapterOpenID.class.php b/lib/opAuthAdapterOpenID.class.php
    index 52a943d..a0ad177 100644
    --- a/lib/opAuthAdapterOpenID.class.php
    +++ b/lib/opAuthAdapterOpenID.class.php
    @@ -25,9 +25,6 @@ class opAuthAdapterOpenID extends opAuthAdapter
       public function configure()
       {
         sfOpenPNEApplicationConfiguration::registerJanRainOpenID();
    -
    -    require_once 'Auth/OpenID/SReg.php';
    -    require_once 'Auth/OpenID/AX.php';
       }
     
       public function getConsumer()

で、計測::

    Total Incl. Wall Time (microsec):   2,005,302 microsecs
    Total Incl. CPU (microsecs):    1,607,431 microsecs
    Total Incl. MemUse (bytes): 38,889,792 bytes
    Total Incl. PeakMemUse (bytes): 39,008,312 bytes
    Number of Function Calls:   148,488

おおさっきのとあわせるとかなりマシになったか。 OpenID や Yadis 関連のライブラリが読み込まれることもない。よしよし。

2011/11/02 - 2
==============

かなり機嫌よくなってきた。次は OpenPNE_KtaiEmoji だ。こいつを必要ないときには読み込まないようにすることはできないか。少なくとも各キャリア向けのファイルを読み込まないようにすることはできないか。

どうも opEmojiFilter で OpenPNE_KtaiEmoji::convertEmoji() がロードされるっぽい。で、 opEmojiFilter は毎回読み込まれると。別にここは特に間違っちゃいない。しかし変換対象となる文字がないにもかかわらずあのような巨大なファイルが読み込まれるのは問題。

うわ、というか OpenPNE_KtaiEmoji そのものがデカイ。ちょっと勘弁してよー。このコードはないわ。

とりあえずの方針としては以下。丸ごとコードを書き換えたいところだが俺はそういう愚かなことをしない主義だ (Joel on Software - Things You Should Never Do, Part I : http://www.joelonsoftware.com/articles/fog0000000069.html を金科玉条にしている。で OpenPNE3 のときに甘言にのせられてこれを破ってひどい目に遭ったと)。

1. OpenPNE_KtaiEmoji で持っている変換リストを外に出す
2. 変換リストを OpenPNE_KtaiEmoji のコンストラクタ時点で構築しない。なぜなら OpenPNE_KtaiEmoji はシングルトンで、しかも OpenPNE_KtaiEmoji::getInstance() の static 変数にインスタンスが格納されるため、リクエストの終わりまでこの巨大なリストを保持したままインスタンスが残り続けると思われ
3. キャリアごとの変換リストを持つスクリプトはそれが必要になるまで読み込まない

ということでまず 1 個目と 2 個目からやっていく::

    Total Incl. Wall Time (microsec):   1,959,579 microsecs
    Total Incl. CPU (microsecs):    1,579,470 microsecs
    Total Incl. MemUse (bytes): 38,889,792 bytes
    Total Incl. PeakMemUse (bytes): 39,008,312 bytes
    Number of Function Calls:   148,488

なんか CPU 時間は改善したけどメモリ周りは特に変化無し。まったく変化ないっていうのもおかしな話だな。リストの読み込みはまだ発生しているのかな。とりあえず先に進む。

ってあれ、 lib/vendor/OpenPNE2 ってオートロードの対象に入ってるよね？　じゃあ OpenPNE_KtaiEmoji の読み込み時点でキャリア向けライブラリインクルードする必要ないじゃん。なんだよ。

つーことでライブラリの読み込みを取り除いて計測::

    Total Incl. Wall Time (microsec):   1,995,318 microsecs
    Total Incl. CPU (microsecs):    1,601,641 microsecs
    Total Incl. MemUse (bytes): 38,846,240 bytes
    Total Incl. PeakMemUse (bytes): 38,972,272 bytes
    Number of Function Calls:   148,478

おっと予想に反してそんなに減ってない。特に不要なファイルが読み込まれた形跡もない。うーんそんなもんか。

2011/11/02 - 3
==============

次は Net_UserAgent_Mobile と Net_IPv4 を見てみる。おっとどっちも opEmojiFilter で opWebRequest::isMobile() するときに読み込まれているぞ。

opWebRequest::isMobile() がこれらのライブラリを使うのは間違いではないけれど、 opEmojiFilter が opWebRequest::isMobile() 呼ぶのは間違いじゃね。 opEmojiFilter は現在のユーザエージェントがモバイルかどうかではなく、現在提供しているページがモバイルかどうかで判断するべき。

で、直してみた::

    Total Incl. Wall Time (microsec):   1,653,887 microsecs
    Total Incl. CPU (microsecs):    1,589,045 microsecs
    Total Incl. MemUse (bytes): 38,800,736 bytes
    Total Incl. PeakMemUse (bytes): 38,951,960 bytes
    Number of Function Calls:   148,395

うう……なんか地道だ。でもそんなもんだよねー。

と思ったら Net_UserAgent_Mobile はまだ読み込まれてらっしゃった。おっとしかも web/prof.php か。うーん……これはしょうがないかな。とりあえず諦めて次に行くか。

しかし Net_UserAgent_Mobile を読み込んでいる限り PEAR も読み込まれるので、うーんなんだかなあ。

2011/11/02 - 4
==============

そしていよいよ Swift だ。こいつをどうしてくれようか。こいつはデフォルトで sfFactoryConfigHandler によってはき出されるキャッシュファイルによって読み込まれるので、 sfFactoryConfigHandler の挙動を変更する必要がある。と一口に言っても、 Swift 周りだけを書き換えるようなシンプルな方法は存在しないので難しいところ。とりあえずコメントアウトだけして効果のほどを確認するか。

ということでキャッシュにある mailer 周りの記述をガッツリ削除してみた::

    Total Incl. Wall Time (microsec):   1,608,736 microsecs
    Total Incl. CPU (microsecs):    1,561,910 microsecs
    Total Incl. MemUse (bytes): 38,480,336 bytes
    Total Incl. PeakMemUse (bytes): 38,631,704 bytes
    Number of Function Calls:   147,980

おお少し減った。むーんこれはできれば採用したいなあ。 factory 周り改善できないかちょっと考えてみようか。

と思って sfContext とか一通り読んでるけど、まあ毎度思うことだけどこのあたりの実装はひどいですねええ。まあ元凶は Mojavi だけども。

で、割と打つ手なしっぽいんですよねこれ。 sfContext を書き換えれば別なんだけどさー。 sfFactoryConfigHandler を拡張して mailer を書き出さないようにしたっていいんだけど、じゃあ mailer が本当に必要になったときにどうするのかというのが残る。 mailer が必要になったとしても mailer を使わない限りパフォーマンスを劣化させない方法を選びたい。そのためには sfContext は邪魔だ。

とりあえずこれは保留ってことで…… Doctrine のコンパイル周りの見直しやります。

2011/11/02 - 5
==============

Doctrine のコンパイラは使わず、 OpenPNE が普通に使いそうな Doctrine のファイルのみを core_compile.yml に追加するようにしてみる。対象となるクラスは以下で割り出す::

    grep "50.*Doctrine" DEFINED_CLASSES_COUNT.201101

これで計測してみた::

    Total Incl. Wall Time (microsec):   1,629,906 microsecs
    Total Incl. CPU (microsecs):    1,576,954 microsecs
    Total Incl. MemUse (bytes): 35,600,744 bytes
    Total Incl. PeakMemUse (bytes): 35,752,352 bytes
    Number of Function Calls:   148,452

おおおおおおおおおおおガッツリ減った！　ガッツリ減ったでー！！！！　うおうういおふふぉうあうふぉうふぁおふおあ

この時点での core_compile.yml は 7.4KB ということで、まだまだ余裕で APC に載るファイルサイズ。うっほほいうっほいほほほ

2011/11/02 - 6
==============

今度は Doctrine 以外で core_compile.yml に入れるべきものを考えてみる。以下のコマンドで割り出す::

    grep -v "50.*Doctrine" DEFINED_CLASSES_COUNT.201101 | grep "^50"

この結果には、これまでの修正で読み込まなくなったファイルや、タイミング的に core_compile.yml で定義できないクラスファイルが含まれるので、それを除外していくことが必要。

あと明らかに言語切り替えガジェットの影響と思われるファイルも混じっている。これは外している SNS が多いと思われるので対象から除外する（今回の計測だけを考えれば除外しない方がいい結果が出るだろうけれども）。具体的にはフォームとバリデータを結果から除外する。

おっと sfYaml とか毎回読み込んでるぞ。これはおかしいな。調べたところ opPluginManager::getPluginActivationList() で使っているっぽい。これはあとで対処する。他の場所では sfYaml は使っていないだろうからこれは結果から除外する。

さらに計測してみた::

    Total Incl. Wall Time (microsec):   1,722,407 microsecs
    Total Incl. CPU (microsecs):    1,650,701 microsecs
    Total Incl. MemUse (bytes): 35,393,360 bytes
    Total Incl. PeakMemUse (bytes): 35,544,928 bytes
    Number of Function Calls:   148,048

むう……思ったより減らなかったな。というか Doctrine が効果的すぎたのか。

読み込んでいるファイル群を一通り眺めてみたけど、モデルファイル以外でこれ以上まとめられそうなのはあまりなさそうな。設定キャッシュ群はなんかもうちょっとまとめられそうな気がするけれども。

2011/11/02 - 7
==============

よし sfYaml が気になるのでこれもやろう。 opPluginManager::getPluginActivationList() がコールする opPluginManager::getPluginListForDatabase() を見てみる。

あーそうか、いろいろ準備が整う前に config/databases.yml を読みに行っているのか……。

このメソッドが何もせずに array() を返すように変更してみると Total Incl. MemUse (bytes) が 35,141,448 bytes になる。ちょっとの労力でこれが改善できるなら改善したいんだけれど、いろいろ考えることが多いなあ。下手にキャッシュとかしても効果的かどうかわからない。

とりあえずこれはこのままにしておくか。

2011/11/02 - 8
==============

結局、我慢できなくてレコードも core_compile に入れた::

    Total Incl. Wall Time (microsec):   1,607,451 microsecs
    Total Incl. CPU (microsecs):    1,569,958 microsecs
    Total Incl. MemUse (bytes): 35,304,784 bytes
    Total Incl. PeakMemUse (bytes): 35,456,248 bytes
    Number of Function Calls:   147,874

うーん微妙だ。 Doctrine_Table のセットアップ周りでコストになっているようなので、もしやと思ったんだけれども……

2011/11/07 - 1
==============

いまさらの話でアレなんですが、 Wall Time とか CPU Time とかがヤケに高い数字をたたき出しているのは、 XDebug 拡張を有効にしているからで、拡張を外すと 0.5 sec くらい縮まります。メモリ使用量も 1MB くらい下がります。

さて、いまのところ問題になっているのは Doctrine_Connection::getTable() で大量にメモリを喰うという問題なのですが、これにはいろいろな原因があって、

* このメソッドでコールする class_exists() で 3,256,768 Bytes のメモリを消費している
* このメソッドでコールする Doctrine_Table::__construct() がコールする、各レコードクラスの ::setUp() メソッドで計 2MB 程度のメモリを消費する
* このメソッドでコールする Doctrine_Table::__construct() がコールする Doctrine_Table::initDefinition() がコールする、各レコードクラスの ::setTableDefinition() メソッドで計 1MB 程度のメモリを消費する
* このメソッドでコールする Doctrine_Table::__construct() がコールする Doctrine_Table::initDefinition() がコールする class_exists() で 5,145,920 Bytes のメモリを消費している

というもの。

::setUp() なり ::setTableDefinition() をもうちょっとなんとかできないかなと思ったけれど、このあたりはちょっと手を加えにくいのと、個々のメモリ消費は目くじらたてるほど多いわけではない（一番高いもので 65,072 Bytes 程度）ので、とりあえず一旦置いておくことにした（でもなぜか毎回動的にテーブル名を構築しているところとかチビチビ改善していけば 1MB 弱くらいの改善はできそうな気はしたけど）。

で、 class_exists() だけれども、やっぱりこいつはオートローディングの仕業です。

まず、 Doctrine_Connection::getTable() における class_exists() は Doctrine_Core::getTable() の引数として与えられたテーブルクラスの存在確認というお仕事をされている。

このうち、最初に読み込まれるのは SnsTermTable なので、こいつのコストを見てみる……あ、これは core_compile の対象に入っているんだった。ということで、 core_compile の対象に入っていないもののなかで最初に読み込まれた ApplicationInviteTable を見てみることにする。

* (run_init::opOpenSocialPlugin/ApplicationInviteTable.class.php : 163,552 Bytes) + (load::opOpenSocialPlugin/ApplicationInviteTable.class.php : 3,576 Bytes)
* (run_init::doctrine/PluginApplicationInviteTable.class.php : 1,144 Bytes) + (load::doctrine/PluginApplicationInviteTable.class.php : 80,336 Bytes)

で、 PluginApplicationInviteTable は (core_compile によって読み込み済みの) Doctrine_Table を継承していますと。 PluginApplicationInviteTable.class.php の load + run_init で 81,480 Bytes かかっているということは、 ApplicationInviteTable のメモリ使用量のうちの 82,072 Bytes + 3,576 Bytes が、 ApplicationInviteTable の読み込みのみで消費したメモリということになるかな。

次に Doctrine_Table::initDefinition() でコールされる class_exists() はどうかなということで、今度は ApplicationInvite を見てみることにしますと。

* (run_init::opOpenSocialPlugin/ApplicationInvite.class.php : 279,336) + (load::opOpenSocialPlugin/ApplicationInvite.class.php : 3,096)
* (run_init::doctrine/PluginApplicationInvite.class.php : 185,504) + (load::doctrine/PluginApplicationInvite.class.php : 3,568)
* (run_init::base/BaseApplicationInvite.class.php : 1,128) + (load::base/BaseApplicationInvite.class.php : 88,224)

うーんさっきから気になっているんだけど、自動生成された基底クラスの load でメモリ使用量が跳ね上がってるのはなんなのかな。まあとりあえず置いておくけど。

せっかく自動生成するのだからもっとメモリに優しいコードを生成したりとか、間に挟んでいるクラスを取っ払ったりとかそういう発想になるかなーとかいろいろ考えてたけど妙案が思いつかない。

ここまで頭をひねっていて、そういえば初期化コストの改善といえば Gree Fast Processor があったじゃないか、と思うに至りましたよ。

Gree Fast Processor 導入
------------------------

つーことで、 http://labs.gree.jp/blog/2010/05/61/ のエントリを参考に、まずは Gree Fast Processor を導入してみる::

    $ wget http://labs.gree.jp/data/source/gree_fast_processor-0.0.1.tgz
    $ tar xf gree_fast_processor-0.0.1.tgz
    $ cd gree_fast_processor-0.0.1
    $ phpize; ./configure; make; sudo make install

おっと::

    /tmp/gree_fast_processor-0.0.1/php_gree_fast_processor.c:31:23: error: sys/epoll.h: No such file or directory

epoll とか OSX で使えないっつーの。

libevent 使って書き直してパッチとか置いておこうと思って、とりあえず epoll のインクルード外してなんとなくコンパイルしてみるかーとか思ったらコンパイル通ってしまったんですけど！::

    $ make test
    (snip)
    No tests were run.

そうですか！

とりあえず先に進むか……::

    $ mkdir -p ~/Sites/sf/op3-ebihara/lib/vendor/gree
    $ cp gree_fast_processor.php gree_fast_processor_listener.php ~/Sites/sf/op3-ebihara/lib/vendor/gree 

この状態で prof.php に Gree_Fast_Processor 用の記述を追加する。そして Gree_Fast_Processor::$ident_list にハンドラを追加するらしい::

    define('PATH_TO_OP3_LISTENER', dirname(__FILE__).'/../../util/gree_fast_processor_handler.php');

して、::

    var	$ident_list = array(
        // sample
        'op3_ebihara' => PATH_TO_OP3_LISTENER,
    );

した。

あとはリスナーを追加するというのでブログの情報の通りやってみた。

で、試したところ "Connection reset by peer" な Warning が gree_fast_processor.php on line 137 で出る。んーどっかで exit() とかしてるんかなあ。それとも単純に epoll 外しただけなのが悪いですか。

うーん、まあ、 Gree Fast Processor を導入して終わりっていうのはいろいろな意味で現実的じゃないし、とりあえず本格的に試すのはまたの機会ということで、ここはとりあえず諦めるか。

じゃあどうすればというのがやっぱり難しいな−。レコードクラスとかテーブルクラスとかをシンプルにしていく方法しか策はない気がするなあ。とりあえず明日に回す。成果出せなかったなー。

2011/11/08 - 1
==============

昨日帰り道で、「レコードクラスファイルとテーブルファイルをセットでモデル単位でコンパイルすればいいんじゃね？」という案が浮かんだのでやってみる。（こういうの帰り道で浮かぶのやめてほしい）

ということでやってみた。まずは Before::

    Total Incl. Wall Time (microsec):   1,691,133 microsecs
    Total Incl. CPU (microsecs):    1,618,762 microsecs
    Total Incl. MemUse (bytes): 35,309,968 bytes
    Total Incl. PeakMemUse (bytes): 35,461,440 bytes
    Number of Function Calls:   147,961

そして After::

    Total Incl. Wall Time (microsec):   1,641,909 microsecs
    Total Incl. CPU (microsecs):    1,596,500 microsecs
    Total Incl. MemUse (bytes): 35,043,608 bytes
    Total Incl. PeakMemUse (bytes): 35,195,112 bytes
    Number of Function Calls:   147,204

おお。

Doctrine_Core::getTable() にかかるメモリは 11,037,760 Bytes から 10,761,800 Bytes に減った。

コンパイル済みクラスファイルの run_init には、一番コストがかかったもので 421,776 Bytes 消費していた。うーんもうちょっとなんとかならないものかな。でもこれ以上減らすにはスクリプトの内容を変えるくらいのコンパイルをしないとダメな気がする。

2011/11/08 - 2
==============

そういや core_compile でモデルとか読み込んでいるのと、今回のコンパイルのやつはどちらか一方にしないとなにかとアレだなーとか思って、 core_compile のほうを外してみた::

    Total Incl. Wall Time (microsec):   1,654,670 microsecs
    Total Incl. CPU (microsecs):    1,598,515 microsecs
    Total Incl. MemUse (bytes): 34,921,000 bytes
    Total Incl. PeakMemUse (bytes): 35,072,456 bytes
    Number of Function Calls:   147,211

おお、さらに減った。こういうの大事だなー。

2011/11/08 - 3
==============

さて、未だテーブルの初期化コストが高い件についてですが、

* ベースのレコードクラスの setTableDefinition() は完全に不要
    * 静的な定義しかおこなわれないので Doctrine_Table の派生クラス側で静的に定義すればいいじゃない
    * ベースのクラス以外で動的に setTableDefinition() がおこなわれることは妨げない
    * まあ setTableDefinition() にかかるコストは 65,072 Bytes で、大して高いものではないのだが、ちりも積もると 1,037,696 Bytes になるので……
* 一方で、 setUp() をどうにかするのはちょっと難しいな
* setUp() をどうにかできないとすると、コンパイル時にベースクラスを取り除くっていうことがやりにくい

ということで、コンパイル時に setTableDefinition() が不要になるようにあらかじめテーブルクラスにプロパティを追加していこうという取り組み::

    Total Incl. Wall Time (microsec):   1,642,723 microsecs
    Total Incl. CPU (microsecs):    1,555,960 microsecs
    Total Incl. MemUse (bytes): 35,259,360 bytes
    Total Incl. PeakMemUse (bytes): 35,410,560 bytes
    Number of Function Calls:   144,287

あれ？　ちょっと前の結果と比べてみる。

あー……プロパティに書きだしたぶん、初期化コストが微増したわけだな（改善したファイルもあるけど）。すべて静的に定義するよりランタイムで定義した方がいいケースもあるのか、そりゃそうか。メソッドコールがないぶん若干速くはなっているけどそこは今回目指しているものじゃない。

じゃあ頑張ってベースクラスを取り除くってことをやってみるかなー。とりあえず無効化しているけどコンパイル結果ちょこちょこいじるやつはコミットしておく。
