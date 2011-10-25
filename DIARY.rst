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
