<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2014 by i-MSCP Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @category    iMSCP
 * @package     iMSCP_Plugin
 * @subpackage  RoundcubePlugins
 * @copyright   Rene Schuster <mail@reneschuster.de>
 * @copyright   Sascha Bay <info@space2place.de>
 * @author      Rene Schuster <mail@reneschuster.de>
 * @author      Sascha Bay <info@space2place.de>
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/**
 * RoundcubePlugins Plugin
 *
 * This plugin allows to use Roundcube Plugins with i-MSCP.
 *
 * @category    iMSCP
 * @package     iMSCP_Plugin
 * @subpackage  RoundcubePlugins
 * @author      Rene Schuster <mail@reneschuster.de>
 */
class iMSCP_Plugin_RoundcubePlugins extends iMSCP_Plugin_Action
{
	/**
	 * Register a callback for the given event(s).
	 *
	 * @param iMSCP_Events_Manager_Interface $eventsManager
	 */
	public function register(iMSCP_Events_Manager_Interface $eventsManager)
	{
		$eventsManager->registerListener(
			array(
				iMSCP_Events::onBeforeInstallPlugin,
				iMSCP_Events::onBeforeUpdatePlugin,
				iMSCP_Events::onBeforeEnablePlugin
			),
			$this
		);
	}

	/**
	 * onBeforeInstallPlugin event listener
	 *
	 * @param iMSCP_Events_Event $event
	 */
	public function onBeforeInstallPlugin($event)
	{
		$this->checkCompat($event);
	}

	/**
	 * Plugin installation
	 *
	 * @throws iMSCP_Plugin_Exception
	 * @param iMSCP_Plugin_Manager $pluginManager
	 * @return void
	 */
	public function install(iMSCP_Plugin_Manager $pluginManager)
	{
		try {
			$this->migrateDb('up');
		} catch (iMSCP_Plugin_Exception $e) {
			throw new iMSCP_Plugin_Exception(sprintf('Unable to install: %s', $e->getMessage()), $e->getCode(), $e);
		}
	}

	/**
	 * Plugin uninstallation
	 *
	 * @throws iMSCP_Plugin_Exception
	 * @param iMSCP_Plugin_Manager $pluginManager
	 * @return void
	 */
	public function uninstall(iMSCP_Plugin_Manager $pluginManager)
	{
		try {
			$this->migrateDb('down');
		} catch (iMSCP_Plugin_Exception $e) {
			throw new iMSCP_Plugin_Exception(tr('Unable to uninstall: %s', $e->getMessage()), $e->getCode(), $e);
		}
	}

	/**
	 * onBeforeUpdatePlugin event listener
	 *
	 * @param iMSCP_Events_Event $event
	 * @return void
	 */
	public function onBeforeUpdatePlugin($event)
	{
		$this->checkCompat($event);
	}
	
	/**
	 * Plugin update
	 *
	 * @throws iMSCP_Plugin_Exception When update fail
	 * @param iMSCP_Plugin_Manager $pluginManager
	 * @param string $fromVersion Version from which plugin update is initiated
	 * @param string $toVersion Version to which plugin is updated
	 * @return void
	 */
	public function update(iMSCP_Plugin_Manager $pluginManager, $fromVersion, $toVersion)
	{
		try {
			$this->migrateDb('up');
		} catch (iMSCP_Plugin_Exception $e) {
			throw new iMSCP_Plugin_Exception(sprintf('Unable to update: %s', $e->getMessage()), $e->getCode(), $e);
		}
	}

	/**
	 * onBeforeEnablePlugin listener
	 *
	 * @param iMSCP_Events_Event $event
	 * @return void
	 */
	public function onBeforeEnablePlugin($event)
	{
		$this->checkCompat($event);
	}

	/**
	 * Plugin enable
	 *
	 * @throws iMSCP_Plugin_Exception
	 * @param iMSCP_Plugin_Manager $pluginManager
	 * @return void
	 */
	public function enable(iMSCP_Plugin_Manager $pluginManager)
	{
		try {
			$this->addDovecotSieveServicePort();
		} catch (iMSCP_Exception_Database $e) {
			throw new iMSCP_Plugin_Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Plugin disable
	 *
	 * @throws iMSCP_Plugin_Exception
	 * @param iMSCP_Plugin_Manager $pluginManager
	 * @return void
	 */
	public function disable(iMSCP_Plugin_Manager $pluginManager)
	{
		try {
			$this->removeDovecotSieveServicePort();
		} catch (iMSCP_Exception_Database $e) {
			throw new iMSCP_Plugin_Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Check plugin compatibility
	 *
	 * @param iMSCP_Events_Event $event
	 */
	protected function checkCompat($event)
	{
		if ($event->getParam('pluginName') == $this->getName()) {
			if (version_compare($event->getParam('pluginManager')->getPluginApiVersion(), '0.2.10', '<')) {
				set_page_message(
					tr('Your i-MSCP version is not compatible with this plugin. Try with a newer version.'), 'error'
				);

				$event->stopPropagation();
			}
		}
	}

	/**
	 * Add dovecot-sieve service port
	 *
	 * @return void
	 */
	protected function addDovecotSieveServicePort()
	{
		$dbConfig = iMSCP_Registry::get('dbConfig');
		$pluginConfig = $this->getConfig();
		
		if ($pluginConfig['managesieve_plugin'] == 'yes') {
			if (!isset($dbConfig['PORT_DOVECOT-SIEVE'])) {
				$dbConfig['PORT_DOVECOT-SIEVE'] = '4190;tcp;DOVECOT-SIEVE;1;127.0.0.1';
			}
		} else {
			$this->removeDovecotSieveServicePort();
		}		
	}

	/**
	 * Remove dovecot-sieve service port
	 *
	 * @return void
	 */
	protected function removeDovecotSieveServicePort()
	{
		$dbConfig = iMSCP_Registry::get('dbConfig');
		
		if (isset($dbConfig['PORT_DOVECOT-SIEVE'])) {
			unset($dbConfig['PORT_DOVECOT-SIEVE']);
		}
	}
}
