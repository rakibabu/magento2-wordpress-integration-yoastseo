<?php
/**
 *
**/

namespace FishPig\WordPress_Yoast\Plugin;

use \FishPig\WordPress\Model\Config;
use \FishPig\WordPress\Helper\Plugin as PluginHelper;
use \FishPig\WordPress\Helper\View as ViewHelper;
use \FishPig\WordPress\Model\AbstractModel;
use \Magento\Framework\Registry;

use \FishPig\WordPress\Model\Post;
use \FishPig\WordPress\Model\Term;
use \FishPig\WordPress\Model\User;
use \FishPig\WordPress\Model\Archive;

abstract class AbstractPlugin extends \Magento\Framework\DataObject
{
	const PLUGIN_FILE_FREE = 'wordpress-seo/wp-seo.php';
	const PLUGIN_FILE_PREMIUM = 'wordpress-seo-premium/wp-seo-premium.php';
	
	protected $_separators = array(
		'sc-dash'   => '-',
		'sc-ndash'  => '&ndash;',
		'sc-mdash'  => '&mdash;',
		'sc-middot' => '&middot;',
		'sc-bull'   => '&bull;',
		'sc-star'   => '*',
		'sc-smstar' => '&#8902;',
		'sc-pipe'   => '|',
		'sc-tilde'  => '~',
		'sc-laquo'  => '&laquo;',
		'sc-raquo'  => '&raquo;',
		'sc-lt'     => '&lt;',
		'sc-gt'     => '&gt;',
	);
	
	protected $_config = null;
	protected $_pluginHelper = null;
	protected $_viewHelper = null;
	
	public function __construct(Config $config, PluginHelper $pluginHelper, ViewHelper $viewHelper, Registry $registry, $data = [])
	{
		$this->_config = $config;
		$this->_pluginHelper = $pluginHelper;
		$this->_viewHelper = $viewHelper;
		$this->_registry = $registry;

		$this->_init();
	}
	
	protected function _init()
	{
		if (!$this->isEnabled()) {
			return $this;
		}
		
		$types = ['wpseo', 'wpseo_titles', 'wpseo_xml', 'wpseo_social', 'wpseo_rss', 'wpseo_internallinks', 'wpseo_permalinks'];
		$data = array();
		
		foreach($types as $type) {
			if ($options = $this->_pluginHelper->getOption($type)) {
				foreach($options as $key => $value) {
					$data[str_replace('-', '_', $key)] = $value;
				}
			}
		}
		
		return $this->setData($data);
	}

	public function isEnabled()
	{
		return $this->_pluginHelper->isEnabled(self::PLUGIN_FILE_FREE)
			|| $this->_pluginHelper->isEnabled(self::PLUGIN_FILE_PREMIUM);
	}
	
	public function aroundGetPageTitle($object, $callback)
	{
		if ($this->isEnabled()) {
			$this->_setupRewriteData($object);
			
			if (($value = $this->_aroundGetPageTitle($object)) !== null) {
				return $value;
			}
		}

		return $callback();
	}
	
	protected function _aroundGetPageTitle(AbstractModel $object)
	{
		return null;
	}
	
	/**
	 *
	 *
	 * @return  string
	**/	
	public function aroundGetMetaDescription($object, $callback)
	{
		if ($this->isEnabled()) {
			$this->_setupRewriteData($object);
			
			if (($value = $this->_aroundGetMetaDescription($object)) !== null) {
				return $value;
			}
		}
		
		return $callback();
	}

	protected function _aroundGetMetaDescription(AbstractModel $object)
	{
		return null;
	}
	
	/**
	 *
	 *
	 * @return  string
	**/
	public function aroundGetMetaKeywords($object, $callback)
	{
		if ($this->isEnabled()) {
			$this->_setupRewriteData($object);
			
			if (($value = $this->_aroundGetMetaKeywords($object)) !== null) {
				return $value;
			}
		}
		
		return $callback();
	}
	
	protected function _aroundGetMetaKeywords(AbstractModel $object)
	{
		return null;
	}
	
	/**
	 *
	 *
	 * @return  string
	**/
	public function aroundGetRobots($object, $callback)
	{		
		if ($this->isEnabled()) {
			if (!$this->_viewHelper->canDiscourageSearchEngines()) {
				$this->_setupRewriteData($object);
				
				if (($value = $this->_aroundGetRobots($object)) !== null) {
					if ($this->_isNoindex('subpages_wpseo') && (int)$this->_viewHelper->getRequest()->getParam('page') > 1) {
						$value['index'] = 'noindex';
					}

					return strtoupper(implode(',', $value));
				}
			}
		}
		
		return $callback();
	}
	
	protected function _aroundGetRobots(AbstractModel $object)
	{
		return null;
	}
	
	/**
	 *
	 *
	 * @return  string
	**/
	public function aroundGetCanonicalUrl($object, $callback)
	{
		if ($this->isEnabled()) {
			$this->_setupRewriteData($object);
			
			if (($value = $this->_aroundGetCanonicalUrl($object)) !== null) {
				return $value;
			}
		}
		
		return $callback();
	}
	
	protected function _aroundGetCanonicalUrl(AbstractModel $object)
	{
		return null;
	}
	
	protected function _debugData()
	{
		echo sprintf('<pre>%s</pre>', print_r($this->getData(), true));
		exit;
	}
	
	/**
	 * Given a key that determines which format to load
	 * and a data array, merge the 2 to create a valid title
	 *
	 * @param string $key
	 * @param array $data
	 * @return string|false
	 */
	protected function _rewriteString($format, $data = [])
	{
		if (!$data) {
			$data = $this->getRewriteData();
		}
		
		$rwt = '%%';
		$value = array();
		$parts = preg_split("/(" . $rwt . "[a-z_-]{1,}" . $rwt . ")/iU", $format, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

		foreach($parts as $part) {
			if (substr($part, 0, strlen($rwt)) === $rwt && substr($part, -(strlen($rwt))) === $rwt) {
				$part = trim($part, $rwt);
				
				if (isset($data[$part])) {
					$value[] = $data[$part];
				}
			}
			else {
				$value[] = $part;
			}
		}

		if (($value = trim(implode('', $value))) !== '') {
			return $value;
		}
		
		return false;
	}
	
	protected function _setupRewriteData(AbstractModel $object, $force = false)
	{
		if (!$this->hasRewriteData() || $force) {
			$data = array(
				'sitename' => $this->_config->getOption('blogname'),
				'sitedesc' => $this->_config->getOption('blogdescription'),
				'currenttime' => $this->_viewHelper->formatTime(date('Y-m-d H:i:s')),
				'currentdate' => $this->_viewHelper->formatDate(date('Y-m-d H:i:s')),
				'currentmonth' => date('F'),
				'currentyear' => date('Y'),
				'sep' => '|',
			);

			if ($sep = $this->getData('separator')) {
				if (isset($this->_separators[$sep])) {
					$data['sep'] = $this->_separators[$sep];
				}
			}

			if (($value = trim($this->_viewHelper->getSearchTerm(true))) !== '') {
				$data['searchphrase'] = $value;
			}

			if ($object  instanceof Post) {
				$data['date'] = $object->getPostDate();
				$data['title'] = $object->getPostTitle();
				$data['excerpt'] = trim($object->getExcerpt(30));
				$data['excerpt_only'] = $data['excerpt'];
				
				$categories = array();

				foreach($object->getTermCollection('category')->load() as $category) {
					$categories[] = $category->getName();	
				}
				
				$data['category'] = implode(', ', $categories);
				$data['modified'] = $object->getPostModified();
				$data['id'] = $object->getId();
				$data['name'] = $object->getUser()->getUserNicename();
				$data['userid'] = $object->getUser()->getId();
			}
			else if ($object instanceof Term) {
				$data['term_description'] = trim(strip_tags($object->getDescription()));
				$data['term_title'] = $object->getName();
			}
			else if ($object instanceof Archive) {
				$data['date'] = $object->getName();
			}
			else if ($object instanceof User) {
				$data['name'] = $object->getDisplayName();
			}	
			
			$this->setRewriteData($data);		
		}
		
		return $this;
	}
	
	/**
	 * Retrieve the rewrite data
	 *
	 * @return array
	 */
	public function getRewriteData(array $updates = [])
	{
		$rewriteData = $this->_getData('rewrite_data');		
		
		if ($updates) {
			$rewriteData = array_merge($rewriteData, $updates);
		}
		
		return $rewriteData;
	}
	
	public function updateRewriteData($key, $value)
	{
		return $this->setRewriteData(
			array_merge($this->getRewriteData(), array($key => $value))
		);
	}
	
	/**
	 * Retrieve the title format for the given key
	 *
	 * @param string $key
	 * @return string
	 */
	protected function _getPageTitleFormat($key)
	{
		return trim($this->getData('title_' . $key));
	}
	
	/**
	 * Retrieve the title format for the given key
	 *
	 * @param string $key
	 * @return string
	 */
	protected function _getMetaDescriptionFormat($key)
	{
		return trim($this->getData('metadesc_' . $key));
	}
	
	/**
	 * Retrieve the title format for the given key
	 *
	 * @param string $key
	 * @return string
	 */
	protected function _getMetaKeywordsFormat($key)
	{
		return trim($this->getData('metakey_' . $key));
	}
	
	
	/**
	 * Retrieve the title format for the given key
	 *
	 * @param string $key
	 * @return string
	 */
	protected function _isNoindex($key)
	{
		return (int)$this->getData('noindex_' . $key) === 1;
	}
	
	/**
	 * Determine whether subpages (eg. page/2/) should always be noindex
	 *
	 * @return bool
	**/
	public function _isSubPageNoindex()
	{
		return $this->_isNoindex('subpages_wpseo');
	}
}