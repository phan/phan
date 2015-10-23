<?php

return [
	'mysqli' => [
		'properties' => [
			'affected_rows' => 'int',
			'client_info' => 'string',
			'client_version' => 'int',
			'connect_errno' => 'int',
			'connect_error' => 'string',
			'errno' => 'int',
			'error' => 'string',
			'error_list' => 'string[]',
			'field_count' => 'int',
			'host_info' => 'string',
			'info' => 'string',
			'insert_id' => 'int|string',
			'server_info' => 'string',
			'server_version' => 'int',
			'sql_state' => 'string',
			'protocol_version' => 'string',
			'thread_id' => 'int',
			'warning_count' => 'int',
			'stat' => 'string'
		],
	],

	'mysqli_result' => [
		'properties' => [
			'current_field' => 'string',
			'field_count' => 'string',
			'lengths' => 'int[]',
			'num_rows' => 'int',
			'type' => 'int'
		],
	],

	'mysqli_driver' => [
		'properties' => [
			'client_info' => 'string',
			'client_version' => 'string',
			'driver_version' => 'string',
			'embedded' => 'string',
			'reconnect' => 'bool',
			'report_mode' => 'int',
		],
	],

	'mysqli_sql_exception' => [
		'properties' => [
			'message' => 'int',
			'file' => 'string',
			'line' => 'int',
			'code' => 'int',
			'sqlstate' => 'string'
		],
	],

	'mysqli_stmt' => [
		'properties' => [
			'affected_rows' => 'int',
			'insert_id' => 'string',
			'num_rows' => 'int',
			'param_count' => 'int',
			'field_count' => 'int',
			'errno' => 'int',
			'error' => 'string',
			'error_list' => 'string[]',
			'sql_state' => 'string',
			'id' => 'int'
		],
	],

	'mysqli_warning' => [
		'properties' => [
			'message' => 'string',
			'sqlstate' => 'string',
			'errno' => 'int'
		],
	],

	'pdoexception' => [
		'properties' => [
			'message' => 'string',
			'code' => 'string',
			'file' => 'string',
			'line' => 'int',
			'errorInfo' => 'string[]'
		],
	],

	'pdostatement' => [
		'properties' => [
			'queryString' => 'string'
		],
	],

	'transliterator' => [
		'properties' => [ 'id'=>'string' ]
	],

];
