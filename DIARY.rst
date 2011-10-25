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



