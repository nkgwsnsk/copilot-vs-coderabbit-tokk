<?php
/* Template Name: 記事一覧リスト */
get_header();
?>
    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">
        <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection">
                <h1 class="title fs--22">記事一覧リスト</h1>
                <?php
                $args = [
                    'post_type' => 'articles',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => 'area',
                            'compare' => 'NOT EXISTS', // ACF未設定（meta自体がない）
                        ]
                    ],
                ];
                $query = new WP_Query($args);

                if ($query->have_posts()) :
                    echo '<ul class="article-list">';
                    while ($query->have_posts()) : $query->the_post();
                        ?>
                        <li class="article-list__item">
                            <a href="<?php the_permalink(); ?>" class="article-list__link">
                                <?php the_title(); ?>
                            </a>
                        </li>
                        <?php
                    endwhile;
                    echo '</ul>';
                    wp_reset_postdata();
                else :
                    echo '<p>該当する記事がありません。</p>';
                endif;
                ?>
            </section>
        </article>
    </main>
<?php get_footer(); ?>