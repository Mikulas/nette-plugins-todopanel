<?php
/**
 * Annotations Todo panel for Nette 1.0+. Displays all todos in APP_DIR folder
 *
 * @author Mikuláš Dítě
 * @license MIT
 */

class TodoPanel extends Object implements IDebugPanel
{

	/**
	 * stores generated todos in one instance
	 * @var array|mixed
	 */
	private $todo = array();

	/** @var bool */
	public $highlight = TRUE;

	/**
	 * list of highlighted words, does not affect todo getter itself
	 * @var array|mixed
	 */
	public $keywords = array('add', 'fix', 'improve', 'remove', 'delete');

	/**
	 * highlight style
	 * @var string
	 */
	public $highlight_begin = '<span style="font-weight: bold;">';

	/** @var string */
	public $highlight_end = '</span>';

	/** @var array */
	public $scanDirs;
	


    	/**
	 * Renders HTML code for custom tab.
	 * @return void
	 */
	function getTab()
	{
		return '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAPnRFWHRDb21tZW50AENyZWF0ZWQgd2l0aCBUaGUgR0lNUAoKKGMpIDIwMDMgSmFrdWIgJ2ppbW1hYycgU3RlaW5lcicz71gAAAGtSURBVHjarZMxSBthFMd/d/feqVjiIAoFcQqVFoogjUSJdDBYaECH4Nq1OLiU2C52KKGi4uTgEhDcFRxEyeDSsQdKhdKSEhAFseEGCxFpa+7r0Fi8axM7+OC/vf/ve+//+ODftQKYiFa4ofLXDb7v/1GpVIrC8lcmuQaYLSzMcPDlhM2dXTzPo1KpAFAul7nb3clo6hEDD/t48WZ5FngdBQCwWXzH1naRarVKLBYDIB6PY9s2hUKBs69Hof4QwBjIZrOkUqlQk2VZAORyOYobq40BYMhknjI+PoGqIFKXKuoIjgrF9aYAEHERdVDRullQR2ibew73+jHGhPrt8PugKrC2RPv8FHv7e3jvPdpeTWKdHuP0D/11uhAgCMB1XXrK+7SffyORSPB4fRHr4hx7+i1uMk0QNJvAGEQVv/c+Vu2Sjpks+vM79vQcLck0qtp8hVoQoKp4gxP4vQ+wapccjEzSOpxGXEVFCCKAcIjG4Kow9mQMzWQQEZKiqApO/SIYGgMCA0svn/H5sIKpZxJ1xO60NgZ8+viB6sUPero6+J2VITLx/3+mG5TntuoX7nmiqfg2Y6EAAAAASUVORK5CYII=">' .
			'Todo (' . $this->getCount() . ')';
	}



	/**
	 * Renders HTML code for custom panel.
	 * @return void
	 */
	function getPanel()
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
	 * @return string
	 */
	function getId()
	{
		return __CLASS__;
	}



	/**
	 * Registeres panel to Debug bar
	 */
	static function register()
	{
		Debug::addPanel(new self);
	}



	/**
	 * Wrapper for generateTodo, performace booster in one instance
	 */
	private function getTodo()
	{
		if (empty($this->todo)) {
			$this->todo = $this->generateTodo();
		}
		return $this->todo;
	}



	/**
	 * Returns count of all second level elements
	 */
	private function getCount()
	{
		$count = 0;
		foreach ($this->getTodo() as $file) {
			$count += count($file);
		}
		return $count;
	}



	/**
	 * Returns array in format $filename => array($todos)
	 * @uses SafeStream
	 */
	private function generateTodo()
	{
		@SafeStream::register(); //intentionally @ (prevents multiple registration warning)
		if (empty($this->scanDirs)) {
			$this->scanDirs[] = APP_DIR;
		}
		foreach ($this->scanDirs as $dir) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
			$todo = array();
			foreach ($iterator as $path => $match) {
				$relative = str_replace(realpath(APP_DIR), '', realpath($path));

				$handle = fopen("safe://" . $path, 'r');
				if (!$handle) {
					throw new InvalidStateException('File not readable, you should set proper priviledges to \'' . $relative . '\'');
				}

				$res = '';
				while(!feof($handle)) {
					$res .= fread($handle, filesize($path));
				}
				fclose($handle);
				preg_match_all('~/(/|\*{1,2})( |\t|\n)*(?P<type>@?(TODO|FIXME|FIX ME|FIX|TO DO|PENDING))( |\t|\n)*(?P<todo>.*?)( |\t|\n)*(\*/|(\r)?\n)~i', $res, $m);
				if (isset($m['todo']) && !empty($m['todo'])) {
					if ($this->highlight) {
						foreach ($m['todo'] as $k => $t) {
							$m['todo'][$k] = $this->highlight($t);
						}
					}
					$todo[$relative] = $m['todo'];
				}
			}
		}
		return $todo;
	}



	/**
	 * Add directory (or directories) to list.
	 * @param  string|array
	 * @return void
	 * @throws \DirectoryNotFoundException if path is not found
	 */
	public function addDirectory($path)
	{
		foreach ((array) $path as $val) {
			$real = realpath($val);
			if ($real === FALSE) {
				throw new /*\*/DirectoryNotFoundException("Directory '$val' not found.");
			}
			$this->scanDirs[] = $real;
		}
	}



	/**
	 * Highlights specified words in given string
	 * @param string $todo
	 * @return string
	 */
	private function highlight($todo)
	{
		foreach ($this->keywords as $kw) {
			$todo = str_replace($kw, $this->highlight_begin . $kw . $this->highlight_end, $todo);
		}
		return $todo;
	}
}