<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 浏览数、喜欢数统计插件
 * 
 * @package TeStat
 * @author 绛木子
 * @version 1.1
 * @link http://lixianhua.com
 */
class TeStat_Plugin implements Typecho_Plugin_Interface
{
	public static $info = array();
	public static $mem = array();
    public static $split = '-';
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        // contents 表中若无 viewsNum 字段则添加
        if (!array_key_exists('viewsNum', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD `viewsNum` INT(10) DEFAULT 0;');
		// contents 表中若无 likesNum 字段则添加
        if (!array_key_exists('likesNum', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD `likesNum` INT(10) DEFAULT 0;');
        //增加浏览数
        Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('TeStat_Plugin', 'viewCounter');
        //把新增的字段添加到查询中
        Typecho_Plugin::factory('Widget_Archive')->select = array('TeStat_Plugin', 'selectHandle');
		//添加动作
		Helper::addAction('likes', 'TeStat_Action');
		
		Typecho_Plugin::factory('Widget_Archive')->header = array('TeStat_Plugin','insertCss');
		Typecho_Plugin::factory('Widget_Archive')->footer = array('TeStat_Plugin','insertJs');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
	{
		Helper::removeAction('likes');
		
        $delFields = Typecho_Widget::widget('Widget_Options')->plugin('TeStat')->delFields;
        if($delFields){
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $db->query('ALTER TABLE `'. $prefix .'contents` DROP `viewsNum`;');
			$db->query('ALTER TABLE `'. $prefix .'contents` DROP `likesNum`;');
        }
	}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
	{	
		$delFields = new Typecho_Widget_Helper_Form_Element_Radio('delFields', 
            array(0=>_t('保留数据'),1=>_t('删除数据'),), '0', _t('卸载设置'),_t('卸载插件后数据是否保留'));
        $form->addInput($delFields);

        $allow_stat = new Typecho_Widget_Helper_Form_Element_Radio('filter_spider',
            array(0=>_t('关闭'), 1=>_t('开启'),), '1', _t('是否过滤爬虫'), _t('开启后爬虫不记录到阅读次数'));
        $form->addInput($allow_stat);

        $callback_select = new Typecho_Widget_Helper_Form_Element_Text('callback_select', NULL, '.like-num-show', _t('点赞自增选择器'), _t('该项用于点赞成功后数值 +1'));
        $form->addInput($callback_select);

	}
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 增加浏览量
     * @params Widget_Archive   $archive
     * @return void
     */
    public static function viewCounter($archive){
        if($archive->is('single') && !$archive->parent){
            $cid = $archive->cid;
            $views = Typecho_Cookie::get('__post_views');
            if(empty($views)){
                $views = array();
            }else{
                $views = explode(self::$split, $views);
            }

            $options = Typecho_Widget::widget('Widget_Options')->plugin('TeStat');

            if(!in_array($cid,$views) && (!$options->filter_spider || !self::isBot())){  // 判断过滤爬虫的访问
                $db = Typecho_Db::get();
                $db->query($db->update('table.contents')->rows(array('viewsNum' => (int)$archive->viewsNum+1))->where('cid = ?', $cid));
                if (count($views) > 10) {
                    $views = array();
                }
                array_push($views, $cid);
                $views = implode(self::$split, $views);
                Typecho_Cookie::set('__post_views', $views, time() + 7200); //记录查看cookie
            }
        }
    }

    public static function isBot() {
        $bots = array(
            'TencentTraveler',
            'Baiduspider',
            'BaiduGame',
            'Googlebot',
            'msnbot',
            'Sosospider+',
            'Sogou web spider',
            'ia_archiver',
            'Yahoo! Slurp',
            'YoudaoBot',
            'Yahoo Slurp',
            'MSNBot',
            'Java (Often spam bot)',
            'BaiDuSpider',
            'Voila',
            'Yandex bot',
            'BSpider',
            'twiceler',
            'Sogou Spider',
            'Speedy Spider',
            'Google AdSense',
            'Heritrix',
            'Python-urllib',
            'Alexa (IA Archiver)',
            'Ask',
            'Exabot',
            'Custo',
            'OutfoxBot/YodaoBot',
            'yacy',
            'SurveyBot',
            'legs',
            'lwp-trivial',
            'Nutch',
            'StackRambler',
            'The web archive (IA Archiver)',
            'Perl tool',
            'MJ12bot',
            'Netcraft',
            'MSIECrawler',
            'WGet tools',
            'larbin',
            'Fish search',
            'crawler',
            'bingbot',
            'YisouSpider',
            'AhrefsBot',
            'ToutiaoSpider',
            '360Spider',
        );

        $request = Typecho_Request::getInstance();
        $ua = $request->getAgent();

        if (empty($ua)) {
            return true;
        }

        $ua = strtolower($ua);
        foreach ($bots as $val) {
            $str = strtolower($val);
            if (strpos($ua, $str) !== false) {
                return true;
            }
        }

        return false;
    }


	public static function insertCss($header,$widget){
		$action = Typecho_Common::url('/action/',Helper::options()->index);
		echo '<style type="text/css">.testat-dialog{position:fixed;top:100px;left:50%;padding:10px;background-color:#fff;display:none;-webkit-border-radius: 3px;-moz-border-radius: 3px;border-radius: 3px;z-index:1024;}
.testat-dialog.error{background-color:#f40;color:#fff;}
.testat-dialog.success{background-color:#24AA42;color:#fff;}</style><script type="text/javascript">window.action="'.$action.'";</script>';
	}
	public static function insertJs($widget){

        $options = Typecho_Widget::widget('Widget_Options')->plugin('TeStat');

        if(!$options->allow_stat)
        $callback_select = $options->callback_select;
		$script = <<<EOT
<script type="text/javascript">
$(function(){
	$('.btn-like').click(function(e){
		e.stopPropagation();
		e.preventDefault();
		var that = $(this),
		    num = $(this).data('num'),
		    cid = $(this).data('cid'),
		    numEl = that.find('.post-likes-num');
		
		if(cid === undefined) return false;
		$.ajax({
		    url: window.action+'likes?cid='+cid,
		    type: 'get',
		    success: function(rs){
                if(rs.status === 1){
                    if(numEl.length>0){
                        numEl.text(num+1);
                    }
                    testatAlert(rs.msg===undefined ? '已成功为该文章点赞!' : rs.msg);
                    $('{$callback_select}').text(function() {
                      return parseInt($(this).text()) + 1;
                    });
                }else{
                    testatAlert(rs.msg===undefined ? '操作出错!' : rs.msg,'err');
                }
            }
		});
	});
});
function testatAlert(msg,type,time){
	type = type === undefined ? 'success' : 'error';
	time = time === undefined ? (type === 'success' ? 1500 : 3000) : time;
	var html = '<div class="testat-dialog '+type+'">'+msg+'</div>';
	$(html).appendTo($('body')).fadeIn(300,function(){
		setTimeout(function(){
			$('body > .testat-dialog').remove();
		},time);
	});
}
</script>
EOT;
		echo $script;
	}
    //cleanAttribute('fields')清除查询字段，select * 
    public static function selectHandle($archive){
        $user = Typecho_Widget::widget('Widget_User');
		if ('post' == $archive->parameter->type || 'page' == $archive->parameter->type) {
            if ($user->hasLogin()) {
                $select = $archive->select()->where('table.contents.status = ? OR table.contents.status = ? OR
                        (table.contents.status = ? AND table.contents.authorId = ?)',
                        'publish', 'hidden', 'private', $user->uid);
            } else {
                $select = $archive->select()->where('table.contents.status = ? OR table.contents.status = ?',
                        'publish', 'hidden');
            }
        } else {
            if ($user->hasLogin()) {
                $select = $archive->select()->where('table.contents.status = ? OR
                        (table.contents.status = ? AND table.contents.authorId = ?)', 'publish', 'private', $user->uid);
            } else {
                $select = $archive->select()->where('table.contents.status = ?', 'publish');
            }
        }
        $select->where('table.contents.created < ?', Typecho_Date::gmtTime());
        $select->cleanAttribute('fields');
        return $select;
	}
}