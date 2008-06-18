<?php
/*
Plugin Name: 17fav Bookmark & Share
Plugin URI: http://17fav.com/wp-plugin
Description: Bookmark & Share by <a href="http://fairyfish.net/">Denis</a> & <a href="http://blog.istef.info">Liu Yang</a>
Version: 2.0
Author: Denis & LiuYang
Author URI: http://17fav.com/
*/


//=== Please DO NOT edit following lines unless you know actually what you do :P ===
define('BS_ALREADY_JQUERY',0);				//if your template or plugin has been loaded jQuery without using wp_enquenue_script, change the value to 1
define('BS_ALREADY_JQUERY_DIMENSIONS',0);	//if your template or plugin has been loaded jQuery-Dimensions plugin without using wp_enquenue_script, or with a name differ from jquery-dimensions change the value to 1
//===END JQUERY===
class WPBS {
	var $uid;
	var $popwin;
	var $insert2;
	var $insert2feed;
	var $insert2home;
	var $bs_server_url = 'http://17fav.com/';
	var $imgbtn;
	var $iconbtn;
	var $postids;
	
	function WPBS() {
		$this->uid = get_option('bs_uid');
		if ($this->uid === false) { // uid did not exists, we create it.
			$this->uid = $this->_generate_uid();
			update_option('bs_uid',$this->uid);
		}
		
		if (false === get_option('bs_popwin')) {
			$this->popwin = false;
		} else {
			$this->popwin = get_option('bs_popwin');
		}
		
		//initialize the button image
		$this->imgbtn = get_bloginfo('siteurl'). "/" .PLUGINDIR . "/" . dirname(plugin_basename (__FILE__))."/bookmark.gif";
		$this->iconbtn = get_bloginfo('siteurl'). "/" .PLUGINDIR . "/" . dirname(plugin_basename (__FILE__))."/icons.gif";
		
		//load jquery & dimensions plugin
		$this->_load_jquery_lib();
		
		//initialize post ids
		$this->postids = array();
		
		//actions
		add_action('wp_head',array(&$this,'bs_header'));
		add_action('wp_footer',array(&$this,'bs_footer'));
		add_action('admin_menu',array(&$this,'admin_page'));
		//auto add to single?
		if (false === get_option('bs_insert2')) {
			$this->insert2 = true;
			update_option('bs_insert2',$this->insert2);
		} else {
      $this->insert2 = get_option('bs_insert2');
    }
    
		if (false === get_option('bs_insert2home')) {
			$this->insert2home = false;
			update_option('bs_insert2home',$this->insert2home);
		} else {
      $this->insert2home = get_option('bs_insert2home');
    }
    
		if (false === get_option('bs_insert2feed')) {
			$this->insert2feed = true;
			update_option('bs_insert2feed',$this->insert2feed);
		} else {
      $this->insert2feed = get_option('bs_insert2feed');
    }
		
		add_filter('the_content',array(&$this,'bs_add_content'));
	}
	
	function bs_string($feed_mode = false) {
		global $post;
		$fitems = array();
		$fitems['blog_hash'] = $this->uid;
		$fitems['url'] = get_permalink($post->ID);
		$fitems['title'] = get_the_title($post->ID);
		if (has_excerpt($post->ID)) {
			$fitems['description'] = get_the_excerpt();
		} else {
			$fitems['description'] = $this->_utrim(strip_tags($post->post_content),100);
		}
		$fitems['tags'] = '';
		$tags = get_the_tags($post->ID);
		if (!is_wp_error($tags) && is_array($tags) && count($tags)) {
			$tagline = '';
			foreach($tags as $tag) {
				if (strlen($tagline)) $tagline .= ",";
				$tagline .= $tag->name;
			}
			$fitems['tags'] = $tagline;
		}
		
		if (!$feed_mode) {
			$fitems['server'] = '';
			
			$js = '<form id="fav-post-' . $post->ID . '" style="display:none" method="post" action=""';
			if ($this->popwin) {
				$js .= ' target="bs_popwin"';
			}
			$js .= '>';
			foreach ($fitems as $k=>$v) {
				$js .= sprintf('<input type="hidden" name="%s" value="%s" />',$k,str_replace("\"","'",$v));
			}
			$js .= "</form>";
			$this->postids[] = $post->ID;
			$feed_str = '';
		} else {
			$feed_str = '';
			foreach ($fitems as $k=>$v) {
				$feed_str .= "$k=".urlencode($v)."&";
			}
			$feed_str = "?$feed_str";
			$feed_str = substr($feed_str,0,-1);
		}
		
		$btn = '<a href="http://17fav.com/%s" rel="%d" class="btn-17fav" title="用 17fav 收藏和分享本文"><img src="%s" alt="17fav 收藏本文" /></a>';
		$btn = sprintf($btn,$feed_str,$post->ID,$this->imgbtn);
		
		return $btn.$js;
	}
	
	function bs_add_content($text) {
		if (is_home() && $this->insert2home) {
			return $text . $this->bs_string(false);
		} 
		
		if(is_single() && $this->insert2) {
				return $text . $this->bs_string(false);
		}
		
		if(is_feed() && $this->insert2feed) {
      return $text . $this->bs_string(true);
		}
		
		return $text;
	}

	function bs_header() {
		$s = $this->_get_services();
		$css  = '<style type="text/css">';
		$css .= '#bs-17fav-pop{display:block;position:absolute;z-index:9999;background:white;border: 1px solid #69f;width: 310px;}';
		$css .= '#bs-17fav-pop h1{display:block;margin:0;padding: 5px 10px;background: #69f;font-size: 14px;text-align: left;color:white;}';
		$css .= '#bs-17fav-pop ul.bs-17fav-list {margin:0;padding: 0 5px;list-style:none;width:300px;overflow:hidden;}';
		$css .= '#bs-17fav-pop ul.bs-17fav-list li {margin: 2px 0;padding: 2px 5px 2px 29px;line-height: 20px;font-size:12px;list-style:none;float:left;width:115px;overflow:hidden;text-align:left;background: url('.$this->iconbtn.') no-repeat}';
		$css .= '#bs-17fav-pop ul.bs-17fav-list li a {color:#69f;text-decoration:none};';
		$css .= '#bs-17fav-pop ul.bs-17fav-list li a:hover {text-decoration:underline};';
		foreach ($s as $se) {
			$css .= ' #bs-17fav-pop ul.bs-17fav-list li.bs-btn-'.$se[1].'{background-position: '.$se[2].' '.$se[3].';}';
		}
		$css .= '#bs-17fav-pop h3 {clear:left;background: #69f;color: white;text-align:right;margin:0;padding: 3px 10px;font-size: 10px;}';
		$css .= '#bs-17fav-pop h3 a {color: white;text-decoration:underline;}';
		$css .= '</style>';
		echo $css;
	}
	
	function bs_footer() {
		$s = $this->_get_services();
		$fav_pop = '<div id="bs-17fav-pop" style="display:none">';
		$fav_pop .= '<h1>收藏 & 分享 </h1>';
		$fav_pop .= '<ul class="bs-17fav-list">';
		foreach ($s as $se) {
			$fav_pop .= '<li class="bs-btn-'.$se[1].'"><a href="#" class="btn-17fav2" id="btn-s-'.$se[1].'" rel="">'.$se[0].'</a></li>';
		}
		$fav_pop .= '</ul>';
		$fav_pop .= '<h3>Powered by <a href="http://17fav.com">17fav.com</a></h3>';
		$fav_pop .= '</div>';
		$js = '<script type="text/javascript">';
		$js .= 'var bsthis = function(i,s) {';
		$js .= 	'if (i<=0) return false;';
		$js .= 	'var fid = "fav-post-"+i;';
		$js .= 	'theForm = jQuery("#"+fid);';
		$js .= 	'if (s.length>0) { ';
		$js .= 		'theForm.get(0).action = "http://17fav.com/redirect.php";';
		$js .= 		'theForm.children("input").each(function(){if (this.name == "server") this.value=s;});';
		$js .= 	'} else {';
		$js .= 		'theForm.get(0).action = "http://17fav.com/index.php";';
		$js .= 	'}';
		if ($this->popwin) {
		$js .= 	'theForm.submit(function(){var e=window.open("http://17fav.com/","bs_popwin"); if (e==null) {alert("Your browser doesn\'t allow our window to popup. Please fix the settings"); return false;} else {return true;}});';
		}
		$js .= 	'theForm.submit();';
		$js .= '};';
		$js .= 'jQuery(document).ready(function(){';
		$js .= 	'jQuery(".btn-17fav").hover(function(){';
		$js .= 		'var btn_rel = this.rel;';
		$js .= 		'var btnp = jQuery(this).position();';
		$js .= 		'jQuery("#bs-17fav-pop").css({left:btnp.left,top:btnp.top,marginTop:jQuery(this).height()});';
		$js .=		'jQuery("#bs-17fav-pop").children("ul").children("li").each(function(){jQuery(this).children("a").get(0).rel=btn_rel;});';
		$js .= 		'jQuery("#bs-17fav-pop").fadeIn("fast");';
		$js .= 	'},function(){});';
		$js .= 	'jQuery("#bs-17fav-pop").hover(function(){},function(){';
		$js .= 		'jQuery(this).fadeOut("fast");';
		$js .=	'});';
		$js .= 	'jQuery(".btn-17fav").click(function(){';
		$js .= 		'bsthis(this.rel,"");';
		$js .=		'return false;';
		$js .= 	'});';
		$js .= 	'jQuery(".btn-17fav2").click(function(){';
		$js .= 		'bsthis(this.rel,this.id.substr(6));';
		$js .=		'return false;';
		$js .= 	'});';
		$js .= '});';
		$js .= '</script>';
		echo $fav_pop;
		echo $js;
	}
	
	function uninstall() {
	}
	function admin_page() {
		add_options_page('收藏&分享','收藏&分享', 9, 'bookmark-share', array(&$this,'admin'));
	}
	function admin() {
		if (!empty($_POST['bs_submit'])) {
			update_option('bs_popwin',(bool)$_POST['bs_popwin']);
			update_option('bs_insert2',(bool)$_POST['bs_insert2']);
			update_option('bs_insert2home',(bool)$_POST['bs_insert2home']);
			update_option('bs_insert2feed',(bool)$_POST['bs_insert2feed']);
		}
?>
<div class="wrap">
	<h2>17fav.com - 收藏&分享</h2>
	<form method="post">
	<table class="form-table">
		<tr valign="top">
			<th scope="row">开启收藏页面</th>
			<td>
				<input type="radio" name="bs_popwin" value="1"<?php if (get_option('bs_popwin')) echo " checked";?> />在新窗口中
				<input type="radio" name="bs_popwin" value="0"<?php if (!get_option('bs_popwin')) echo " checked";?> />在原页面
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">自动插入按钮</th>
			<td>
				<input type="radio" name="bs_insert2" value="1"<?php if (get_option('bs_insert2')) echo " checked";?> />自动插入按钮
				<input type="radio" name="bs_insert2" value="0"<?php if (!get_option('bs_insert2')) echo " checked";?> />手动插入按钮
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">在首页文章列表中插入按钮</th>
			<td>
				<input type="radio" name="bs_insert2home" value="1"<?php if (get_option('bs_insert2home')) echo " checked";?> />是
				<input type="radio" name="bs_insert2home" value="0"<?php if (!get_option('bs_insert2home')) echo " checked";?> />否
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">自动插入到 Feed</th>
			<td>
				<input type="radio" name="bs_insert2feed" value="1"<?php if (get_option('bs_insert2feed')) echo " checked";?> />是
				<input type="radio" name="bs_insert2feed" value="0"<?php if (!get_option('bs_insert2feed')) echo " checked";?> />否
			</td>
		</tr>
	</table>
	<p class="submit"><input type="submit" name="bs_submit" class="button" value="更新设置" /></p>
	</form>
</div>
<?php
	}
	function _get_services() {
		$services = array();
		
		$services[] = array('del.icio.us','delicious','5px','0');
		$services[] = array('百度搜藏','baidu','5px','-24px');
		$services[] = array('QQ 书签','qq','5px','-48px');
		$services[] = array('Google 书签','google','5px','-72px');
		$services[] = array('雅虎收藏','yahoo','5px','-96px');
		$services[] = array('mister-wong.cn','mister-wong-cn','5px','-120px');
		$services[] = array('饭否','fanfou','5px','-144px');
		$services[] = array('Facebook','facebook','5px','-168px');
		
		return $services;
	}
	function _generate_uid() {
		return md5(get_bloginfo('home'));
	}
	function _utrim($str,$length) {
		$char = get_bloginfo('charset');
		if (function_exists('iconv_substr')) {
			$l = iconv_strlen($str,$char);
			if ($l<=$length) return $str;
			else {
				return iconv_substr($str,0,$length,$char) . "...";
			}
		} elseif (function_exists('mb_substr')) {
			$l = mb_strlen($str,$char);
			if ($l<=$length) return $str;
			else {
				return mb_substr($str,0,$length,$char) . "...";
			}
		} else {
			$l = strlen($str);
			if ($l<$length) return $str;
			else {
				$r = substr($str,0,$length);
				for ($i=strlen($r)-1; $i>=0; $i-=1){
					$hex .= ' '.ord($r[$i]);
					$ch = ord($r[$i]);
			        if (($ch & 128)==0) return substr($r,0,$i)."...";
					if (($ch & 192)==192) return substr($r,0,$i)."...";
				}
				return $r.$hex."...";
			}
		}
	}
	function _load_jquery_lib() {
		if (!defined('BS_ALREADY_JQUERY') || !BS_ALREADY_JQUERY) {
			wp_enqueue_script('jquery-dimensions',get_bloginfo('siteurl'). "/" .PLUGINDIR . "/" . dirname(plugin_basename (__FILE__)).'/jquery.dimensions.js',array('jquery'),'1.1.2');
		} else {
			if (!defined('BS_ALREADY_JQUERY_DIMENSIONS') || !BS_ALREADY_JQUERY_DIMENSIONS) {
				wp_enqueue_script('jquery-dimensions',get_bloginfo('siteurl'). "/" .PLUGINDIR . "/" . dirname(plugin_basename (__FILE__)).'/jquery.dimensions.js',array(),'1.1.2');
			}
		}
	}
}
$wpbs = new WPBS;
function bookmark_share() {
	global $wpbs;
	echo $wpbs->bs_string(false);
}
?>