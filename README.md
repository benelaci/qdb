# qdb · Query Disburdener

A very basic MySQLi query builder for PHP, suitable for simple applications that use low-level database management. Its sole purpose is building clean queries via chained methods.

It simply builds an sql script and executes the `mysqli_query()` function. That means, it doesn't create a result object, like query builders tipically do.

The components for simple and more complex queries are separated, further enhancing the lightweight nature.

# 🟧 Website / Contact

This README is a slightly short-spoken documentation. For assistance during use, I recommend the full documentation, available on the website:&thinsp;
**https://blapp.hu/qdb**

You can request new features or send feedback at:&thinsp; **bl![|](https://i.ibb.co/7WLcqb3/ch1.gif)blapp![|](https://i.ibb.co/R45zkLX/ch2.gif)hu**  
Please mention whether your request requires immediate action.

----
# 🟩 Installation

## Composer

```bash
composer require benelaci/qdb
```

## Manual Install

Inside the current release, download &thinsp;__qdb-*[version]*\_manual-install.zip__

# 🟦 Initialization

The tool needs a MySQLi database connector object as it performs `mysqli_query()` in object-oriented style.

Choose between the two tool variants according to the complexity of the queries you'll need. There's **_Basic_** and there's **_Extended_**.

### With Composer

The classes are not autoloaded, use `qdb_load()` instead to conviniently load the required variant:

#### Basic variant

```php
qdb_load('basic');
$qdb = new qdb\Basic($mysqli_connector);
```

#### Extended variant

```php
qdb_load('extended');
$qdb = new qdb\Extended($mysqli_connector);
```

### Without Composer

#### Basic variant

```php
require 'path/to/qdb/Basic.php';
$qdb = new qdb\Basic($mysqli_connector);
```

#### Extended variant

```php
require 'path/to/qdb/Extended.php';
$qdb = new qdb\Extended($mysqli_connector);
```

## Setting the initial values

```php
$qdb->table('[most_used_table]', QDB_SET_DEFAULT);
$qdb->table_prefix('[table_prefix]');
```

# 🟦 Examples

### Select

```php
$result = $qdb
	->table('things')
	->columns('name, description')
	->where('type', 3)
	->order_by('date DESC, pos')
	->limit(20)
	->select();
```

```php
$result = $qdb
	->table('items')
	->columns('count(id) c, group_id')
	->group_by('group_id')
	->select();
```

Return value is a MySQLi result set.

### Insert

```php
$qdb->table('people')
	->values([
		'name' => $_POST['name'],
		'email' => $_POST['email'],
		'ip' => [$_SERVER['REMOTE_ADDR'], null],
	])
	->insert();
```

### Update
```php
$qdb->table('objects')
	->values([
		'color' => $_POST['color'],
		'shape' => $_POST['shape'],
	])
	->where('id', $_GET['id'])
	->update();
```

### Delete
```php
$qdb->table('records')
	->where('to_delete', 1, null)
	->delete();
```


# 🟦 Main Methods

The SQL statements `select()`, `insert()`, `update()`, `delete()`, `select_distinct()`, are moved to the end of the method chain, as they are the *action methods* that perform the query.
The table name, the column names and the values are put into different methods from these: `table()`, `columns()` and `values()`.

## table()

`table()` is the overall method for naming the table for any type of query.

```php
$result = $qdb
	->table('items')
	->columns('id, name')
	->select();
```

You can also set a default table, which will be used if there's no `table()` method during the query building,

```php
$qdb->table('items', QDB_SET_DEFAULT)
```

or

```php
$qdb->table('items', 1)
```

## columns() and values()

These are the overall methods that reference the contents of tables.

### Single value example

```php
->values('value', $value)
```

### Multiple values example

```php
->where([
	'value1' => $value1,
	'value2' => $value2,
])
```

## Multiple where()s, AND, OR

### AND

By default, WHERE clauses are connected with AND.

### OR

For OR add an `'OR'` or `'or'` as last argument to the where() that connects with OR.

```php
$result = $qdb
	->columns('*')
	->where('value1', 'xxx')
	->where('value2', 'yyy', 'or')
	->select();
```

### Putting WHERE clauses in parentheses

When you add three where() methods, **the first two will be put in parentheses**, the third one won't.

```php
$result = $qdb
	->columns('*')
	->where('value1', 'xxx')
	->where('value2', 'yyy', 'or')
	->where('value3', 'zzz')
	->select();
```

## Escaping

An optional extra argument is the escape rule.

| value           | meaning                                           |
|-----------------|---------------------------------------------------|
| *not specified* | the whole value goes through escaping             |
| null            | there's no escaping                               |
| *string*        | the given string will be escaped inside the value |

In case of single value, the escape rule is the optional third argument.  
In case of multiple values, if escape rule is needed, the array item becomes a two-item subarray containing the value and the escape rule.

### Single value

```php
->values('completed', 1, null)
```

### Multiple values

```php
->values('value', [
	'comment' => $_POST['comment'],
	'completed' => [1, null],
])
```

### String as escape rule

if the value is a combination of a static string and a variable, pass the variable as escape rule.  
This way only the variable gets escaped.

```php
->values('time_elapsed', 'ADDTIME(time_elapsed, "'.$time.'")', $time)
```

### Escaping multiple where()s

Multiple where()s use single values, where OR is the **last** argument. But escaping is an extra argument too.
If you need both an escape rule and an OR connection, add the escape rule as third argument and 'OR' as last one.

```php
$result = $qdb
	->columns('*')
	->where('value1', 'no escape 1', null)
	->where('value2', 'no escape 2', null, 'or')
	->select();
```

## WHERE, HAVING operators other then "="

```php
->having('count(id)', '> 5')
```

```php
->where('id', 'IN (1,2,3,4)')
```

# 🟦 Preview

Preview mode halts the php script and echoes the produced query.  
There are two ways to use preview mode:

## Usage 1 – As method


```php
$result = $qdb
	->columns('*')
	->order_by('pos')
	->preview()
	->select();
```
It should be placed somewhere before the *action method*.

By default, `preview()` produces formatted HTML with syntax highlighting.  
The formatting can be disabled by adding a `false` or a `QDB_PREVIEW_TEXT` argument.

### Echo formatted HTML

```php
->preview()
```

### Echo plaintext

```php
->preview(false)
```

```php
->preview(QDB_PREVIEW_TEXT)
```

## Usage 2 – As argument

Give the argument to the *action method*.

### Echo formatted HTML

```php
->select(0);
```

```php
->select(QDB_PREVIEW_HTML);
```

### Echo plaintext

```php
->select(false);
```

```php
->select(QDB_PREVIEW_TEXT);
```

# 🟦 Other methods

## columns_except()

Selects all columns in the table except the ones given.

```php
$result = $qdb
	->columns_except('id, pos')
	->columns('SUBTIME(time_estimate, time_elapsed) AS time_left')
	->where('id', $id)
	->select();
```

## backticks()

Forces the use of `` ` `` marks around column names.

```php
$result = $qdb
	->backticks()
	->columns('id, name')
	->select();
```

You can set a default mode. Backtick forcing is off by default.

```php
$qdb->backticks(QDB_SET_DEFAULT)
````

or


```php
$qdb->backticks(1)
```

# 🟦 Extended variant

The following features are only included in the Extended variant.

## join()

The first argument is the type of join if it's needed. In case of a single `JOIN` (which equals `INNER JOIN`), this argument can be skipped.

```php
$result = $qdb
	->table('users u')
	->columns('u.name, o.date, p.name')
	->join('orders o', 'u.id = o.user_id')
	->join('left', 'products p', 'o.product_id = p.id')
	->select();
```


## Subqueries

Put a `QDB_SUB` constant or a `[sub]` as text into the value, where the subquery begins. From then on, the task of each method is changed to build the subquery until the action method (`select()` or `select_distinct()`) finalizes it. In this case, the action method does not fire the whole query, we return the upper scope instead.

```php
$qdb->table('paintings', QDB_SET_DEFAULT);

$result = $qdb
	->columns('name, price')
	->where('price', '> [sub]')
		->columns('AVG(price)')
		->select()
	->select();
```

## Subquery AS

If there's an alias for the subquery, define it with `as()`.

```php
$q = $qdb
	->columns([
		'd.name AS doctor_name',
		'p.name AS patient_name',
		'best_diagnosis.diagnosis_score'
	])
	->table(QDB_SUB)
		->columns('diagnosis_score')
		->table('examinations')
		->where('diagnosis_score', QDB_SUB)
			->table('examinations')
			->columns('MAX(diagnosis_score)')
			->select()
		->as('best_diagnosis')
		->select()
	->join('doctors d', 'd.id = best_diagnosis.doctor_id')
	->join('patients p', 'p.id = best_diagnosis.patient_id')
	->select();
```

## SELECT DISTINCT

```php
$result = $qdb
	->table('users')
	->columns('country')
	->select_distinct();
```

## HAVING

```php
$result = $qdb
	->table('items')
	->columns('count(id) c, group_id')
	->group_by('group_id')
	->having('c', '>= 10')
	->select();
```
