<?php
/**
 * MySQL database driver class for eStats
 * @author Emdek <http://emdek.pl>
 * @version 4.0.02
 */

class EstatsDriverMysql extends EstatsDriver
{

/**
 * Returns filed name string
 * @param string Field
 * @return string
 */

	private function fieldString($field)
	{
		if (preg_match('#^.+\.#', $field) > 0)
		{
			$position = strpos($field, '.');
			$table = substr($field, 0, $position);
			$field = substr($field, ($position + 1));

			return '`'.$table.'`.'.(($field == '*')?'*':'`'.$field.'`');
		}
		else
		{
			return '`'.$field.'`';
		}
	}

/**
 * Returns operator string
 * @param integer Operator
 * @return string
 */

	private function operatorString($operator)
	{
		if ($operator == self::OPERATOR_NOT)
		{
			return 'NOT';
		}
		else
		{
			$not = ($operator & self::OPERATOR_NOT);
			$operator = ($operator & ~self::OPERATOR_NOT);

			switch ($operator)
			{
				case self::OPERATOR_AND:
					return 'AND';
				case self::OPERATOR_OR:
					return 'OR';
				case self::OPERATOR_EQUAL:
					return ($not?'!':'').'=';
				case self::OPERATOR_REGEXP:
					return 'REGEXP';
				case self::OPERATOR_LIKE:
					return ($not?'NOT ':'').'LIKE';
				case self::OPERATOR_GREATER:
					return '>';
				case self::OPERATOR_GREATEROREQUAL:
					return '>=';
				case self::OPERATOR_LESS:
					return '<';
				case self::OPERATOR_LESSOREQUAL:
					return '<=';
				case self::OPERATOR_ISNULL:
					return 'IS '.($not?'NOT ':'').'NULL';
				case self::OPERATOR_PLUS:
					return '+';
				case self::OPERATOR_MINUS:
					return '-';
				case self::OPERATOR_INCREASE:
					return '+ 1';
				case self::OPERATOR_DECREASE:
					return '- 1';
				case self::OPERATOR_MULTIPLICATION:
					return '*';
				case self::OPERATOR_DIVISION:
					return '/';
				case self::OPERATOR_GROUPING_START:
					return '(';
				case self::OPERATOR_GROUPING_END:
					return ')';
				default:
					return '';
			}
		}
	}

/**
 * Returns element string
 * @param integer Element
 * @param array Data
 * @return string
 */

	private function elementString($element, $data)
	{
		switch ($element)
		{
			case self::ELEMENT_FIELD:
				return $this->fieldString($data);
			case self::ELEMENT_VALUE:
				return $this->PDO->quote($data);
			case self::ELEMENT_FUNCTION:
				if ($data[0] == self::FUNCTION_COUNT)
				{
					return 'COUNT('.($data[1]?$this->fieldString($data[1]):'*').')';
				}
				else if ($data[0] == self::FUNCTION_DATETIME)
				{
					return 'DATE_FORMAT('.$this->fieldString($data[1][0]).', '.$this->PDO->quote(strtr($data[1][1], array('%M' => '%i', '%W' => '%u'))).')';
				}
				else
				{
					if (is_array($data[1]))
					{
						$data[1] = $this->elementString($data[1][0], $data[1][1]);
					}
					else
					{
						$data[1] = $this->fieldString($data[1]);
					}

					switch ($data[0])
					{
						case self::FUNCTION_SUM:
							return 'SUM('.$data[1].')';
						case self::FUNCTION_MIN:
							return 'MIN('.$data[1].')';
						case self::FUNCTION_MAX:
							return 'MAX('.$data[1].')';
						case self::FUNCTION_AVG:
							return 'AVG('.$data[1].')';
						case self::FUNCTION_TIMESTAMP:
							return 'UNIX_TIMESTAMP('.$data[1].')';
						default:
							return '';
					}
				}
			case self::ELEMENT_OPERATION:
				if ($data[1] & self::OPERATOR_BETWEEN)
				{
					return (is_array($data[0])?$this->elementString($data[0][0], $data[0][1]):$this->PDO->quote($data[2])).' '.(($data[1] & self::OPERATOR_NOT)?'NOT ':'').'BETWEEN '.$this->fieldString($data[2]).' AND '.$this->fieldString($data[3]);
				}
				else if ($data[1] & self::OPERATOR_IN)
				{
					$items = array();

					for ($i = 0, $c = count($data[2]); $i < $c; ++$i)
					{
						$items[] = $this->PDO->quote($data[2][$i]);
					}

					return $this->fieldString($data[0]).' '.(($data[1] & self::OPERATOR_NOT)?'NOT ':'').'IN('.implode(', ', $items).')';
				}
				else
				{
					return (is_array($data[0])?$this->elementString($data[0][0], $data[0][1]):$this->fieldString($data[0])).' '.$this->operatorString($data[1]).(isset($data[2])?' '.(is_array($data[2])?$this->elementString($data[2][0], $data[2][1]):$this->PDO->quote($data[2])):'');
				}
			case self::ELEMENT_EXPRESSION:
				$string = '';

				for ($i = 0, $c = count($data); $i < $c; ++$i)
				{
					if (is_array($data[$i]))
					{
						$string.= $this->elementString($data[$i][0], $data[$i][1]);
					}
					else if (is_int($data[$i]))
					{
						if ($data[$i] == self::OPERATOR_GROUPING_START || $data[$i] == self::OPERATOR_GROUPING_END)
						{
							$string.= $this->operatorString($data[$i]);
						}
						else
						{
							$string.= ' '.$this->operatorString($data[$i]).' ';
						}
					}
					else
					{
						$string.= $this->fieldString($data[$i]);
					}
				}

				return $string;
			case self::ELEMENT_CONCATENATION:
				$parts = array();

				for ($i = 0, $c = count($data); $i < $c; ++$i)
				{
					if (is_array($data[$i]))
					{
						$parts[] = $this->elementString($data[$i][0], $data[$i][1]);
					}
					else
					{
						$parts[] = $this->fieldString($data[$i]);
					}
				}

				return 'CONCAT('.implode(', ', $parts).')';
			case self::ELEMENT_CASE:
				$parts = array();

				for ($i = 0, $c = count($data); $i < $c; ++$i)
				{
					if (isset($data[$i][1]))
					{
						$parts[] = 'WHEN '.(is_array($data[$i][0])?$this->elementString($data[$i][0][0], $data[$i][0][1]):$this->fieldString($data[$i][0])).' THEN '.(is_array($data[$i][1])?$this->elementString($data[$i][1][0], $data[$i][1][1]):$this->fieldString($data[$i][1]));
					}
					else
					{
						$parts[] = 'ELSE '.(is_array($data[$i][0])?$this->elementString($data[$i][0][0], $data[$i][0][1]):$this->fieldString($data[$i][0]));
					}
				}

				return 'CASE '.implode(' ', $parts).' END';
			CASE self::ELEMENT_SUBQUERY:
				return ('('.self::selectData($data[0], (isset($data[1])?$data[1]:NULL), (isset($data[2])?$data[2]:NULL), (isset($data[3])?$data[3]:0), (isset($data[4])?$data[4]:0), (isset($data[5])?$data[5]:NULL), (isset($data[6])?$data[6]:NULL), (isset($data[7])?$data[7]:NULL), (isset($data[8])?$data[8]:FALSE), self::RETURN_QUERY).')');
			default:
				return '';
		}
	}

/**
 * Returns TRUE if driver is available
 * @return boolean
 */

	public function isAvailable()
	{
		return in_array('mysql', PDO::getAvailableDrivers());
	}

/**
 * Generates connection string
 * @param array Parameters
 * @return string
 */

	public function connectionString($parameters)
	{
		return 'mysql:host='.$parameters['DatabaseHost'].($parameters['DatabasePort']?';port='.$parameters['DatabasePort']:'').';dbname='.$parameters['DatabaseName'];
	}

/**
 * Returns option value
 * @param string Option
 * @return string
 */

	public function option($option)
	{
		if (!$this->Information || count($this->Information) < 2)
		{
			$information =  parse_ini_file(dirname(__FILE__).'/plugin.ini', TRUE);
			$this->Information = &$information['Information'];
		}

		return (isset($this->Information[$option])?$this->Information[$option]:'');
	}

/**
 * Connects to the database
 * @param string Connection
 * @param string User
 * @param string Password
 * @param string Prefix
 * @param boolean Persistent
 * @return boolean
 */

	public function connect($connection, $user, $password, $prefix = '', $persistent = FALSE)
	{
		if (parent::connect($connection, $user, $password, $prefix, $persistent))
		{
			$this->Information['DatabaseVersion'] = $this->PDO->getAttribute(PDO::ATTR_SERVER_VERSION);
			$this->PDO->query('SET NAMES \'utf8\'');
		}

		return $this->Connected;
	}

/**
 * Creates database table
 * @param string Table
 * @param array Atrributes
 * @param boolean Replace
 * @return boolean
 */

	public function createTable($table, $attributes, $replace = FALSE)
	{
		$parts = $primaryKeys = $foreignKeys = $indexKeys = $constraints = array();

		if ($this->tableExists($table))
		{
			if ($replace)
			{
				deleteTable($table);
			}
			else
			{
				return FALSE;
			}
		}

		$textTypes = array('TEXT', 'VARCHAR', 'CHAR');

		foreach ($attributes as $key => $value)
		{
			$sQL = '`'.$key.'` '.$value['type'].(isset($value['length'])?'('.$value['length'].')':'').(in_array(strtoupper($value['type']), $textTypes)?' CHARACTER SET \'utf8\' COLLATE \'utf8_unicode_ci\'':'').(isset($value['null'])?'':' NOT NULL').(isset($value['autoincrement'])?' AUTO_INCREMENT':'');

			if (isset($value['unique']))
			{
				if ($value['unique'] !== 'TRUE')
				{
					if (isset($constraints[$value['unique']]))
					{
						$constraints[$value['unique']][1][] = $key;
					}
					else
					{
						$constraints[$value['unique']] = array('UNIQUE', array($key));
					}
				}
				else
				{
					$sQL.= ' UNIQUE';
				}
			}
			else if (isset($value['default']))
			{
				$sQL.= ' DEFAULT '.$this->PDO->quote($value['default']);
			}

			$parts[] = $sQL;

			if (isset($value['primary']))
			{
				$primaryKeys[] = '`'.$key.'`';
			}

			if (isset($value['foreign']))
			{
				$field = explode('.', $value['foreign']);
				$foreignKeys[] = 'FOREIGN KEY(`'.$key.'`) REFERENCES `'.$this->Prefix.$field[0].'` (`'.$field[1].'`)'.(isset($value['onupdate'])?' ON UPDATE '.$value['onupdate']:'').(isset($value['ondelete'])?' ON DELETE '.$value['ondelete']:'');
			}

			if (isset($value['index']) && !isset($value['unique']))
			{
				$indexKeys[] = 'INDEX(`'.$key.'`)';
			}
		}

		if ($primaryKeys)
		{
			$parts[] = 'PRIMARY KEY('.implode (', ', $primaryKeys).')';
		}

		foreach ($constraints as $key => $value)
		{
			if ($value[0] == 'UNIQUE')
			{
				$parts[] = 'UNIQUE(`'.implode('`, `', $value[1]).'`)';
			}
		}

		$parts = array_merge($parts, $foreignKeys, $indexKeys);

		$this->PDO->exec('CREATE TABLE `'.$this->Prefix.$table.'` ('.implode(', ', $parts).') ENGINE=InnoDB CHARACTER SET \'utf8\' COLLATE \'utf8_unicode_ci\'');

		return $this->tableExists($table);
	}

/**
 * Deletes database table
 * @param string Table
 * @return boolean
 */

	public function deleteTable($table)
	{
		$this->PDO->exec('DROP TABLE `'.$this->Prefix.$table.'`');

		return !$this->tableExists($table);
	}

/**
 * Checks if database table exists
 * @param string Table
 * @return boolean
 */

	public function tableExists($table)
	{
		$result = $this->PDO->query('SHOW TABLES LIKE'.$this->PDO->quote($this->Prefix.$table));

		return ($result?(strlen($result->fetchColumn(0)) > 1):0);
	}

/**
 * Returns database table size in bytes or FALSE if failed
 * @param string Table
 * @return integer
 */

	public function tableSize($table)
	{
		$result = $this->PDO->query('SHOW TABLE STATUS LIKE '.$this->PDO->quote($this->Prefix.$table));

		if (!$result)
		{
			return FALSE;
		}

		$array = $result->fetchAll(PDO::FETCH_ASSOC);

		return ($array[0]['Data_length'] + $array[0]['Index_length']);
	}

/**
 * Retrieves data from database table
 * @param array Tables
 * @param array Fields
 * @param array Where
 * @param integer Amount
 * @param integer Offset
 * @param array OrderBy
 * @param array GroupBy
 * @param array Having
 * @param boolean Distinct
 * @param integer Mode
 * @return mixed
 */

	public function selectData($tables, $fields = NULL, $where = NULL, $amount = 0, $offset = 0, $orderBy = NULL, $groupBy = NULL, $having = NULL, $distinct = FALSE, $mode = self::RETURN_RESULT)
	{
		if (is_array($fields))
		{
			$parts = array();

			for ($i = 0, $c = count($fields); $i < $c; ++$i)
			{
				if ($fields[$i] == self::FUNCTION_COUNT)
				{
					$parts[] = 'COUNT(*)';
				}
				else if (is_array($fields[$i]))
				{
					$parts[] = $this->elementString($fields[$i][0], $fields[$i][1]).(empty($fields[$i][2])?'':' AS `'.$fields[$i][2].'`');
				}
				else
				{
					$parts[] = $this->fieldString($fields[$i]);
				}
			}

			$fieldsPart = implode(', ', $parts);
		}
		else
		{
			$fieldsPart = '*';
		}

		$tablesPart = '';

		for ($i = 0, $c = count($tables); $i < $c; ++$i)
		{
			if (is_array($tables[$i]))
			{
				if (is_int($tables[$i][0]))
				{
					$natural = ($tables[$i][0] & self::JOIN_NATURAL);
					$tables[$i][0] = ($tables[$i][0] & ~self::JOIN_NATURAL);
					$tablesPart.= ($natural?' NATURAL':'').' ';

					switch ($tables[$i][0])
					{
						case self::JOIN_CROSS:
							$tablesPart.= 'CROSS';
						break;
						case self::JOIN_LEFT:
							$tablesPart.= 'LEFT';
						break;
						case self::JOIN_RIGHT:
							$tablesPart.= 'RIGHT';
						break;
						case self::JOIN_FULL:
							$tablesPart.= 'FULL';
						break;
						default:
							$tablesPart.= 'INNER';
						break;
					}

					$tablesPart.= ' JOIN ';
				}
				else
				{
					$tablesPart.= '`'.$this->Prefix.$tables[$i][0].'` AS `'.$tables[$i][1].'`';
				}
			}
			else
			{
				$tablesPart.= '`'.$this->Prefix.$tables[$i].'`'.($this->Prefix?' AS `'.$tables[$i].'`':'');
			}

			if ($i > 0 && is_array($tables[$i - 1]) && is_int($tables[$i - 1][0]))
			{
				if ($tables[$i - 1][1] == self::OPERATOR_JOIN_ON)
				{
					$tablesPart.= ' ON '.$this->elementString(self::ELEMENT_EXPRESSION, $tables[$i - 1][2]).' ';
				}
				else
				{
					for ($j = 0, $c = count($tables[$i - 1][2]); $j < $c; ++$j)
					{
						if (is_array($tables[$i - 1][2][$j]))
						{
							$tables[$i - 1][2][$j] = $this->elementString($tables[$i - 1][2][$j][0], $tables[$i - 1][2][$j][1]);
						}
						else
						{
							$tables[$i - 1][2][$j] = $this->fieldString($tables[$i - 1][2][$j]);
						}
					}

					$tablesPart.= ' USING('.implode(', ', $tables[$i - 1][2]).') ';
				}
			}
		}

		if (is_array($orderBy))
		{
			foreach ($orderBy as $key => $value)
			{
				if (is_array($value))
				{
					$orderBy[$key] = $this->elementString($key[0], $key[1]).($value?' ASC':' DESC');
				}
				else
				{
					$orderBy[$key] = $this->fieldString($key).($value?' ASC':' DESC');
				}
			}

			$orderBy = array_values($orderBy);
		}

		if (is_array($groupBy))
		{
			for ($i = 0, $c = count($groupBy); $i < $c; ++$i)
			{
				if (is_array($groupBy[$i]))
				{
					$groupBy[$i] = $this->elementString($groupBy[$i][0], $groupBy[$i][1]);
				}
				else
				{
					$groupBy[$i] = $this->fieldString($groupBy[$i]);
				}
			}
		}

		$sQL = 'SELECT '.($distinct?'DISTINCT ':'').$fieldsPart.' FROM '.$tablesPart.($where?' WHERE '.$this->elementString(self::ELEMENT_EXPRESSION, $where):'').($groupBy?' GROUP BY '.implode(', ', $groupBy).($having?' HAVING '.$this->elementString(self::ELEMENT_EXPRESSION, $having):''):'').($orderBy?' ORDER BY '.implode(', ', $orderBy):'').(($amount || $offset)?' LIMIT '.(int) $offset.', '.(int) $amount:'');

		if ($mode == self::RETURN_QUERY)
		{
			return $sQL;
		}

		$statement = $this->PDO->prepare($sQL);
		$result = ($statement?$statement->execute():NULL);

		if ($result)
		{
			return (($mode == self::RETURN_RESULT)?$statement->fetchAll(PDO::FETCH_ASSOC):$statement);
		}
		else
		{
			return array();
		}
	}

/**
 * Inserts data to database table and returns FALSE if failed, ID of last inserted row or TRUE on success
 * @param string Table
 * @param array Values
 * @param boolean ReturnID
 * @return integer
 */

	public function insertData($table, $values, $returnID = FALSE)
	{
		$statement = $this->PDO->prepare('INSERT INTO `'.$this->Prefix.$table.'` (`'.implode('`, `', array_keys($values)).'`) VALUES('.str_repeat('?, ', (count($values) - 1)).'?)');

		if (!$statement || !$statement->execute(array_values($values)))
		{
			return FALSE;
		}

		if ($returnID)
		{
			return $this->PDO->lastInsertId();
		}
		else
		{
			return TRUE;
		}
	}

/**
 * Changes data in database table
 * @param string Table
 * @param array Values
 * @param array Where
 * @return boolean
 */

	public function updateData($table, $values, $where)
	{
		$parts = array();

		if (!$this->selectAmount($table, $where))
		{
			return FALSE;
		}

		foreach ($values as $key => $value)
		{
			if (is_array($value))
			{
				$parts[] = '`'.$key.'` = '.$this->elementString($value[0], $value[1]);
			}
			else
			{
				$parts[] = '`'.$key.'` = '.$this->PDO->quote($value);
			}
		}

		$statement = $this->PDO->prepare('UPDATE `'.$this->Prefix.$table.'` SET '.implode(', ', $parts).' WHERE '.$this->elementString(self::ELEMENT_EXPRESSION, $where));

		return ($statement?$statement->execute():FALSE);
	}

/**
 * Deletes data from database table
 * @param string Table
 * @param array Where
 * @return boolean
 */

	public function deleteData($table, $where = NULL)
	{
		$statement = $this->PDO->prepare('DELETE FROM `'.$this->Prefix.$table.'`'.($where?' WHERE '.$this->elementString(self::ELEMENT_EXPRESSION, $where):''));

		return ($statement?$statement->execute():FALSE);
	}
}
?>