<?php
/**
  * NP_ContentsList Admin Page Script 
  *     Taka ( http://vivian.stripper.jp ) 2004-04-14
  *     Nucleus(JP) team (http://japan.nucleuscms.org/) 2006-11-26
  */

	// if your 'plugin' directory is not in the default location,
	// edit this variable to point to your site directory
	// (where config.php is)
	$strRel = '../../../';

	include($strRel . 'config.php');
	if (!$member->isLoggedIn())
		doError('You\'re not logged in.');

	include($DIR_LIBS . 'PLUGINADMIN.php');

	// create the admin area page
	$oPluginAdmin = new PluginAdmin('ContentsList');

	/* Exit if not valid ticket */
	if ( (requestVar('action') && requestVar('action')!='overview')
					&& (!$manager->checkTicket()) ){
		$oPluginAdmin->start();
		echo '<p>' . _ERROR_BADTICKET . '</p>';
		$oPluginAdmin->end();
		exit;
	}

	$contentslist_classfile = $oPluginAdmin->plugin->getDirectory().'/class.php';



	include($contentslist_classfile);
	
// ------------------------------------------------------------------

class ContentsList_ADMIN extends PLUG_ADMIN {

	function ContentsList_ADMIN() {
		global $oPluginAdmin;
		
		$this->plug =& $oPluginAdmin->plugin;
		$this->plugname = $this->plug->getName();
		$this->url = $this->plug->getAdminURL();

		
		//rank
		$this->blogmaxrank = $this->plug->get('blogmaxrank');
		$this->catmaxrank = $this->plug->get('catmaxrank');

		// template
		$this->template_table = sql_table('plug_contentslist');
		$this->template_idname = 'tp_id';
		$this->template_namepart = 'name';
		$this->template_default = 'tp_def';
		$this->tmanager = new PLUG_TEMPLATE_MANAGER(
				$this->template_table,
				$this->template_idname,
				$this->template_namepart);
		$this->template_parts = array(
			'name','showmode','hideblog','blogorder','catorder',
			'header','list','footer','flag',
			'catheader','catlist','catfooter','catflag'
		);
		if ($this->plug->getOption('subcatfield') == "yes") {
			$temp = array('subheader','sublist','subfooter','subflag');
			$this->template_parts = array_merge($this->template_parts,$temp);
		}
		
		// selectmenu array
		$this->blogorders = array(
			'b.bnumber ASC'=>_NUMBER_ASC,
			'b.bnumber DESC'=>_NUMBER_DESC,
			'b.bname ASC'=>_NAME_ASC,
			'b.bname DESC'=>_NAME_DESC
			);
		$this->catorders = array(
			'c.catid ASC'=>_NUMBER_ASC,
			'c.catid DESC'=>_NUMBER_DESC,
			'c.cname ASC'=>_NAME_ASC,
			'c.cname DESC'=>_NAME_DESC
			);
		$this->smode_array = array(_HIDE_BLOG,_DISPLAY_BLOG);
	}

	function getRankQuery($type, $blogid=1) {
		switch ($type) {
			case 'blog':
				return 'SELECT b.bname as name, p.rid as id, p.rank as rank FROM '.sql_table('plug_contentslist_rank').' as p, '.sql_table('blog').' as b WHERE p.blog=1 and p.rid=b.bnumber ORDER BY p.rank, p.rid ASC';
				break;
			case 'category':
				return 'SELECT c.cname as name, p.rid as id, p.rank as rank FROM '.sql_table('plug_contentslist_rank').' as p, '.sql_table('category').' as c WHERE p.blog=0 and c.cblog='.intval($blogid).' and p.rid=c.catid ORDER BY p.rank, p.rid ASC';
				break;
		}
	}
	
	function updateRank($type,$ranks) {
		$flag = ($type=='blog') ? 1 : 0;
		foreach ($ranks as $k => $v) {
			$query = 'UPDATE '.sql_table('plug_contentslist_rank').' SET '
			. 'rank='.intval($v).' WHERE rid='.intval($k).' and blog='.$flag;
			sql_query($query);
		}
	}
	
	function getDefaultTemplateID() {
		return quickQuery('SELECT tp_id as result FROM '.sql_table('plug_contentslist').' WHERE tp_def=1');
	}
	
	function updateDefaultTemplate($defid) {
		sql_query('UPDATE '.sql_table('plug_contentslist').' SET tp_def=1 WHERE tp_id='.intval($defid));
		sql_query('UPDATE '.sql_table('plug_contentslist').' SET tp_def=0 WHERE tp_id<>'.intval($defid));
	}

//-----
	function action_overview($msg='') {
		global $member, $oPluginAdmin, $manager;
		
		$member->isAdmin() or $this->disallow();

		$oPluginAdmin->start();
		
		echo '<p><a href="index.php?action=pluginlist">('._PLUGS_BACK.')</a></p>';
		echo '<h2>' .$this->plugname. '</h2>'."\n";
		if ($msg) echo "<p>"._MESSAGE.": $msg</p>";
		echo '<p>'.$this->helplink('use').'<img src="documentation/icon-help.gif" width="15" height="15" alt="'._HELP_TT.'" />'._NPPLUGIN_HOWTOUSE.'</a> | <a href="index.php?action=pluginoptions&amp;plugid='.$this->plug->getID().'">'._NPPLUGIN_EDITOPTION.'</a></p>';
?>

<h3><?php echo _RANK_EDIT_TITLE ?></h3>
	<form method="post" action="<?php echo $this->url ?>index.php"><div>
		<?php $manager->addTicketHidden(); ?>
		<input name="action" value="blogrankupdate" type="hidden" />
		<table>
		<thead>
			<tr><th><?php echo _RANK_ID ?></th><th><?php echo _RANK_BLOG_NAME ?></th><th><?php echo _RANK_RANK; $this->help('rank');?></th><th><?php echo _LISTS_ACTIONS ?></th></tr>
		</thead>
		<tbody>
<?php
		$query = $this->getRankQuery('blog');
		$res = sql_query($query);
		while ($o = sql_fetch_object($res)) {
?>
			<tr>
				<td><?php echo $o->id ?></td>
				<td><?php echo $o->name ?></td>
				<td><?php $this->showRankSelectMenu('rank['.$o->id.']',$o->rank,$this->blogmaxrank,10) ?></td>
				<td><a href="<?php echo htmlspecialchars($manager->addTicketToUrl($this->url.'index.php?action=catrankedit&blogid='.$o->id)); ?>" tabindex="10"><?php echo _RANK_CATEGORY_EDIT_TITLE ?></a></td>
			</tr>
<?php
		}
?>
			<tr>
				<td colspan="4">
					<input type="submit" tabindex="10" value="<?php echo _RANK_BLOG_UPDATE_BTN ?>" onclick="return checkSubmit();" />
					<input type="reset" tabindex="10" value="<?php echo _RANK_RESET_BTN?>" />
				</td>
			</tr>
		</tbody>
		</table>
	</div></form>

<?php
		echo '<h3>' . _TEMPLATE_TITLE . '</h3>'."\n\n";
?>
	<form method="post" action="<?php echo $this->url ?>index.php"><div>
	<?php $manager->addTicketHidden(); ?>
	<input name="action" value="templatedefupdate" type="hidden" />
		<table>
			<tbody>
				<tr>
					<th><?php echo _NPTEMPLATE_DEFAULT; $this->help('default');?></th>
					<td>
<?php
		$templates = $this->tmanager->getNameList();
		$defid = $this->getDefaultTemplateID();
		$this->showSelectMenu($this->template_default,$templates,$defid,20);
?>
				</td>
				<td>
					<input type="submit" tabindex="20" value="<?php echo _NPTEMPLATE_DEF_UPDATE_BTN ?>" onclick="return checkSubmit();" />
				</td>
				</tr>
			</tbody>
		</table>
	</div></form>
	<table>
<?php
		echo '<caption style="text-align:left;padding-left:20px;">' . _TEMPLATE_AVAILABLE_TITLE . '</caption>';
?>
	
	<thead>
		<tr><th><?php echo _LISTS_NAME ?></th><th colspan='3'><?php echo _LISTS_ACTIONS ?></th></tr>
	</thead>
	<tbody>
<?php
		foreach ($templates as $k=>$v) {
?>
		<tr onmouseover='focusRow(this);' onmouseout='blurRow(this);'>
			<td>
				<?php echo htmlspecialchars($v) ?></td>
			<td><a href="<?php echo htmlspecialchars($manager->addTicketToUrl($this->url.'index.php?action=templateedit&'.$this->template_idname.'='.$k)); ?>" tabindex="50"><?php echo _LISTS_EDIT ?></a></td>
			<td><a href="<?php echo htmlspecialchars($manager->addTicketToUrl($this->url.'index.php?action=templateclone&'.$this->template_idname.'='.$k)); ?>" tabindex="50"><?php echo _LISTS_CLONE ?></a></td>
			<td><a href="<?php echo htmlspecialchars($manager->addTicketToUrl($this->url.'index.php?action=templatedelete&'.$this->template_idname.'='.$k)); ?>" tabindex="50"><?php echo _LISTS_DELETE ?></a></td>
		</tr>
<?php
		}
?>
	</tbody>
	</table>
<?php
		
		echo "\n\n".'<h3>' . _TEMPLATE_NEW_TITLE . '</h3>'."\n\n";
		
?>
	<form method="post" action="<?php echo $this->url ?>index.php"><div>
	
		<?php $manager->addTicketHidden(); ?>
		<input name="action" value="templatenew" type="hidden" />
		<table><tr>
			<td><?php echo _TEMPLATE_NAME?> <?php $this->help('name');?></td>
			<td><input name="<?php echo $this->template_namepart ?>" tabindex="10010" maxlength="20" size="20" /></td>
		</tr><tr>
			<td><?php echo _TEMPLATE_CREATE?></td>
			<td><input type="submit" tabindex="10020" value="<?php echo _TEMPLATE_CREATE_BTN?>" onclick="return checkSubmit();" /></td>
		</tr></table>
		
	</div></form>
<?php
		
		$oPluginAdmin->end();
	
	}

//-----
	function action_blogrankupdate() {
		global $member;

		$member->isAdmin() or $this->disallow();
		
		$ranks = $this->requestEx('rank');
		$this->updateRank('blog',$ranks);
		$this->action_overview(_MSG_BLOGRANK_UPDATED);
	}

	function action_catrankupdate() {
		global $member;

		$member->isAdmin() or $this->disallow();
		
		$ranks = $this->requestEx('rank');
		$blogid = intPostVar('blogid');
		$this->updateRank('category',$ranks);
		$this->action_catrankedit($blogid,_MSG_CATRANK_UPDATED);
	}
	
	function action_catrankedit($blogid=0, $msg='') {
		global $member, $oPluginAdmin, $manager;
		
		$member->isAdmin() or $this->disallow();
		
		if(!$blogid) {
			$blogid = intRequestVar('blogid');
		}
		$blogname = getBlogNameFromID($blogid);
		
		$oPluginAdmin->start();
?>
<p><a href="<?php echo $this->url ?>index.php?action=overview">(<?php echo _NPPLUGIN_GOBACK?>)</a></p>

<h2><?php 
		echo _RANK_CATEGORY_EDIT_TITLE."</h2>\n";

		if ($msg) echo "<p>"._MESSAGE.": $msg</p>";
?>
	<h3><?php echo $blogname ?></h3>
	<form method="post" action="<?php echo $this->url ?>index.php"><div>
		<?php $manager->addTicketHidden(); ?>
		<input name="action" value="catrankupdate" type="hidden" />
		<input name="blogid" value="<?php echo $blogid ?>" type="hidden" />
		<table>
		<thead>
			<tr><th><?php echo _RANK_CATEGORY_NAME?></th><th><?php echo _RANK_RANK; $this->help('rank');?></th></tr>
		</thead>
		<tbody>

<?php
		$a_crank = array();
		for ($i=1; $i<=$this->catmaxrank; $i++) {
			$a_crank[$i] = $i;
		}
		$a_crank[$this->catmaxrank + 1] = _RANK_HIDE_CAT;
		
		$query = $this->getRankQuery('category',$blogid);
		$res = sql_query($query);
		while ($o = sql_fetch_object($res)) {
?>
			<tr>
				<td><?php echo $o->name ?></td>
				<td><?php $this->showSelectMenu('rank['.$o->id.']',$a_crank,$o->rank,10) ?></td>
			</tr>
<?php
		}
?>
			<tr>
				<td colspan="3">
					<input type="submit" tabindex="10" value="<?php echo _RANK_CAT_UPDATE_BTN?>" onclick="return checkSubmit();" />
					<input type="reset" tabindex="10" value="<?php echo _RANK_RESET_BTN?>" />
				</td>
			</tr>
		</tbody>
		</table>
	</div>
	</form>
<?php
	
		$oPluginAdmin->end();
	}

	function action_templatedefupdate() {
		global $member;

		$member->isAdmin() or $this->disallow();
		
		$defid = intPostVar($this->template_default);
		$this->updateDefaultTemplate($defid);
		$this->action_overview(_MSG_DEFTEMPLATE_UPDATED);
	}

	function action_templateedit($msg = '') {
		global $member, $oPluginAdmin, $manager;
		
		$member->isAdmin() or $this->disallow();
		
		$templateid = intRequestVar($this->template_idname);
		
		$oPluginAdmin->start();

		$templatename = $this->tmanager->getNameFromID($templateid);
		$template = $this->tmanager->read($templatename);

?>
<p><a href="<?php echo $this->url ?>index.php?action=overview">(<?php echo _NPPLUGIN_GOBACK?>)</a></p>

<h2><?php 
		echo $this->plugname.' '._TEMPLATE_EDIT_TITLE;
		echo  " '$templatename'</h2>\n";

		if ($msg) echo "<p>"._MESSAGE.": $msg</p>";
?>

<form method="post" action="<?php echo $this->url ?>index.php">
	<div>
	
	<?php $manager->addTicketHidden(); ?>
	<input type="hidden" name="action" value="templateupdate" />
	<input type="hidden" name="<?php echo $this->template_idname ?>" value="<?php echo $templateid; ?>" />
		
	<table><tr>
			<th colspan="2"><?php echo _TEMPLATE_SETTINGS ?></th>
		</tr><tr>
			<td><?php echo _TEMPLATE_NAME?> <?php $this->help('name');?></td>
			<td><input name="<?php echo $this->template_namepart ?>" tabindex="4" size="20" maxlength="20" value="<?php echo htmlspecialchars($templatename) ?>" /></td>
		</tr><tr>
			<td rowspan="2"><?php echo _NPTEMPLATE_HIDESHOWBLOG; $this->help('hideblog');?></td>
			<td><?php $this->showRadioButton('showmode',$this->smode_array,$template['showmode'],5); ?></td>
		</tr><tr>
			<td><?php $this->showBlogCheckbox('hideblog',explode("/",$template['hideblog']),6); ?></td>
		</tr><tr>
			<td><?php echo _NPTEMPLATE_BLOGDEFAULT_ORDER; $this->help('blogorder');?></td>
			<td><?php $this->showSelectMenu('blogorder',$this->blogorders,$template['blogorder'],7); ?> </td>
		</tr><tr>
			<td><?php echo _NPTEMPLATE_CATDEFAULT_ORDER; $this->help('catorder');?></td>
			<td><?php $this->showSelectMenu('catorder',$this->catorders,$template['catorder'],8); ?></td>
		</tr><tr>
			<th colspan="2"><?php echo _TEMPLATE_UPDATE?></th>
		</tr><tr>
			<td><?php echo _TEMPLATE_UPDATE ?></td>
			<td>
				<input type="submit" tabindex="9" value="<?php echo _TEMPLATE_UPDATE_BTN?>" onclick="return checkSubmit();" />
				<input type="reset" tabindex="10" value="<?php echo _TEMPLATE_RESET_BTN?>" />
			</td>
		</tr><tr>
			<th colspan="2"><?php echo _NPTEMPLATE_BLOGLIST; $this->help('bloglist'); ?></th>
<?php
			
		$this->templateEditRow($template, _NPTEMPLATE_BLOGLIST_HEADER, 'header', '', 20);
		$this->templateEditRow($template, _NPTEMPLATE_BLOGLIST_ITEM, 'list', '', 30);
		$this->templateEditRow($template, _NPTEMPLATE_BLOGLIST_FOOTER, 'footer', '', 40);
		$this->templateEditRow($template, _NPTEMPLATE_BLOGLIST_FLAG, 'flag', '', 50);
?>
		</tr><tr>
			<th colspan="2"><?php echo _NPTEMPLATE_CATLIST; $this->help('categorylist'); ?></th>
<?php

		$this->templateEditRow($template, _NPTEMPLATE_CATLIST_HEADER, 'catheader', '', 60);
		$this->templateEditRow($template, _NPTEMPLATE_CATLIST_ITEM, 'catlist', '', 70);
		$this->templateEditRow($template, _NPTEMPLATE_CATLIST_FOOTER, 'catfooter', '', 80);
		$this->templateEditRow($template, _NPTEMPLATE_CATLIST_FLAG, 'catflag', '', 90);
	if ($this->plug->getOption('subcatfield') == "yes") {
?>
		</tr><tr>
			<th colspan="2"><?php echo _NPTEMPLATE_SUBLIST; $this->help('subcategorylist'); ?></th>
<?php

		$this->templateEditRow($template, _NPTEMPLATE_SUBLIST_HEADER, 'subheader', '', 100);
		$this->templateEditRow($template, _NPTEMPLATE_SUBLIST_ITEM, 'sublist', '', 110);
		$this->templateEditRow($template, _NPTEMPLATE_SUBLIST_FOOTER, 'subfooter', '', 120);
		$this->templateEditRow($template, _NPTEMPLATE_SUBLIST_FLAG, 'subflag', '', 130);
	}
?>
		</tr><tr>
			<th colspan="2"><?php echo _TEMPLATE_UPDATE?></th>
		</tr><tr>
			<td><?php echo _TEMPLATE_UPDATE?></td>
			<td>
				<input type="submit" tabindex="290" value="<?php echo _TEMPLATE_UPDATE_BTN?>" onclick="return checkSubmit();" />
				<input type="reset" tabindex="300" value="<?php echo _TEMPLATE_RESET_BTN?>" />
			</td>
		</tr></table>
		
	</div>
</form>

<?php
	
		$oPluginAdmin->end();
	
	}

	function action_templatenew() {
		global $member;
		
		$member->isAdmin() or $this->disallow();
		
		$name = postVar($this->template_namepart);
		
		if (!isValidTemplateName($name))
			$this->error(_ERROR_BADTEMPLATENAME);
		
		if ($this->tmanager->exists($name))
			$this->error(_ERROR_DUPTEMPLATENAME);

		$newid = $this->tmanager->createTemplate($name);

		$this->action_overview();
	}
	
	function action_templateclone() {
		global $member;
		
		$templateid = intRequestVar($this->template_idname);
		
		$member->isAdmin() or $this->disallow();
				
		// 1. read old template
		$basename = $this->tmanager->getNameFromID($templateid);

		// 2. create desc thing
		$newname = "cloned" . $basename;
		
		// if a template with that name already exists:
		if ($this->tmanager->exists($newname)) {
			$i = 1;
			while ($this->tmanager->exists($newname . $i))
				$i++;
			$newname .= $i;
		}		
		
		$newid = $this->tmanager->createTemplate($newname);

		// 3. create clone
		// go through parts of old template and add them to the new one
		$this->addToTemplate(intval($newid), $basename);

		$this->action_overview();
	}
	
	function action_templateupdate() {
		global $member;
		
		$templateid = intRequestVar($this->template_idname);

		$member->isAdmin() or $this->disallow();
		
		$name = postVar($this->template_namepart);
		
		if (!isValidTemplateName($name))
			$this->error(_ERROR_BADTEMPLATENAME);
		
		if (($this->tmanager->getNameFromID($templateid) != $name) && $this->tmanager->exists($name))
			$this->error(_ERROR_DUPTEMPLATENAME);

		$this->addToTemplate($templateid);
		
		// jump back to template edit
		$this->action_templateedit(_TEMPLATE_UPDATED);
	
	}	

	function addToTemplate($nowid, $basename='') {
		if ($basename) {
			$template = $this->tmanager->read($basename);
			$newname = $this->tmanager->getNameFromID($nowid);
			$template[$this->template_idname] = $nowid;
			$template[$this->template_namepart] = $newname;
			$template[$this->template_default] = 0;
		} else {
			$datanames = $this->template_parts;
			foreach ($datanames as $val) {
				$template[$val] = $this->requestEx($val);
				if(is_array($template[$val])) $template[$val] = implode('/',$template[$val]);
			}
		}
		$this->tmanager->updateTemplate($nowid,$template);
	}

	function action_templatedelete() {
		global $member, $oPluginAdmin, $manager;
		
		$member->isAdmin() or $this->disallow();
		
		$templateid = intRequestVar($this->template_idname);
		// TODO: check if template can be deleted
		
		$oPluginAdmin->start();
		
		$name = $this->tmanager->getNameFromId($templateid);
		
		?>
			<h2><?php echo _DELETE_CONFIRM?></h2>
			
			<p><?php echo _CONFIRMTXT_TEMPLATE?><b><?php echo $name ?></b></p>
			
			<form method="post" action="<?php echo $this->url ?>index.php"><div>
				<?php $manager->addTicketHidden(); ?>
				<input type="hidden" name="action" value="templatedeleteconfirm" />
				<input type="hidden" name="<?php echo $this->template_idname ?>" value="<?php echo $templateid ?>" />
				<input type="submit" tabindex="10" value="<?php echo _DELETE_CONFIRM_BTN?>" />
			</div></form>
		<?php
		
		$oPluginAdmin->end();
	}	
	
	function action_templatedeleteconfirm() {
		global $member, $manager;
		
		$templateid = intRequestVar($this->template_idname);
		
		$member->isAdmin() or $this->disallow();
		
		$this->tmanager->deleteTemplate($templateid);
		
		$this->action_overview(_MSG_NPTEMPLATE_DELETED);
	}
	

} // ContentsList_ADMIN end
	
// ------------------------------------------------------------------

$myAdmin = new ContentsList_ADMIN();
if (requestVar('action')) {
	$myAdmin->action(requestVar('action'));
} else {
	$myAdmin->action('overview');
}

?>

