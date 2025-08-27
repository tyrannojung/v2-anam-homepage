<?php
	/**
	 * @class  popup
	 * @author XESCHOOL Revised for use in v1.7
	 * @brief  high class of the popup module
	 **/

	class popup extends ModuleObject {

		/**
		 * @brief Implement if additional tasks are necessary when installing
		 **/
		function moduleInstall() {

			return new BaseObject();
		}

		/**
		 * @brief a method to check if successfully installed
		 **/
		function checkUpdate() {
			$oDB = &DB::getInstance();
			$oModuleModel = &getModel('module');

			if(!$oModuleModel->isIDExists($this->module)) return true;

			// 2013-05-20 Add columns
			if(!$oDB->isColumnExists("popup","target_actions")) return true;
			if(!$oDB->isColumnExists("popup","popup_style")) return true;
			if(!$oDB->isColumnExists("popup","popup_checkbox")) return true;

			return false;
		}

		/**
		 * @brief Execute update
		 **/
		function moduleUpdate() {
			$oDB = &DB::getInstance();
			$oModuleModel = &getModel('module');
			$oPopupAdminController = &getAdminController('popup');

			if(!$oModuleModel->isIDExists($this->module)) $oPopupAdminController->popupModuleRegister();

			// 2013-05-20 Add columns
			if(!$oDB->isColumnExists("popup","target_actions")) {
				$oDB->addColumn('popup','target_actions','varchar',250);
			}
			if(!$oDB->isColumnExists("popup","popup_style")) {
				$oDB->addColumn('popup','popup_style','varchar',20);
			}
			if(!$oDB->isColumnExists("popup","popup_checkbox")) {
				$oDB->addColumn('popup','popup_checkbox','varchar',10,'Y',true);
			}

			return new BaseObject(0, 'success_updated');
		}

		/**
		 * @brief Re-generate the cache file
		 **/
		function recompileCache() {
		}

	}
?>
