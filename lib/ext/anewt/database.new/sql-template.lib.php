<?php

/*
 * Anewt, Almost No Effort Web Toolkit, database module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */


anewt_include('datetime');


/**
 * SQL template class with mandatory type checking.
 *
 * This class implements the type checking logic for SQL queries. It is used
 * internally by AnewtDatabasePreparedQuery, but can also be used directly to
 * construct complex SQL queries with e.g. a variable number of placeholders.
 *
 * SQL templates can be used in two modes: positional and named mode. The amount
 * of input checking performed is the same, but the way the placeholders are
 * specified and filled differs.
 *
 * In positional mode, placeholders look like <code>?int?</code>, and when
 * filling the template the values are provided as a list of values to be used
 * for those placeholders, either as multiple parameters (if
 * AnewtDatabaseSQLTemplate::fill() is used) or as an array (if
 * AnewtDatabaseSQLTemplate::fillv() is used).
 *
 * In named mode, placeholders look like <code>?int:name?</code>, and when
 * filling the template the values are provided as an associative array or as
 * a Container instance, and the placeholder name is used to obtain the value
 * from the associative array or Container. In this mode you can only pass
 * a single parameter to provide values, which means
 * AnewtDatabaseSQLTemplate::fillv() must be used
 * ( AnewtDatabaseSQLTemplate::fill() cannot). This also applies for the query
 * methods on AnewtDatabaseConnection, where only the <code>...v()</code>
 * variants can be used, e.g. use
 * AnewtDatabaseConnection::prepare_executev_fetch_all(), not
 * AnewtDatabaseConnection::prepare_execute_fetch_all().
 *
 * \see AnewtDatabaseConnection::prepare()
 */
final class AnewtDatabaseSQLTemplate
{
	/* Static methods */

	/**
	 * Find out the type of an SQL query.
	 *
	 * The return value is one of the \c ANEWT_DATABASE_SQL_QUERY_TYPE_*
	 * constants, e.g. \c ANEWT_DATABASE_SQL_QUERY_TYPE_SELECT or
	 * \c ANEWT_DATABASE_SQL_QUERY_TYPE_INSERT.
	 *
	 * \param $sql
	 *   The SQL query
	 *
	 * \return
	 *   The type of the query.
	 */
	public static function query_type_for_sql($sql)
	{
		$first_word = preg_replace('/^([a-z]+).*$/s', '\1', strtolower(trim(substr(ltrim($sql), 0, 10))));
		switch ($first_word)
		{
			case 'select':    return ANEWT_DATABASE_SQL_QUERY_TYPE_SELECT;
			case 'insert':    return ANEWT_DATABASE_SQL_QUERY_TYPE_INSERT;
			case 'replace':   return ANEWT_DATABASE_SQL_QUERY_TYPE_REPLACE;
			case 'update':    return ANEWT_DATABASE_SQL_QUERY_TYPE_UPDATE;
			case 'delete':    return ANEWT_DATABASE_SQL_QUERY_TYPE_DELETE;

			case 'create':    return ANEWT_DATABASE_SQL_QUERY_TYPE_CREATE;
			case 'alter':     return ANEWT_DATABASE_SQL_QUERY_TYPE_ALTER;
			case 'drop':      return ANEWT_DATABASE_SQL_QUERY_TYPE_DROP;

			case 'begin':     return ANEWT_DATABASE_SQL_QUERY_TYPE_BEGIN;
			case 'commit':    return ANEWT_DATABASE_SQL_QUERY_TYPE_COMMIT;
			case 'rollback':  return ANEWT_DATABASE_SQL_QUERY_TYPE_ROLLBACK;

			default:          return ANEWT_DATABASE_SQL_QUERY_TYPE_UNKNOWN;
		}
	}

	/**
	 * Convert a column type string into the associated constant.
	 *
	 * This function returns one of the
	 * <code>ANEWT_DATABASE_SQL_FIELD_TYPE_*</code> constants, and throws an
	 * exception if the passes string is not a valid identifier.
	 *
	 * Example: The string <code>int</code> results in the
	 * <code>ANEWT_DATABASE_SQL_FIELD_TYPE_INTEGER</code> constant.
	 *
	 * \param $field_type
	 *   A string indicating a database field type, e.g. <code>int</code>.
	 *
	 * \return
	 *   Associated <code>ANEWT_DATABASE_SQL_FIELD_TYPE_*</code> constant.
	 */
	public static function field_type_for_string($field_type)
	{
		assert('is_string($field_type);');

		switch ($field_type)
		{
			case 'bool':
			case 'boolean':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_BOOLEAN;

			case 'i':
			case 'int':
			case 'integer':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_INTEGER;

			case 'f':
			case 'float':
			case 'double':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_FLOAT;

			case 's':
			case 'str':
			case 'string':
			case 'varchar':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_STRING;

			case 'date':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_DATE;

			case 'datetime':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_DATETIME;

			case 'time':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_TIME;

			case 'timestamp':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_TIMESTAMP;

			case 'r':
			case 'raw':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_RAW;

			case 'col':
			case 'column':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_COLUMN;

			case 'table':
				return ANEWT_DATABASE_SQL_FIELD_TYPE_TABLE;

			default:
				throw new AnewtDatabaseException('Field type "%s" is unknown', $type_str);
		}
	}


	/* Instance methods and variables */

	/**
	 * \private
	 *
	 * The associated AnewtDatabaseConnection.
	 */
	private $connection;

	/**
	 * \private
	 *
	 * The SQL query with sprintf-style format specifiers, after the
	 * placeholders have been parsed.
	 */
	private $sql;

	/**
	 * \private
	 *
	 * The placeholders in this template.
	 */
	private $placeholders;

	/**
	 * \private
	 *
	 * Indicates whether this SQL template uses positional or named
	 * placeholders.
	 */
	private $named_mode = false;

	/**
	 * \private
	 *
	 * Construct a new AnewtDatabaseSQLTemplate instance.
	 *
	 * Do not create instances directly; use
	 * AnewtDatabaseConnection::sql_template() instead.
	 *
	 * \param $sql
	 *   The SQL template string
	 *
	 * \param $connection
	 *   An AnewtDatabaseConnection instance
	 *
	 * \see AnewtDatabaseConnection::prepare
	 * \see AnewtDatabaseConnection::sql_template
	 */
	function __construct($sql, $connection)
	{
		assert('is_string($sql)');
		assert('$connection instanceof AnewtDatabaseConnection');

		$this->connection = $connection;
		$this->placeholders = array();
		

		/* Since vsprintf is used to substitute escaped values into the sql
		 * query later on, % characters need to be escaped. */

		$sql = str_replace('%', '%%', $sql);


		/* Find placeholders fields. All placeholders start with ? followed by
		 * a keyword and end with ? too, e.g. ?int? for a positional
		 * placeholder and ?string:somename? for a named placeholder. */

		$placeholder_matches = array();
		$placeholder_pattern_positional = '/\?([a-z]+)\?/i';
		$placeholder_pattern_named = '/\?([a-z]+):([^?]+)\?/i';

		if (preg_match_all($placeholder_pattern_named, $sql, $placeholder_matches))
		{
			$this->named_mode = true;
			 if (preg_match($placeholder_pattern_positional, $sql))
				 throw new AnewtDatabaseQueryException('Mixing positional and named placeholders is not supported');
		}
		else
		{
			preg_match_all($placeholder_pattern_positional, $sql, $placeholder_matches);
		}

		if ($placeholder_matches[1])
		{
			/* There is at least one placeholder and we know whether we are
			 * using named mode. Extract the placeholder types and save them in
			 * $this->placeholders, and replace all ?field? parts with %s to
			 * allow easy vsprintf substitution when in the fill() method.
			 *
			 * In positional mode:
			 * - $placeholder_matches[1] contains the placeholder types
			 * - $this->placeholders will be a numeric array of field types
			 *
			 * In named mode:
			 * - $placeholder_matches[1] contains the placeholder types
			 * - $placeholder_matches[2] contains the placeholder names
			 * - $this->placeholders will be a numeric array of (name, field type) tuples
			 */

			if ($this->named_mode)
			{
				$sql = preg_replace($placeholder_pattern_named, '%s', $sql);

				while ($placeholder_matches[1])
				{
					$type = AnewtDatabaseSQLTemplate::field_type_for_string(array_shift($placeholder_matches[1]));
					$name = array_shift($placeholder_matches[2]);

					$this->placeholders[] = array($name, $type);
				}
			}
			else
			{
				$sql = preg_replace($placeholder_pattern_positional, '%s', $sql);

				foreach ($placeholder_matches[1] as $type_string)
					$this->placeholders[] = AnewtDatabaseSQLTemplate::field_type_for_string($type_string);
			}
		}

		$this->sql = $sql;
	}

	/**
	 * Escape a field for embedding in an SQL query.
	 *
	 * This method does rigid sanity checking and throws errors when the
	 * supplied value is not suitable for the specified field type.
	 *
	 * \param $field_type
	 *   The field type (one of the \c ANEWT_DATABASE_SQL_FIELD_TYPE_* constants)
	 * \param $value
	 *   The value to escape
	 *
	 * \return
	 *   The escaped value
	 */
	private function escape_field($field_type, $value)
	{
		/* Escaping is not needed for NULL values. */

		if (is_null($value))
			return 'NULL';


		/* The value is non-null. Perform very restrictive input sanitizing
		 * based on the field type. */

		switch ($field_type)
		{
			case ANEWT_DATABASE_SQL_FIELD_TYPE_BOOLEAN:

				/* Integers: only accept 0 and 1 (no type juggling!) */
				if (is_int($value))
				{
					if ($value === 0)
						$value = false;
					elseif ($value === 1)
						$value = true;
				}

				/* Strings: only accept literal "0" and "1" (no type juggling!) */
				if (is_string($value))
				{
					if ($value === "0")
						$value = false;
					elseif ($value === "1")
						$value = true;
				}

				if (is_bool($value))
				{
					$value = $this->connection->escape_boolean($value);
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid boolean value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_INTEGER:

				if (is_int($value))
				{
					$value = (string) $value;
					break;
				}
				
				if (is_string($value) && preg_match('/^-?\d+$/', $value))
					break;

				throw new AnewtDatabaseQueryException('Invalid integer value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_FLOAT:

				/* FIXME: this does not accept .123 (without a leading zero) */
				if (is_string($value) && preg_match('/^-?\d+(\.\d*)?$/', $value))
				{
					/* Enough checks done by the regex, no need to do any
					 * formatting/escaping */
					break;
				}
				elseif (is_int($value) || is_float($value))
				{
					/* Locale-agnostic float formatting */
					$value = number_format($value, 10, '.', '');

					if (str_has_suffix($value, '.'))
						$value .= '0';

					break;
				}

				throw new AnewtDatabaseQueryException('Invalid float value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_STRING:

				/* Accept integers and objects with a render() method. */
				if (is_int($value))
					$value = (string) $value;
				elseif (is_object($value) && method_exists($value, 'render'))
					$value = to_string($value);

				/* From this point on only strings are accepted. */
				if (is_string($value))
				{
					$value = $this->connection->escape_string($value);
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid string value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_DATE:

				if ($value instanceof AnewtDateTimeAtom)
					$value = AnewtDateTime::sql_date($value);

				if (is_string($value) && preg_match('/^\d{2,4}-\d{2}-\d{2}$/', $value))
				{
					$value = $this->connection->escape_date($value);
					break;
				}

				if (is_string($value) && strtoupper($value) == 'NOW')
				{
					$value = 'NOW()';
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid date value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_TIME:

				if ($value instanceof AnewtDateTimeAtom)
					$value = AnewtDateTime::sql_time($value);

				if (is_string($value) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value))
				{
					$value = $this->connection->escape_time($value);
					break;
				}

				if (is_string($value) && strtoupper($value) == 'NOW')
				{
					$value = 'NOW()';
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid time value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_DATETIME:
			case ANEWT_DATABASE_SQL_FIELD_TYPE_TIMESTAMP:

				if ($value instanceof AnewtDateTimeAtom)
					$value = AnewtDateTime::sql($value);

				if (is_string($value) && preg_match('/^\d{2,4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value))
				{
					$value = $this->connection->escape_datetime($value);
					break;
				}

				if (is_string($value) && strtoupper($value) == 'NOW')
				{
					$value = 'NOW()';
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid datetime or timestamp value: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_RAW:
				/* No checking, no escaping... use at your own risk! */
				break;


			/* The column and table type are mostly for internal usage, it's
			 * a BAD idea to use user data for these fields! */

			case ANEWT_DATABASE_SQL_FIELD_TYPE_COLUMN:

				if (is_string($value) && preg_match('/^([a-z0-9_-]+\.)*[a-z0-9_-]+$/i', $value))
				{
					$value = $this->connection->escape_column_name($value);
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid column name: "%s"', $value);


			case ANEWT_DATABASE_SQL_FIELD_TYPE_TABLE:

				if (is_string($value) && preg_match('/^([a-z0-9_-]+\.)*[a-z0-9_-]+$/i', $value))
				{
					$value = $this->connection->escape_table_name($value);
					break;
				}

				throw new AnewtDatabaseQueryException('Invalid table name: "%s"', $value);


			default:

				throw new AnewtDatabaseQueryException('Unsupported field type! Please file a bug.');
				break;
		}

		assert('is_string($value)');
		return $value;
	}

	/**
	 * Fill the SQL template using the values passed as multiple parameters.
	 *
	 * See AnewtDatabaseSQLTemplate::fillv() for a detailed description.
	 *
	 * \param   $values
	 * \return  Quoted query
	 *
	 * \see AnewtDatabaseSQLTemplate::fillv
	*/
	public function fill($values=null)
	{
		$values = func_get_args();
		return $this->fillv($values);
	}

	/**
	 * Fill the SQL template using the values passed as a single parameter.
	 *
	 * This method will check all values for correctness to avoid nasty SQL
	 * injection vulnerabilities.
	 *
	 * \param $values
	 *   Array with values to use for substitution. For positional placeholders
	 *   this should be a numeric array. For named placeholders an associative
	 *   array or Container instance should be passed.
	 *
	 * \return
	 *   The query containing all values, quoted correctly.
	 *
	 * \see AnewtDatabaseSQLTemplate::fill
	 */
	public function fillv($values=null)
	{
		$n_placeholders = count($this->placeholders);
		$escaped_values = array();

		/* Named mode... */
		if ($this->named_mode)
		{
			/* Sanity checks */
			$values_is_container = ($values instanceof Container);
			if (!is_assoc_array($values) && !$values_is_container)
				throw new AnewtDatabaseQueryException(
					'SQL templates in named mode require a single associative array or Container instance when filled.');

			/* Fill the placeholders */
			for ($i = 0; $i < $n_placeholders; $i++)
			{
				list ($field_name, $field_type) = $this->placeholders[$i];

				if ($values_is_container)
				{
					$value = $values->get($field_name);
				}
				else
				{
					if (!array_key_exists($field_name, $values))
						throw new AnewtDatabaseQueryException('No value specified for field "%s".', $field_name);

					$value = $values[$field_name];
				}

				$escaped_values[] = $this->escape_field($field_type, $value);
			}
		}

		/* ... and positional mode */
		else
		{
			/* Sanity checks */
			if (!is_numeric_array($values))
				throw new AnewtDatabaseQueryException('SQL templates in positional mode can only be filled using a numeric array');

			$n_values = count($values);
			if ($n_placeholders != $n_values)
				throw new AnewtDatabaseQueryException(
					'Expected %d placeholder values, but %d values were provided.',
					$n_placeholders, $n_values);

			/* Fill the placeholders */
			for ($i = 0; $i < $n_placeholders; $i++)
			{
				$field_type = $this->placeholders[$i];
				$value = $values[$i];
				$escaped_values[] = $this->escape_field($field_type, $value);
			}
		}

		/* Now that all supplied values are validated and escaped properly, we
		 * can easily substitute them into the query template. The %s
		 * placeholders were already prepared during initial parsing. */

		if ($this->named_mode)
			$query = vsprintf($this->sql, $escaped_values);
		else
			$query = vsprintf($this->sql, $escaped_values);

		return $query;
	}
}

?>
