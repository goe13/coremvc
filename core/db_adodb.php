<?php
/**
 * 定义(define)
 */
class db_adodb {
	
	/**
	 * 连接数据库
	 * @param array $args
	 * @return dbh
	 */
	public static function connect($args) {
		$dsn = $args ['connect_dsn'];
		$type = $args ['connect_type'];
		$server = $args ['connect_server'];
		$username = $args ['connect_username'];
		$password = $args ['connect_password'];
		$new_link = $args ['connect_new_link'];
		$dbname = $args ['connect_dbname'];
		$charset = $args ['connect_charset'];
		$client_flags = $args ['connect_client_flags'];
		$GLOBALS['ADODB_CACHE_CLASS'] =  'ADODB_Cache_File';
		$dbh = ADONewConnection( $dsn );
		if ($server !== '') {
			if ($client_flags !== '' && preg_match ( '/msyql/i', $dsn )) {
				$dbh->clientFlags = $client_flags;
			}
			if ($type === 'persist') {
				$dbh->PConnect ( $server, $username, $password, $dbname );
			} elseif ($new_link) {
				$dbh->NConnect ( $server, $username, $password, $dbname );
			} else {
				$dbh->Connect ( $server, $username, $password, $dbname );
			}
			if ($charset !== '' && preg_match ( '/msyql/i', $dsn )) {
				$dbh->Execute ( 'SET NAMES ' . $charset );
			}
		}
		return $dbh;
	}
	
	/**
	 * 断开数据库
	 * @param dbh $dbh
	 * @param array $args
	 * @return bool
	 */
	public static function disconnect($dbh, $args) {
		if(is_object ( $dbh ) && preg_match ( '/^ADODB/i', get_class($dbh))){
			$dbh->Close ();
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * 执行语句
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @param array &$ref
	 * @return rs
	 */
	public static function execute($dbh, $args, $class, $sql, $param = null, &$ref = null) {
		if (is_array( $param )) {
			$rs = $dbh->Execute ( $sql, $param );
		} else {
			$rs = $dbh->Execute ( $sql );
		}
		if ($args ['debug_enable'] === true) {
			if ($rs === false) {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
			}
		}
		if(func_num_args()>4){
			$ref = array();
			$ref ['insert_id'] = $dbh->Insert_ID();
			$ref ['affected_rows'] = (int)$dbh->Affected_Rows();
			$ref ['num_fields'] = is_object($rs)?$rs->FieldCount():0;
			$ref ['num_rows'] = is_object($rs)?$rs->RecordCount():0;
		}
		return $rs;
	}
	
	/**
	 * 准备SQL语句
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @param bool $format
	 * @param bool $debug
	 * @param string $output
	 * @param string $errno
	 * @param string $error
	 * @return mix
	 */
	public static function prepare($dbh, $args, $class, $sql, $param = null, $format = null, $debug = null, $output = null, $errno = null, $error = null) {
		return call_user_func ( array($class,'prepare'), 'mysql_' . $sql, $param, $format, $debug, $output, $errno, $error );
	}
	
	/**
	 * 自增序列
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $tablename
	 * @param int $start_index
	 * @return int
	 */
	public static function sequence($dbh, $args, $class, $tablename, $start_index) {
		$return = $dbh->GenID ( $tablename, $start_index );
		if($start_index>$return){
			$dbh->Execute('UPDATE '.$tablename.' SET id='.$start_index);
			$return = $start_index;
		}
		return $return;
	}
	/**
	 * 对象选择
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @param array $ref
	 * @return array
	 */
	public static function selects($dbh, $args, $class, $sql, $param, $ref) {
		$page = &$ref['page'];
		$class_arr = $ref['class_arr'];
		$classkey = $ref['classkey'];
		$classname = $ref['classname'];
		$calledclass = $ref['calledclass'];
		if($page !== null){
			if($page['count'] === null){
				$sql = preg_replace('/SELECT/i','SELECT SQL_CALC_FOUND_ROWS',$sql,1);
			}
			$limit = 'LIMIT '.($page['size']*($page['page']-1)).','.$page['size'];
			if(isset($page['limit'])){
				$sql = preg_replace('/(.*)'.$page['limit'].'/i','$1'.$limit,$sql,1);
			} else {
				$sql .= ' '.$limit;
			}
		}
		$data_key = array ();
		foreach($class_arr as $value){
			if($value!==null && $value!=='' && !in_array($value,$data_key,true)){
				$data_key[] = $value;
			}
		}
		// 数据
		$data_all = array ();
		if($data_key!==array()){
			$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
			$sth = $dbh->Execute($sql,$param);
			if ($args ['debug_enable'] === true) {
				if ($sth === false) {
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
				} else {
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
				}
			}
			while ( $obj = $sth->FetchRow (  ) ) {
				$obj_arr = array();
				foreach($data_key as $value){
					if(array_key_exists($value,$obj)){
						$obj_arr[$value] = $obj[$value];
					}
				}
				$data_all[] = $obj_arr ;
			}
			$dbh->setFetchMode($mode);
		}
		$data_arr = array ();
		switch ($classkey) {
			case 'assoc' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
				$data_arr = $dbh->GetALL($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				$dbh->setFetchMode($mode);
				break;
			case 'num' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_NUM);
				$data_arr = $dbh->GetALL($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				$dbh->setFetchMode($mode);
				break;
			case 'both' :
			case 'array' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
				$data_arr = $dbh->GetALL($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				$dbh->setFetchMode($mode);
				break;
			case 'column' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
				$sth = $dbh->Execute($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				while ( $obj = $sth->FetchRow (  ) ) {
					if (isset ( $obj [$classname] )) {
						$data_arr[] = $obj [$classname] ;
					} else {
						$data_arr[] = null ;
					}
				}
				$dbh->setFetchMode($mode);
				break;
			default :
			case 'class' :
				if (class_exists ( $classname )) {
					$obj_classname = $classname;
				}else{
					$obj_classname = $calledclass;
				}
				$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
				$sth = $dbh->Execute($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				while ( $obj = $sth->FetchRow (  ) ) {
					$clone = new $obj_classname ;
					foreach($obj as $key=>$value){
						$clone->$key = $value;
					}
					$data_arr[] = $clone ;
				}
				$dbh->setFetchMode($mode);
				break;
			case 'class|classtype' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
				$sth = $dbh->Execute($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				while ( $obj = $sth->FetchRow (  ) ) {
					$obj_classname = $classname;
					foreach($obj as $key=>$obj_classname){
						unset($obj[$key]);
						break;
					}
					if(preg_match ( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $obj_classname ) && class_exists($obj_classname)){
						$clone = new $obj_classname ();
					}elseif(class_exists ( $classname )){
						$clone = new $classname ();
					}else{
						$clone = new $calledclass ();
					}
					foreach($obj as $key=>$value){
						$clone->$key = $value;
					}
					$data_arr[] = $clone ;
				}
				$dbh->setFetchMode($mode);
				break;
			case 'clone' :
				if (is_object ( $classname )) {
					$obj_classname = $classname;
				}else{
					$obj_classname = new $calledclass ( );
				}
				$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
				$sth = $dbh->Execute($sql,$param);
				if ($args ['debug_enable'] === true) {
					if ($sth === false) {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
					} else {
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
					}
				}
				while ( $obj = $sth->FetchRow (  ) ) {
					$clone = clone $obj_classname ;
					foreach($obj as $key=>$value){
						$clone->$key = $value;
					}
					$data_arr[] = $clone ;
				}
				$dbh->setFetchMode($mode);
				break;
		}
		if($page !== null){
			if($page['count'] === null){
				$page ['count'] = $dbh->GetOne("SELECT FOUND_ROWS()");
			}
			$page ['total'] = (int)ceil ( $page ['count'] / $page ['size'] );
		}
		return array($data_arr,$data_all);
	}
	
	/**
	 * 对象插入
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function inserts($dbh, $args, $class, $sql, $param) {
		$result = $dbh->Execute ( $sql, $param );
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
			}
		}
		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 对象修改
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function updates($dbh, $args, $class, $sql, $param) {
		$result = $dbh->Execute ( $sql, $param );
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
			}
		}
		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 对象删除
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function deletes($dbh, $args, $class, $sql, $param) {
		$result = $dbh->Execute ( $sql, $param );
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
			}
		}
		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 对象更新
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function replaces($dbh, $args, $class, $sql, $param) {
		$result = $dbh->Execute ( $sql, $param );
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'] );
			}
		}
		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 实例选择
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function select($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		if ($primary_name !== null) {
			$sql = 'SELECT * FROM ' . $tablename . ' WHERE ' . $primary_name . '=? LIMIT 1';
			$paramvars = array ($primary_value );
			$rs = $dbh->Execute ( $sql, $paramvars );
		} else {
			$sql = 'SELECT * FROM ' . $tablename . ' LIMIT 1';
			$paramvars = null;
			$rs = $dbh->Execute ( $sql );
		}
		$result = ( bool ) $rs;
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'] );
			}
		}
		if (! $rs) {
			return false;
		}
		if ($rs->RecordCount() == 0) {
			$rs->Close ();
			return false;
		}
		$row = $rs->GetRowAssoc ( 2 );
		$rs->Close ();
		foreach ( $row as $key => $value ) {
			$that->$key = $value;
		}
		return true;
	}
	
	/**
	 * 实例插入
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function insert($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		$sql = 'INSERT INTO ' . $tablename . ' (' . $fieldname . ') VALUES (' . $valuename . ')';
		$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'] );
			}
		}
		if ($result && $primary_name !== null) {
			$that->$primary_name = $dbh->Insert_ID ();
		}
		return $result;
	}
	
	/**
	 * 实例修改
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function update($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		if ($primary_name !== null) {
			$sql = 'UPDATE ' . $tablename . ' SET ' . $valuename . ' WHERE ' . $primary_name . '=? LIMIT 1';
		} else {
			$sql = 'UPDATE ' . $tablename . ' SET ' . $valuename . ' LIMIT 1';
		}
		$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'] );
			}
		}
		if($result && $dbh->Affected_Rows()==0){
			return false;
		}
		return $result;
	}
	
	/**
	 * 实例删除
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function delete($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		if ($primary_name !== null) {
			$sql = 'DELETE FROM ' . $tablename . ' WHERE ' . $primary_name . '=? LIMIT 1';
			$paramvars = array ($primary_value );
			$result =( bool ) $dbh->Execute ( $sql, $paramvars );
		} else {
			$sql = 'DELETE FROM ' . $tablename . ' LIMIT 1';
			$paramvars = null;
			$result = ( bool ) $dbh->Execute ( $sql );
		}
		if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'] );
			}
		}
		if($result && $dbh->Affected_Rows()==0){
			return false;
		}
		return  $result;
	}
	
	/**
	 * 实例更新
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function replace($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		$sql = 'REPLACE INTO ' . $tablename . ' (' . $fieldname . ') VALUES (' . $valuename . ')';
		$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
			if ($args ['debug_enable'] === true) {
			if ($result === false) {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $dbh->ErrorNo(), $dbh->ErrorMsg() );
			} else {
				call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'] );
			}
		}
	if ($result && $primary_name !== null) {
			$that->$primary_name = $dbh->Insert_ID ();
		}
		return $result;
	}
	
}

/**
 * 执行(execute)
 */

// 配置信息
$adodb_file = ''; //adodb.inc.php所在的位置，默认使用AdodbZip

// 载入类文件
if (! class_exists ( 'ADOConnection' , false ) ) {
	if ( $adodb_file === '' ) {
		require_once dirname(__FILE__) . '/AdodbZip.php';
	} else {
		require_once $adodb_file;
	}
}

?>