<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
    'array_is_list' => ['bool', 'array'=>'array'],
    'fsync' => ['bool', 'stream'=>'resource'],
    'mysqli_fetch_column' => ['null|int|float|string|false', 'result' => 'mysqli_result', 'column'=>'int'],
    'mysqli_result::fetch_column' => ['null|int|float|string|false', 'column'=>'int'],
  ],

  'changed' => [
    'ini_set' => [
      'old' => ['string|false', 'option'=>'string', 'value'=>'string'],
      'new' => ['string|false', 'option'=>'string', 'value'=>'string|int|float|bool|null'],
    ],
    'ldap_add' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_add_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_bind' => [
      'old' => ['bool', 'ldap'=>'resource','dn='=>'string','password='=>'?string','serverctls='=>'?array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn='=>'string','password='=>'?string','serverctls='=>'?array'],
    ],
    'ldap_bind_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn='=>'string','password='=>'?string','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn='=>'string','password='=>'?string','controls='=>'?array'],
    ],
    'ldap_close' => [
      'old' => ['bool', 'ldap'=>'resource'],
      'new' => ['bool', 'ldap'=>'ldap\connection'],
    ],
    'ldap_compare' => [
      'old' => ['bool|int', 'ldap'=>'resource','dn'=>'string','attribute'=>'string','value'=>'string','controls='=>'?array'],
      'new' => ['bool|int', 'ldap'=>'ldap\connection','dn'=>'string','attribute'=>'string','value'=>'string','controls='=>'?array'],
    ],
    'ldap_connect' => [
      'old' => ['resource|false', 'uri='=>'string','port='=>'int','wallet='=>'string','password='=>'string','auth_mode='=>'int'],
      'new' => ['ldap\connection|false', 'uri='=>'string','port='=>'int','wallet='=>'string','password='=>'string','auth_mode='=>'int'],
    ],
    'ldap_count_entries' => [
      'old' => ['int', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['int', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_delete' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string'],
    ],
    'ldap_delete_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','controls='=>'?array'],
    ],
    'ldap_errno' => [
      'old' => ['int', 'ldap'=>'resource'],
      'new' => ['int', 'ldap'=>'ldap\connection'],
    ],
    'ldap_error' => [
      'old' => ['string', 'ldap'=>'resource'],
      'new' => ['string', 'ldap'=>'ldap\connection'],
    ],
    'ldap_exop' => [
      'old' => ['bool|resource', 'ldap'=>'resource','request_oid'=>'string','request_data='=>'string','controls='=>'?array','&w_response_data='=>'string','&w_response_oid='=>'string'],
      'new' => ['bool|ldap\result', 'ldap'=>'ldap\connection','request_oid'=>'string','request_data='=>'string','controls='=>'?array','&w_response_data='=>'string','&w_response_oid='=>'string'],
    ],
    'ldap_exop_passwd' => [
      'old' => ['bool|string', 'ldap'=>'resource','user='=>'string','old_password='=>'string','new_password='=>'string','&controls='=>'?array'],
      'new' => ['bool|string', 'ldap'=>'ldap\connection','user='=>'string','old_password='=>'string','new_password='=>'string','&controls='=>'?array'],
    ],
    'ldap_exop_refresh' => [
      'old' => ['int|false', 'ldap'=>'resource','dn'=>'string','ttl'=>'int'],
      'new' => ['int|false', 'ldap'=>'ldap\connection','dn'=>'string','ttl'=>'int'],
    ],
    'ldap_exop_whoami' => [
      'old' => ['string|false', 'ldap'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection'],
    ],
    'ldap_first_attribute' => [
      'old' => ['string|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_first_entry' => [
      'old' => ['resource|false', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_first_reference' => [
      'old' => ['resource|false', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_free_result' => [
      'old' => ['bool', 'result'=>'resource'],
      'new' => ['bool', 'result'=>'ldap\result'],
    ],
    'ldap_get_attributes' => [
      'old' => ['array', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['array', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_get_dn' => [
      'old' => ['string|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_get_entries' => [
      'old' => ['array|false', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['array|false', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_get_option' => [
      'old' => ['bool', 'ldap'=>'resource','option'=>'int','&w_value='=>'array|int|string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','option'=>'int','&w_value='=>'array|int|string'],
    ],
    'ldap_get_values' => [
      'old' => ['array|false', 'ldap'=>'resource','entry'=>'resource','attribute'=>'string'],
      'new' => ['array|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry','attribute'=>'string'],
    ],
    'ldap_get_values_len' => [
      'old' => ['array|false', 'ldap'=>'resource','entry'=>'resource','attribute'=>'string'],
      'new' => ['array|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry','attribute'=>'string'],
    ],
    'ldap_list' => [
      'old' => ['resource|false', 'ldap'=>'resource|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
      'new' => ['ldap\result|array|false', 'ldap'=>'ldap\connection|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
    ],
    'ldap_mod_add' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_mod_add_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_mod_del' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_mod_del_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_mod_replace' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_mod_replace_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_modify' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_modify_batch' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','modifications_info'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','modifications_info'=>'array'],
    ],
    'ldap_next_attribute' => [
      'old' => ['string|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_next_entry' => [
      'old' => ['resource|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_next_reference' => [
      'old' => ['resource|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_parse_exop' => [
      'old' => ['bool', 'ldap'=>'resource','result'=>'resource','&w_response_data='=>'string','&w_response_oid='=>'string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','result'=>'ldap\result','&w_response_data='=>'string','&w_response_oid='=>'string'],
    ],
    'ldap_parse_reference' => [
      'old' => ['bool', 'ldap'=>'resource','entry'=>'resource','&referrals'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry','&referrals'=>'array'],
    ],
    'ldap_parse_result' => [
      'old' => ['bool', 'ldap'=>'resource','result'=>'resource','&w_error_code'=>'int','&w_matched_dn='=>'string','&w_error_message='=>'string','&w_referrals='=>'array','&w_controls='=>'?array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','result'=>'ldap\result','&w_error_code'=>'int','&w_matched_dn='=>'string','&w_error_message='=>'string','&w_referrals='=>'array','&w_controls='=>'?array'],
    ],
    'ldap_read' => [
      'old' => ['resource|false', 'ldap'=>'resource|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
      'new' => ['ldap\result|array|false', 'ldap'=>'ldap\connection|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
    ],
    'ldap_rename' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool'],
    ],
    'ldap_rename_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool','controls='=>'?array'],
    ],
    'ldap_sasl_bind' => [
      'old' => ['bool', 'ldap'=>'resource','dn='=>'string','password='=>'string','mech='=>'string','realm='=>'string','authc_id='=>'string','authz_id='=>'string','props='=>'string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn='=>'string','password='=>'string','mech='=>'string','realm='=>'string','authc_id='=>'string','authz_id='=>'string','props='=>'string'],
    ],
    'ldap_search' => [
      'old' => ['resource|false', 'ldap'=>'resource|resource[]','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int','controls='=>'?array'],
      'new' => ['ldap\result|array|false', 'ldap'=>'ldap\connection|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int','controls='=>'?array'],
    ],
    'ldap_set_option' => [
      'old' => ['bool', 'ldap'=>'resource','option'=>'int','value'=>'array|bool|int|string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','option'=>'int','value'=>'array|bool|int|string'],
    ],
    'ldap_set_rebind_proc' => [
      'old' => ['bool', 'ldap'=>'resource','callback'=>'?callable'],
      'new' => ['bool', 'ldap'=>'ldap\connection','callback'=>'?callable'],
    ],
    'ldap_start_tls' => [
      'old' => ['bool', 'ldap'=>'resource'],
      'new' => ['bool', 'ldap'=>'ldap\connection'],
    ],
    'ldap_unbind' => [
      'old' => ['bool', 'ldap'=>'resource'],
      'new' => ['bool', 'ldap'=>'ldap\connection'],
    ],

    'mysqli_stmt::execute' => [
      'old' => ['bool'],
      'new' => ['bool', 'params=' => '?list<string>'],
    ],
    'mysqli_stmt_execute' => [
      'old' => ['bool', 'statement'=>'mysqli_stmt'],
      'new' => ['bool', 'statement'=>'mysqli_stmt', 'params=' => '?list<string>'],
    ],
  ],
  'removed' => [
  ],
];
