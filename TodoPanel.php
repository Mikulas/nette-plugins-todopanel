<?php
/**
 * Todo panel for Nette 1.0+. Displays all presenters and their required and optional parameters.
 *
 * @author Mikuláš Dítě
 * @license MIT
 */

class TodoPanel extends Object implements IDebugPanel
{

	const HIGHLIGHT = TRUE;



	/** stores generated todos in one instance */
	private $todo = array();



	/** list of highlighted words, does not affect todo getter itself */
	private $keywords = array('add', 'fix', 'improve', 'remove', 'delete');

	/** highlight style */
	private $highlight_begin = '<span style="font-weight: bold;">';
	private $highlight_end = '</span>';
	


    	/**
	 * Renders HTML code for custom tab.
	 * @return void
	 */
	function getTab()
	{
		return 'Todo (' . $this->getCount() . ')';
	}



	/**
	 * Renders HTML code for custom panel.
	 * @return void
	 */
	function getPanel()
	{
		ob_start();
		$template = new Template(dirname(__FILE__) . '/bar.todo.panel.phtml');
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
		SafeStream::register();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_DIR));
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
				if (self::HIGHLIGHT) {
					foreach ($m['todo'] as $k => $t) {
						$m['todo'][$k] = $this->highlight($t);
					}
				}
				$todo[$relative] = $m['todo'];
			}
		}
		return $todo;
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