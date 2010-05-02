<?php
/**
 * Todo panel for Nette 1.0+. Displays all presenters and their required and optional parameters.
 *
 * @author Mikuláš Dítě
 * @license MIT
 */

/*
use Nette\Templates\
namespace Nette;
*/
class TodoPanel extends Object implements IDebugPanel
{

	/** Stores  */
	private $todo = array();
	


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



	private function getTodo()
	{
		if (empty($this->todo)) {
			$this->todo = $this->generateTodo();
		}
		return $this->todo;
	}



	private function getCount()
	{
		$count = 0;
		foreach ($this->getTodo() as $file) {
			$count += count($file);
		}
		return $count;
	}



	private function generateTodo()
	{
		SafeStream::register();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_DIR));
		$todo = array();
		foreach ($iterator as $path => $match) {
			//$fileinfo = pathinfo($path);
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
				$todo[$relative] = $m['todo'];
			}
		}
		return $todo;
	}
}