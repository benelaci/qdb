<?php
/*    qdb - QueryDisburdener
          version 1.0.2

       "Extended" component


When using qdb without Composer, and you don't need the qdb constants,
you can calmly load this class instead of calling "qdb\load()".
Just be sure "Basic.php" is present in this directory.

*/


namespace qdb;

if (!class_exists('Basic')) {
	$file__Basic = __DIR__.'/Basic.php';
	if (file_exists($file__Basic))
		require $file__Basic;
	else
		die('<i>Extended</i> requires <i>Basic</i>.');
}

class Extended extends Basic
{
	protected $spheres = []; // the variables related to the current subquery level

	function __construct($db_connector) {
		parent::__construct($db_connector);

		// non-empty array means Basic is used by Extended
		$this->extended = [
			'level' => 0 // subquery level. 0 is the default outer query
		];
	}
	
	public function join() // args through func_get_args
	{
		$args = func_get_args();
		if (count($args)==2) {
			array_unshift($args, '');
		}
		list ($mode, $table_str, $on) = $args;
		
		$parts = explode(' ',$table_str);
		if (count($parts)==3 && strtoupper($parts[1])=='AS') {
			$parts[1] = $parts[2];
			unset ($parts[2]);
		}
		
		$joining_table = $this->_table_prefix.$parts[0];
		
		$joining_table_alias = count($parts)==2 ? $parts[1] : '';

		$bt = $this->backtick();

		$inviting_table_ref = $this->q['table_alias'] ?? $this->q['table'];

		$this->q['join'][] =
			($mode ? strtoupper($mode).' ' : '').' JOIN '.
			$bt.$joining_table.$bt .($joining_table_alias ? ' AS '.$joining_table_alias : '').
			' ON '.$on;
		
		return $this;
	}

	public function start_subquery($keyword) {
		// save $q to the sphere we're leaving
		$this->spheres[$this->extended['level']] = $this->q;
		
		// enter the sphere of the subquery
		$this->extended['level'] ++;
		
		// empty $q for the new subquery
		$this->purge();
		
		// the part of the sql to which the subquery sql will be assigned
		$this->q['opening_keyword'] = $keyword;

		return $this;
	}
	
	protected function finish_subquery()
	{
		// note the opening component of this sphere, because $q will be overwritten
		$opening_keyword = $this->q['opening_keyword'];

		// note the subquery alias, because $q will be overwritten
		$as = $this->q['as'];

		// retrieve the sql created in the subquery sphere and tab indent its lines
		$indent = $this->extended['level']-1;
		if ($opening_keyword=='columns') $indent++; // SELECT columns are one indent further in
		$t = str_repeat("\t", $indent);
		$subquery_sql = array_map(
			function($line) use ($t) {
				return $t."\t".$line;
			},
			$this->q['sql']
		);
		$subquery_sql_str = implode("\n", $subquery_sql);

		// leave the sphere
		$this->extended['level']--;
		
		// load the data of the sphere we're returning to into $q
		$this->q = $this->spheres[$this->extended['level']];

		// assign the subquery to whatever component that opened it
		$replace =
			" (\n".
				$subquery_sql_str.
				"\n".
			$t.")" . ($as ? " AS ".$as : '');

		$this->q[$opening_keyword] = str_replace('[sub]', $replace, $this->q[$opening_keyword]); // '[sub]' = SUB

		return $this;
	}

	public function as($alias) { // the one for aliasing subqueries
		$this->q['as'] = $alias;
		return $this;
	}

	public function select_distinct($preview_type=null) {
		$this->_select_distinct = true;
		$this->select($preview_type);
		$this->_select_distinct = false;
		return $this;
	}
	
	public function having($column, $value_str) {
		$bt = $this->backtick();
		echo'<pre>'; print_r($this->operator_and_value($value_str)); echo'</pre>';
		list($oper, $value) = $this->operator_and_value($value_str);

		$this->q['having'] = 'HAVING '.$bt.$column.$bt.' '.$oper.' '.$value;
		return $this;
	}
}