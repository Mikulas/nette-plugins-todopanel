<?php
namespace Panel;
use \Nette\Object;
use \Nette\Debug;
use \Panel\TodoPanel;
use \ReflectionMethod;

class TodoPanelTest extends TodoPanel
{
	public function run()
	{
		$this->scanDirs = array(dirname(__FILE__) . '\resource');
		$res = $this->generateTodo();

		$output = '';
		foreach ($res as $file => $items) {
			$pathinfo = pathinfo($file);
			$output .= $pathinfo['basename'] . ':';
			$output .= "\n";
			foreach ($items as $line => $todo) {
				$output .= "\t" . $line . ' => ' . $todo;
				$output .= "\n";
			}
		}

		$handle = fopen(dirname(__FILE__) . '\output.raw', 'w');
		fwrite($handle, $output);
		fclose($handle);

		$expected = dirname(__FILE__) . '\expected.raw';
		$output = dirname(__FILE__) . '\output.raw';

		exec('git diff --unified=0 --text --no-prefix ' . $expected . ' ' . $output, $diff);
		if (count($diff) > 1) {
			echo '<h1 style="background-color: red; color: white;">TodoPanel test failed</h1>';
			Debug::dump($diff);
		}
	}
}
