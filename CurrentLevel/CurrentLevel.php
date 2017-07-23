<?php

/**
 * A plugin for Pico which reads only the current directory /
 * subdirectory in Pico's `$pages` variable.
 *
 * Based on Bigi Lui's PicoTooManyPages which can be found under
 * <https://github.com/bigicoin/PicoTooManyPages>. Without this
 * code this plugin would not exist!
 * @author  Eike Kühn
 * @link    https://github.com/randomchars42/pico-currentlevel
 * @license http://opensource.org/licenses/MIT The MIT License
 * @version 1.0
 */
final class CurrentLevel extends AbstractPicoPlugin
{
	/**
	 * This plugin is not enabled by default
	 *
	 * @see AbstractPicoPlugin::$enabled
	 * @var boolean
	 */
	protected $enabled = false;

	/**
	 * This plugin depends on nothing
	 *
	 * @see AbstractPicoPlugin::$dependsOn
	 * @var string[]
	 */
	protected $dependsOn = array();

	protected $configCache = null;
	protected $contentDir = '';
	protected $currentLevel = '';
	protected $currentFile = '';
	protected $currentNav = array();
	protected $isPicoEdit = false;

	/**
	 * Triggered after Pico has read its configuration
	 *
	 * @see    Pico::getConfig()
	 * @param  array &$config array of config variables
	 * @return void
	 */
	public function onConfigLoaded(array &$config) {
		$this->configCache = &$config; // for updating later
		$this->contentDir = $this->configCache['content_dir'];
	}

	/**
	 * Triggered after Pico has evaluated the request URL
	 *
	 * @see    Pico::getRequestUrl()
	 * @param  string &$url part of the URL describing the requested contents
	 * @return void
	 */
	public function onRequestUrl(&$url) {
		$parts = explode('/', $url);
		// detect for pico_edit, but only pico_edit main page, not the action urls.
		if ($parts[0] == 'pico_edit' && count($parts) == 1) {
			$this->isPicoEdit = true;
		}
	}

	/**
	 * Triggered after Pico has discovered the content file to serve
	 *
	 * @see    Pico::getBaseUrl()
	 * @see    Pico::getRequestFile()
	 * @param  string &$file absolute path to the content file to serve
	 * @return void
	 */
	public function onRequestFile(&$file)	{
		// because pico_edit triggers sessions in onRequestUrl, we need to check for its session status afterward,
		// which is here.
		// Additionally, the login action uses $_POST['password'], make sure to load files on that action too.
		if ($this->isPicoEdit) {
			if(empty($_SESSION['backend_logged_in']) && empty($_POST['password'])) {
				$this->isPicoEdit = false; // if pico_edit, but not logged in, no need to load files either.
			}
		} else {
			// the directory where the currently displayed file resides
			// relative to the content directory
			$this->currentLevel = substr(dirname($file), strlen($this->contentDir));
			$this->currentFile = $file;
		}
	}

	/**
	 * Triggered before Pico reads all known pages
	 *
	 * @see    Pico::readPages()
	 * @see    DummyPlugin::onSinglePageLoaded()
	 * @see    DummyPlugin::onPagesLoaded()
	 * @return void
	 */
	public function onPagesLoading() {
		if ($this->isPicoEdit) {
			return; // do not skip loading pages on pico_edit urls
		}
		// we disable reading of pages by setting content_dir to a dummy directory
		$this->contentDir = $this->configCache['content_dir'];
		$this->configCache['content_dir'] = dirname(__FILE__).'/empty/';
	}

	/**
	 * Triggered after Pico has read all known pages
	 *
	 * See {@link DummyPlugin::onSinglePageLoaded()} for details about the
	 * structure of the page data.
	 *
	 * @see    Pico::getPages()
	 * @see    Pico::getCurrentPage()
	 * @see    Pico::getPreviousPage()
	 * @see    Pico::getNextPage()
	 * @param  array[]    &$pages        data of all known pages
	 * @param  array|null &$currentPage  data of the page being served
	 * @param  array|null &$previousPage data of the previous page
	 * @param  array|null &$nextPage     data of the next page
	 * @return void
	 */
	public function onPagesLoaded(
		array &$pages,
		array &$currentPage = null,
		array &$previousPage = null,
		array &$nextPage = null
	) {
		// set the content_dir back to normal
		$this->configCache['content_dir'] = $this->contentDir;

		if (!$this->isPicoEdit) {
			// scan the current directory
			$pages = $this->readPages($this->currentLevel);
			$this->currentNav = $this->rediscoverCurrentPage($pages);
			isset($this->currentNav['current']) && $currentPage = $pages[$this->currentNav['current']];
			isset($this->currentNav['previous']) && $previousPage = $pages[$this->currentNav['previous']];
			isset($this->currentNav['next']) && $nextPage = $pages[$this->currentNav['next']];
		}
	}

	private function parseDir(string $dir) {
		$path = $this->contentDir . $dir;
		$result = array();
		if (file_exists($path) && is_dir($path)) {

			$files = array_diff(scandir($path), array('.', '..'));

			if (count($files) > 0) {
				foreach ($files as $file) {
					if (is_file("$path/$file")) {
						if (substr($file, -3) === $this->configCache['content_ext']) {
							$result[] = "$path/$file";
						}
					} else if (is_dir("$path/$file") && is_file("$path/$file/index" . $this->configCache['content_ext'])){
						$result[] = "$path/$file/index" . $this->configCache['content_ext'];
					}
				}
			}
		}
		//if (file_exists("$this->contentDir/404" . $this->configCache['content_ext'])) {
		//	$result[] = "$this->contentDir/404" . $this->configCache['content_ext'];
		//}
		return $result;
	}


	/**
	 * Reads the data of all pages in the given directory.
	 *
	 * The page data will be an array containing the following values:
	 *
	 * | Array key      | Type   | Description                              |
	 * | -------------- | ------ | ---------------------------------------- |
	 * | id             | string | relative path to the content file        |
	 * | url            | string | URL to the page                          |
	 * | title          | string | title of the page (YAML header)          |
	 * | description    | string | description of the page (YAML header)    |
	 * | author         | string | author of the page (YAML header)         |
	 * | time           | string | timestamp derived from the Date header   |
	 * | date           | string | date of the page (YAML header)           |
	 * | date_formatted | string | formatted date of the page               |
	 * | raw_content    | string | raw, not yet parsed contents of the page |
	 * | meta           | string | parsed meta data of the page             |
	 *
	 * @return void
	 */
	protected function readPages(string $path) {
		$pico = $this->getPico();
		$pages = array();
		$files = $this->parseDir($path, $pico->getConfig('content_ext'));
		foreach ($files as $i => $file) {
			// skip 404 page
			if (basename($file) === '404' . $pico->getConfig('content_ext')) {
				unset($files[$i]);
				continue;
			}

			$id = substr($file, strlen($pico->getConfig('content_dir')), -strlen($pico->getConfig('content_ext')));

			$id = (substr($id, 0, 1) == '/') ? substr($id, 1) : $id;

			// drop inaccessible pages (e.g. drop "sub.md" if "sub/index.md" exists)
			$conflictFile = $pico->getConfig('content_dir') . $id . '/index' . $pico->getConfig('content_ext');
			if (in_array($conflictFile, $files, true)) {
				continue;
			}

			$url = $pico->getPageUrl($id);
			if ($file != $this->currentFile) {
				$rawContent = file_get_contents($file);

				$headers = $pico->getMetaHeaders();
				try {
					$meta = $pico->parseFileMeta($rawContent, $headers);
				} catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
					$meta = $pico->parseFileMeta('', $headers);
					$meta['YAML_ParseError'] = $e->getMessage();
				}
			} else {
				//$rawContent = &$pico->rawContent;
				//$meta = &$pico->meta;
				$rawContent = $pico->getRawContent();
				$meta = $pico->getFileMeta();
			}

			// build page data
			// title, description, author and date are assumed to be pretty basic data
			// everything else is accessible through $page['meta']
			$page = array(
				'id' => $id,
				'url' => $url,
				'title' => &$meta['title'],
				'description' => &$meta['description'],
				'author' => &$meta['author'],
				'time' => &$meta['time'],
				'date' => &$meta['date'],
				'date_formatted' => &$meta['date_formatted'],
				'raw_content' => &$rawContent,
				'meta' => &$meta
			);

			if ($file === $this->currentFile) {
				$page['content'] = $pico->getFileContent();
			}

			unset($rawContent, $meta);

			$pages[$id] = $page;
		}
		return $pages;
	}

	/**
	 * Walks through the list of known pages and discovers the requested page
	 * as well as the previous and next page relative to it
	 *
	 * @see    Pico::getCurrentPage()
	 * @see    Pico::getPreviousPage()
	 * @see    Pico::getNextPage()
	 * @return void
	 */
	public function rediscoverCurrentPage(array &$pages) {
		$pageIds = array_keys($pages);

		$contentDir = $this->configCache['content_dir'];
		$contentDirLength = strlen($contentDir);

		// the requested file is not in the regular content directory, therefore its ID
		// isn't specified and it's impossible to determine the current page automatically
		if (substr($this->currentFile, 0, $contentDirLength) !== $contentDir) {
			return;
		}

		$contentExt = $this->configCache['content_ext'];
		$currentPageId = substr($this->currentFile, $contentDirLength, -strlen($contentExt));

		$currentPageIndex = array_search($currentPageId, $pageIds);

		$nav = array();

		if ($currentPageIndex !== false) {
			$nav['current'] = $currentPageId;

			if (($this->configCache['pages_order_by'] === 'date') && ($this->configCache['pages_order'] === 'desc')) {
				$previousPageOffset = 1;
				$nextPageOffset = -1;
			} else {
				$previousPageOffset = -1;
				$nextPageOffset = 1;
			}

			if (isset($pageIds[$currentPageIndex + $previousPageOffset])) {
				$previousPageId = $pageIds[$currentPageIndex + $previousPageOffset];
				$nav['previous'] = $previousPageId;
			}

			if (isset($pageIds[$currentPageIndex + $nextPageOffset])) {
				$nextPageId = $pageIds[$currentPageIndex + $nextPageOffset];
				$nav['next'] = $nextPageId;
			}
		}

		return $nav;
	}

	public function getBreadcrumbs(string $id = null) {
		if (empty($id)) {
			return array();
		}
		$levels = explode('/', $id);
		$breadcrumbs = array();
		$fileId = '';

		foreach ($levels as $level) {
			if ($level !== '') {
				$fileId = "$fileId/$level";
				if (is_file("$this->contentDir/$fileId/index" . $this->configCache['content_ext'])) {
					$breadcrumbs["$level"] = "$fileId";
				}
			}
		}

		return $breadcrumbs;
	}

	public function onPageRendering(Twig_Environment &$twig, array &$twigVariables, &$templateName) {
		//$twig->addFilter(new Twig_SimpleFilter('breadcrumbs', array($this, 'breadcrumbs')));
		$twig->addFunction(new Twig_SimpleFunction('breadcrumbs', array($this, 'getBreadcrumbs')));
	}
}
