<?php
/**
 * @name dbAccess
 * @package IntelliRadio.dbAccess
 * @author Ramindu Deshapriya
 * 
 * @desc Class to handle database connections
 */

class dbAccess {
	protected static $dParams = array(
						'host' 		=> '',
						'user' 		=> '',
						'password' 	=> '',
						'database'	=> ''
					);

	
	
	
	protected $_connection = null; 
	
	
	/**
	 * The database driver name
	 *
	 * @var string
	 */
	protected $_sql = null;

	/**
	 *  The null/zero date string
	 *
	 * @var string
	 */
	protected $_nullDate = '0000-00-00 00:00:00';

	/**
	 * Quote for named objects
	 *
	 * @var string
	 */
	protected $_nameQuote = '`';
	protected static $dbInstance;
	final private function __construct($host,$user,$password,$db) {
		if ( !($this->_connection = @mysql_connect($host,$user,$password,true)) ) {
			die('Unable to connect to database, recheck your DB server');	
			
		}
		mysql_select_db($db, $this->_connection); 
		return $this;
		
	}

    /**
     * Function to return instance of dbAccess class
     * @return dbAccess singleton object
     */
    final public static function getInstance() {
		if ( is_null(dbAccess::$dbInstance) ) {
			dbAccess::$dbInstance = new dbAccess(	getenv('DB_HOST'), 
													getenv('DB_USER'), 
													getenv('DB_PASSWORD'), 
													getenv('DB_DATABASE')
												);
		}
		return dbAccess::$dbInstance;
		
	}
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		$this->_sql		= $query;
		$this->_limit	= (int) $limit;
		$this->_offset	= (int) $offset;

		return $this;
	}
	public function query()
	{
		if (!is_resource($this->_connection)) {
			echo 'DB Connection not available';
			return false;
		}

		// Take a local copy so that we don't modify the original query and cause issues later
		$sql = (string) $this->_sql;
		if ($this->_limit > 0 || $this->_offset > 0) {
			$sql .= ' LIMIT '.$this->_offset.', '.$this->_limit;
		}
		$this->_errorNum = 0;
		$this->_errorMsg = '';
		$this->_cursor = mysql_query($sql, $this->_connection);

		if (!$this->_cursor) {
			$this->_errorNum = mysql_errno($this->_connection);
			$this->_errorMsg = mysql_error($this->_connection)." SQL=$sql";

			
			return false;
		}
		return $this->_cursor;
	}
	/**
	 * Description
	 *
	 * @access public
	 */
	function insertid()
	{
		return mysql_insert_id( $this->_connection);
	}
	/**
	 * Get a database escaped string
	 *
	 * @param	string	The string to be escaped
	 * @param	boolean	Optional parameter to provide extra escaping
	 * @return	string
	 * @access	public
	 * @abstract
	 */
	function getEscaped( $text, $extra = false )
	{
		$result = mysql_real_escape_string( $text, $this->_connection );
		if ($extra) {
			$result = addcslashes( $result, '%_' );
		}
		return $result;
	}
	/**
	* Get a quoted database escaped string
	*
	* @param	string	A string
	* @param	boolean	Default true to escape string, false to leave the string unchanged
	* @return	string
	* @access public
	*/
	function Quote( $text, $escaped = true )
	{
		return '\''.($escaped ? $this->getEscaped( $text ) : $text).'\'';
	}
	public function nameQuote($s)
	{
		$q = $this->_nameQuote;

		if (strlen($q) == 1) {
			return $q.$s.$q;
		} else {
			return $q{0}.$s.$q{1};
		}
	}
	public function isQuoted($fieldName)
	{
		if ($this->_hasQuoted) {
			return in_array($fieldName, $this->_quoted);
		} else {
			return true;
		}
	}
	
	
/**
	 * This method loads the first field of the first row returned by the query.
	 *
	 * @return	mixed	The value returned in the query or null if the query failed.
	 */
	public function loadResult()
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$ret = null;
		if ($row = mysql_fetch_row($cur)) {
			$ret = $row[0];
		}
		mysql_free_result($cur);
		return $ret;
	}

	/**
	 * Load an array of single field results into an array
	 */
	public function loadResultArray($numinarray = 0)
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$array = array();
		while ($row = mysql_fetch_row($cur)) {
			$array[] = $row[$numinarray];
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	 * Fetch a result row as an associative array
	 *
	 * @return	array
	 */
	public function loadAssoc()
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$ret = null;
		if ($array = mysql_fetch_assoc($cur)) {
			$ret = $array;
		}
		mysql_free_result($cur);
		return $ret;
	}

	/**
	 * Load a assoc list of database rows.
	 *
	 * @param	string	The field name of a primary key.
	 * @param	string	An optional column name. Instead of the whole row, only this column value will be in the return array.
	 * @return	array	If <var>key</var> is empty as sequential list of returned records.
	 */
	public function loadAssocList($key = null, $column = null)
	{
		if (!($cur = $this->query())) {
			print $this->query();
			return null;
		}
		$array = array();
		while ($row = mysql_fetch_assoc($cur)) {
			$value = ($column) ? (isset($row[$column]) ? $row[$column] : $row) : $row;
			if ($key) {
				$array[$row[$key]] = $value;
			} else {
				$array[] = $value;
			}
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	 * This global function loads the first row of a query into an object.
	 *
	 * @param	string	The name of the class to return (stdClass by default).
	 *
	 * @return	object
	 */
	public function loadObject($className = 'stdClass')
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$ret = null;
		if ($object = mysql_fetch_object($cur, $className)) {
			$ret = $object;
		}
		mysql_free_result($cur);
		return $ret;
	}

	/**
	 * Load a list of database objects
	 *
	 * If <var>key</var> is not empty then the returned array is indexed by the value
	 * the database key.  Returns <var>null</var> if the query fails.
	 *
	 * @param	string	The field name of a primary key
	 * @param	string	The name of the class to return (stdClass by default).
	 *
	 * @return	array	If <var>key</var> is empty as sequential list of returned records.
	 */
	public function loadObjectList($key='', $className = 'stdClass')
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$array = array();
		while ($row = mysql_fetch_object($cur, $className)) {
			if ($key) {
				$array[$row->$key] = $row;
			} else {
				$array[] = $row;
			}
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	 * Description
	 *
	 * @return The first row of the query.
	 */
	public function loadRow()
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$ret = null;
		if ($row = mysql_fetch_row($cur)) {
			$ret = $row;
		}
		mysql_free_result($cur);
		return $ret;
	}

	/**
	 * Load a list of database rows (numeric column indexing)
	 *
	 * @param	string	The field name of a primary key
	 * @return	array	If <var>key</var> is empty as sequential list of returned records.
	 * If <var>key</var> is not empty then the returned array is indexed by the value
	 * the database key.  Returns <var>null</var> if the query fails.
	 */
	public function loadRowList($key=null)
	{
		if (!($cur = $this->query())) {
			return null;
		}
		$array = array();
		while ($row = mysql_fetch_row($cur)) {
			if ($key !== null) {
				$array[$row[$key]] = $row;
			} else {
				$array[] = $row;
			}
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	 * Load the next row returned by the query.
	 *
	 * @return	mixed	The result of the query as an array, false if there are no more rows, or null on an error.
	 *
	 * @since	1.6.0
	 */
	public function loadNextRow()
	{
		static $cur;

		if (!($cur = $this->query())) {
			return $this->_errorNum ? null : false;
		}

		if ($row = mysql_fetch_row($cur)) {
			return $row;
		}

		mysql_free_result($cur);
		$cur = null;

		return false;
	}

	/**
	 * Load the next row returned by the query.
	 *
	 * @param	string	The name of the class to return (stdClass by default).
	 *
	 * @return	mixed	The result of the query as an object, false if there are no more rows, or null on an error.
	 *
	 * @since	1.6.0
	 */
	public function loadNextObject($className = 'stdClass')
	{
		static $cur;

		if (!($cur = $this->query())) {
			return $this->_errorNum ? null : false;
		}

		if ($row = mysql_fetch_object($cur, $className)) {
			return $row;
		}

		mysql_free_result($cur);
		$cur = null;

		return false;
	}

	/**
	 * Inserts a row into a table based on an objects properties
	 *
	 * @param	string	The name of the table
	 * @param	object	An object whose properties match table fields
	 * @param	string	The name of the primary key. If provided the object property is updated.
	 */
	public function insertObject($table, &$object, $keyName = NULL)
	{
		$fmtsql = 'INSERT INTO '.$this->nameQuote($table).' (%s) VALUES (%s) ';
		$fields = array();

		foreach (get_object_vars($object) as $k => $v) {
			if (is_array($v) or is_object($v) or $v === NULL) {
				continue;
			}
			if ($k[0] == '_') { // internal field
				continue;
			}
			$fields[] = $this->nameQuote($k);
			$values[] = $this->isQuoted($k) ? $this->Quote($v) : (int) $v;
		}
		$this->setQuery(sprintf($fmtsql, implode(",", $fields) ,  implode(",", $values)));
		if (!$this->query()) {
			return false;
		}
		$id = $this->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}
		return true;
	}

	/**
	 * Description
	 *
	 * @param [type] $updateNulls
	 */
	public function updateObject($table, &$object, $keyName, $updateNulls=false)
	{
		$fmtsql = 'UPDATE '.$this->nameQuote($table).' SET %s WHERE %s';
		$tmp = array();

		foreach (get_object_vars($object) as $k => $v) {
			if (is_array($v) or is_object($v) or $k[0] == '_') { // internal or NA field
				continue;
			}

			if ($k == $keyName) {
				// PK not to be updated
				$where = $keyName . '=' . $this->Quote($v);
				continue;
			}

			if ($v === null) {
				if ($updateNulls) {
					$val = 'NULL';
				} else {
					continue;
				}
			} else {
				$val = $this->isQuoted($k) ? $this->Quote($v) : (int) $v;
			}
			$tmp[] = $this->nameQuote($k) . '=' . $val;
		}

		// Nothing to update.
		if (empty($tmp)) {
			return true;
		}

		$this->setQuery(sprintf($fmtsql, implode(",", $tmp) , $where));
		return $this->query();
	}		
}
