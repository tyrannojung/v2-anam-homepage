<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */

/**
 * document class
 * @brief document the module's high class
 * {@internal Silently adds one extra Foo to compensate for lack of Foo }
 *
 * @author NAVER (developers@xpressengine.com)
 * @package /modules/document
 * @version 0.1
 */
class Document extends ModuleObject
{
	/**
	 * Search option to use in admin page
	 * @var array
	 */
	public $search_option = array('title', 'content', 'title_content', 'user_name');

	/**
	 * List of status texts supported by Rhymix.
	 *
	 * Also see status constants in common/constants.php
	 * and integer status codes used in the comment module.
	 *
	 * @var array
	 */
	public static $statusList = array(
		'temp' => 'TEMP',
		'private' => 'PRIVATE',
		'public' => 'PUBLIC',
		'secret' => 'SECRET',
		'embargo' => 'EMBARGO',
		'trash' => 'TRASH',
		'censored' => 'CENSORED',
		'censored_by_admin' => 'CENSORED_BY_ADMIN',
		'deleted' => 'DELETED',
		'deleted_by_admin' => 'DELETED_BY_ADMIN',
		'other' => 'OTHER',
	);

	/**
	 * Implement if additional tasks are necessary when installing
	 * @return Object
	 */
	function moduleInstall()
	{
		$oDB = DB::getInstance();
		$oDB->addIndex("documents","idx_module_list_order", array("module_srl","list_order"));
		$oDB->addIndex("documents","idx_module_update_order", array("module_srl","update_order"));
		$oDB->addIndex("documents","idx_module_readed_count", array("module_srl","readed_count"));
		$oDB->addIndex("documents","idx_module_voted_count", array("module_srl","voted_count"));
		$oDB->addIndex("documents","idx_module_regdate", array("module_srl","regdate"));
		$oDB->addIndex("documents","idx_module_notice", array("module_srl","is_notice"));
		$oDB->addIndex("documents","idx_module_document_srl", array("module_srl","document_srl"));
		$oDB->addIndex("documents","idx_module_blamed_count", array("module_srl","blamed_count"));
		$oDB->addIndex("document_aliases", "idx_module_title", array("module_srl","alias_title"), true);
		$oDB->addIndex("document_extra_vars", "unique_extra_vars", array("module_srl","document_srl","var_idx","lang_code"), true);
	}

	/**
	 * A method to check if successfully installed
	 * @return bool
	 */
	function checkUpdate()
	{
		$oDB = DB::getInstance();

		// 2007. 8. 23: create a clustered index in the document table
		if(!$oDB->isIndexExists("documents","idx_module_list_order")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_update_order")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_readed_count")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_voted_count")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_regdate")) return true;

		// 2007. 11. 20 create a composite index on the columns(module_srl + is_notice)
		if(!$oDB->isIndexExists("documents","idx_module_notice")) return true;

		// 2008. 02. 18 create a composite index on the columns(module_srl + document_srl) (checked by Manian))
		if(!$oDB->isIndexExists("documents","idx_module_document_srl")) return true;

		// 2008. 04. 23 Add a column(blamed_count)
		if(!$oDB->isIndexExists("documents","idx_module_blamed_count")) return true;

		// 2009. 03. 11 check the index in the document_extra_vars table
		if(!$oDB->isIndexExists("document_extra_vars", "unique_extra_vars")) return true;
		if($oDB->isIndexExists("document_extra_vars", "unique_module_vars")) return true;

		// 2011. 03. 30 Cubrid index Check the index in the document_extra_vars table
		if(!$oDB->isIndexExists("document_extra_vars", "idx_document_list_order")) return true;

		// 2011. 10. 25 status index check
		if(!$oDB->isIndexExists("documents", "idx_module_status")) return true;

		// 2016. 1. 27: Add a column(declare_message) for report
		if(!$oDB->isColumnExists("document_declared_log","declare_message")) return true;

		// 2016. 3. 14 Add a column(document_upate_log) for admin
		if(!$oDB->isColumnExists('document_update_log', 'is_admin')) return true;

		// 2019. 3. 07 #1146
		if(!$oDB->isColumnExists('document_update_log', 'reason_update')) return true;

		// 2017.12.21 Add an index for nick_name
		if(!$oDB->isIndexExists('documents', 'idx_nick_name')) return true;

		// 2024.05.04 Add default category
		if(!$oDB->isColumnExists('document_categories', 'is_default')) return true;
		if(!$oDB->isIndexExists('document_categories', 'idx_list_order')) return true;

		// 2024.10.08 Add columns to document_extra_keys table
		if(!$oDB->isColumnExists('document_extra_keys', 'var_is_strict')) return true;
		if(!$oDB->isColumnExists('document_extra_keys', 'var_options')) return true;

		return false;
	}

	/**
	 * Execute update
	 * @return Object
	 */
	function moduleUpdate()
	{
		$oDB = DB::getInstance();

		// 2007. 8. 23: create a clustered index in the document table
		if(!$oDB->isIndexExists("documents","idx_module_list_order"))
		{
			$oDB->addIndex("documents","idx_module_list_order", array("module_srl","list_order"));
		}

		if(!$oDB->isIndexExists("documents","idx_module_update_order"))
		{
			$oDB->addIndex("documents","idx_module_update_order", array("module_srl","update_order"));
		}

		if(!$oDB->isIndexExists("documents","idx_module_readed_count"))
		{
			$oDB->addIndex("documents","idx_module_readed_count", array("module_srl","readed_count"));
		}

		if(!$oDB->isIndexExists("documents","idx_module_voted_count"))
		{
			$oDB->addIndex("documents","idx_module_voted_count", array("module_srl","voted_count"));
		}

		if(!$oDB->isIndexExists("documents","idx_module_regdate"))
		{
			$oDB->addIndex("documents","idx_module_regdate", array("module_srl","regdate"));
		}

		// 2007. 11. 20 create a composite index on the columns(module_srl + is_notice)
		if(!$oDB->isIndexExists("documents","idx_module_notice"))
		{
			$oDB->addIndex("documents","idx_module_notice", array("module_srl","is_notice"));
		}

		// 2008. 02. 18 create a composite index on the columns(module_srl + document_srl) (checked by Manian))
		if(!$oDB->isIndexExists("documents","idx_module_document_srl"))
		{
			$oDB->addIndex("documents","idx_module_document_srl", array("module_srl","document_srl"));
		}

		// 2008. 04. 23 Add a column(blamed count)
		if(!$oDB->isIndexExists("documents","idx_module_blamed_count"))
		{
			$oDB->addIndex('documents', 'idx_module_blamed_count', array('module_srl', 'blamed_count'));
		}

		// 2009. 03. 11 Check the index in the document_extra_vars table
		if(!$oDB->isIndexExists("document_extra_vars", "unique_extra_vars"))
		{
			$oDB->addIndex("document_extra_vars", "unique_extra_vars", array("module_srl","document_srl","var_idx","lang_code"), true);
		}
		if($oDB->isIndexExists("document_extra_vars", "unique_module_vars"))
		{
			$oDB->dropIndex("document_extra_vars", "unique_module_vars", true);
		}

		// 2011. 03. 30 Cubrid index Check the index in the document_extra_vars table
		if(!$oDB->isIndexExists("document_extra_vars", "idx_document_list_order"))
		{
			$oDB->addIndex("document_extra_vars", "idx_document_list_order", array("document_srl","module_srl","var_idx"), false);
		}

		if(!$oDB->isIndexExists("documents", "idx_module_status"))
		{
			$oDB->addIndex("documents", "idx_module_status", array("module_srl","status"));
		}

		// 2016. 1. 27: Add a column(declare_message) for report
		if(!$oDB->isColumnExists("document_declared_log","declare_message"))
		{
			$oDB->addColumn('document_declared_log',"declare_message","text");
		}

		// 2016. 3. 14 Add a column(document_update_log) for admin
		if(!$oDB->isColumnExists('document_update_log', 'is_admin'))
		{
			$oDB->addColumn('document_update_log', 'is_admin', 'varchar', 1);
			$oDB->addIndex('document_update_log', 'idx_is_admin', array('is_admin'));
		}

		// 2019. 3. 07 #1146
		if(!$oDB->isColumnExists('document_update_log', 'reason_update'))
		{
			$oDB->addColumn('document_update_log', 'reason_update', 'text', '', null, false, 'extra_vars');
		}

		// 2017.12.21 Add an index for nick_name
		if(!$oDB->isIndexExists('documents', 'idx_nick_name'))
		{
			$oDB->addIndex('documents', 'idx_nick_name', array('nick_name'));
		}

		// 2024.05.04 Add default category
		if(!$oDB->isColumnExists('document_categories', 'is_default'))
		{
			$oDB->addColumn('document_categories', 'is_default', 'char', '1', 'N', true, 'expand');
		}
		if(!$oDB->isIndexExists('document_categories', 'idx_list_order'))
		{
			$oDB->addIndex('document_categories', 'idx_list_order', array('list_order'));
		}

		// 2024.10.08 Add columns to document_extra_keys table
		if(!$oDB->isColumnExists('document_extra_keys', 'var_is_strict'))
		{
			$oDB->addColumn('document_extra_keys', 'var_is_strict', 'char', '1', 'N', true, 'var_is_required');
		}
		if(!$oDB->isColumnExists('document_extra_keys', 'var_options'))
		{
			$oDB->addColumn('document_extra_keys', 'var_options', 'text', null, null, false, 'var_default');
		}
	}

	/**
	 * Document Status List
	 * @return array
	 */
	public static function getStatusList()
	{
		return self::$statusList;
	}

	/**
	 * Return default status
	 * @return string
	 */
	public static function getDefaultStatus()
	{
		return self::$statusList['public'];
	}

	/**
	 * Return status by key
	 * @return string
	 */
	public static function getConfigStatus($key)
	{
		return self::$statusList[$key];
	}
}
/* End of file document.class.php */
/* Location: ./modules/document/document.class.php */
