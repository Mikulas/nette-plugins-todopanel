<?php
/**
 * Annotations Todo panel for Nette 1.0+. Displays all todos in all files given.
 *
 * @author Mikuláš Dítě, Peter Ped Helcmanovsky
 * @license MIT
 */

namespace Panel;
use \Nette\Debug;
use \Nette\IDebugPanel;
use \Nette\IO\SafeStream;
use \Nette\Object;
use \Nette\Templates\Template;
use \Nette\Templates\LatteFilter;
use \InvalidStateException;
use \DirectoryNotFoundException;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class TodoPanel extends Object implements IDebugPanel
{

	/** @var array|mixed stores found todos */
	private $items = array();

	/** @var array any path or file containing one of the patterns to skip */
	private $ignoreMask = array();
	
	/** @var string - regexp prepared from ignoreMask */
	private $ignorePCRE;

	/** @var array */
	protected $scanDirs = array();




	/** @var array catched patterns for todo comments */
	public $todoMask = array('TODO', 'FIXME', 'FIX ME', 'FIXED', 'FIX', 'TO DO', 'PENDING', 'XXX');



	/**
	 * @param string|path $basedir
	 * @param array $ignoreMask
	 */
	public function __construct($basedir = APP_DIR, $ignoreMask = array( '.svn', 'sessions', 'temp', 'log' ))
	{
		$this->scanDirs = array(realpath($basedir));
		$this->setSkipPatterns($ignoreMask);
	}



	/**
	 * Renders HTML code for custom tab.
	 * IDebugPanel
	 * @return void
	 */
	public function getTab()
	{
		$count = 0;
		foreach ($this->getTodo() as $file) {
			$count += count($file);
		}

		return '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAPnRFWHRDb21tZW50AENyZWF0ZWQgd2l0aCBUaGUgR0lNUAoKKGMpIDIwMDMgSmFrdWIgJ2ppbW1hYycgU3RlaW5lcicz71gAAAGtSURBVHjarZMxSBthFMd/d/feqVjiIAoFcQqVFoogjUSJdDBYaECH4Nq1OLiU2C52KKGi4uTgEhDcFRxEyeDSsQdKhdKSEhAFseEGCxFpa+7r0Fi8axM7+OC/vf/ve+//+ODftQKYiFa4ofLXDb7v/1GpVIrC8lcmuQaYLSzMcPDlhM2dXTzPo1KpAFAul7nb3clo6hEDD/t48WZ5FngdBQCwWXzH1naRarVKLBYDIB6PY9s2hUKBs69Hof4QwBjIZrOkUqlQk2VZAORyOYobq40BYMhknjI+PoGqIFKXKuoIjgrF9aYAEHERdVDRullQR2ibew73+jHGhPrt8PugKrC2RPv8FHv7e3jvPdpeTWKdHuP0D/11uhAgCMB1XXrK+7SffyORSPB4fRHr4hx7+i1uMk0QNJvAGEQVv/c+Vu2Sjpks+vM79vQcLck0qtp8hVoQoKp4gxP4vQ+wapccjEzSOpxGXEVFCCKAcIjG4Kow9mQMzWQQEZKiqApO/SIYGgMCA0svn/H5sIKpZxJ1xO60NgZ8+viB6sUPero6+J2VITLx/3+mG5TntuoX7nmiqfg2Y6EAAAAASUVORK5CYII=">' .
			'Todo (' . $count . ')';
	}



	/**
	 * Renders HTML code for custom panel.
	 * IDebugPanel
	 * @return void
	 */
	public function getPanel()
	{
		ob_start();
		$template = new Template(dirname(__FILE__) . '/bar.todo.panel.phtml');
		$template->registerFilter(new LatteFilter());
		$template->todos = $this->getTodo();
		$template->render();
		return $cache['output'] = ob_get_clean();
	}



	/**
	 * Returns panel ID.
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
	 * @uses SafeStream
	 */
	protected function generateTodo()
	{
		if (count($this->todoMask) === 0) {
			throw new InvalidStateException('No todo mask specified for TodoPanel.');
		}
		
		@SafeStream::register(); //intentionally @ (prevents multiple registration warning)
		$items = array();
		foreach ($this->scanDirs as $dir) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
			foreach ($iterator as $path => $match) {
				if (preg_match($this->ignorePCRE, $path)) continue;

				$phpBlock = FALSE;
				$latteBlock = FALSE;
				$htmlBlock = FALSE;
				
				foreach(file("safe://" . $path) as $n => $line) {
					if ($comment = strpos($line, '//') !== FALSE || $shell = strpos($line, '#') !== FALSE) {
						if (preg_match('~[*@/\ ](' . implode('|', $this->todoMask) . ')(\ (?P<todo>.*?))?( |\t)*(\t|\r|\n)~mixs', substr($line, $comment + $shell), $found)) {
							$todo = trim($found['todo']);
							if (!empty($todo)) {
								$items[$path][$n] = $todo;
							} else {
								$items[$path][$n] = trim($line);
							}
						}
						continue;
					}

					if (!$phpBlock && strpos($line, '/*') !== FALSE) {
						$phpBlock = TRUE;
					}
					elseif (!$latteBlock && strpos($line, '{*') !== FALSE) {
						$latteBlock = TRUE;
					}
					elseif (!$htmlBlock && strpos($line, '<!--') !== FALSE) {
						$htmlBlock = TRUE;
					}

					if ($phpBlock || $latteBlock || $htmlBlock) {
						if (preg_match('~(\*|\ |@)(' . implode('|', $this->todoMask) . ')\ (?P<todo>.*?)(\*/|\*}|-->|\r|\n)~mixs', $line, $found)) {
							$items[$path][$n] = trim($found['todo']);
						}
						
						if (strpos($line, '*/') !== FALSE) {
							$phpBlock = FALSE;
						}
						if (strpos($line, '*}') !== FALSE) {
							$latteBlock = FALSE;
						}
						if (strpos($line, '-->') !== FALSE) {
							$htmlBlock = FALSE;
						}
					}
				}
			}
		}
		return $items;
	}



	/**
	 * Add directory (or directories) to list.
	 * @param  string|array
	 * @return void
	 * @throws DirectoryNotFoundException if path is not found
	 */
	public function addDirectory($path)
	{
		foreach ((array) $path as $val) {
			$real = realpath($val);
			if ($real === FALSE) {
				throw new DirectoryNotFoundException("Directory '$val' not found.");
			}
			$this->scanDirs[] = $real;
		}
	}


	
	/**
	 * Set string patterns to ignore files which contain some pattern in full path.
	 * example: $todopanel->setSkipPatterns( array('/.git', 'app/sessions/') );
	 * @param array $ignoreMask
	 */
	public function setSkipPatterns($ignoreMask)
	{
		$this->ignoreMask = $ignoreMask;		//store original skip patterns (for debug purposes?)
		//prepare regexp string with correctly quoted PCRE control characters and both types of slashes
		$pattterns = array_merge( str_replace( '\\', '/', $ignoreMask ), str_replace( '/', '\\', $ignoreMask ) );
		foreach( $pattterns as $k => $v ) $pattterns[$k] = preg_quote( $v, '/' );
		$this->ignorePCRE = '~(' . implode('|', $pattterns) . ')~';
	}
}
