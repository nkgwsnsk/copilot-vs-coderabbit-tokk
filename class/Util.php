<?php

/**
 * Created by PhpStorm.
 * User: kondo
 * Date: 2017/07/06
 * Time: 8:28
 */
class Util
{
	/**
	 * 文字列を、指定された長さで区切って返却する
	 * @note $post->post_content のように、タグの含まれた値が送信されるケースを想定しています。
	 *
	 * @param mixed $target
	 * @param int $limit 長さ
	 */
	static function get_extract($target, $limit)
	{
		// WP_Post オブジェクトの場合は、本文を取得
		if ($target instanceof WP_Post) {
			setup_postdata($target);
			global $post;
			$former_post = $post;
			$post = $target;
			if ($target->post_type == 'event') {
				$string = '';
			} elseif ($target->post_type == 'feature') {
				$string = get_field('csa_text_lead');
				$string = preg_replace('/\n|\r|\r\n/', '', $string );
			} else {
				$string = get_the_content();
			}
			wp_reset_postdata();
			$post = $former_post;
		} else {
			$string = $target;
		}
		$string = wp_strip_all_tags($string);
		$string = strip_shortcodes($string);
		// 文字数がリミットより多い場合は、三点リーダをつける
		if (mb_strlen($string, 'UTF-8') > $limit) {
			$string = mb_substr($string, 0, $limit, 'utf-8') . '...';
		}
		return $string;
	}
	
	/*
	* スマホ判定
	*/
	static function is_mobile()
	{
		$useragents = array(
			'iPhone',          // iPhone
			'iPod',            // iPod touch
			'Android',         // Android
		);
		$pattern = '/' . implode('|', $useragents) . '/i';
		$ret = FALSE;
		if (preg_match($pattern, $_SERVER['HTTP_USER_AGENT']) == 1) {
			$ret = TRUE;
		}
		return $ret;
	}
}