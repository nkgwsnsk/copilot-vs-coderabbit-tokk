<?php get_template_part('meta'); ?>

<?php get_header(); ?>
<?php get_template_part('sidenav'); ?>

	<div id="wrapper" class="l-wrapper--under">
		<?php get_template_part('breadcrumb'); ?>
		<main id="main" class="l-main">
			<article class="p-article">
				<?php if(have_posts()): while(have_posts()): the_post(); ?>
				<div class="p-article__inner">
					<?php
						// MOD::20240207:s.ito Exit if $termvalue type is not Array 
						$termvalue = get_the_terms( $post->ID, 'articles_cat' );
						if ( is_array($termvalue) ) {
							//タクソノミーのターム取得
							$terms = array_shift(get_the_terms( $post->ID, 'articles_cat' ));
						}else{
							$terms = get_the_terms( $post->ID, 'articles_cat' );
						}

                        //カスタムフィールド画像取得
                        $articlesImg = get_post_meta($post->ID, 'articles_img', true);
                        $fieldName = wp_get_attachment_image_src($articlesImg, 'full');
                    ?>

					<div class="p-article__thum" style="background-image: url(<?php echo $fieldName[0]; ?>);"></div>
					<div class="p-article__content">
						<h1 class="p-article__title"><?php the_title(); ?></h1>
						<p class="c-articleDate"><span class="c-articleDate__time"><?php the_time('Y.m.d'); ?></span><span class="c-articleDate__category"><?php echo $terms->name; ?></span></p>
						<div class="p-article__tagWrap">
							<ul class="p-article__tagList">
								<?php
								if ($terms = get_the_terms($post->ID, 'articles_station')) {
								    foreach ( $terms as $term ) {
								        echo '<li class="c-tag--station"><a href="'.get_term_link($term->slug, 'articles_station').'">' .$term->name. '</a></li>';
								    }
								}
								?>
							</ul>
							<ul class="p-article__tagList">
								<?php
								if ($terms = get_the_terms($post->ID, 'articles_tag')) {
								    foreach ( $terms as $term ) {
								        echo '<li class="c-tag"><a href="'.get_term_link($term->slug, 'articles_tag').'">' .$term->name. '</a></li>';
								    }
								}
								?>
							</ul>
						</div>
						
						<div class="p-article__body">
							<?php the_content(); ?>
						</div>

						<?php if(get_field('articles_free')): ?>
						<div class="p-article__freeText">
							<p><?php the_field('articles_free'); ?></p>
						</div>
						<?php endif; ?>


						<?php if(get_field('supplementText')): ?>
						<div class="p-article__remarks">
							<ul class="p-article__remarksList">
								<?php the_field('supplementText'); ?>
							</ul>
						</div>
						<?php endif; ?>

						
						<div class="p-article__sns">
							<ul class="p-article__snsList">
								<li class="p-article__snsListItem sns_tw">
									<a href="https://twitter.com/share?url=<?php the_permalink(); ?>&text=【<?php the_title(); ?>】" rel="nofollow" target="_blank"></a>
								</li>
								<li class="p-article__snsListItem sns_fb">
									<a href="https://www.facebook.com/share.php?u=<?php the_permalink(); ?>" rel="nofollow" target="_blank"></a>
								</li>
								<li class="p-article__snsListItem sns_line">
									<a href="https://timeline.line.me/social-plugin/share?url=<?php the_permalink(); ?>"></a>
								</li>
							</ul>
							<p class="p-article__snsTxt">この記事をシェアする</p>
						</div>
						<div class="p-article__lowerTagwrap">
							<p class="p-article__lowerTagHeading">関連キーワード</p>
							<ul class="p-article__tagList">
								<?php
								if ($terms = get_the_terms($post->ID, 'articles_tag')) {
								    foreach ( $terms as $term ) {
								        echo '<li class="c-tag"><a href="'.get_term_link($term->slug, 'articles_tag').'">' .esc_html($term->name). '</a></li>';
								    }
								}
								?>
							</ul>
						</div>
					</div> <!-- /article__content -->
				</div><!-- /article__inner -->
				<?php endwhile; endif; ?>
			</article>
			<!-- 広告 -->
			<?php if(get_field('ad_articlesBottom',37)): ?>
				<div class="c-adContainer u-pb20"><?php the_field('ad_articlesBottom',37); ?></div>
			<?php endif; ?>
			<!-- 関連記事 -->
			<div class="l-section--white">
				<div class="l-section__inner">
					<h2 class="l-section__title">関連記事</h2>
					<div id="logly-lift-4333539"></div>
					<div id="logly-lift-4333540"></div>
					<script>
					if (window.innerWidth < 960) {
					var _lgy_lw = document.createElement("script");
						_lgy_lw.type = "text/javascript";
						_lgy_lw.charset = "UTF-8";
						_lgy_lw.async = true;
						_lgy_lw.src= "https://l.logly.co.jp/lift_widget.js?adspot_id=4333540";
						var _lgy_lw_0 = document.getElementsByTagName("script")[0];
						_lgy_lw_0.parentNode.insertBefore(_lgy_lw, _lgy_lw_0);
					} else {
					var _lgy_lw = document.createElement("script");
						_lgy_lw.type = "text/javascript";
						_lgy_lw.charset = "UTF-8";
						_lgy_lw.async = true;
						_lgy_lw.src= "https://l.logly.co.jp/lift_widget.js?adspot_id=4333539";
						var _lgy_lw_0 = document.getElementsByTagName("script")[0];
						_lgy_lw_0.parentNode.insertBefore(_lgy_lw, _lgy_lw_0);
					}
					</script>
	<style>
		#logly-lift-4333540 .logly-lift-widget-header {
			display: none;
		}
	</style>
				</div>
			</div>
			<?php get_template_part('content-bottom'); ?>
		</main>
		<?php get_template_part('sidebar'); ?>
	</div>
	<!-- /wrapper -->

<?php get_footer(); ?>