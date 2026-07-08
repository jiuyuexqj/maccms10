<?php
namespace app\index\controller;
use think\Controller;

class Search extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // 主题未提供 search/index 模板时回退到站点搜索（vod/search），避免 TemplateNotFoundException 被吞成空白 200
        if (mac_tpl_exists('search/index')) {
            return $this->label_fetch('search/index');
        }
        return $this->redirect(url('vod/search'));
    }

}
