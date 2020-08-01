<?php
namespace Ubiquity\db\providers\pgsql;

use Ubiquity\db\providers\AbstractDbWrapper;

class PgsqlWrapper extends AbstractDbWrapper {

	private $async = false;

	public function queryColumn(string $sql, int $columnNumber = null) {}

	public function __construct($dbType = 'pgsql') {
		$this->quote = '"';
	}

	public function getDSN(string $serverName, string $port, string $dbName, string $dbType = 'mysql') {
		$port ??= 5432;
		$serverName ??= '127.0.0.1';
		return "host='$serverName' port=$port dbname='$dbName'";
	}

	public function fetchAllColumn($statement, array $values = null, string $column = null) {}

	public function ping() {}

	public function commit() {}

	public function prepareStatement(string $sql) {
		$values = \explode('?', $sql);
		$r = '';
		$count = \count($values);
		for ($i = 1; $i < $count; $i ++) {
			$r .= $values[$i - 1] . "\$$i";
		}
		$sql = $r . $values[$count - 1];
		$id = \crc32($sql);
		if ($this->async) {
			\pg_send_prepare($this->dbInstance, $id, $sql);
		} else {
			\pg_prepare($this->dbInstance, $id, $sql);
		}

		return $id;
	}

	public function queryAll(string $sql, int $fetchStyle = null) {}

	public function releasePoint($level) {}

	public function lastInsertId($name = null) {}

	public function nestable() {}

	public static function getAvailableDrivers() {}

	public function rollbackPoint($level) {}

	public function getTablesName() {}

	public function getStatement(string $sql) {
		return $this->prepareStatement($sql);
	}

	public function connect(string $dbType, $dbName, $serverName, string $port, string $user, string $password, array $options) {
		$connect_type = $options['connect_type'] ?? \PGSQL_CONNECT_FORCE_NEW;
		$identif = " user='$user' password='$password'";
		$this->async = $connect_type >= \PGSQL_CONNECT_ASYNC;
		if ($options['persistent'] ?? false) {
			return $this->dbInstance = \pg_pconnect($this->getDSN($serverName, $port, $dbName) . $identif, $connect_type);
		}
		return $this->dbInstance = \pg_connect($this->getDSN($serverName, $port, $dbName) . $identif, $connect_type);
	}

	public function groupConcat(string $fields, string $separator): string {}

	public function inTransaction() {}

	public function fetchAll($statement, array $values = null, $mode = null) {}

	public function query(string $sql) {}

	public function fetchColumn($statement, array $values = null, int $columnNumber = null) {}

	public function execute(string $sql) {}

	public function fetchOne($statement, array $values = null, $mode = null) {}

	public function getFieldsInfos($tableName) {}

	public function bindValueFromStatement($statement, $parameter, $value) {}

	public function getRowNum(string $tableName, string $pkName, string $condition): int {}

	public function rollBack() {}

	public function getForeignKeys($tableName, $pkName, $dbName = null) {}

	public function beginTransaction() {}

	public function _optPrepareAndExecute($sql, array $values = null, $one = false) {}

	public function _optExecuteAndFetch($statement, array $values = null, $one = false) {
		if ($this->async) {
			\pg_send_execute($this->dbInstance, $statement, $values);
			$result = $this->getAsyncResult();
		} else {
			$result = \pg_execute($this->dbInstance, $statement, $values);
		}
		if ($one) {
			return \pg_fetch_array($result, null, \PGSQL_ASSOC);
		}
		return \pg_fetch_all($result, \PGSQL_ASSOC);
	}

	private function getAsyncResult($timeout = 3) {
		$still_running = \pg_connection_busy($this->dbInstance);
		$socket = [
			\pg_socket($this->dbInstance)
		];
		while ($still_running) {
			\stream_select($socket, null, null, $timeout); // Will wait on that socket until that happens or the timeout is reached
			$still_running = \pg_connection_busy($this->dbInstance);
		}
		return \pg_get_result($this->dbInstance);
	}

	public function statementRowCount($statement) {}

	public function savePoint($level) {}

	public function executeStatement($statement, array $values = null) {}

	public function getPrimaryKeys($tableName) {}
}

