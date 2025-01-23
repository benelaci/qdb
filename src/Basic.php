<?php
/*    qdb - QueryDisburdener
          version 1.0.2

        "Basic" component


When using qdb without Composer, and you don't need the qdb constants,
you can calmly load this class instead of calling "qdb\load()".

*/


namespace qdb;

class Basic
{
	protected $extended = []; // has items if mode is Extended, is empty if it's Basic
	protected $db; // the database connentor from outside
	protected $q; // the sql script components the builder uses to concatenate the full sql script
	protected $sql; // the sql script
	protected $default_table; // if there is one, purge() sets $q['table'] to this
	protected $default_table_alias; // if there is one, purge() sets $q['table_alias'] to this
	protected $map=[]; // columns_except() need the list of all columns of the table, and saves the list into this
	
	// null:         preview() has not been called
	// PREVIEW_HTML: formatted HTML preview
	// PREVIEW_TEXT: plain text preview;
	protected $preview_type=null;
	
	protected $_table_prefix='';
	
	// if backticks() has been called, this is set to TRUE, and columns will be placed between backticks
	protected $_backticks=false;
	
	protected $default_backticks=false;

	
	// -------------
	// BUILD METHODS
	// -------------

	function __construct($db_connector) {
		$this->db = $db_connector;
		$this->purge(); // set initial empty values, but values still
	}

	public function table($table_str, $set_default=false)
	{
		// start subquery if name is SUB or '[sub]'
		if ($table_str == '[sub]' && $this->extended) {
			$this->q['table'] = $table_str;
			$this->start_subquery('table');
			return $this;
		}

		// table name optionally combined with alias
		$parts = explode(' ',$table_str);
		// drop "AS" from aliasing
		if (count($parts)==3 && strtoupper($parts[1])=='AS') {
			$parts[1] = $parts[2];
			unset ($parts[2]);
		}
		// there's no alias
		if (count($parts)==1)
			$table = $parts[0];
		// there's alias
		elseif (count($parts)==2)
			list ($table, $table_alias) = $parts;
		else
			$this->error(
				'Table name should be defined one of the following ways:<br>'.
				'- table_name<br>'.
				'- table_name alias<br>'.
				'- table_name AS alias<br><br>'.
				'If your script uses joins, the alias is necessary.'
			);

		// adding prefix
		$table = $this->_table_prefix.$table;

		if ($set_default===1) { // 1 = SET_DEFAULT
			$this->default_table = $table;
			if (isset($table_alias))
				$this->default_table_alias = $table_alias;
		}

		$this->q['table'] = $table;
		if (isset($table_alias))
			$this->q['table_alias'] = $table_alias;

		return $this;
	}
	
	protected function value($p) {
		list ($value, $escrule) = $this->value_and_escrule($p);
		
		if (is_bool($value)) {
			$ret = $value ? 1 : 0;
		}
		elseif (is_numeric($value)) {
			$ret = $value;
		}
		else {
			// full escaping, this is default
			if ($escrule===1) { // 1 = ESCAPE_FULL
				$ret = "'".$this->db->real_escape_string($value)."'";
			}
			// skip escaping
			elseif ($escrule===null) {
				$ret = $value;
			}
			// escape the string specified as the escape rule value
			else {
				$target = $escrule;
				$ret = str_replace($target, $this->db->real_escape_string($target), $value);
			}
		}

		return $ret;
	}
	
	public function columns($columns_raw)
	{
		if ($columns_raw=='*' || $columns_raw=='1') {
			$columns = [$columns_raw];
		}
		else {
			if (is_array($columns_raw)) {
				$columns = $columns_raw;
			}
			else {
				// step 1: Mask commas within parentheses (function argument delimiters)
				$masked_columns = preg_replace_callback(
					'/\(([^()]+)\)/', // match content inside parentheses
					function ($matches) {
						return '('. str_replace(', ', '##', $matches[1]) .')'; // replace ", " with "##" within parentheses
					},
					$columns_raw
				);
				
				// step 2: Explode by ", "
				$columns = explode(', ',$masked_columns);
				
				// step 3: Replace "##" back with ", " in each column
				$columns = array_map(
					function ($column) {
						return str_replace('##', ', ', $column);
					},
					$columns
				);
			}
			
			// adding ` to column names
			if ($this->_backticks) {
				foreach ($columns as &$column) {
					// if column name contains (), it needs special processing
					if (strpos($column, '(') !== false) {
						$column = preg_replace_callback(
							'/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', // look for the column name
							function ($matches) {
								$word = $matches[1];
								// if it's not an SQL keyword, add `
								if (preg_match('/[^A-Z_]/', $word)) {
									return '`'.$word.'`';
								}
								return $word;
							},
							$column
						);
					// otherwise just add `
					} else {
						if (preg_match('/[^A-Z_]/', $column)) {
							$column = '`'.$column.'`';
						}
					}
				}
			}
		}

		$this->q['columns'] = array_merge($this->q['columns'], $columns);

		if (in_array('[sub]', $columns)) { // '[sub]' = SUB
			$this->start_subquery('columns');
		}

		return $this;
	}

	public function columns_except($except_raw) {
		$t = $this->q['table'];
		if (!isset($this->map[$t])) {
			$this->map[$t] = [];
			$sql = 'SHOW COLUMNS FROM `'.$t.'`';
			$q = $this->db->query($sql);
			while ($a = $q->fetch_assoc()) {
				$this->map[$t][] = $a['Field'];
			}
		}
		$except = explode(', ', $except_raw);
		$columns = [];
		$bt = $this->backtick();
		foreach ($this->map[$t] as $map_item) {
			if (!in_array($map_item, $except)) {
				$columns[] = $bt.$map_item.$bt;
			}
		}
		$this->q['columns'] = array_merge($this->q['columns'], $columns);

		return $this;
	}
	
	protected function value_and_escrule($p) {
		// escape rule is specified
		if (is_array($p)) {
			list($value, $escrule) = $p;
		// escape rule is not specified, use default escaping
		}else{
			$value = $p;
			$escrule = 1; // 1 = ESCAPE_FULL
		}
		return array_merge([$value, $escrule], compact('value', 'escrule'));
	}

	protected function columns_and_values($p1, $p2, $p3)
	{
		$array = false;
		
		// columns and values passed as array.
		// can be leaved untouched.
		if (is_array($p1)) {
			$array = $p1;
		}
		// single column, its value and escape rule passed as arguments
		elseif ($p2!=='') {
			// escape rule is set
			if ($p3!=='')
				$array = [$p1 => [$p2, $p3]];
			// escape rule is not set
			else
				$array = [$p1 => $p2];
		}
		
		return $array;
	}
	
	public function values($p1, $p2='', $p3='')
	{
		$columns_and_values = $this->columns_and_values($p1, $p2, $p3);

		$this->q['columns'] = [];
		$this->q['values'] = [];

		if ($columns_and_values === false)
			$this->error(
				"{values()}: There's some bad input. Possibilities:<br><br>".
				"1. {column, value}<br>".
				"2. {column, [value, escape_rule]}<br>".
				"3. {[column => value, column => [value, escape_rule]]}<br>"
			);

		$bt = $this->backtick();
		foreach ($columns_and_values as $column => $value) {
			if (!$column) $this->error('{values()}: Input array is not an associative one. {[column => value]}');
			$this->q['columns'][] = $bt.$column.$bt;
			$this->q['values'][] = $this->value($value);
		}

		return $this;
	}
	
	public function where($p1, $p2='', $p3='', $p4='') // column, value, escrule, or
	{
		// OR is always the last argument, but $p3 is the place for escrule as well.
		if (strtoupper($p3)=='OR') {
			$p4 = $p3;
			$p3 = '';
		}
		
		$columns_and_values = $this->columns_and_values($p1, $p2, $p3);

		$where_word = $this->q['where'] ? '' : 'WHERE ';
		$where = '';
		$parenthesis = false;
		
		// if there are chained where()s
		if ($this->q['where']) {
			// if WHERE has contained AND or OR so far, put it between ()
			if (preg_match('/( AND )|( OR )/', $this->q['where'])) {
				echo $this->q['where'].'<br>';
				$where = preg_replace('/WHERE /', 'WHERE (', $this->q['where'], 1) . ')'; // usually empty but might be something already chained
			}else
				$where = $this->q['where'];
			// if current WHERE contains AND or OR, put it between ()
			if (count($columns_and_values)>1)
				$parenthesis = true;
		}

		$i=0;
		foreach ($columns_and_values as $column => $p)
		{
			if ($where)
				$where .= strtoupper($p4)=='OR' ? ' OR ' : ' AND ';
			
			if ($parenthesis && $i==0)
				$where .= '(';
			
			if (preg_match('/[=<>]/',$column) || preg_match('/ in$/',$column))
				$this->error('{where()}: Put {< > IN} things into the value, like this: {\'> 1\'}.');
			if (preg_match('/[^a-zA-Z0-9_.]/',$column)) $this->hack(884);

			list ($value_str, $escrule) = $this->value_and_escrule($p);
			
			list ($oper, $value) = $this->operator_and_value($value_str);

			if ($value=='[sub]' && $this->extended) { // '[sub]' = SUB
				$value_final = $value;
			}else{
				$value_final = $this->value([$value, $escrule]);
			}

			$bt = $this->backtick();
			$where .= $bt.$column.$bt.' '.$oper.' '.$value_final;

			if ($parenthesis && $i==count($columns_and_values)-1)
				$where .= ')';
			
			$i++;
		}
		$this->q['where'] = $where_word.$where;
		
		if ($value_final == '[sub]') { // '[sub]' = SUB
			$this->start_subquery('where');
		}

		return $this;
	}

	protected function operator_and_value($value_str) {
		if (
			($space_pos = strpos($value_str, ' ')) &&
			in_array(strtoupper(substr($value_str, 0, $space_pos)), ['<','<=','>=','>','IN','NOT IN','LIKE'])
		) {
			$oper = substr($value_str, 0, $space_pos);
			$value = substr($value_str, $space_pos+1);
		}else{
			$oper = '=';
			$value = $value_str;
		}
		return array_merge([$oper, $value], compact('oper','value'));
	}
	
	public function group_by($column) {
		$bt = $this->backtick();
		$this->q['group_by'] = 'GROUP BY '.$bt.$column.$bt;
		return $this;
	}

	public function order_by($order_by) {
		if ($this->backtick()) {
			$order_by = str_replace(['DESC', 'desc'], '##', $order_by);
			$order_by = preg_replace('/[a-zA-Z0-9_]+/', '`$0`', $order_by);
			$order_by = str_replace('##', 'DESC', $order_by);
		}
		$this->q['order_by'] = 'ORDER BY '.$order_by;
		return $this;
	}
	
	public function limit($value1, $value2=0) {
		if (!is_numeric($value1)) $this->hack(760);
		if (!is_numeric($value2)) $this->hack(986);
		$this->q['limit'] = 'LIMIT '.$value1.($value2 ? ','.$value2 : '');
		return $this;
	}
	
	// --------------
	// ACTION METHODS
	// --------------
	
	public function select($preview_type=null)
	{
		$this->handle_previewType($preview_type);

		$columns_strlen = 0;
		foreach ($this->q['columns'] as $column)
			$columns_strlen += strlen($column);
		$multiline = $columns_strlen>50;
		
		if ($this->q['columns'])
		{
			$last_index = count($this->q['columns'])-1;
			
			if ($multiline) {
				$columns_multiline = [];
				foreach ($this->q['columns'] as $i=>$column) {
					$s = "\t".$column;
					if ($i<$last_index) $s.= ',';
					$columns_multiline[] = $s;
				}
			}else{
				$columns_sameline = ' '.implode(', ',$this->q['columns']);
			}
		}
		else{
			$select = '*';
		}
		
		$bt = $this->backtick();
		$table_str = $bt.$this->q['table'].$bt . (isset($this->q['table_alias']) ? ' AS '.$this->q['table_alias'] : '');

		$distinct = ($this->_select_distinct ?? false) ? ' DISTINCT' : '';

		$this->q['sql'] = array_merge(
			['SELECT'.$distinct.($columns_sameline ?? '')],
			$columns_multiline ?? [],
			['FROM '.$table_str],
			$this->q['join'] ?? [],
			[
				$this->q['where'],
				$this->q['group_by'],
				$this->q['having'],
				$this->q['order_by'],
				$this->q['limit']
			]
		);

		$level = $this->extended['level'] ?? 0;

		if ($level===0) {
			// It's the outermost query
			$res = $this->finish('perform_query');
			$this->purge();
			return $res;
		}
		else {
			// It's a subquery
			$this->finish('finish_subquery');
			return $this;
		}
	}
	
	public function insert($preview_type=null) {
		$this->handle_previewType($preview_type);

		$bt = $this->backtick();
		$this->q['sql'] = [
			'INSERT INTO '.$bt.$this->q['table'].$bt,
			"\t (".implode(', ',$this->q['columns']).') ',
			'VALUES ('.implode(', ',$this->q['values']).')'
		];
		
		$this->finish();
		$this->purge();
	}

	public function update($preview_type=null) {
		$this->handle_previewType($preview_type);

		$bt = $this->backtick();
		$this->q['sql'] = ['UPDATE '.$bt.$this->q['table'].$bt.' SET '];
		
		foreach ($this->q['columns'] as $i=>$c) {
			$pair = $c.' = '.$this->q['values'][$i];
			$this->q['sql'][] .= "\t" . $pair . ($i < count($this->q['columns'])-1 ? ',' : '');
		}
		
		$this->q['sql'][] =
			$this->q['where'].
			$this->q['group_by'].
			$this->q['having'].
			$this->q['order_by'].
			$this->q['limit'];
		
		$this->finish();
		$this->purge();
	}
	
	public function delete($preview_type=null) {
		$this->handle_previewType($preview_type);

		$bt = $this->backtick();

		$this->q['sql'] = [
			'DELETE FROM '.$bt.$this->q['table'].$bt,
			$this->q['where'].
			$this->q['group_by'].
			$this->q['having'].
			$this->q['order_by'].
			$this->q['limit']
		];
		
		$this->finish();
		$this->purge();
	}
	
	protected function finish($action = 'perform_query')
	{
		// remove empty lines
		foreach ($this->q['sql'] as $i=>$line)
			if ($line=='')
				unset($this->q['sql'][$i]);

		// reindex array (because some lines have been removed from in-between, and implode doesn't care)
		$this->q['sql'] = array_values($this->q['sql']);

		if ($this->preview_type!==null)
			// echo preview
			$this->execute_preview();
		else{
			switch ($action) {
				case 'perform_query':
					// perform the query
					try{
						$sql = implode("\n", $this->q['sql']);
						$q = $this->db->query($sql);
					}catch (Exception $e){
						$trace = $e->getTrace();
						$this->error($e->getMessage(), $trace[2]);
					}
					return $q;

				case 'finish_subquery':
					$this->finish_subquery(); // a method of Extended
					return $this;
			}
		}
	}

	protected function purge() {
		$this->q = [
			'table' => $this->default_table,
			'columns' => [],
			'values' => '',
			'where' => '',
			'order_by' => '',
			'having' => '',
			'group_by' => '',
			'limit' => ''
		];
		
		if ($this->default_table_alias)
			$q['table_alias'] = $this->default_table_alias;
		
		$this->_backticks = $this->default_backticks;

		$this->preview_type = null;

		if ($this->extended) {
			// normally action methods create a new $sql,
			// but if Extended is starting a subquery by entering an another sphere,
			// it needs to start a new $sql
			$this->q['sql'] = ''; 

			$this->q['as'] = ''; // alias of the subquery

			$this->q['join'] = [];
		}
	}

	public function sql() {
		return $this->q['sql'];
	}

	
	// ----------------
	// SETTINGS METHODS
	// ----------------

	public function table_prefix($table_prefix='') {
		$this->_table_prefix= $table_prefix ? $table_prefix.'_' : '';
	}
	
	public function backticks($p=true) {
		if ($p===1) { // SET_DEFAULT
			$this->default_backticks = true;
			$this->_backticks = true;
		}
		else {
			$this->_backticks = $p;
		}
		return $this;
	}
	
	protected function backtick() {
		return $this->_backticks ? '`' : '';
	}

	// ----------------
	// FEEDBACK METHODS
	// ----------------

	public function preview($preview_type=0) { // called when preview_type is set by builder method. 0 = PREVIEW_HTML
		$this->preview_type = $preview_type;
		return $this;
	}

	private function handle_previewType($preview_type) { // called at the beginning of each action method
		if ($preview_type!==null) // if not null, $preview_type was supplied as argument to the action method
			$this->preview_type = $preview_type;
	}
	
	protected function execute_preview() {
		if ($this->preview_type === 0) { // 0 = PREVIEW_HTML
			$str = array_map(
				function ($s, $index) {
					if ($s=='') {
						unset($this->q['sql'][$index]);
						return;
					}
					$s = preg_replace('/[A-Z]{2,}/', 'word[$0]word', $s);
					$s = preg_replace('/[=<>\(\),]/', 'operator[$0]operator', $s);
					$s = preg_replace('/`[a-z_0-9.]+`/', 'column[$0]column', $s);
					$s = str_replace("\'", '{ESC_QUOTE}', $s);
					$s = preg_replace("/['][^']+[']/", 'string[$0]string', $s);
					$s = str_replace('{ESC_QUOTE}', "\'", $s);
					$s = preg_replace('/["][^"]+["]/', 'string[$0]string', $s);
					$s = preg_replace('/(?<![a-zA-Z])(\d+)(?![a-zA-Z])/', 'number[$1]number', $s);
					$s = str_replace('%', 'percent[%]percent', $s);

					foreach (['word','operator','column','string', 'number', 'percent'] as $name) {
						$s = str_replace($name.'[', "<$name>", $s);
						$s = str_replace(']'.$name, "</$name>", $s);
					}
					
					$s = '<code>'.$s.'</code>';

					$style = [
						'word {color: #a47; font-weight: bold}',
						'operator {color: #26a; font-weight: bold}',
						'column {color: #09b; font-style: italic}',
						'string, string * {color: #d90; font-weight: normal; font-style: normal}',
						'string percent {color: #fb1; font-weight: bold; font-style: normal}',
						'number {color: #590; font-weight: normal; font-style: normal}',
					];

					$s = str_replace("\t", '&nbsp; &nbsp; ', $s);
					
					// subquery lines were already imploded with \n in finish_subquery(),
					// because the SUB mark was replaced by them
					$s = str_replace("\n", '<br>', $s);

					$s = "<style>\n".implode("\n", $style)."\n</style>\n\n" . $s;
					return $s;
				}, 
				$this->q['sql'],
				array_keys($this->q['sql'])
			);
			$sql = implode('<br>', $str);
		}
		else {
			$sql = implode("\n", $this->q['sql']);
		}
		$this->db->close();
		exit($sql);
	}
	
	protected function error($message, $trace=null) {
		if ($trace) {
			$message = 'Caught exception: '.$message;
		}
		else {
			$message = str_replace('{', '<code style="background: #dde; padding: 1px 2px; color: #05c; font-weight: bold">', $message);
			$message = str_replace('}', '</code>', $message);
			$trace_chain = debug_backtrace();
			$trace = $trace_chain[1];
		}
		echo '<p style="font: 15px Arial">'.$message.'</p>';
		echo '<p style="font: 13px Tahoma; color: #187">&rarr; Line <b>'.$trace['line'].'</b> of <b>'.$trace['file'].'</b></p>';
		$this->db->close();
		exit;
	}

	protected function hack($callback) {
		$this->db->close();
		header('Content-Type: text/html; charset=utf-8');
		exit(
			"<h2>Shady sql query. &#x1F937;&#x200D;&#x2642;&#xFE0F; Program halted. &#x1F610;</h2>".
			"Thrown by QueryDisBurdener with callback number &thinsp;<big><b>$callback</b></big>"
		);
	}
}
