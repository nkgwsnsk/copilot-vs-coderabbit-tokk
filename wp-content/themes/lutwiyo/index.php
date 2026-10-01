<?php get_header(); ?>
<?php
$args = array(
	'post_type'      => 'post',
	'posts_per_page' => -1,
);
$query = new WP_Query($args);
?>
	<div class="container">
		<ul>
			<?php if ($query->have_posts()) : ?>
				<?php while ($query->have_posts()) : $query->the_post(); ?>
					<li><?php echo get_the_title(); ?></li>
				<?php endwhile; ?>
			<?php endif; ?>
		</ul>
	</div>
<?php get_footer(); ?>