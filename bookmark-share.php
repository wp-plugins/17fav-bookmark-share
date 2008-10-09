<?php
/*
Plugin Name: 17fav Bookmark & Share
Plugin URI: http://17fav.com/wp-plugin
Description: Bookmark & Share by <a href="http://fairyfish.net/">Denis</a> & <a href="http://blog.istef.info">Liu Yang</a>
Version: 3.0.2
Author: Denis & LiuYang
Author URI: http://17fav.com/
*/


//=== Please DO NOT edit following lines unless you know actually what you do :P ===
define('BS_ALREADY_JQUERY',0);				//if your template or plugin has been loaded jQuery without using wp_enquenue_script, change the value to 1
define('BS_ALREADY_JQUERY_DIMENSIONS',0);	//if your template or plugin has been loaded jQuery-Dimensions plugin without using wp_enquenue_script, or with a name differ from jquery-dimensions change the value to 1
//===END JQUERY===
if (!function_exists('json_encode')) {
	require_once(dirname(__FILE__)."/portable-pear-json.php");
}

class WPBS {
	var $version = "3.0.2";
	var $uid;
	var $popwin;
	var $insert2;
	var $insert2feed;
	var $insert2home;
	var $bs_server_url = 'http://17fav.com/';
	var $imgbtn;
	var $iconbtn;
	var $postids;
	var $footer_forms;

	
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
		
		//load jquery & dimensions plugin
		if (!defined('WP_ADMIN')) $this->_load_jquery_lib();
		
		//initialize post ids
		$this->postids = array();
		
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
	
		//actions
		add_action('wp_head',array(&$this,'bs_header'));
		add_action('admin_head',array(&$this,'bs_admin_head'));
		add_action('wp_footer',array(&$this,'bs_footer'));
		add_action('admin_menu',array(&$this,'admin_page'));
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
		$this->footer_forms = '';
		echo $this->bs_code(false,'head');
	}
	function bs_admin_head() {
		echo $this->bs_code(true,'head');
	}
	function bs_footer() {
		echo $this->bs_code(false,'footer');
		echo '<div style="display:none">';
		echo $this->footer_forms;
		echo '</div>';
	}

	function bs_string($feed_mode = false) {
		global $post;
		$fitems = array();
		$fitems['v'] = $this->version;
		$fitems['hash'] = $this->uid;
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
			
			$formd = '<form id="fav-post-' . $post->ID . '" style="display:none" method="post" action=""';
			if ($this->popwin) {
				$formd .= ' target="bs_popwin"';
			}
			$formd .= '>';
			foreach ($fitems as $k=>$v) {
				$formd .= sprintf('<input type="hidden" name="bs17fav_%s" value="%s" />',$k,str_replace("\"","'",$v));
			}
			$formd .= "</form>";
			$this->footer_forms .= $formd;
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
		//
		$r = $this->_get_color_schemes();
		$btnurl = $this->_get_btn_url($r[0]);
		$btn = '<a href="http://17fav.com/%s" rel="%d" class="btn-17fav" title="用 17fav 收藏和分享本文"><img src="%s" alt="17fav 收藏本文" /></a>';
		$btn = sprintf($btn,$feed_str,$post->ID,$btnurl);
		
		return $btn;
	}

	
	function bs_code($preview = false,$where = 'all') {
		$css = ''; $js = ''; $html = '';
		$all_servs = $this->_get_all_services();
		$serv_slugs = $this->_get_services();
		$servs = array();
		foreach($serv_slugs as $serv) {
			foreach($all_servs->detail as $s) {
				if ($serv == $s->slug) {
					$servs[] = $s;
					break;
				}
			}
		}
		if ($where == 'all' || $where == 'head') {
			list(
				$bs_btn,
				$bg_color,
				$bg_border_color,
				$title_bg_color,
				$title_text_color,
				$item_text_color,
				$item_hover_border_color
				) = $this->_get_color_schemes();
			
			$css = '<style type="text/css">';
			$css .= '#bs-17fav-pop{background:'.$bg_color.';width:310px;border:1px solid '.$bg_border_color;
			if (!$preview) {
				$css .= ';position:absolute;display:none';
			}
			$css .= '} ';
			$css .= '#bs-17fav-pop h1{margin:0;padding:5px 10px;text-align:left;font-size:14px;color:'.$title_text_color.';background:'.$title_bg_color.'} ';
			$css .= '#bs-17fav-pop h3{clear:both;margin:0;padding:2px 10px;text-align:right;font-size:10px;font-weight:normal;background:'.$title_bg_color.';color:'.$title_text_color.'} ';
			$css .= '#bs-17fav-pop h3 a,#bs-17fav-pop h3 a:hover{color:'.$title_text_color.'} ';
			$css .= 'ul.bs-17fav-list{margin:0;padding:5px;list-style:none;width:300px;} ';
			$css .= 'li.bs-17fav-item{cursor:pointer;display:block;float:left;width:140px;overflow:hidden;height:22px;padding:1px;margin:2px 4px;list-style:none;position:relative} ';
			$css .= 'li.bs-17fav-item-hover{padding:0;border:1px solid '.$item_hover_border_color.'} ';
			$css .= 'li.bs-17fav-item span.bs-17fav-icon{display:block;position:absolute;width:16px;height:16px;top:3px;left:3px;overflow:hidden} ';
			if ($preview) $css_servs = &$all_servs->detail;
			else $css_servs = &$servs;
			foreach ($css_servs as $serv) {
				$css .= 'li#bs-17fav-item-'.$serv->slug.' span.bs-17fav-icon{background:url('.$all_servs->url.') no-repeat -'.$serv->left.'px -'.$serv->top.'px} ';
			}
			$css .= 'li.bs-17fav-item span.bs-17fav-text{display:block;height:22px;line-height:22px;width:110px;position:absolute;top:0;left:30px;text-align:left;font-size:12px;color:'.$item_text_color.'} ';
			$css .= '</style>';
			$js = '<script type="text/javascript">';
			$js .= 'jQuery(document).ready(function(){jQuery(".bs-17fav-item").hover(function(){jQuery(this).addClass("bs-17fav-item-hover");},function(){jQuery(this).removeClass("bs-17fav-item-hover");});});';
			if (!$preview){
				$js .= 'var BSJS={';
				
				$js .= 'mouse_in_pos: [],';
				
				$js .= 'bsthis:function(i,s){';
				$js .= 'if (i<=0) return;';
				$js .= 'var fid = "fav-post-"+i;';
				$js .= 'theForm = jQuery("#"+fid);';
				$js .= 	'if (s.length>0) { ';
				$js .= 		'theForm.get(0).action = "http://17fav.com/redirect.php";';
				$js .= 		'theForm.children("input").each(function(){if (this.name == "bs17fav_server") this.value=s;});';
				$js .= 	'} else {';
				$js .= 		'theForm.get(0).action = "http://17fav.com/index.php";';
				$js .= 	'}';
				if ($this->popwin) {
				$js .= 	'theForm.submit(function(){var e=window.open("http://17fav.com/","bs_popwin"); if (e==null) {alert("Your browser doesn\'t allow our window to popup. Please fix the settings"); return false;} else {return true;}});';
				}
				$js .= 	'theForm.submit();';
				$js .= '},';
				
				$js .= 'hover_in_btn: function(e){';
				$js .= 		'var btn_rel = this.rel;';
				$js .=		'BSJS.mouse_in_pos =[e.pageX,e.pageY];';
				$js .= 		'var btnp = jQuery(this).position();';
				$js .= 		'jQuery("#bs-17fav-pop").css({left:btnp.left,top:btnp.top,marginTop:jQuery(this).height()});';
				$js .=		'jQuery("#bs-17fav-pop").children("ul").children("li").each(function(){this.rel=btn_rel;});';
				$js .= 		'jQuery("#bs-17fav-pop").fadeIn("fast");';
				$js .= '},';
				
				$js .= 'hover_out_btn: function(e){';
				$js .= 		'if (e.pageY <= BSJS.mouse_in_pos[1]) jQuery("#bs-17fav-pop").fadeOut("fast");';
				$js .= '},';
				$js .= 'hover_in_box: function(){';
				$js .= '},';
				$js .= 'hover_out_box: function(){';
				$js .= 		'jQuery(this).fadeOut("fast");';
				$js .= '}';
				
				$js .= '};';
				
				$js .= 'jQuery(document).ready(function(){';
				$js .= 'jQuery(".btn-17fav").hover(BSJS.hover_in_btn,BSJS.hover_out_btn);';
				$js .= 'jQuery("#bs-17fav-pop").hover(BSJS.hover_in_box,BSJS.hover_out_box);';
				$js .= 'jQuery(".btn-17fav").click(function(){BSJS.bsthis(this.rel,"");return false;});';
				$js .= 'jQuery(".bs-17fav-item").click(function(){BSJS.bsthis(this.rel,this.id.substr(14));});';
				$js .= '});';
			}
			$js .= '</script>';
		}
		if ($where == 'all' || $where == 'footer') {
			$html = '<div id="bs-17fav-pop">';
			$html .= '<h1>收藏 &amp; 分享</h1>';
			$html .= '<ul class="bs-17fav-list"';
			if ($preview) $html .= ' id="bs-17fav-list-preview"';
			$html .= '>';
			if (!$preview) {
				foreach($servs as $s) {
					$temp = '<li class="bs-17fav-item" id="bs-17fav-item-%s">';
					$temp .= '<span class="bs-17fav-icon"></span>';
					$temp .= '<span class="bs-17fav-text">%s</span>';
					$temp .= '</li>';
					$html .= sprintf($temp,$s->slug,$s->title);
				}
			}
			$html .= '</ul>';
			$html .= '<h3>Powered by <a href="http://17fav.com">17fav.com</a></h3>';
			$html .= '</div>';
		}
		return $css.$js.$html;
	}
	
	function admin_page() {
		add_options_page('收藏&分享','收藏&分享', 9, 'bookmark-share', array(&$this,'admin'));
	}
	function admin() {
		$allserv = $this->_get_all_services();
		if (!empty($_POST['bs_submit'])) {
			update_option('bs_popwin',(bool)$_POST['bs_popwin']);
			update_option('bs_insert2',(bool)$_POST['bs_insert2']);
			update_option('bs_insert2home',(bool)$_POST['bs_insert2home']);
			update_option('bs_insert2feed',(bool)$_POST['bs_insert2feed']);
			$servs = explode(',',$_POST['bs_selservs']);
			update_option('bs_servs',$servs);
			$tmpc = explode(",",$_POST['bs_colors']);
			$color_schemes = array();
			$color_schemes[] = intval($_POST['bs_button_type']);
			foreach($tmpc as $c) $color_schemes[] = $c;
			update_option("bs_color_scheme",$color_schemes);
		}
		$currserv = $this->_get_services();
		$icourl = $allserv->url;
		$allserv = $allserv->detail;
		list($bs_btn,$bsc_bg,$bsc_border,$bsc_tbg,$bsc_tt,$bsc_it,$bsc_ihb) = $this->_get_color_schemes();
		//var_dump($allserv);
?>
<script type="text/javascript">
	var BSJS = {
		services: <?php echo json_encode($allserv);?>,
		pre_sel_servs: <?php echo json_encode($currserv);?>,
		sel_servs: [],
		init_allservices: function() {
			var i;
			for(i=0;i<BSJS.services.length;i++) {
				jQuery('#all_services').append('<option class="all-serv-item" value="'+i+'">'+BSJS.services[i].title+'</option>');
			}
			for(i=0;i<BSJS.pre_sel_servs.length;i++) {
				BSJS.add_serv(BSJS.pre_sel_servs[i]);
			}
		},
		update_input: function() {
			jQuery('#bs_selservs').get(0).value = BSJS.sel_servs.join(',');
		},
		add_serv: function(slug) {
			var opts = jQuery(".all-serv-item");
			var i,opt;
			for(i=0;i<opts.length;i++) {
				if (typeof slug != 'string') {
					//find out onsel
					if (opts.get(i).selected) {
						opt = opts.get(i);
						slug = BSJS.services[opt.value].slug;
						break;
					}
				} else {
					//find specific
					if (BSJS.services[opts.get(i).value].slug == slug) {
						opt = opts.get(i);
						break;
					}
				}
			}
			if (opt) {
				var tmp = '<option class="sel-serv-item" value="'+opt.value+'">'+jQuery(opt).html()+'</option>';
				jQuery('#sel_services').append(tmp);
				BSJS.sel_servs.push(slug);
				jQuery(opt).remove();
				BSJS.update_input();
			}
			BSJS.update_enable();
		},
		remove_serv: function(slug) {
			var opts = jQuery(".sel-serv-item");
			var i,opt;
			for(i=0;i<opts.length;i++) {
				if (typeof slug != 'string') {
					//find out onsel
					if (opts.get(i).selected) {
						opt = opts.get(i);
						slug = BSJS.services[opt.value].slug;
						break;
					}
				} else {
					//find specific
					if (BSJS.services[opts.get(i).value].slug == slug) {
						opt = opts.get(i);
						break;
					}
				}
			}
			if (opt) {
				if (BSJS.services[opt.value].is_sponsor) {
					alert('"'+jQuery(opt).html()+'"是 17fav 的赞助商，为了支持 17fav 发展，请不要移除它，谢谢合作！');
					return;
				}
				jQuery('#all_services').append('<option class="all-serv-item" value="'+opt.value+'>'+jQuery(opt).html()+'</option>');
				var first_slug = BSJS.sel_servs[0];
				if (first_slug == slug) {
					BSJS.sel_servs.shift();
				} else {
					var tmp;
					do {
						tmp = BSJS.sel_servs.shift();
						if (tmp != slug) {
							BSJS.sel_servs.push(tmp);
						}
					} while (BSJS.sel_servs[0] != first_slug)
				}
				jQuery(opt).remove();
				BSJS.update_input();
			}
			BSJS.update_enable();
		},
		up: function() {
			var opts = jQuery('.sel-serv-item');
			if (!opts.length) return;
			var i,opt,optprev;
			for(i=0;i<opts.length;i++) {
				if (opts.get(i).selected) {
					opt = opts.get(i);
					optprev = jQuery(opt).prev();
					break;
				}
			}
			if (!opt || !optprev.length) return false;
			var cslug = BSJS.services[opt.value].slug;
			//swap show
			var tmpv,tmph;
			tmpv = opt.value; tmph = jQuery(opt).html();
			opt.value = optprev.get(0).value;
			jQuery(opt).html(optprev.html());
			optprev.get(0).value = tmpv;
			optprev.html(tmph);
			opt.selected = false;
			optprev.get(0).selected = true;
			//swap data
			for(i=0;i<BSJS.sel_servs.length;i++) {
				if (BSJS.sel_servs[i] == cslug) {
					BSJS.sel_servs[i] = BSJS.sel_servs[i-1];
					BSJS.sel_servs[i-1] = cslug;
					break;
				}
			}
			BSJS.update_input();
			BSJS.update_enable();
		},
		down: function() {
			var opts = jQuery('.sel-serv-item');
			if (!opts.length) return;
			var i,opt,optnext;
			for(i=0;i<opts.length;i++) {
				if (opts.get(i).selected) {
					opt = opts.get(i);
					optnext = jQuery(opt).next();
					break;
				}
			}
			if (!opt || !optnext.length) return false;
			var cslug = BSJS.services[opt.value].slug;
			//swap show
			var tmpv,tmph;
			tmpv = opt.value; tmph = jQuery(opt).html();
			opt.value = optnext.get(0).value;
			jQuery(opt).html(optnext.html());
			optnext.get(0).value = tmpv;
			optnext.html(tmph);
			opt.selected = false;
			optnext.get(0).selected = true;
			//swap data
			for(i=0;i<BSJS.sel_servs.length;i++) {
				if (BSJS.sel_servs[i] == cslug) {
					BSJS.sel_servs[i] = BSJS.sel_servs[i+1];
					BSJS.sel_servs[i+1] = cslug;
					break;
				}
			}
			BSJS.update_input();
			BSJS.update_enable();
		},
		update_enable: function() {
			//add btn
			var i,items,btn;
			btn = jQuery('#btn_add_serv');
			btn.get(0).disabled = true;
			items = jQuery('.all-serv-item');
			if (items.length) {
				for(i=0;i<items.length;i++) {
					if (items.get(i).selected) {
						btn.get(0).disabled = false;
					}
				}
			}
			
			//remove up down btn
			btn = jQuery('#btn_remove_serv');
			var btn2,btn3;
			btn2 = jQuery('#btn_up_serv');
			btn3 = jQuery('#btn_down_serv');
			
			btn.get(0).disabled = true;
			btn2.get(0).disabled = true;
			btn3.get(0).disabled = true;
			items = jQuery('.sel-serv-item');
			if (items.length) {
				for(i=0;i<items.length;i++) {
					if (items.get(i).selected && !BSJS.services[items.get(i).value].is_sponsor) {
						btn.get(0).disabled = false;
					}
					if (items.get(i).selected && jQuery(items.get(i)).prev().length) {
						btn2.get(0).disabled = false;
					}
					if (items.get(i).selected && jQuery(items.get(i)).next().length) {
						btn3.get(0).disabled = false;
					}
				}
			}
			
			//build preview item
			if (BSJS.sel_servs.length == 0) return;
			var pre_list='';
			var j;
			for(i=0;i<BSJS.sel_servs.length;i++) {
				for(j=0;j<BSJS.services.length;j++) {
					if (BSJS.sel_servs[i] == BSJS.services[j].slug) {
						pre_list += '<li class="bs-17fav-item" id="bs-17fav-item-'+BSJS.services[j].slug+'"><span class="bs-17fav-icon"></span><span class="bs-17fav-text">'+BSJS.services[j].title+'</span></li>';
						break;
					}
				}
			}
			jQuery('#bs-17fav-list-preview').html(pre_list);
		},
		change_color: function() {
			var bs_colors = jQuery('#bs_colors').get(0).value.split(',');
			var bsc_arr = ['bsc_bg','bsc_border','bsc_tbg','bsc_tt','bsc_it','bsc_ihb'];
			var i,obj;
			for(i=0;i<bsc_arr.length;i++) {
				//validate color string
				obj = jQuery('#'+bsc_arr[i]);
				if (!obj.get(0).value.match(/^#[0-9a-fA-F]{3,6}$/)) {
					alert('颜色代码不合法！');
					obj.get(0).value = bs_colors[i];
					obj.get(0).focus();
				} else {
					bs_colors[i] = obj.get(0).value;
				}
			}
			jQuery('#bs_colors').get(0).value = bs_colors.join(',');
			
			//apply the css
			jQuery('#bs-17fav-pop').css({backgroundColor:bs_colors[0],borderColor:bs_colors[1]});
			jQuery('#bs-17fav-pop h1').css({color:bs_colors[3],backgroundColor:bs_colors[2]});
			jQuery('#bs-17fav-pop h3').css({color:bs_colors[3],backgroundColor:bs_colors[2]});
			jQuery('#bs-17fav-pop h3 a').css({color:bs_colors[3]});
			jQuery('li.bs-17fav-item-hover').css({borderColor:bs_colors[5]});
			jQuery('li.bs-17fav-item span.bs-17fav-text').css({color:bs_colors[4]});
			
		}
	};
	
	jQuery(document).ready(function(){
		BSJS.init_allservices();
		jQuery('#btn_add_serv').click(BSJS.add_serv);
		jQuery('#btn_remove_serv').click(BSJS.remove_serv);
		jQuery('#btn_up_serv').click(BSJS.up);
		jQuery('#btn_down_serv').click(BSJS.down);
		jQuery('#all_services').click(BSJS.update_enable);
		jQuery('#sel_services').click(BSJS.update_enable);
		jQuery('.txtbsc').change(BSJS.change_color);
		
	});
</script>
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
			<th scope="row">首页文章列表中插入按钮</th>
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
		<tr valign="top">
			<th scope="row">选择收藏服务</th>
			<td>
				<div style="display:none" id="selservices">
					<input type="hidden" id="bs_selservs" name="bs_selservs" value="" />
					<input type="hidden" id="bs_colors" name="bs_colors" value="<?php echo "$bsc_bg,$bsc_border,$bsc_tbg,$bsc_tt,$bsc_it,$bsc_ihb";?>" />
				</div>
				<table border="0" cellpadding="0" cellspacing="0"><tr>
					<td style="border-bottom: 0" width="200"><select size="15" id="all_services" style="width: 190px;height: 200px;">
					</select></td>
					<td style="border-bottom: 0" valign="middle" align="center" width="80">
						<p><input type="button" id="btn_add_serv" value="=>" /></p>
						<p><input type="button" id="btn_remove_serv" value="<=" /></p>
					</td>
					<td style="border-bottom: 0;padding-right: 0;" width="200"><select size="15" id="sel_services" style="width: 190px;height: 200px;">
					</select></td>
					<td style="border-bottom: 0;padding-left: 0;" valign="middle" align="center" width="40">
						<p><input type="button" id="btn_up_serv" value="上" /></p>
						<p><input type="button" id="btn_down_serv" value="下" /></p>
					</td>
				</tr></table>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">样式设定和预览</th>
			<td><table border="0" cellpadding="0" cellspacing="0"><tr valign="top">
				<td style="border:0">
					<h3>选择按钮样式</h3>
					<?php for($i=1;$i<=6;$i++) { ?>
					<p><input type="radio" name="bs_button_type" value="<?php echo $i;?>"<?php if($i==$bs_btn) echo " checked";?> /> <img src="<?php echo $this->_get_btn_url($i);?>" title="按钮<?php echo $i;?>" /></p>
					<?php } ?>
				</td>
				<td style="border:0">
					<h3>设定颜色</h3>
					<p><label>背景颜色：</label><input class="txtbsc" type="text" size="7" maxlength="7" id="bsc_bg" value="<?php echo $bsc_bg;?>" /></p>
					<p><label>边框颜色：</label><input class="txtbsc" type="text" size="7" maxlength="7" id="bsc_border" value="<?php echo $bsc_border;?>" /></p>
					<p><label>标题背景：</label><input class="txtbsc" type="text" size="7" maxlength="7" id="bsc_tbg" value="<?php echo $bsc_tbg;?>" /></p>
					<p><label>标题颜色：</label><input class="txtbsc" type="text" size="7" maxlength="7" id="bsc_tt" value="<?php echo $bsc_tt;?>" /></p>
					<p><label>条目颜色：</label><input class="txtbsc" type="text" size="7" maxlength="7" id="bsc_it" value="<?php echo $bsc_it;?>" /></p>
					<p><label>条目边框：</label><input class="txtbsc" type="text" size="7" maxlength="7" id="bsc_ihb" value="<?php echo $bsc_ihb;?>" /></p>
				</td>
				<td style="border:0">
					<h3>预览</h3>
					<?php echo $this->bs_code(true,'footer');?>
				</td>
			</tr></table></td>
		</tr>
	</table>
	<p class="submit"><input type="submit" name="bs_submit" class="button" value="更新设置" /></p>
	</form>
</div>
<?php
	}
	function _get_color_schemes() {
		$rtn_array = get_option('bs_color_scheme');
		if (false===$rtn_array) {
			$rtn_array = array(1,'#fff','#69f','#69f','#fff','#069','#69f');
		}
		return $rtn_array;
	}
	function _get_services() {
		$services = get_option('bs_servs');
		if (false === $services) {
			$services = array(
				'delicious',
				'baidu',
				'qq',
				'google',
				'yahoo',
				'mister-wong-cn',
				'fanfou',
				'facebook'
				);
		}
		//add sponsors
		$sponslug = get_option('bs_sp_services');
		if (is_array($sponslug) && count($sponslug)) {
			foreach($sponslug as $spon) {
				if (!in_array($spon,$services)) array_push($services,$spon);
			}
		}
		return $services;
	}
	function _get_all_services() {
		$all_services_time = get_option('bs_all_services_time');
		if (time()-$all_services_time > 3600*24) {
			$s = $this->_reqapi('icon');
			$sponslug = array();
			if (count($s->detail)) foreach ($s->detail as $i) {
				if ($i->is_sponsor) array_push($sponslug,$i->slug);
			}
			update_option('bs_sp_services',$sponslug);
			update_option('bs_all_services',$s);
			update_option('bs_all_services_time',time());
		} else {
			$s = get_option('bs_all_services');
		}
		return $s;
	}
	function _get_btn_url($which = 1) {
		if ( !defined('WP_CONTENT_URL') )
			define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
		if ( !defined('WP_PLUGIN_URL') )
			define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
		$url = WP_PLUGIN_URL . '/' .dirname(plugin_basename(__FILE__))."/bookmark-%s.gif";
		switch($which) {
			case 1: return sprintf($url,'blue');
			case 2: return sprintf($url,'green');
			case 3: return sprintf($url,'orange');
			case 4: return sprintf($url,'purple');
			case 5: return sprintf($url,'red');
			case 6: return sprintf($url,'yellow');
		}
	}
	
	function _reqapi($cmd,$params='') {
		require_once(ABSPATH.WPINC.'/class-snoopy.php');
		$snoopy = new Snoopy;
		$snoopy->agent = "17fav Bookmark Share Wordpress Plugin v"+$this->version+"($this->uid)";
		$snoopy->submit($this->bs_server_url . "api/?cmd=$cmd",$params);
		if ($snoopy->status == 200) {
			$r=json_decode($snoopy->results);
			return $r;
		} else return false;
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
			wp_enqueue_script('jquery-dimensions',get_bloginfo('siteurl'). "/" .PLUGINDIR . "/" . dirname(plugin_basename (__FILE__)).'/jquery.dimensions.js',array('jquery'),'1.2.6');
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