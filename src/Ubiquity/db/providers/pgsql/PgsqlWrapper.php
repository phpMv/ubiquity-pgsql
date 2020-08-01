<?php
namespace Ubiquity\db\providers\pgsql;

use Ubiquity\db\providers\AbstractDbWrapper;
use Ubiquity\exceptions\DBException;

class PgsqlWrapper extends AbstractDbWrapper {

	private $async = false;

	private $pool = [];

	private $coStatements = [];

	private function initPool($size, $dbName, $serverName, $port, $user, $password, $options) {
		while ($size --) {
			$this->pool[] = $this->_connect($dbName, $serverName, $port, $user, $password, $options);
		}
	}

	private function getPool() {
		$i = 0;
		while (true) {
			if (! pg_connection_busy($this->pool[$i])) {
				$this->dbInstance = $this->pool[$i];
				unset($this->pool[$i]);
				return $this->dbInstance;
			}
			if ($i < count($this->pool) - 1) {
				$i ++;
			} else {
				$i = 0;
			}
		}
	}

	private function useCo($co) {
		foreach ($this->pool as $i => $coInPool) {
			if ($coInPool === $co) {
				unset($this->pool[$i]);
				return;
			}
		}
	}

	private function freePool($co) {
		$this->pool[] = $co;
	}

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
			$this->getPool();
			\pg_send_prepare($this->dbInstance, $id, $sql);
			\pg_get_result($this->dbInstance);
			$this->coStatements[$id] = $this->dbInstance;
			$this->freePool($this->dbInstance);
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
		$this->async = $options['async'] ?? false;
		if ($this->async) {
			return $this->initPool(20, $dbName, $serverName, $port, $user, $password, $options);
		}
		return $this->dbInstance = $this->_connect($dbName, $serverName, $port, $user, $password, $options);
	}

	private function _connect($dbName, $serverName, string $port, string $user, string $password, array $options) {
		$connect_type = $options['connect_type'] ?? \PGSQL_CONNECT_FORCE_NEW;
		$identif = " user='$user' password='$password'";
		if ($options['persistent'] ?? false) {
			return \pg_pconnect($this->getDSN($serverName, $port, $dbName) . $identif, $connect_type);
		}
		return \pg_connect($this->getDSN($serverName, $port, $dbName) . $identif, $connect_type);
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
			$instance = $this->coStatements[$statement];
			$this->useCo($instance);
			return $this->sendQuery($instance, $statement, $values, $one);
		}
		$result = \pg_execute($this->dbInstance, $statement, $values);
		if ($one) {
			$rows = \pg_fetch_array($result, null, \PGSQL_ASSOC);
		}
		$rows = \pg_fetch_all($result, \PGSQL_ASSOC);
		return $rows;
	}

	private function sendQuery($conn, $statement, $values, $one, $timeout = 3) {
		\assert(\pg_get_result($conn) === false);

		$socket = [
			\pg_socket($conn)
		];
		$null = [];

		\pg_send_execute($conn, $statement, $values);

		$still_running = \pg_connection_busy($conn);

		while ($still_running) {
			stream_select($socket, $null, $null, $timeout);
			$still_running = pg_connection_busy($conn);

			if ($still_running) {
				\pg_cancel_query($conn);
				throw new DBException("TIMEOUT");
			}
		}

		$res = \pg_get_result($conn);

		try {
			$error_msg = \pg_result_error($res);
			if ($error_msg) {
				throw new DBException($error_msg);
			}
			if ($one) {
				return \pg_fetch_array($res, null, \PGSQL_ASSOC);
			}
			return \pg_fetch_all($res, \PGSQL_ASSOC);
		} finally {
			\pg_free_result($res);
			$this->freePool($conn);
		}
	}

	public function statementRowCount($statement) {}

	public function savePoint($level) {}

	public function executeStatement($statement, array $values = null) {}

	public function getPrimaryKeys($tableName) {}
}

