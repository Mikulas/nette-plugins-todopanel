<?php
/**
 * TodoPanel for Nette 2.0
 *
 * @author Mikuláš Dítě, Peter Ped Helcmanovsky
 * @license MIT
 */

namespace Panel;
use Nette\Diagnostics\Debugger;
use Nette\Utils\Finder;
use Nette\Diagnostics\IBarPanel;
use Nette\Object;
use Nette\Utils\SafeStream;
use Nette\Utils\Strings as String;
use Nette\Templating\FileTemplate;
use Nette\Latte\Engine;


class Todo extends Object implements IBarPanel
{

	/** @var array|mixed stores found todos */
	private $items = array();

	/** @var array */
	private $scanDir = array();

	/** @var array any path or file containing one of the patterns to skip */
	private $ignoreMask = array();

	/** @var array */
	private $commentBlockMask = array();



	/** @var array patterns for "todo" comments to catch */
	public $todoMask = array('TO\s?DO', 'FIX\s?ME', 'PENDING', 'XXX');

	/** @var bool defines wheter the todo type should be visible */
	public $showType = FALSE;



	/**
	 * @param array|string $basedir path or paths to scan
	 * @param array $ignoreMask can use wildcards
	 */
	public function __construct($basedir, $ignoreMask = NULL)
	{
		if (is_array($basedir)) {
			foreach ($basedir as $path) {
				$this->addDirectory(realpath($path));
			}
		} else {
			$this->addDirectory(realpath($basedir));
		}
		
		$this->setIgnoreMask($ignoreMask ?: array('.git',  '.svn', 'cache', 'log', 'sessions', 'temp'));


		$patterns = array(
			array('~^(php|css|js)$~', '~/\*(?P<content>.*?)\*/~sm'),
			array('~^(php|js)$~', '~//(?P<content>.*?)$~sm'),
			array('~^(php|sh|ps1|neon)$~', '~#(?P<content>.*?)$~sm'),
			array('~^(latte|phtml)$~', '~{\*(?P<content>.*?)\*}~sm'),
			array('~^(latte|phtml|html)$~', '~<!--(?P<content>.*?)-->~sm'),
			array('~^(ini)$~', '~;(?P<content>.*?)$~sm'),
			array('~^(bat)$~', '~^[ \t]*REM[ \t]+(?P<content>.*?)$~smi'),
		);

		foreach ($patterns as $pattern) {
			call_user_func_array(callback($this, 'addPattern'), $pattern);
		}
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
	 * Renders HTML code for custom panel
	 * IDebugPanel
	 * @return void
	 */
	public function getPanel()
	{
		ob_start();
		$template = new FileTemplate(dirname(__FILE__) . '/bar.todo.panel.latte');
		$template->registerFilter(new Engine());
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
	 * Registers panel to Debug bar
	 */
	public static function register($basedir = NULL, $ignoreMask = NULL)
	{
		Debugger::addPanel(new self($basedir, $ignoreMask));
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
			throw new \Nette\DirectoryNotFoundException("Directory `$path` not found.");
		}
		if (!array_search($realpath, $this->scanDir)) {
			$this->scanDir[] = $realpath;
		}
	}



	/**
	 * Adds custom comment block pattern
	 * @param string $extension regex
	 * @param string $pattern regex Must contain group named `content`
	 */
	public function addPattern($extension, $pattern)
	{
		self::validatePattern($pattern);
		$this->commentBlockMask[] = array('extension' => $extension, 'pattern' => $pattern);
	}



	/**
	 * Throws exception if custom pattern does not name comment block content group
	 * @example pattern `~block_start(?P<content>.*?)block_end~sm`
	 * @throws \InvalidArgumentException
	 * @param string $pattern regex
	 */
	private static function validatePattern($pattern)
	{
		if (String::match($pattern, '~\\(\\?P<content>\\.\\*\\?\\)~') === NULL) {
			throw new \InvalidArgumentException('Custom pattern `' . $pattern . '` does not contain a group named `content`.');
		}
	}


	
	/**
	 * Files to ignore
	 * @example $todoPanel->setIgnoreMask(array('.git', 'app/sessions'));
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
	 * @throws \Nette\InvalidStateException
	 */
	private function generateTodo()
	{
		if (count($this->todoMask) === 0) {
			throw new \Nette\InvalidStateException('No todo mask specified for TodoPanel.');
		}

		@SafeStream::register(); //intentionally @ (prevents multiple registration warning)

		$items = array();
		foreach (Finder::findFiles('*')->size('> 3B')->exclude('.*', '*/' . $this->ignoreMask . '/*')->from($this->scanDir) as $path => $file) {
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

		
		$patterns = array();
		foreach ($this->commentBlockMask as $pattern) {
			if (String::match(pathinfo($file, PATHINFO_EXTENSION), $pattern['extension'])) {
				$patterns[] = $pattern['pattern'];
			}
		}

		$matches = array();
		
		// find block comments
		foreach ($patterns as $pattern) {
			$matches = array_merge($matches, String::matchAll($content, $pattern));
			$content = String::replace($content, $pattern);
		}

		$comment_lines = array();

		// split block comments by lines
		foreach ($matches as $match) {
			$comment_block = String::trim($match['content']);
			$comment_lines = array_merge($comment_lines, String::split($comment_block, '~[\r\n]{1,2}~'));
		}

		foreach ($comment_lines as $key => $comment_content) {
			$match = String::match($comment_content, '~(^[@*\s-]*|[@*\s-])(?P<type>' . implode('|', $this->todoMask) . ')\s+(?P<todo>.*?)$~mi');
			
			if ($match === NULL) {
				continue;
			}

			$skip = 0;
			foreach ($comment_lines as $tkey => $line) {
				if ($tkey >= $key) {
					break;
				}
				if ($line == $comment_content) {
					$skip++;
				}
			}

			$line = 0;
			// assign line number
			foreach (String::split($content_original, '~\n~') as $line_number => $content_line) {
				if (strpos($content_line, $comment_content) !== FALSE) {
					if ($skip > 0) {
						$skip--;
						continue;
					}
					$line = $line_number + 1;
					break;
				}
			}

			$todos[] = array(
				'line' => $line,
				'type' => String::lower($match['type']),
				'content' => $match['todo'],
				'link' => strtr(Debugger::$editor, array('%file' => urlencode($file->getRealPath()), '%line' => $line)),
				'file' => $file->getFilename(),
			);
		}

		usort($todos, callback($this, 'compareTodos'));
		
		return $todos;
	}
}
