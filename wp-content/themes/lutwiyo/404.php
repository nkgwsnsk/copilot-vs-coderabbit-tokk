<?php get_header(); ?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <article class="notFound__article">
        <section class="notFound__inner">
            <h1 class="fontEn notFound__title notFound__title--en">404 Not Found</h1>
            <h2 class="notFound__title notFound__title--jp">
                お探しのページは見つかりませんでした。<br>
                アクセスしようとしたページは削除、<br class="brSp">変更された可能性があります。
            </h2>
            <div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">
                <a class="flex--cc btnLink" href="<?= home_url('/'); ?>" aria-label="トップに戻る" title="トップに戻る">
                    <p class="btnTtext">トップに戻る</p>
                    <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                </a>
            </div>
        </section>
    </article>
</main>
<?php get_footer(); ?>
