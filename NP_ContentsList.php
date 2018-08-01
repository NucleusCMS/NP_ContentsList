<?php
// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table')){
	function sql_table($name) {
		return 'nucleus_' . $name;
	}
}

class NP_ContentsList extends NucleusPlugin {

	function getName() { return 'Contents List'; }
	function getAuthor()  { return 'unknown + Taka + Nucleus(JP) team'; }
	function getURL() { return 'http://japan.nucleuscms.org/wiki/plugins:contentslist'; }
	function getVersion() { return '2.2'; }
	function getDescription() { 
		return 'A blog list including the category list is displayed. &lt;%ContentsList%&gt;.';
	}
	function supportsFeature($what) {
		switch ($what) {
			case 'SqlTablePrefix':
			case 'SqlApi':
				return 1;
			default:
				return 0;
		}
	}
	function getMinNucleusVersion()    { return '350'; }
	
	function getTableList() { 
		return array(
			sql_table('plug_contentslist'),
			sql_table('plug_contentslist_rank'),
		);
	}
	function hasAdminArea() { return 1; }
	
	function getEventList() {
		return array('PostAddBlog','PostAddCategory','PostPluginOptionsUpdate','QuickMenu');
	}
	
	function event_QuickMenu(&$data) {
		// only show when option enabled
		if ($this->getOption('quickmenu') != 'yes') return;
		global $member;
		// only show to admins
		if (!($member->isLoggedIn() && $member->isAdmin())) return;
		array_push(
			$data['options'],
			array(
				'title' => 'Contents List',
				'url' => $this->getAdminURL(),
				'tooltip' => 'Edit NP_ContentsList'
			)
		);
	}
	
	function event_PostAddBlog($data) {
		$b =& $data['blog'];
		$blogid = $b->getID();
		$res = sql_query('INSERT INTO '.sql_table('plug_contentslist_rank').' SET '
				. 'rid='.intval($blogid).', rank='.$this->vars['d_blogrank'].', blog=1');
		if(!$res) {
			ACTIONLOG::add(WARNING, 'NP_ContentsList : '.sql_error());
		}
	}
	
	function event_PostAddCategory($data) {
		$res = sql_query('INSERT INTO '.sql_table('plug_contentslist_rank').' SET '
				. 'rid='.intval($data['catid']).', rank='.$this->vars['d_catrank'].', blog=0');
		if(!$res) {
			ACTIONLOG::add(WARNING, 'NP_ContentsList : '.sql_error());
		}
	}
	
	function event_PostPluginOptionsUpdate($data) {
		global $manager;
		
		if (!isset($data['plugid']) || $data['plugid'] != $this->getID()) return;
		
		if ($this->getOption('subcatfield') != 'yes') return;
		
		if ($manager->pluginInstalled('NP_MultipleCategories')) {
			$check_column = sql_query('SELECT * FROM '. sql_table('plug_contentslist'). ' WHERE 1=0');
			for ($i=0; $i<sql_num_fields($check_column); $i++) {
				if ($meta = sql_fetch_field($check_column)) {
					$names[] = $meta->name;
				}
			}
			if (!in_array("subheader",$names)) {
				sql_query ('ALTER TABLE '.sql_table('plug_contentslist').' ADD (subheader text not null, sublist text not null, subfooter text not null, subflag text not null)');
			}
		} else {
			$this->setOption('subcatfield',"no");
		}
	}
	
	function init() {
		$this->vars['d_blogrank'] = 10;
		$this->vars['blogmaxrank'] = 20;
		$this->vars['d_catrank'] = 10;
		$this->vars['catmaxrank'] = 19;
		global $admin;
		$thisisPlgindir = strpos(getenv('REQUEST_URI'), '/plugins/');
		if(empty($admin) && empty($thisisPlgindir)) return;
		$lang = preg_replace('@\\|/@', '', getLanguageName());
		$langDir  = $this->getDirectory() . 'lang/';
		if (! @include_once($langDir . $lang . '.php')) {include_once($langDir . 'english.php');}
	}
	
	function get($name) {
		return $this->vars[$name];
	}
	
	function install(){
		sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plug_contentslist'). ' (
			tp_id int(11) not null auto_increment,
			tp_def int(2) not null,
			name varchar(128) not null,
			showmode int(2) not null,
			hideblog varchar(100) not null,
			blogorder varchar(13) not null,
			catorder varchar(13) not null,
			header text not null, 
			list text not null,
			footer text not null, 
			flag text not null,
			catheader text not null, 
			catlist text not null,
			catfooter text not null, 
			catflag text not null, 
			UNIQUE (name),
			PRIMARY KEY (tp_id))');

		sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plug_contentslist_rank'). ' (
			blog int (2) not null,
			rid int (11) not null,
			rank int (11) not null)');
		
		$check_column = sql_query('SELECT * FROM '. sql_table('plug_contentslist'). ' WHERE 1=0');
		for ($i=0; $i<sql_num_fields($check_column); $i++) {
			if ($meta = sql_fetch_field($check_column)) {
				$names[] = $meta->name;
			}
		}
		if (!in_array("showmode",$names)) {
			sql_query ('ALTER TABLE '.sql_table('plug_contentslist').' ADD showmode int(2) not null AFTER name');
			sql_query ('ALTER TABLE '.sql_table('plug_contentslist').' ADD UNIQUE (name)');
		}
		$check_rows = sql_query('SELECT rid FROM '. sql_table('plug_contentslist_rank'));
		if (sql_num_rows($check_rows) < 1) {

			$verup_file = $this->getDirectory().'/_verup.php';
			include_once ($verup_file);

			$setting = new UPDATE;
			$hide = $setting->hide;
			$tp = $setting->tp;
			$templates = $setting->multipleTemplateSetting();
			array_unshift($templates,'default');

			$prevname = '';
			foreach ($templates as $name) {
				if ($prevname == $name) continue;
				if ($name != 'default') {
					$setting->changeTemplate($name,$tp);
				}
				$tp['name'] = $name;
				$tp['showmode'] = 0;
				$tp['hideblog'] = implode('/',$hide);
				$tp['blogorder'] = $setting->blogOrder;
				$tp['catorder'] = $setting->catOrder;
				$tp = array_map("addslashes",$tp);
				if ($name == 'default') {
					$tp['tp_def'] = 1;
				} else {
					$tp['tp_def'] = 0;
				}
				$query = 'INSERT INTO '. sql_table('plug_contentslist'). ' SET ';
				foreach ($tp as $k => $v)  {
					$query .= "$k='$v',";
				}
				$query = substr($query,0,-1);
				sql_query($query);
				$prevname = $name;
			}

			$res = sql_query('SELECT bnumber FROM '.sql_table('blog'));
			while ($row = sql_fetch_row($res)) {
				$query = 'INSERT INTO '.sql_table('plug_contentslist_rank'). ' SET ';
				$query .= 'blog=1, rid='. $row[0] .', rank='. $this->vars['d_blogrank'];
				sql_query($query);
			}

			$res = sql_query('SELECT catid FROM '.sql_table('category'));
			while ($row = sql_fetch_row($res)) {
				$query = 'INSERT INTO '.sql_table('plug_contentslist_rank'). ' SET ';
				$query .= 'blog=0, rid='. $row[0] .', rank='. $this->vars['d_catrank'];
				sql_query($query);
			}
		
		}
		
		$oldnucleus = 0;
		if (getNucleusVersion() < 220) {
			$oldnucleus = 1;
		}
		$this->createOption('addindex', _CONTENTSLIST_PLUGINOPTION01, 'yesno', 'yes');
		if ($oldnucleus) {
			$this->createOption('cattype', _CONTENTSLIST_PLUGINOPTION02.' ["category" or "catid"]', 'text', 'category');
		} else {
			$this->createOption('cattype', _CONTENTSLIST_PLUGINOPTION02, 'select', 'category','category|category|catid|catid');
		}
		$this->createOption('magical', _CONTENTSLIST_PLUGINOPTION03, 'yesno', 'no');
		if ($oldnucleus) {
			$this->createOption('urimode', _CONTENTSLIST_PLUGINOPTION04.' ["nucleus"(catid -> blogid) or "contentslist"(blogid -> catid)]', 'text', 'nucleus');
		} else {
			$this->createOption('urimode', _CONTENTSLIST_PLUGINOPTION04, 'select', 'nucleus','catid -> blogid|nucleus|blogid -> catid|contentslist');
		}
		$this->createOption('add_defblogid', _CONTENTSLIST_PLUGINOPTION05, 'yesno', 'yes');
		$this->createOption('add_defblogid_cat', _CONTENTSLIST_PLUGINOPTION06, 'yesno', 'yes');
		$this->createOption('add_blogid', _CONTENTSLIST_PLUGINOPTION07, 'yesno', 'yes');
		$this->createOption('add_blogid_cat', _CONTENTSLIST_PLUGINOPTION08, 'yesno', 'yes');
		$this->createOption('subcatfield', _CONTENTSLIST_PLUGINOPTION09, 'yesno', 'no');
		$this->createOption('quickmenu', _CONTENTSLIST_PLUGINOPTION10, 'yesno', 'yes');
		$this->createOption('del_uninstall', _CONTENTSLIST_PLUGINOPTION11, 'yesno', 'no');
	}

	function unInstall() {
		if ($this->getOption('del_uninstall') == "yes") {
			sql_query("DROP table ".sql_table('plug_contentslist'));
			sql_query("DROP table ".sql_table('plug_contentslist_rank'));
		}
	}
	

// ------------------------

	function doSkinVar($skinType){
		global $CONF, $manager, $blog, $catid, $archive;

		if ($blog) {
			$b =& $blog; 
		} else {
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		}
		
		$addindex = ($this->getOption('addindex') == 'yes');
		$cattype = trim($this->getOption('cattype'));
		$magical = ($this->getOption('magical') == 'yes');
		$urimode = trim($this->getOption('urimode'));
		$nodefbid = ($this->getOption('add_defblogid') == 'no');
		$nodefcatbid = ($this->getOption('add_defblogid_cat') == 'no');
		$nobid = ($this->getOption('add_blogid') == 'no');
		$nocatbid = ($this->getOption('add_blogid_cat') == 'no');

		$params = func_get_args();
		array_shift($params);
		if($params[0] == '') array_shift($params);

		$aWhere = array();
		$sNewtemplate = '';
		$catAll = 0;
		$subcurrent = 0;
		$subnoOpen = 0;
		if (count($params)>0) {
			$cnt = 0;
			foreach ($params as $val) { //easy check
				if (preg_match("/^[a-z]+\s*[!<>=]{1,3}\s*[^!<>=\s]+$/",$val)) {
					$aWhere[] = 'b.'.$val;
					$cnt ++;
				} elseif ($val == "@s") {
					$subcurrent = 1;
					$cnt ++;
				} elseif ($val == "-*s") {
					$subnoOpen = 1;
					$cnt ++;
				} else {
					switch(substr($val,0,1)){
						case '>':
							$sNewtemplate = substr($val,1);
							$cnt ++;
							break;
						case '@':
							$aOpen[] = $b->getShortName();
							break;
						case '-':
							if(substr($val,1,2) != '*'){
								$aClose[] = substr($val,1);
							}
							break;
						default:
							$aOpen[] = $val;
					}
				}
			}
			if ($cnt == count($params)) $catAll = 1;
		} else {
			$catAll = 1;
		}

		global $DIR_PLUGINS;
		$multicat = 0;
		$subcat = 0;
		$fileName = $DIR_PLUGINS . 'NP_MultipleCategories.php';
		if ($manager->pluginInstalled('NP_MultipleCategories') && file_exists($fileName)) {
			$multicat = 1;
			$mplugin =& $manager->getPlugin('NP_MultipleCategories');
			$subcat = (method_exists($mplugin,"getRequestName") && $this->getOption('subcatfield') == "yes");
			if ($subcat) {
				global $subcatid;
				$subrequest = $mplugin->getRequestName();
			}
		}

		$query = 'SELECT * FROM '.sql_table('plug_contentslist');
		if ($sNewtemplate) {
			$query .= ' WHERE name="'.addslashes($sNewtemplate).'"';
		} else {
			$query .= ' WHERE tp_def=1';
		}
		$res = sql_query($query);
		if(!($tp = sql_fetch_assoc($res))) return;
		$tp = array_map(array('NP_ContentsList','parse_skinfile_var'),$tp);
		$h = explode("/",$tp['hideblog']);
		$h = array_map("intval",$h);
		$smode = intval($tp['showmode']);
		if ($h[0] && !$smode) {
			$aWhere[] = 'b.bnumber not in ('.implode(",",$h).')';
		} elseif ($h[0] && $smode) {
			$aWhere[] = 'b.bnumber in ('.implode(",",$h).')';
		} elseif ($smode) {
			return;
		}

		$nowbid = $b->getID();
		$defurl = quickQuery('SELECT burl as result FROM '.sql_table('blog').' WHERE bnumber='.$CONF['DefaultBlog']);
		if (!$defurl) {
			$defurl = $CONF['Self'];
		}

		$query = 'SELECT b.bnumber as blogid, b.bname as blogname, b.burl as blogurl, b.bshortname, b.bdesc as blogdesc';
		$query .= ' FROM '.sql_table('blog').' b,'
		       .  sql_table('plug_contentslist_rank').' p';
		$query .= ' WHERE p.rid=b.bnumber and p.blog=1';
		if (count($aWhere) >= 1) $query .= ' and ('. implode(' or ',$aWhere).')';
		$query .= ' ORDER BY p.rank, ' . $tp['blogorder'];


		echo $tp['header'];

		$res = sql_query($query);
		while ($data = sql_fetch_assoc($res)) {
			if (!$data['blogurl']) {
				$data['blogurl'] = $defurl;
			}
			if ($CONF['URLMode'] == 'pathinfo' && $manager->pluginInstalled('NP_CustomURL')) {
				$data['blogurl'] = createBlogidLink($data['blogid']);
			}
			$data['self'] = $data['blogurl'];
			$noFlg = 0;
			$catnoFlg = 0;
			
			if (($data['blogid'] != $CONF['DefaultBlog'] && (!$nobid || $data['blogurl'] == $defurl)) || ($data['blogid'] == $CONF['DefaultBlog'] && !$nodefbid && $data['blogurl'] != $CONF['IndexURL'])) {
				// These surely have a parameter.
				if ($CONF['URLMode'] == 'pathinfo'){
					if (substr($data['blogurl'], -1) != '/') $data['blogurl'] .= '/';
				} else {
					if (substr($data['blogurl'], -4) != '.php') {
						if (substr($data['blogurl'], -1) != '/') $data['blogurl'] .= '/';
						if ($addindex) $data['blogurl'] .= 'index.php';
					}
					$data['blogurl'] .= '?';
				}
			} else {
				$noFlg = 1;
				if (($CONF['URLMode'] == 'pathinfo' || substr($data['blogurl'], -4) != '.php') && substr($data['blogurl'], -1) != '/'){
					$data['blogurl'] .= '/';
				}
				if ($nodefcatbid && $data['blogid'] == $CONF['DefaultBlog']) {
					$catnoFlg = 1;
				} elseif ($nocatbid && $data['blogid'] != $CONF['DefaultBlog'] && $data['blogurl'] != $defurl) {
					$catnoFlg = 1;
				}
			}
			
			if ($CONF['URLMode'] == 'pathinfo'){
				$conn = $v_conn = ($magical) ? '_' : '/';
			} else {
				$conn = '=';
				$v_conn = '&amp;';
			}
			if ($CONF['URLMode'] == 'pathinfo' && !$magical){
				$qstr = 'blog' . $conn . $data['blogid'];
			} else {
				$qstr = 'blogid' . $conn . $data['blogid'];
			}
			
			if ( $data['blogid'] == $nowbid ) {
				$data['flag'] = $tp['flag'];
			}

			switch (true) {
				case $catAll:
				case isset($aOpen) && in_array($data['bshortname'],$aOpen):
				case isset($aClose) && !in_array($data['bshortname'],$aClose):

				// Categories ---------------------------------------------------
					$temp = $tp['catheader'];
			
					$query = 'SELECT c.catid as catid, c.cname as catname, c.cdesc as catdesc'
//					        .' count(i.ititle) as ammount '
					        .' FROM '.sql_table('category').' as c, '
					        .  sql_table('plug_contentslist_rank').' as p '
//					        . sql_table('item').' i, '
//					        .' WHERE i.iblog='.$data['blogid']
					        .' WHERE c.cblog='.$data['blogid']
//					        .' and c.catid=i.icat'
					        .' and p.rid=c.catid and p.blog=0'
					        .' and p.rank<='.$this->get('catmaxrank')
//					        .' and i.itime<=' . mysqldate($b->getCorrectTime())
//					        .' and i.idraft=0'
//					        .' GROUP BY c.cname '
					        .' ORDER BY p.rank, '. $tp['catorder'];
				
					$cres = sql_query($query);
				
					$paramblogurl = $data['blogurl'];
					
					if ($noFlg) {
						if ($CONF['URLMode'] != 'pathinfo') {
							if (substr($data['blogurl'], -1) == '/' && $addindex) {
								$paramblogurl .= 'index.php';
							}
							$paramblogurl .= '?';
						}
					} else {
						$data['blogurl'] .= $qstr;
						if ($CONF['URLMode'] == 'pathinfo' && $magical) {
							$data['blogurl'] .= '.html';
						}
					}

					while ($catdata = sql_fetch_assoc($cres)) {
						
						$temp_catlist = "";
						$myblogurl = $paramblogurl;
						
						$cq = 'SELECT count(*) as result FROM '.sql_table('item').' as i';
						if ($multicat) {
							$cq .= ' LEFT JOIN '.sql_table('plug_multiple_categories').' as p ON  i.inumber=p.item_id';
							$cq .= ' WHERE ((i.inumber=p.item_id and (p.categories REGEXP "(^|,)'.$catdata['catid'].'(,|$)" or i.icat='.$catdata['catid'].')) or (p.item_id IS NULL and i.icat='.$catdata['catid'].'))';
						} else {
							$cq .= ' WHERE i.icat='.$catdata['catid'];
						}
						$cq .= ' and i.itime<=' . mysqldate($b->getCorrectTime()) . ' and i.idraft=0';
						$catdata['amount'] = quickQuery($cq);
						if (intval($catdata['amount']) < 1) {
							continue;
						}
						$catdata['ammount'] = $catdata['amount'];

						if ($CONF['URLMode'] == 'pathinfo' && $cattype == 'category')	{
							$qstr_c = 'category';
						} else {
							$qstr_c = 'catid';
						}
						$qstr_c .= $conn . $catdata['catid'];
						
						if ($urimode == 'nucleus') {
							$catdata['catlink'] = $myblogurl . $qstr_c ;
							if (!$catnoFlg) $catdata['catlink'] .=  $v_conn . $qstr;
						} else {
							$catdata['catlink'] = $myblogurl;
							if (!$catnoFlg) $catdata['catlink'] .= $qstr . $v_conn;
							$catdata['catlink'] .= $qstr_c;
						}
						if ($CONF['URLMode'] == 'pathinfo' && $manager->pluginInstalled('NP_CustomURL')) {
							$catdata['catlink'] = createCategoryLink($catdata['catid']);
						}
						
						// sub category ---
						if ($subcat && (!$subcurrent || $catid == $catdata['catid']) && !$subnoOpen) {
							$sres = sql_query("SELECT scatid as subcatid, sname as subname, sdesc as subdesc FROM ".sql_table('plug_multiple_categories_sub')." WHERE catid=".$catdata['catid']);
							if (sql_num_rows($sres) > 0) {
								$subliststr = "";
								while ($subdata =  sql_fetch_assoc($sres)) {
									$ares = sql_query(
										'SELECT count(i.inumber) FROM '
										. sql_table('item').' as i, '
										. sql_table('plug_multiple_categories').' as p'
										. ' WHERE i.idraft=0'
										. ' and i.itime<='.mysqldate($b->getCorrectTime())
										. ' and i.inumber=p.item_id'
										. ' and p.subcategories REGEXP "(^|,)'.$subdata['subcatid'].'(,|$)"'
									);
									if ($ares && $row = sql_fetch_row($ares)) {
										$subdata['subamount'] = $row[0];
										if ($subdata['subamount'] > 0) {
											$temp_catlist = preg_replace("/<%if\(subcategory\)%>([\s\S]+(?=<%else%>))?(?:<%else%>[\s\S]*)?<%endif%>/","$1",$tp['catlist']);
											$subdata['sublink'] = createCategoryLink($catdata['catid'], array($subrequest => $subdata['subcatid']));
											if ($CONF['URLMode'] == 'pathinfo' && $manager->pluginInstalled('NP_CustomURL')) {
												$customurls = $manager->getPlugin('NP_CustomURL');
												$subdata['sublink'] = $customurls->_addLinkParams($catdata['catlink'], array($subrequest => $subdata['subcatid']));
											}
											if ($subdata['subcatid'] == $subcatid) {
												$subdata['subflag']= $tp['subflag'];
											}
											$subliststr .= TEMPLATE::fill($tp['sublist'],array_merge($data,$catdata,$subdata));
										}
									}
								}
								if ($subliststr) {
									$catdata['subcategorylist'] = $tp['subheader'];
									$catdata['subcategorylist'] .= $subliststr;
									$catdata['subcategorylist'] .= $tp['subfooter'];
								}
							}
							sql_free_result($sres);
						}
						// ---------------
						
						if ($CONF['URLMode'] == 'pathinfo' && $magical) {
							$catdata['catlink'] .= '.html';
						}
						if (!$temp_catlist) {
							$temp_catlist = preg_replace("/<%if\(subcategory\)%>(?:[\s\S]+(?=<%else%>))?<%else%>([\s\S]+(?=<%endif%>))?<%endif%>/","$1",$tp['catlist']);
						}
						
						if ($catid == $catdata['catid']) $catdata['catflag']= $tp['catflag'];
						
						$tempcat .= TEMPLATE::fill($temp_catlist,array_merge($data,$catdata));
					}
					$temp .= $tempcat;
					$temp .= $tp['catfooter'];
					sql_free_result($cres);
					$temp = (isset($subdata) || isset($tempcat)) ? $temp : '';
					$item = str_replace("<%categorylist%>",$temp,$tp['list']);
					break;
		// Categories end -----------------------------------------------

				default:
					if (!$noFlg) $data['blogurl'] .= $qstr;
					if ($CONF['URLMode'] == 'pathinfo' && $magical) {
						$data['blogurl'] .= '.html';
					}
					$item = $tp['list'];
			}
		
		echo TEMPLATE::fill($item,$data);
		
		}
		sql_free_result($res);

		echo $tp['footer'];

	}// doSkinVar end
	

	
	function parse_skinfile_var($str) {
		global $CONF;
		
		$rep = $CONF['SkinsURL'] . PARSER::getProperty('IncludePrefix') . "$2";
		$str = preg_replace("/(<%skinfile\(?)([^\(\)]+)?(\)?%>)/",$rep,$str);
		return $str;
	}

}
