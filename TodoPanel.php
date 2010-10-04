<?php
/**
 * Annotations "Todo" panel for Nette 1.0+. Displays all todos in all files given.
 *
 * @author Mikuláš Dítě, Peter Ped Helcmanovsky
 * @license MIT
 */

namespace Panel;
use Nette\Debug;
use Nette\Finder;
use Nette\IDebugPanel;
use Nette\Object;
use Nette\SafeStream;
use Nette\String;
use Nette\Templates\FileTemplate;
use Nette\Templates\LatteFilter;


class TodoPanel extends Object implements IDebugPanel
{

	/** @var array|mixed stores found todos */
	private $items = array();

	/** @var array any path or file containing one of the patterns to skip */
	private $ignoreMask = array();

	/** @var array */
	private $scanDirs = array();



	/** @var array patterns for "todo" comments to catch */
	public $todoMask = array('TO\s?DO', 'FIX\s?ME', 'PENDING', 'XXX');

	/** @var bool defines wheter the todo type should be visible */
	public $showType = FALSE;



	/**
	 * @param array|string $basedir path or paths to scan
	 * @param array $ignoreMask can use wildcards
	 */
	public function __construct($basedir = APP_DIR, $ignoreMask = array('.git',  '.svn', 'cache', 'log', 'sessions', 'temp'))
	{
		if (is_array($basedir)) {
			foreach ($basedir as $path) {
				$this->addDirectory(realpath($path));
			}
		} else {
			$this->addDirectory(realpath($basedir));
		}
		
		$this->setIgnoreMask($ignoreMask);
	}



	/**
	 * Renders HTML code for custom tab
	 * IDebugPanel
	 * @return void
	 */
	public function getTab()
	{
		return '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAPnRFWHRDb21tZW50AENyZWF0ZWQgd2l0aCBUaGUgR0lNUAoKKGMpIDIwMDMgSmFrdWIgJ2ppbW1hYycgU3RlaW5lcicz71gAAAGtSURBVHjarZMxSBthFMd/d/feqVjiIAoFcQqVFoogjUSJdDBYaECH4Nq1OLiU2C52KKGi4uTgEhDcFRxEyeDSsQdKhdKSEhAFseEGCxFpa+7r0Fi8axM7+OC/vf/ve+//+ODftQKYiFa4ofLXDb7v/1GpVIrC8lcmuQaYLSzMcPDlhM2dXTzPo1KpAFAul7nb3clo6hEDD/t48WZ5FngdBQCwWXzH1naRarVKLBYDIB6PY9s2hUKBs69Hof4QwBjIZrOkUqlQk2VZAORyOYobq40BYMhknjI+PoGqIFKXKuoIjgrF9aYAEHERdVDRullQR2ibew73+jHGhPrt8PugKrC2RPv8FHv7e3jvPdpeTWKdHuP0D/11uhAgCMB1XXrK+7SffyORSPB4fRHr4hx7+i1uMk0QNJvAGEQVv/c+Vu2Sjpks+vM79vQcLck0qtp8hVoQoKp4gxP4vQ+wapccjEzSOpxGXEVFCCKAcIjG4Kow9mQMzWQQEZKiqApO/SIYGgMCA0svn/H5sIKpZxJ1xO60NgZ8+viB6sUPero6+J2VITLx/3+mG5TntuoX7nmiqfg2Y6EAAAAASUVORK5CYII=">' .
			'Todo (' . $this->getTodoCount() . ')';
	}



	/**
	 * Sum of found todos in browsed files
	 * @return int
	 */
	public function getTodoCount()
	{
		$count = 0;
		foreach ($this->getTodo() as $file) {
			$count += count($file);
		}
		return $count;
	}



	/**
	 * Renders HTML code for custom panel
	 * IDebugPanel
	 * @return void
	 */
	public function getPanel()
	{
		ob_start();
		$template = new FileTemplate(dirname(__FILE__) . '/bar.todo.panel.phtml');
		$template->registerFilter(new LatteFilter());
		$template->todos = $this->getTodo();
		$template->todoCount = $this->getTodoCount();
		$template->showType = $this->showType;
		$template->render();

		return ob_get_clean();
	}



	/**
	 * IDebugPanel
	 * @return string
	 */
	public function getId()
	{
		return __CLASS__;
	}



	/**
	 * Registeres panel to Debug bar
	 */
	public static function register()
	{
		Debug::addPanel(new self);
	}



	/**
	 * Wrapper for generateTodo, performace booster in one instance
	 */
	private function getTodo()
	{
		if (empty($this->items)) {
			$this->items = $this->generateTodo();
		}
		return $this->items;
	}



	/**
	 * Returns array in format $filename => array($todos)
	 * @uses \Nette\SafeStream
	 * @throws \InvalidStateException
	 */
	protected function generateTodo()
	{
		if (count($this->todoMask) === 0) {
			throw new \InvalidStateException('No todo mask specified for TodoPanel.');
		}

		@SafeStream::register(); //intentionally @ (prevents multiple registration warning)

		$items = array();
		foreach (Finder::findFiles('*')->exclude('.*', '*/' . $this->ignoreMask . '/*')->from($this->scanDirs) as $path => $file) {
			$items[$path] = $this->parseFile($file);
		}
		
		return $items;
	}



	/**
	 * Reads pointed file and returns all comments found
	 * @param SplFileInfo $file
	 * @returns array
	 */
	private function parseFile($file)
	{
		$todos = array();

		$stream = fopen("safe://" . $file->getRealPath(), 'r');
		$content_original = $content = fread($stream, filesize("safe://" . $file->getRealPath()));
		fclose($stream);
		
		// Remove harcoded strings so we do not search in them
		$content = String::replace($content, '~("|\')(.|\\\"|\\\')*?("|\')~s', '\'\'');

		
		$matches = array();

		$patterns = array(
			'php' => '~/\*(?P<content>.*?)\*/~sm',
			'latte' => '~{\*(?P<content>.*?)\*}~sm',
			'html' => '~<!--(?P<content>.*?)-->~sm',
		);

		// find block comments
		foreach ($patterns as $pattern) {
			$matches = array_merge($matches, String::matchAll($content, $pattern));
			$content = String::replace($content, $pattern);
		}

		$comment_lines = array();

		// find oneline comments
		foreach (String::matchAll($content, '~(//|#)(?P<content>.*?)$~m') as $match) {
			$comment_lines[] = String::trim($match['content']);
		}

		// split block comments by lines
		foreach ($matches as $match) {
			$comment_block = String::trim($match['content']);
			$comment_lines = array_merge($comment_lines, String::split($comment_block, '~[\r\n]{1,2}~'));
		}

		foreach ($comment_lines as $comment_content) {
			$match = String::match($comment_content, '~(^[@*\s-]*|[@*\s-])(?P<type>' . implode('|', $this->todoMask) . ')\s+(?P<todo>.*?)$~mi');
			
			if ($match === NULL) {
				continue;
			}

			$line = 0;
			// assign line number
			foreach (String::split($content_original, '~\n~') as $line_number => $content_line) {
				if (strpos($content_line, $comment_content) !== FALSE) {
					$line = $line_number + 1;
					break;
				}
			}

			$todos[] = array(
				'line' => $line,
				'type' => String::lower($match['type']),
				'content' => $match['todo'],
				'link' => strtr(Debug::$editor, array('%file' => urlencode($file->getRealPath()), '%line' => $line)),
				'file' => $file->getFilename(),
			);
		}

		usort($todos, callback($this, 'compareTodos'));
		
		return $todos;
	}



	/**
	 * Add directory to list
	 * @param string
	 * @return void
	 * @throws DirectoryNotFoundException
	 */
	public function addDirectory($path)
	{
		$realpath = realpath($path);
		if (!$realpath) {
			throw new \DirectoryNotFoundException("Directory `$path` not found.");
		}
		$this->scanDirs[] = $realpath;
	}


	
	/**
	 * Set string patterns to ignore files which contain some pattern in full path
	 * @example $todoPanel->setSkipPatterns(array('/.git', 'app/sessions/'));
	 * @param array $ignoreMask
	 */
	public function setIgnoreMask(array $ignoreMask, $merge = FALSE)
	{
		if ($merge) {
			foreach ($ignoreMask as $mask) {
				if (!array_search($mask, $this->ignoreMask)) {
					$this->ignoreMask[] = $mask;
				}
			}
		} else {
			$this->ignoreMask = $ignoreMask;
		}
	}



	/**
	 * usort implementation
	 * @param array $compared
	 * @param array $todo
	 * @return int
	 */
	public function compareTodos($compared, $todo)
	{
		if ($compared['line'] == $todo['line']) {
			return 0;
		}
		return $compared['line'] < $todo['line'] ? -1 : 1;
	}
}
