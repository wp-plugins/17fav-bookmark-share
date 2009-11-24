<?php
/*
Plugin Name: bShare 分享
Plugin URI: http://www.bshare.cn/wp-plugin/
Description: 数以万计的分享，源自一个简单的按钮， <a href="http://fairyfish.net/">bShare 分享</a> 是一个强大的网页分享插件工具，您的读者可以将您网站上精采的内容快速分享、转贴到社群网络上。
Version: 4.0
Author: Denis
Author URI: http://fairyfish.net/
*/
$bshare_code = '<script language="javascript" type="text/javascript" src="http://www.bshare.cn/bshare_load"></script>';

add_filter('the_content', 'bshare');
function bshare($content){
	if(is_single() || is_page()){
		global $bshare_code;
		$content = $content."<p>".$bshare_code."</p>";
	}elseif(is_feed()){
		global $post;
		
		
		$bshare_feed_code = '<p><a href="http://17fav.com/?url='.urlencode(get_permalink($post->ID)).'&title='.urlencode($post->post_title).'" title="用 17fav 收藏和分享本文"><img src="http://17fav.com/i/bookmark.gif" alt="17fav 收藏本文" /></a></p>';
		
		$content = $content.$bshare_feed_code;
	}
	return $content;
}
add_action('plugins_loaded', 'widget_sidebar_bshare');
function widget_sidebar_bshare() {
	function widget_bshare($args) {
		if(is_single()||is_page()) return;
	    extract($args);
		echo $before_widget;		
		//echo $before_title . 'bShare 分享' . $after_title;
		global $bshare_code;
		echo $bshare_code;
		echo $after_widget;
	}
	register_sidebar_widget('bShare 分享', 'widget_bshare');
}
?>