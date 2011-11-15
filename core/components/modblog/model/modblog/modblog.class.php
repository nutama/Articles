<?php
require_once MODX_CORE_PATH.'model/modx/modprocessor.class.php';
require_once MODX_CORE_PATH.'model/modx/processors/resource/create.class.php';
require_once MODX_CORE_PATH.'model/modx/processors/resource/update.class.php';
/**
 * @package modBlog
 */
class modBlog extends modResource {
    function __construct(& $xpdo) {
        parent :: __construct($xpdo);
        $this->set('class_key','modBlog');
        $this->set('hide_children_in_tree',true);
        $this->showInContextMenu = true;
    }

    public static function getControllerPath(xPDO &$modx) {
        return $modx->getOption('modblog.core_path',null,$modx->getOption('core_path').'components/modblog/').'controllers/blog/';
    }

    public function getContextMenuText() {
        $this->xpdo->lexicon->load('modblog:default');
        return array(
            'text_create' => $this->xpdo->lexicon('modblog.blog'),
            'text_create_here' => $this->xpdo->lexicon('modblog.blog_create_here'),
        );
    }

    public function getResourceTypeName() {
        $this->xpdo->lexicon->load('modblog:default');
        return $this->xpdo->lexicon('modblog.blog');
    }

    public function process() {
        $this->xpdo->lexicon->load('modblog:frontend');
        $this->getPosts();
        $this->getArchives();
        return parent::process();
    }
    public function getContent(array $options = array()) {
        $content = parent::getContent($options);

        return $content;
    }

    public function getPosts() {
        $settings = $this->getBlogSettings();

        $output = '[[!getPage?
          &elementClass=`modSnippet`
          &element=`getArchives`
          &cache=`0`
          &pageVarKey=`page`
          &parents=`[[*id]]`
          &where=`{"class_key":"modBlogPost"}`
          &limit=`'.$this->xpdo->getOption('postsPerPage',$settings,10).'`
          &showHidden=`1`
          &includeContent=`1`
          &includeTVs=`1`
          &tagKey=`modblogtags`
          &tagSearchType=`contains`
          &tpl=`'.$this->xpdo->getOption('tplPost',$settings,'modBlogPostRowTpl').'`
        ]]';
        $this->xpdo->setPlaceholder('posts',$output);

        $this->xpdo->setPlaceholder('paging','[[!+page.nav:notempty=`
<div class="paging">
<ul class="pageList">
  [[!+page.nav]]
</ul>
</div>
`]]');
    }

    public function getArchives() {
        $settings = $this->getBlogSettings();
        $output = '[[!Archivist?
            &tpl=`'.$this->xpdo->getOption('tplArchiveMonth',$settings,'modBlogArchiveMonthTpl').'`
            &target=`'.$this->get('id').'`
            &parents=`'.$this->get('id').'`
            &depth=`4`
            &limit=`'.$this->xpdo->getOption('archiveListingsLimit',$settings,10).'`
            &useMonth=`'.$this->xpdo->getOption('archiveByMonth',$settings,1).'`
            &useFurls=`'.$this->xpdo->getOption('archiveWithFurls',$settings,1).'`
            &cls=`'.$this->xpdo->getOption('archiveCls',$settings,'').'`
            &altCls=`'.$this->xpdo->getOption('archiveAltCls',$settings,'').'`
            &setLocale=`1`
        ]]';
        $this->xpdo->setPlaceholder('archives',$output);
    }

    public function getBlogSettings() {
        $settings = $this->get('blog_settings');
        $this->xpdo->setDebug(false);
        if (!empty($settings)) {
            $settings = is_array($settings) ? $settings : $this->xpdo->fromJSON($settings);
        }
        return !empty($settings) ? $settings : array();
    }
}

/**
 * Overrides the modResourceCreateProcessor to provide custom processor functionality for the modBlog type
 *
 * @package modblog
 */
class modBlogCreateProcessor extends modResourceCreateProcessor {
    public function beforeSave() {
        $properties = $this->getProperties();
        $settings = $this->object->get('blog_settings');
        foreach ($properties as $k => $v) {
            if (substr($k,0,8) == 'setting_') {
                $key = substr($k,8);
                if ($v === 'false') $v = 0;
                if ($v === 'true') $v = 1;
                $settings[$key] = $v;
            }
        }
        $this->object->set('blog_settings',$settings);

        $this->object->set('cacheable',true);
        $this->object->set('isfolder',true);
        return parent::beforeSave();
    }

    public function afterSave() {
        $this->addBlogId();
        $this->setProperty('clearCache',true);
        return parent::afterSave();
    }

    public function addBlogId() {
        $saved = true;
        /** @var modSystemSetting $setting */
        $setting = $this->modx->getObject('modSystemSetting',array('key' => 'modblog.blog_ids'));
        if (!$setting) {
            $setting = $this->modx->newObject('modSystemSetting');
            $setting->set('key','modblog.blog_ids');
            $setting->set('namespace','modblog');
            $setting->set('area','furls');
            $setting->set('xtype','textfield');
        }
        $value = $setting->get('value');
        $archiveKey = $this->object->get('id').':arc_';
        $value = is_array($value) ? $value : explode(',',$value);
        if (!in_array($archiveKey,$value)) {
            $value[] = $archiveKey;
            $value = array_unique($value);
            $setting->set('value',implode(',',$value));
            $saved = $setting->save();
        }
        return $saved;
    }
}

/**
 * Overrides the modResourceUpdateProcessor to provide custom processor functionality for the modBlog type
 *
 * @package modblog
 */
class modBlogUpdateProcessor extends modResourceUpdateProcessor {
    public function beforeSave() {
        $properties = $this->getProperties();
        $settings = $this->object->get('blog_settings');
        foreach ($properties as $k => $v) {
            if (substr($k,0,8) == 'setting_') {
                $key = substr($k,8);
                if ($v === 'false') $v = 0;
                if ($v === 'true') $v = 1;
                $settings[$key] = $v;
            }
        }
        $this->object->set('blog_settings',$settings);
        return parent::beforeSave();
    }

    public function afterSave() {
        $this->addArchivistArchive();
        $this->setProperty('clearCache',true);
        $this->object->set('isfolder',true);
        return parent::afterSave();
    }

    public function addArchivistArchive() {
        $saved = true;
        /** @var modSystemSetting $setting */
        $setting = $this->modx->getObject('modSystemSetting',array('key' => 'modblog.blog_ids'));
        if (!$setting) {
            $setting = $this->modx->newObject('modSystemSetting');
            $setting->set('key','modblog.blog_ids');
            $setting->set('namespace','modblog');
            $setting->set('area','furls');
            $setting->set('xtype','textfield');
        }
        $value = $setting->get('value');
        $archiveKey = $this->object->get('id').':arc_';
        $value = is_array($value) ? $value : explode(',',$value);
        if (!in_array($archiveKey,$value)) {
            $value[] = $archiveKey;
            $value = array_unique($value);
            $setting->set('value',implode(',',$value));
            $saved = $setting->save();
        }
        return $saved;
    }
}